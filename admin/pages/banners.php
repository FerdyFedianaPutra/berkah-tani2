<?php
require_once __DIR__ . '/../includes/auth.php';
admin_check();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

// ====================== DELETE ======================
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('admin', 'Token tidak valid.', 'danger');
    } else {
        // Hapus file gambar jika ada
        $s = db()->prepare("SELECT image FROM banners WHERE id=?");
        $s->execute([$id]);
        $b = $s->fetch();

        if ($b && $b['image'] && file_exists(UPLOAD_DIR . 'banners/' . $b['image'])) {
            unlink(UPLOAD_DIR . 'banners/' . $b['image']);
        }

        db()->prepare("DELETE FROM banners WHERE id=?")->execute([$id]);
        flash('admin', 'Banner berhasil dihapus.', 'success');
    }
    redirect(APP_URL . '/admin/pages/banners.php');
}

// ====================== TOGGLE ACTIVE ======================
if ($action === 'toggle' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('admin', 'Token tidak valid.', 'danger');
    } else {
        db()->prepare("UPDATE banners SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        flash('admin', 'Status banner diperbarui.', 'success');
    }
    redirect(APP_URL . '/admin/pages/banners.php');
}

// ====================== SAVE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['delete', 'toggle'])) {
    if (!verify_csrf()) {
        $errors[] = 'Token tidak valid.';
    } else {
        $title      = trim($_POST['title'] ?? '');
        $subtitle   = trim($_POST['subtitle'] ?? '');
        $link_url   = trim($_POST['link_url'] ?? '');
        $link_text  = trim($_POST['link_text'] ?? 'Lihat Produk');
        $position   = $_POST['position'] ?? 'hero';
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active  = !empty($_POST['is_active']) ? 1 : 0;
        $image_url  = trim($_POST['image_url'] ?? '');
        $start_date = $_POST['start_date'] ?: null;
        $end_date   = $_POST['end_date'] ?: null;

        if (!$title) $errors[] = 'Judul banner wajib diisi.';

        // Handle image: file upload takes priority over URL
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $uploaded = upload_file($_FILES['image'], 'banners');
            if ($uploaded) $image = $uploaded;
            else $errors[] = 'Gagal upload gambar.';
        } elseif ($image_url) {
            $image = $image_url;
        }

        if ($action === 'add' && !$image) $errors[] = 'Gambar banner wajib diisi.';

        if (empty($errors)) {
            if ($action === 'add') {
                db()->prepare("INSERT INTO banners (title,subtitle,image,link_url,link_text,position,sort_order,is_active,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$title, $subtitle, $image, $link_url, $link_text, $position, $sort_order, $is_active, $start_date, $end_date]);
                flash('admin', 'Banner berhasil ditambahkan.', 'success');
            } else {
                $set_img = $image ? ', image=?' : '';
                $params  = [$title, $subtitle, $link_url, $link_text, $position, $sort_order, $is_active, $start_date, $end_date];
                if ($image) $params[] = $image;
                $params[] = $id;

                db()->prepare("UPDATE banners SET title=?,subtitle=?,link_url=?,link_text=?,position=?,sort_order=?,is_active=?,start_date=?,end_date=? $set_img WHERE id=?")
                     ->execute($params);
                flash('admin', 'Banner berhasil diperbarui.', 'success');
            }
            redirect(APP_URL . '/admin/pages/banners.php');
        }
    }
}

// Fetch for edit
$banner = null;
if ($action === 'edit' && $id) {
    $s = db()->prepare("SELECT * FROM banners WHERE id=?");
    $s->execute([$id]);
    $banner = $s->fetch();
    if (!$banner) redirect(APP_URL . '/admin/pages/banners.php');
}

$banners = db()->query("SELECT * FROM banners ORDER BY position, sort_order")->fetchAll();

$page_title = match($action) {
    'add' => 'Tambah Banner',
    'edit' => 'Edit Banner',
    default => 'Manajemen Banner'
};

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'list'): ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h2 style="font-size:1rem;font-weight:800;color:var(--gray-800)">
    <i class="fas fa-images" style="color:var(--green-600)"></i> Manajemen Banner
  </h2>
  <a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah Banner</a>
</div>

<div class="admin-table-wrap">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:120px">Preview</th>
          <th>Judul</th>
          <th>Posisi</th>
          <th>Urutan</th>
          <th>Periode</th>
          <th>Status</th>
          <th style="width:140px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($banners)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Belum ada banner</td></tr>
        <?php endif; ?>

        <?php foreach ($banners as $b): ?>
        <tr>
          <td>
            <img src="<?= banner_img($b['image']) ?>" alt="<?= e($b['title']) ?>"
                 style="width:110px;height:60px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border)">
          </td>
          <td>
            <strong style="font-size:.88rem"><?= e($b['title']) ?></strong>
            <?php if ($b['subtitle']): ?>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e(truncate($b['subtitle'],60)) ?></div>
            <?php endif; ?>
            <?php if ($b['link_url']): ?>
            <div style="font-size:.72rem;color:var(--green-600)"><i class="fas fa-link"></i> <?= e(truncate($b['link_url'],40)) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $b['position']==='hero'?'badge-primary':($b['position']==='mid'?'badge-info':'badge-secondary') ?>">
              <?= ucfirst($b['position']) ?>
            </span>
          </td>
          <td><?= $b['sort_order'] ?></td>
          <td style="font-size:.78rem;color:var(--text-muted)">
            <?php if ($b['start_date'] || $b['end_date']): ?>
              <?= $b['start_date'] ? date('d/m/Y', strtotime($b['start_date'])) : '–' ?>
              → <?= $b['end_date'] ? date('d/m/Y', strtotime($b['end_date'])) : '∞' ?>
            <?php else: ?>Selalu Aktif<?php endif; ?>
          </td>
          <td>
            <!-- Toggle Form -->
            <form method="POST" action="?action=toggle&id=<?= $b['id'] ?>" style="display:inline;">
              <?= csrf_field() ?>
              <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                <span style="position:relative;display:inline-block;width:38px;height:22px">
                  <input type="checkbox" <?= $b['is_active'] ? 'checked' : '' ?>
                         onchange="this.form.submit()" 
                         style="opacity:0;width:0;height:0">
                  <span style="position:absolute;inset:0;border-radius:22px;background:<?= $b['is_active']?'var(--green-500)':'var(--gray-300)' ?>;transition:var(--transition)"></span>
                  <span style="position:absolute;left:<?= $b['is_active']?'18px':'2px' ?>;top:2px;width:18px;height:18px;border-radius:50%;background:#fff;transition:var(--transition);box-shadow:var(--shadow-xs)"></span>
                </span>
              </label>
            </form>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="?action=edit&id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm" style="padding:5px 8px">
                <i class="fas fa-edit" style="color:var(--blue-500)"></i>
              </a>

              <!-- Delete Form -->
              <form method="POST" action="?action=delete&id=<?= $b['id'] ?>" style="display:inline;" 
                    onsubmit="return confirm('Hapus banner ini?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-sm" style="padding:5px 8px">
                  <i class="fas fa-trash" style="color:var(--red-500)"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<!-- ===== ADD / EDIT FORM ===== -->
