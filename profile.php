<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$uid  = $_SESSION['user_id'];
$user = current_user();
$tab  = $_GET['tab'] ?? 'profile';
$errors = [];

// ── Save Profile ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    if (!verify_csrf()) { $errors[] = 'Token tidak valid.'; }
    else {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (!$name) $errors[] = 'Nama wajib diisi.';
        if (empty($errors)) {
            $avatar = null;
            if (!empty($_FILES['avatar']['name'])) {
                $avatar = upload_file($_FILES['avatar'], 'avatars');
            }
            $set_av = $avatar ? ', avatar=?' : '';
            $params = [$name, $phone];
            if ($avatar) $params[] = $avatar;
            $params[] = $uid;
            db()->prepare("UPDATE users SET name=?, phone=? $set_av WHERE id=?")->execute($params);
            $_SESSION['user_name'] = $name;
            flash('global','Profil berhasil diperbarui.','success');
            redirect('profile.php?tab=profile');
        }
    }
}

// ── Change Password ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf()) { $errors[] = 'Token tidak valid.'; }
    else {
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $full = db()->prepare("SELECT password FROM users WHERE id=?"); $full->execute([$uid]);
        $current_hash = $full->fetchColumn();
        if (!password_verify($old, $current_hash)) $errors[] = 'Password lama salah.';
        elseif (strlen($new) < 8) $errors[] = 'Password baru minimal 8 karakter.';
        elseif ($new !== $conf)   $errors[] = 'Konfirmasi password tidak cocok.';
        if (empty($errors)) {
            db()->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>12]),$uid]);
            flash('global','Password berhasil diubah.','success');
            redirect('profile.php?tab=security');
        }
    }
}

