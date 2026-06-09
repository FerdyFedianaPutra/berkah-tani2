<?php
require_once __DIR__ . '/../includes/auth.php';
admin_check();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(APP_URL . '/admin/pages/orders.php');

$stmt = db()->prepare("
    SELECT o.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           p.status AS pay_status, p.payment_type, p.bank, p.transaction_id, p.settled_at, p.gross_amount
    FROM orders o
    JOIN users u ON u.id=o.user_id
    LEFT JOIN payments p ON p.order_id=o.id
    WHERE o.id=?
");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { flash('admin','Pesanan tidak ditemukan.','danger'); redirect(APP_URL . '/admin/pages/orders.php'); }

$items_s = db()->prepare("SELECT oi.*, p.slug AS product_slug FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?");
$items_s->execute([$id]);
$items = $items_s->fetchAll();

$page_title = 'Detail Pesanan #' . $order['order_number'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
  <div>
    <a href="orders.php" style="font-size:.82rem;color:var(--green-600);font-weight:700;display:inline-flex;align-items:center;gap:5px;margin-bottom:6px">
      <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
    </a>
    <h1 style="font-size:1.1rem;font-weight:900;color:var(--gray-900)">Pesanan #<?= e($order['order_number']) ?></h1>
  </div>
  <span class="badge <?= order_status_class($order['status']) ?>" style="font-size:.88rem;padding:7px 16px">
    <?= order_status_label($order['status']) ?>
  </span>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:20px" id="adminOrderLayout">

  <!-- Left: Items, Timeline, Notes -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Order Items -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-box"></i> Produk Dipesan</span></div>
      <?php foreach($items as $oi): ?>
      <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);align-items:center">
        <img src="<?= product_img($oi['product_image'] ?? '') ?>" alt=""
             style="width:52px;height:52px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);flex-shrink:0">
        <div style="flex:1;min-width:0">
          <div style="font-size:.88rem;font-weight:700"><?= e($oi['product_name']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted)"><?= $oi['quantity'] ?> × <?= rp($oi['price']) ?></div>
        </div>
        <strong style="color:var(--green-700)"><?= rp($oi['subtotal']) ?></strong>
      </div>
      <?php endforeach; ?>
      <div style="margin-top:12px">
        <div class="summary-row"><span>Subtotal</span><span><?= rp($order['subtotal']) ?></span></div>
        <div class="summary-row"><span>Ongkos Kirim</span><span><?= $order['shipping_cost']>0?rp($order['shipping_cost']):'<span style="color:var(--green-600)">Gratis</span>' ?></span></div>
        <div class="summary-row total"><span>Total Bayar</span><span class="val"><?= rp($order['total']) ?></span></div>
      </div>
    </div>

    <!-- Update Status Form -->
    <?php if(!in_array($order['status'],['completed','cancelled'])): ?>
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-sync-alt"></i> Update Status Pesanan</span></div>
      <form method="POST" action="orders.php?action=update_status&id=<?= $id ?>">
        <?= csrf_field() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:16px 0">
          <div class="form-group">
            <label class="form-label">Status Baru</label>
            <select name="status" id="statusSelect" class="form-control" onchange="toggleFields(this.value)">
              <?php
              $next = match($order['status']) {
                'pending'    => ['paid'=>'Dibayar','cancelled'=>'Dibatalkan'],
                'paid'       => ['processing'=>'Diproses','cancelled'=>'Dibatalkan'],
                'processing' => ['shipped'=>'Dikirim','cancelled'=>'Dibatalkan'],
                'shipped'    => ['completed'=>'Selesai'],
                default      => [],
              };
              foreach($next as $v => $l): ?>
              <option value="<?= $v ?>"><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <!-- <div class="form-group" id="trackingFields" style="display:none">
            <label class="form-label">No. Resi Pengiriman</label>
            <input type="text" name="tracking_number" class="form-control" value="<?= e($order['tracking_number'] ?? '') ?>" placeholder="JNE123456789">
          </div> -->
          <div class="form-group" id="shipMethodField" style="display:none;grid-column:1/-1">
            <label class="form-label">Ekspedisi</label>
            <select name="shipping_method" class="form-control">
              <?php foreach(['JNE','J&T','SiCepat','Anteraja','Ninja Xpress','Pos Indonesia','Tiki'] as $exp): ?>
              <option value="<?= $exp ?>" <?= ($order['shipping_method']===$exp)?'selected':'' ?>><?= $exp ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" id="cancelField" style="display:none;grid-column:1/-1">
            <label class="form-label">Alasan Pembatalan</label>
            <textarea name="cancel_reason" class="form-control" rows="2" placeholder="Alasan pembatalan pesanan…"><?= e($order['cancel_reason'] ?? '') ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update Status</button>
      </form>
    </div>
    <?php endif; ?>

  </div>

  <!-- Right: Customer, Shipping, Payment -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Customer -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-user"></i> Pelanggan</span></div>
      <div style="font-size:.88rem;display:flex;flex-direction:column;gap:6px">
        <div><strong><?= e($order['user_name']) ?></strong></div>
        <div style="color:var(--text-muted)"><i class="fas fa-envelope" style="width:16px;color:var(--green-600)"></i> <?= e($order['user_email']) ?></div>
        <?php if($order['user_phone']): ?>
        <div style="color:var(--text-muted)"><i class="fas fa-phone" style="width:16px;color:var(--green-600)"></i> <?= e($order['user_phone']) ?></div>
        <?php endif; ?>
        <div style="margin-top:6px"><a href="users.php?q=<?= urlencode($order['user_email']) ?>" class="btn btn-ghost btn-sm" style="border:1px solid var(--border);padding:5px 10px"><i class="fas fa-external-link-alt"></i> Lihat Profil</a></div>
      </div>
    </div>

    <!-- Shipping Address -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</span></div>
      <div style="font-size:.85rem;color:var(--text-body);line-height:1.8">
        <strong><?= e($order['ship_name']) ?></strong><br>
        <?= e($order['ship_phone']) ?><br>
        <?= e($order['ship_address']) ?><br>
        Kec. <?= e($order['ship_district']) ?>, <?= e($order['ship_city']) ?><br>
        <?= e($order['ship_province']) ?> <?= e($order['ship_postal']) ?>
      </div>
      <?php if($order['notes']): ?>
      <div style="margin-top:10px;padding:8px;background:var(--yellow-100);border-radius:var(--radius-sm);font-size:.8rem;color:#78350f">
        <i class="fas fa-sticky-note"></i> <?= e($order['notes']) ?>
      </div>
      <?php endif; ?>
      <?php if($order['tracking_number']): ?>
      <div style="margin-top:10px;padding:8px;background:var(--green-50);border-radius:var(--radius-sm);font-size:.82rem;color:var(--green-700)">
        <i class="fas fa-truck"></i> Resi: <strong><?= e($order['tracking_number']) ?></strong>
        <?= $order['shipping_method'] ? ' via ' . e($order['shipping_method']) : '' ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Payment -->
    <div class="card">
      <div class="card-header"><span class="card-title"><i class="fas fa-credit-card"></i> Pembayaran</span></div>
      <div style="font-size:.85rem;display:flex;flex-direction:column;gap:7px">
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-muted)">Status</span>
          <span class="badge <?= $order['pay_status']==='settlement'?'badge-success':($order['pay_status']==='pending'?'badge-warning':'badge-secondary') ?>">
            <?= match($order['pay_status']) { 'settlement'=>'Lunas','pending'=>'Belum Bayar','expire'=>'Kadaluarsa','cancel','deny'=>'Gagal',default=>'–' } ?>
          </span>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-muted)">Jumlah</span>
          <strong style="color:var(--green-700)"><?= rp($order['gross_amount'] ?? $order['total']) ?></strong>
        </div>
        <?php if($order['payment_type']): ?>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-muted)">Metode</span>
          <span style="font-weight:700"><?= ucwords(str_replace('_',' ',$order['payment_type'])) . ($order['bank']?' - '.strtoupper($order['bank']):'') ?></span>
        </div>
        <?php endif; ?>
        <?php if($order['transaction_id']): ?>
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:4px">
          <span style="color:var(--text-muted)">ID Transaksi</span>
          <code style="font-size:.75rem;background:var(--gray-100);padding:2px 6px;border-radius:4px;word-break:break-all"><?= e($order['transaction_id']) ?></code>
        </div>
        <?php endif; ?>
        <?php if($order['settled_at']): ?>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-muted)">Waktu Bayar</span>
          <span style="font-weight:700"><?= date('d M Y H:i',strtotime($order['settled_at'])) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
function toggleFields(status) {
  document.getElementById('trackingFields').style.display  = status==='shipped'?'block':'none';
  document.getElementById('shipMethodField').style.display = status==='shipped'?'block':'none';
  document.getElementById('cancelField').style.display     = status==='cancelled'?'block':'none';
}
toggleFields(document.getElementById('statusSelect')?.value);

function applyLayout() {
  const el = document.getElementById('adminOrderLayout');
  if (el) el.style.gridTemplateColumns = window.innerWidth >= 1024 ? '1fr 320px' : '1fr';
}
applyLayout(); window.addEventListener('resize', applyLayout);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