<?php if ($errors): ?>
<div class="alert alert-danger mb-16">
  <i class="fas fa-exclamation-circle"></i>
  <ul style="margin:0;padding-left:14px"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr;gap:20px" id="bannerFormGrid">
  <div class="admin-form-panel">
    <div class="admin-form-header">
      <span style="font-weight:800"><i class="fas fa-image" style="color:var(--green-600)"></i> <?= $action==='add'?'Tambah Banner Baru':'Edit Banner' ?></span>
      <a href="?action=list" class="btn btn-ghost btn-sm" style="border:1px solid var(--border)"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="admin-form-body" style="display:flex;flex-direction:column;gap:14px">
        <!-- Form fields tetap sama seperti kode asli Anda -->
        <div class="form-group">
          <label class="form-label">Judul Banner <span class="required">*</span></label>
          <input type="text" name="title" class="form-control" required value="<?= e($banner['title'] ?? '') ?>" placeholder="Beras Premium Berkualitas">
        </div>

        <div class="form-group">
          <label class="form-label">Sub-judul</label>
          <input type="text" name="subtitle" class="form-control" value="<?= e($banner['subtitle'] ?? '') ?>" placeholder="Langsung dari petani mitra">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">Link URL</label>
            <input type="text" name="link_url" class="form-control" value="<?= e($banner['link_url'] ?? '') ?>" placeholder="products.php">
          </div>
          <div class="form-group">
            <label class="form-label">Teks Tombol</label>
            <input type="text" name="link_text" class="form-control" value="<?= e($banner['link_text'] ?? 'Lihat Produk') ?>" placeholder="Belanja Sekarang">
          </div>
          <div class="form-group">
            <label class="form-label">Posisi</label>
            <select name="position" class="form-control">
              <?php foreach(['hero'=>'Hero (Utama)','mid'=>'Tengah','bottom'=>'Bawah'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($banner['position'] ?? 'hero')===$v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Urutan</label>
            <input type="number" name="sort_order" class="form-control" min="0" value="<?= $banner['sort_order'] ?? 0 ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="start_date" class="form-control" value="<?= $banner['start_date'] ?? '' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Berakhir</label>
            <input type="date" name="end_date" class="form-control" value="<?= $banner['end_date'] ?? '' ?>">
          </div>
        </div>

        <!-- Image Section -->
        <div class="form-group">
          <label class="form-label">Gambar Banner <?= $action==='add'?'<span class="required">*</span>':'' ?></label>

          <?php if ($action === 'edit' && !empty($banner['image'])): ?>
          <div style="margin-bottom:10px">
            <img src="<?= banner_img($banner['image']) ?>" alt="" style="width:100%;max-height:140px;object-fit:cover;border-radius:var(--radius-md);border:1px solid var(--border)">
          </div>
          <?php endif; ?>

          <label class="img-upload-wrap" for="bannerImg">
            <img id="bannerPreview" class="img-preview">
            <i class="fas fa-cloud-upload-alt" style="font-size:1.8rem;color:var(--gray-300);display:block;margin-bottom:8px"></i>
            <div style="font-size:.85rem;font-weight:700;color:var(--gray-600)">Upload file gambar</div>
            <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px">JPG, PNG, WEBP – Rekomendasi 1400×500px</div>
          </label>
          <input type="file" name="image" id="bannerImg" accept="image/*" style="display:none" data-preview="bannerPreview">

          <div style="margin-top:10px">
            <label class="form-label" style="font-size:.78rem">Atau gunakan URL gambar:</label>
            <input type="url" name="image_url" class="form-control"
                   value="<?= (!empty($banner['image']) && str_starts_with($banner['image'],'http')) ? e($banner['image']) : '' ?>"
                   placeholder="https://example.com/gambar.jpg">
          </div>
        </div>

        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="is_active" value="1" <?= ($banner['is_active'] ?? 1) ? 'checked' : '' ?> style="accent-color:var(--green-600);width:16px;height:16px">
          <span style="font-size:.88rem;font-weight:700">Banner Aktif</span>
        </label>
      </div>

      <div class="admin-form-footer">
        <a href="?action=list" class="btn btn-ghost" style="border:1px solid var(--border)">Batal</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Banner</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>