// ── Address CRUD ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
    if (!verify_csrf()) { $errors[] = 'Token tidak valid.'; }
    else {
        $addr_id    = (int)($_POST['address_id'] ?? 0);
        $label      = trim($_POST['label'] ?? 'Rumah');
        $recipient  = trim($_POST['recipient'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $province   = trim($_POST['province'] ?? '');
        $city       = trim($_POST['city'] ?? '');
        $district   = trim($_POST['district'] ?? '');
        $postal     = trim($_POST['postal_code'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $is_default = !empty($_POST['is_default']) ? 1 : 0;
        foreach(['recipient'=>$recipient,'phone'=>$phone,'province'=>$province,'city'=>$city,'address'=>$address] as $f => $v) {
            if (!$v) $errors[] = ucfirst($f) . ' wajib diisi.';
        }
        if (empty($errors)) {
            if ($is_default) db()->prepare("UPDATE addresses SET is_default=0 WHERE user_id=?")->execute([$uid]);
            if ($addr_id) {
                db()->prepare("UPDATE addresses SET label=?,recipient=?,phone=?,province=?,city=?,district=?,postal_code=?,address=?,is_default=? WHERE id=? AND user_id=?")
                     ->execute([$label,$recipient,$phone,$province,$city,$district,$postal,$address,$is_default,$addr_id,$uid]);
            } else {
                db()->prepare("INSERT INTO addresses (user_id,label,recipient,phone,province,city,district,postal_code,address,is_default) VALUES (?,?,?,?,?,?,?,?,?,?)")
                     ->execute([$uid,$label,$recipient,$phone,$province,$city,$district,$postal,$address,$is_default]);
            }
            flash('global','Alamat berhasil disimpan.','success');
            redirect('profile.php?tab=addresses');
        }
    }
}

if (isset($_GET['delete_address'])) {
    db()->prepare("DELETE FROM addresses WHERE id=? AND user_id=?")->execute([(int)$_GET['delete_address'],$uid]);
    flash('global','Alamat dihapus.','success');
    redirect('profile.php?tab=addresses');
}

// Reload user
$user = db()->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$uid]); $user = $user->fetch();
$addresses = db()->prepare("SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC");
$addresses->execute([$uid]); $addresses = $addresses->fetchAll();

$page_title = 'Profil Saya';
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container" style="max-width:900px">
    <div class="breadcrumb mb-16">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current">Profil Saya</span>
    </div>

    <?php $flash = get_flash('global'); if($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> mb-16"><i class="fas fa-check-circle"></i> <?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr;gap:20px" id="profileLayout">

      <!-- Sidebar -->
      <aside>
        <div class="card" style="padding:20px;text-align:center;margin-bottom:14px">
          <div style="width:70px;height:70px;border-radius:50%;background:var(--green-100);color:var(--green-700);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:900;margin:0 auto 12px">
            <?= mb_substr($user['name'],0,1) ?>
          </div>
          <div style="font-weight:800;color:var(--gray-900)"><?= e($user['name']) ?></div>
          <div style="font-size:.8rem;color:var(--text-muted);margin-top:4px"><?= e($user['email']) ?></div>
        </div>
        <div class="card" style="padding:6px 0">
          <?php
          $menu_tabs = [
            'profile'   => ['fa-user','Profil Saya'],
            'security'  => ['fa-lock','Keamanan'],
            'addresses' => ['fa-map-marker-alt','Alamat Saya'],
          ];
          foreach($menu_tabs as $t => [$icon,$label]): ?>
          <a href="?tab=<?= $t ?>" style="display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:.88rem;font-weight:700;color:<?= $tab===$t?'var(--green-700)':'var(--text-body)' ?>;background:<?= $tab===$t?'var(--green-50)':'transparent' ?>;border-left:3px solid <?= $tab===$t?'var(--green-600)':'transparent' ?>;transition:var(--transition)">
            <i class="fas <?= $icon ?>" style="color:<?= $tab===$t?'var(--green-600)':'var(--gray-400)' ?>;width:16px"></i>
            <?= $label ?>
          </a>
          <?php endforeach; ?>
          <a href="orders.php" style="display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:.88rem;font-weight:700;color:var(--text-body)">
            <i class="fas fa-box-open" style="color:var(--gray-400);width:16px"></i> Pesanan Saya
          </a>
          <a href="logout.php" style="display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:.88rem;font-weight:700;color:var(--red-500)">
            <i class="fas fa-sign-out-alt" style="width:16px"></i> Keluar
          </a>
        </div>
      </aside>

      <!-- Main Content -->
      <div>
        <?php if($errors): ?>
        <div class="alert alert-danger mb-16"><i class="fas fa-exclamation-circle"></i>
          <ul style="margin:0;padding-left:14px"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <?php if($tab === 'profile'): ?>
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="fas fa-user"></i> Informasi Profil</span></div>
          <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="save_profile" value="1">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:4px 0">
              <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
              </div>
              <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Email <span style="font-size:.75rem;color:var(--text-muted)">(tidak bisa diubah)</span></label>
                <input type="email" class="form-control" value="<?= e($user['email']) ?>" readonly style="background:var(--gray-50);color:var(--text-muted)">
              </div>
              <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Nomor Telepon</label>
                <div class="input-group"><i class="input-icon fas fa-phone"></i>
                <input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx"></div>
              </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
          </form>
        </div>

        <?php elseif($tab === 'security'): ?>
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="fas fa-lock"></i> Ganti Password</span></div>
          <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="change_password" value="1">
            <div style="display:flex;flex-direction:column;gap:14px;max-width:420px">
              <div class="form-group">
                <label class="form-label">Password Lama</label>
                <div class="input-group"><i class="input-icon fas fa-lock"></i>
                <input type="password" name="old_password" id="old_password" class="form-control" required>
                <span class="input-suffix" data-toggle-pwd="old_password"><i class="fas fa-eye"></i></span></div>
              </div>
              <div class="form-group">
                <label class="form-label">Password Baru</label>
                <div class="input-group"><i class="input-icon fas fa-lock"></i>
                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="8">
                <span class="input-suffix" data-toggle-pwd="new_password"><i class="fas fa-eye"></i></span></div>
                <p class="form-hint">Minimal 8 karakter</p>
              </div>
              <div class="form-group">
                <label class="form-label">Konfirmasi Password Baru</label>
                <div class="input-group"><i class="input-icon fas fa-lock"></i>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                <span class="input-suffix" data-toggle-pwd="confirm_password"><i class="fas fa-eye"></i></span></div>
              </div>
              <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Ubah Password</button>
            </div>
          </form>
        </div>

        <?php elseif($tab === 'addresses'): ?>
        <div class="card" style="margin-bottom:16px">
          <div class="card-header">
            <span class="card-title"><i class="fas fa-map-marker-alt"></i> Alamat Tersimpan</span>
            <button onclick="document.getElementById('addAddressForm').style.display='block';this.style.display='none'"
                    class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah Alamat</button>
          </div>

          <!-- Add Form -->
          <div id="addAddressForm" style="display:none;padding:16px;background:var(--gray-50);border-radius:var(--radius-md);margin-bottom:14px;border:1px solid var(--border)">
            <h4 style="font-size:.9rem;font-weight:800;margin-bottom:14px">Alamat Baru</h4>
            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="save_address" value="1">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="form-group">
                  <label class="form-label">Label</label>
                  <select name="label" class="form-control">
                    <?php foreach(['Rumah','Kantor','Lainnya'] as $l): ?><option value="<?= $l ?>"><?= $l ?></option><?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Nama Penerima</label>
                  <input type="text" name="recipient" class="form-control" required placeholder="Nama penerima">
                </div>
                <div class="form-group">
                  <label class="form-label">Telepon</label>
                  <input type="tel" name="phone" class="form-control" required placeholder="08xxxxxxxxxx">
                </div>
                <div class="form-group">
                  <label class="form-label">Provinsi</label>
                  <input type="text" name="province" class="form-control" required placeholder="Jawa Barat">
                </div>
                <div class="form-group">
                  <label class="form-label">Kota/Kabupaten</label>
                  <input type="text" name="city" class="form-control" required placeholder="Bandung">
                </div>
                <div class="form-group">
                  <label class="form-label">Kecamatan</label>
                  <input type="text" name="district" class="form-control" placeholder="Coblong">
                </div>
                <div class="form-group">
                  <label class="form-label">Kode Pos</label>
                  <input type="text" name="postal_code" class="form-control" placeholder="40135" maxlength="10">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                  <label class="form-label">Alamat Lengkap</label>
                  <textarea name="address" class="form-control" rows="2" required placeholder="Nama jalan, nomor, RT/RW…"></textarea>
                </div>
                <div style="grid-column:1/-1">
                  <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.85rem">
                    <input type="checkbox" name="is_default" value="1" style="accent-color:var(--green-600)">
                    Jadikan alamat default
                  </label>
                </div>
              </div>
              <div style="display:flex;gap:8px;margin-top:10px">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Alamat</button>
                <button type="button" onclick="document.getElementById('addAddressForm').style.display='none'" class="btn btn-ghost btn-sm" style="border:1px solid var(--border)">Batal</button>
              </div>
            </form>
          </div>

          <!-- Saved Addresses -->
          <?php if(empty($addresses)): ?>
          <div class="empty-state" style="padding:32px">
            <div class="empty-icon" style="font-size:2.5rem"><i class="fas fa-map-marker-alt"></i></div>
            <p class="empty-title">Belum ada alamat tersimpan</p>
          </div>
          <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach($addresses as $addr): ?>
            <div style="padding:14px;border:1.5px solid <?= $addr['is_default']?'var(--green-400)':'var(--border)' ?>;border-radius:var(--radius-md);background:<?= $addr['is_default']?'var(--green-50)':'var(--white)' ?>">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
                <div>
                  <div style="display:flex;gap:8px;align-items:center;margin-bottom:5px">
                    <strong style="font-size:.9rem"><?= e($addr['recipient']) ?></strong>
                    <span class="badge badge-secondary"><?= e($addr['label']) ?></span>
                    <?php if($addr['is_default']): ?><span class="badge badge-success">Default</span><?php endif; ?>
                  </div>
                  <div style="font-size:.82rem;color:var(--text-muted)">
                    <?= e($addr['phone']) ?><br>
                    <?= e($addr['address']) ?>, <?= e($addr['district']) ?>, <?= e($addr['city']) ?>, <?= e($addr['province']) ?> <?= e($addr['postal_code']) ?>
                  </div>
                </div>
                <div style="display:flex;gap:6px">
                  <a href="?delete_address=<?= $addr['id'] ?>" class="btn btn-ghost btn-sm" style="padding:5px 8px;border:1px solid var(--border)"
                     data-confirm="Hapus alamat ini?">
                    <i class="fas fa-trash" style="color:var(--red-500)"></i>
                  </a>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div><!-- /main -->
    </div><!-- /grid -->
  </div>
</main>

<script>
function applyProfileLayout() {
  const el = document.getElementById('profileLayout');
  if (el) el.style.gridTemplateColumns = window.innerWidth >= 768 ? '220px 1fr' : '1fr';
}
applyProfileLayout(); window.addEventListener('resize', applyProfileLayout);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
