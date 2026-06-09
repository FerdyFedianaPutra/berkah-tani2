<?php
require_once __DIR__ . '/../includes/auth.php';
admin_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('admin','Token tidak valid.','danger'); }
    else {
        $fields = [
            'site_name','site_tagline','site_email','site_phone','site_address',
            'shipping_cost','free_shipping_min',
            'midtrans_server_key','midtrans_client_key','midtrans_is_sandbox',
        ];
        $stmt = db()->prepare("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?");
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $stmt->execute([$f, $val, $val]);
        }
        flash('admin','Pengaturan berhasil disimpan.','success');
        redirect(APP_URL . '/admin/pages/settings.php');
    }
}

// Reload settings
$settings = db()->query("SELECT `key`,`value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = 'Pengaturan Toko';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width:760px">
<form method="POST">
  <?= csrf_field() ?>

  <!-- General -->
  <div class="admin-form-panel" style="margin-bottom:20px">
    <div class="admin-form-header"><span style="font-weight:800"><i class="fas fa-store" style="color:var(--green-600)"></i> Informasi Toko</span></div>
    <div class="admin-form-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Nama Toko</label>
          <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name'] ?? APP_NAME) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Tagline</label>
          <input type="text" name="site_tagline" class="form-control" value="<?= e($settings['site_tagline'] ?? '') ?>" placeholder="Beras Berkualitas Langsung dari Petani">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <div class="input-group"><i class="input-icon fas fa-envelope"></i>
          <input type="email" name="site_email" class="form-control" value="<?= e($settings['site_email'] ?? '') ?>"></div>
        </div>
        <div class="form-group">
          <label class="form-label">No. WhatsApp <span style="font-size:.72rem;color:var(--text-muted)">(tanpa +)</span></label>
          <div class="input-group"><i class="input-icon fab fa-whatsapp"></i>
          <input type="text" name="site_phone" class="form-control" value="<?= e($settings['site_phone'] ?? '') ?>" placeholder="6285601372013"></div>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label class="form-label">Alamat Toko</label>
          <textarea name="site_address" class="form-control" rows="2"><?= e($settings['site_address'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- Shipping -->
  <div class="admin-form-panel" style="margin-bottom:20px">
    <div class="admin-form-header"><span style="font-weight:800"><i class="fas fa-truck" style="color:var(--green-600)"></i> Pengaturan Pengiriman</span></div>
    <div class="admin-form-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Biaya Ongkos Kirim Default (Rp)</label>
          <div class="input-group"><i class="input-icon fas fa-rupiah-sign" style="font-size:.75rem"></i>
          <input type="number" name="shipping_cost" class="form-control" min="0" step="1000"
                 value="<?= e($settings['shipping_cost'] ?? 15000) ?>"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Min. Belanja Gratis Ongkir (Rp)</label>
          <div class="input-group"><i class="input-icon fas fa-rupiah-sign" style="font-size:.75rem"></i>
          <input type="number" name="free_shipping_min" class="form-control" min="0" step="10000"
                 value="<?= e($settings['free_shipping_min'] ?? 200000) ?>"></div>
        </div>
      </div>
    </div>
  </div>

  Midtrans
  <!-- <div class="admin-form-panel" style="margin-bottom:20px">
    <div class="admin-form-header">
      <span style="font-weight:800"><i class="fas fa-credit-card" style="color:var(--green-600)"></i> Konfigurasi Midtrans</span>
      <a href="https://dashboard.midtrans.com" target="_blank" class="btn btn-ghost btn-sm" style="border:1px solid var(--border)">
        <i class="fas fa-external-link-alt"></i> Buka Dashboard
      </a>
    </div>
    <div class="admin-form-body">
      <div class="alert alert-info" style="margin-bottom:16px">
        <i class="fas fa-info-circle"></i>
        <div>Daftarkan callback URL di Midtrans Dashboard: <code><?= APP_URL ?>/payment-callback.php</code></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="form-group">
          <label class="form-label">Server Key</label>
          <div class="input-group"><i class="input-icon fas fa-key"></i>
          <input type="password" name="midtrans_server_key" id="midtrans_server_key" class="form-control"
                 value="<?= e($settings['midtrans_server_key'] ?? '') ?>" placeholder="SB-Mid-server-xxxxxxxx">
          <span class="input-suffix" data-toggle-pwd="midtrans_server_key"><i class="fas fa-eye"></i></span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Client Key</label>
          <div class="input-group"><i class="input-icon fas fa-key"></i>
          <input type="text" name="midtrans_client_key" class="form-control"
                 value="<?= e($settings['midtrans_client_key'] ?? '') ?>" placeholder="SB-Mid-client-xxxxxxxx"></div>
        </div>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="midtrans_is_sandbox" value="1"
                 <?= ($settings['midtrans_is_sandbox'] ?? '1') === '1' ? 'checked' : '' ?>
                 style="accent-color:var(--green-600);width:16px;height:16px">
          <div>
            <div style="font-weight:700;font-size:.88rem">Mode Sandbox (Testing)</div>
            <div style="font-size:.75rem;color:var(--text-muted)">Nonaktifkan untuk mode produksi</div>
          </div>
        </label>
      </div>
    </div>
  </div> -->

  <div style="display:flex;gap:10px;justify-content:flex-end">
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fas fa-save"></i> Simpan Semua Pengaturan
    </button>
  </div>
</form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
