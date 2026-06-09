<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) redirect('orders.php');

$stmt = db()->prepare("
    SELECT o.*, p.status AS pay_status, p.payment_type, p.bank, p.transaction_id, p.settled_at
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) redirect('orders.php');

$items_s = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_s->execute([$order_id]);
$items = $items_s->fetchAll();

// Status timeline
$timeline = [
    ['status'=>'pending',    'label'=>'Pesanan Dibuat',      'icon'=>'fa-receipt'],
    ['status'=>'paid',       'label'=>'Pembayaran Diterima', 'icon'=>'fa-check-circle'],
    ['status'=>'processing', 'label'=>'Sedang Diproses',     'icon'=>'fa-cog'],
    ['status'=>'shipped',    'label'=>'Dikirim',             'icon'=>'fa-truck'],
    ['status'=>'completed',  'label'=>'Pesanan Selesai',     'icon'=>'fa-box-open'],
];
$status_order = ['pending'=>0,'paid'=>1,'processing'=>2,'shipped'=>3,'completed'=>4,'cancelled'=>-1];
$current_idx  = $status_order[$order['status']] ?? 0;

$page_title = 'Detail Pesanan #' . $order['order_number'];
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container" style="max-width:820px">

    <div class="breadcrumb">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <a href="orders.php">Pesanan</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current"><?= e($order['order_number']) ?></span>
    </div>

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:16px 0 20px">
      <div>
        <h1 style="font-size:1.2rem;font-weight:900;color:var(--gray-900)">
          <i class="fas fa-box" style="color:var(--green-600)"></i> Pesanan #<?= e($order['order_number']) ?>
        </h1>
        <p style="font-size:.82rem;color:var(--text-muted);margin-top:4px">
          Dibuat: <?= date('d M Y H:i', strtotime($order['created_at'])) ?>
        </p>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <span class="badge <?= order_status_class($order['status']) ?>" style="font-size:.85rem;padding:6px 14px">
          <?= order_status_label($order['status']) ?>
        </span>
        <?php if($order['status'] === 'pending'): ?>
        <a href="payment.php?order_id=<?= $order_id ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-credit-card"></i> Bayar Sekarang
        </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if(!empty($_GET['paid'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Pembayaran berhasil dikonfirmasi! Pesanan Anda sedang diproses.</div>
    <?php endif; ?>

    <!-- Timeline -->
    <?php if($order['status'] !== 'cancelled'): ?>
    <div class="card" style="margin-bottom:16px;overflow:hidden">
      <div style="display:flex;justify-content:space-between;position:relative;padding:20px 10px 8px">
        <div style="position:absolute;top:33px;left:10%;right:10%;height:2px;background:var(--border);z-index:0"></div>
        <div style="position:absolute;top:33px;left:10%;height:2px;background:var(--green-500);z-index:1;transition:width .5s ease;width:<?= $current_idx > 0 ? min(100, ($current_idx/(count($timeline)-1))*80) : 0 ?>%"></div>
        <?php foreach($timeline as $i => $step):
            $done    = $i <= $current_idx;
            $active  = $i === $current_idx;
        ?>
        <div style="display:flex;flex-direction:column;align-items:center;gap:6px;z-index:2;flex:1">
          <div style="width:28px;height:28px;border-radius:50%;background:<?= $done?'var(--green-600)':'var(--gray-200)' ?>;color:<?= $done?'#fff':'var(--gray-400)' ?>;display:flex;align-items:center;justify-content:center;font-size:.72rem;border:2px solid <?= $active?'var(--green-400)':'transparent' ?>;transition:all .3s">
            <i class="fas <?= $step['icon'] ?>"></i>
          </div>
          <span style="font-size:.65rem;font-weight:<?= $active?'800':'600' ?>;color:<?= $done?'var(--green-700)':'var(--gray-400)' ?>;text-align:center;line-height:1.3">
            <?= $step['label'] ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-danger" style="margin-bottom:16px">
      <i class="fas fa-times-circle"></i>
      Pesanan ini telah dibatalkan<?= $order['cancel_reason'] ? ': ' . e($order['cancel_reason']) : '' ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr;gap:16px" id="detailLayout">

      <!-- Items & Payment -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Items -->
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="fas fa-box"></i> Produk Dipesan</span></div>
          <?php foreach($items as $oi): ?>
          <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);align-items:center">
            <img src="<?= product_img($oi['product_image'] ?? '') ?>" alt=""
                 style="width:60px;height:60px;object-fit:cover;border-radius:var(--radius-md);border:1px solid var(--border);flex-shrink:0">
            <div style="flex:1;min-width:0">
              <div style="font-size:.9rem;font-weight:700;color:var(--gray-800)"><?= e($oi['product_name']) ?></div>
              <div style="font-size:.78rem;color:var(--text-muted);margin-top:3px"><?= $oi['quantity'] ?> × <?= rp($oi['price']) ?></div>
            </div>
            <div style="font-weight:800;color:var(--green-700);white-space:nowrap"><?= rp($oi['subtotal']) ?></div>
          </div>
          <?php endforeach; ?>

          <!-- Totals -->
          <div style="margin-top:12px">
            <div class="summary-row"><span>Subtotal</span><span><?= rp($order['subtotal']) ?></span></div>
            <div class="summary-row">
              <span>Ongkos Kirim</span>
              <span><?= $order['shipping_cost'] > 0 ? rp($order['shipping_cost']) : '<span style="color:var(--green-600)">-</span>' ?></span>
            </div>
            <?php if($order['discount'] > 0): ?>
            <div class="summary-row"><span>Diskon</span><span style="color:var(--red-500)">-<?= rp($order['discount']) ?></span></div>
            <?php endif; ?>
            <div class="summary-row total"><span>Total Bayar</span><span class="val"><?= rp($order['total']) ?></span></div>
          </div>
        </div>

        <!-- Payment Info -->
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="fas fa-credit-card"></i> Informasi Pembayaran</span></div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:.88rem">
            <div style="display:flex;justify-content:space-between">
              <span style="color:var(--text-muted)">Status Pembayaran</span>
              <span class="badge <?= $order['pay_status']==='settlement'?'badge-success':($order['pay_status']==='pending'?'badge-warning':'badge-danger') ?>">
                <?= match($order['pay_status']) { 'settlement'=>'Lunas','pending'=>'Belum Dibayar','expire'=>'Expired','cancel','deny'=>'Dibatalkan',default=>'–' } ?>
              </span>
            </div>
            <?php if($order['payment_type']): ?>
            <div style="display:flex;justify-content:space-between">
              <span style="color:var(--text-muted)">Metode Bayar</span>
              <span style="font-weight:700"><?= ucwords(str_replace('_',' ',$order['payment_type'])) . ($order['bank'] ? ' - '.strtoupper($order['bank']) : '') ?></span>
            </div>
            <?php endif; ?>
            <?php if($order['transaction_id']): ?>
            <div style="display:flex;justify-content:space-between">
              <span style="color:var(--text-muted)">ID Transaksi</span>
              <span style="font-size:.8rem;font-family:monospace"><?= e($order['transaction_id']) ?></span>
            </div>
            <?php endif; ?>
            <?php if($order['settled_at']): ?>
            <div style="display:flex;justify-content:space-between">
              <span style="color:var(--text-muted)">Waktu Pembayaran</span>
              <span style="font-weight:700"><?= date('d M Y H:i', strtotime($order['settled_at'])) ?></span>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- Shipping -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</span></div>
          <div style="font-size:.88rem;color:var(--text-body);line-height:1.8">
            <strong style="font-size:.95rem"><?= e($order['ship_name']) ?></strong><br>
            <span style="color:var(--text-muted)"><?= e($order['ship_phone']) ?></span><br>
            <?= e($order['ship_address']) ?><br>
            Kec. <?= e($order['ship_district']) ?>, <?= e($order['ship_city']) ?><br>
            <?= e($order['ship_province']) ?> <?= e($order['ship_postal']) ?>
          </div>
          <?php if($order['notes']): ?>
          <div style="margin-top:12px;padding:10px;background:var(--yellow-100);border-radius:var(--radius-sm);font-size:.82rem;color:#78350f">
            <i class="fas fa-sticky-note"></i> Catatan: <?= e($order['notes']) ?>
          </div>
          <?php endif; ?>
        </div>

        <?php if($order['tracking_number']): ?>
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="fas fa-truck"></i> Info Pengiriman</span></div>
          <div style="font-size:.88rem">
            <?php if($order['shipping_method']): ?>
            <div style="margin-bottom:6px"><span style="color:var(--text-muted)">Ekspedisi:</span> <strong><?= e($order['shipping_method']) ?></strong></div>
            <?php endif; ?>
            <div><span style="color:var(--text-muted)">No. Resi:</span> <strong style="font-family:monospace"><?= e($order['tracking_number']) ?></strong></div>
          </div>
        </div>
        <?php endif; ?>

        <a href="orders.php" class="btn btn-ghost" style="border:1px solid var(--border)">
          <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
        </a>
      </div>

    </div>
  </div>
</main>

<script>
function applyLayout() {
  const el = document.getElementById('detailLayout');
  if (el) el.style.gridTemplateColumns = window.innerWidth >= 768 ? '1fr 320px' : '1fr';
}
applyLayout(); window.addEventListener('resize', applyLayout);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
