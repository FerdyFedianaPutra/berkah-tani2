<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) redirect('orders.php');

// Re-check payment status from Midtrans if server key configured
$order_s = db()->prepare("SELECT o.*,p.status AS pay_status FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?");
$order_s->execute([$order_id, $_SESSION['user_id']]);
$order = $order_s->fetch();
if (!$order) redirect('orders.php');

$status = $_GET['status'] ?? 'success';

$page_title = 'Status Pembayaran';
require_once __DIR__ . '/includes/header.php';
?>
<main style="padding:40px 0 80px">
  <div class="container" style="max-width:520px;text-align:center">

    <?php if(in_array($order['status'],['paid','processing','shipped','completed'])): ?>
    <!-- SUCCESS -->
    <div style="width:80px;height:80px;border-radius:50%;background:var(--green-50);color:var(--green-600);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 20px">
      <i class="fas fa-check-circle"></i>
    </div>
    <h1 style="font-size:1.6rem;font-weight:900;color:var(--gray-900);margin-bottom:10px">Pembayaran Berhasil!</h1>
    <p style="color:var(--text-muted);margin-bottom:8px">Pesanan <strong><?= e($order['order_number']) ?></strong> telah dikonfirmasi.</p>
    <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:28px">Kami akan segera memproses pesanan Anda. Notifikasi akan dikirim ke email Anda.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="order-detail.php?id=<?= $order_id ?>" class="btn btn-primary"><i class="fas fa-box"></i> Lihat Detail Pesanan</a>
      <a href="products.php" class="btn btn-outline"><i class="fas fa-shopping-bag"></i> Belanja Lagi</a>
    </div>

    <?php elseif($order['status'] === 'cancelled'): ?>
    <!-- CANCELLED -->
    <div style="width:80px;height:80px;border-radius:50%;background:var(--red-100);color:var(--red-500);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 20px">
      <i class="fas fa-times-circle"></i>
    </div>
    <h1 style="font-size:1.6rem;font-weight:900;color:var(--gray-900);margin-bottom:10px">Pembayaran Dibatalkan</h1>
    <p style="color:var(--text-muted);margin-bottom:28px">Pesanan Anda telah dibatalkan karena pembayaran tidak berhasil.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="products.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Belanja Lagi</a>
      <a href="orders.php" class="btn btn-outline"><i class="fas fa-box"></i> Lihat Pesanan</a>
    </div>

    <?php else: ?>
    <!-- PENDING -->
    <div style="width:80px;height:80px;border-radius:50%;background:var(--yellow-100);color:var(--yellow-500);display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 20px">
      <i class="fas fa-clock"></i>
    </div>
    <h1 style="font-size:1.6rem;font-weight:900;color:var(--gray-900);margin-bottom:10px">Menunggu Pembayaran</h1>
    <p style="color:var(--text-muted);margin-bottom:8px">Pesanan <strong><?= e($order['order_number']) ?></strong> sedang menunggu konfirmasi pembayaran.</p>
    <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:28px">Selesaikan pembayaran Anda sebelum batas waktu yang ditentukan.</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="payment.php?order_id=<?= $order_id ?>" class="btn btn-primary"><i class="fas fa-credit-card"></i> Bayar Sekarang</a>
      <a href="orders.php" class="btn btn-outline"><i class="fas fa-box"></i> Lihat Pesanan</a>
    </div>
    <?php endif; ?>

  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
