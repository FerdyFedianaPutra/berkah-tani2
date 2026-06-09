<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) redirect('orders.php');

$uid = $_SESSION['user_id'];

// Fetch order
$stmt = db()->prepare("
    SELECT o.*, p.snap_token, p.snap_redirect_url, p.status AS pay_status, p.id AS payment_id
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $uid]);
$order = $stmt->fetch();
if (!$order) redirect('orders.php');

// If already paid, redirect to order detail
if (in_array($order['status'], ['paid','processing','shipped','completed'])) {
    redirect("order-detail.php?id=$order_id");
}

// Fetch order items
$items_s = db()->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items_s->execute([$order_id]);
$order_items = $items_s->fetchAll();

// Midtrans Snap Token generation
$snap_token = $order['snap_token'];
$midtrans_client_key = setting('midtrans_client_key', MIDTRANS_CLIENT_KEY);
$is_sandbox = (bool)setting('midtrans_is_sandbox', '1');

if (!$snap_token && MIDTRANS_SERVER_KEY) {
    // Build Midtrans request
    $user_data = current_user();
    $item_details = [];
    foreach ($order_items as $oi) {
        $item_details[] = [
            'id'       => (string)$oi['product_id'],
            'price'    => (int)$oi['price'],
            'quantity' => (int)$oi['quantity'],
            'name'     => mb_substr($oi['product_name'], 0, 50),
        ];
    }
    if ($order['shipping_cost'] > 0) {
        $item_details[] = [
            'id'       => 'SHIPPING',
            'price'    => (int)$order['shipping_cost'],
            'quantity' => 1,
            'name'     => 'Ongkos Kirim',
        ];
    }

    $payload = [
        'transaction_details' => [
            'order_id'     => $order['order_number'],
            'gross_amount' => (int)$order['total'],
        ],
        'customer_details' => [
            'first_name'   => $user_data['name'],
            'email'        => $user_data['email'],
            'phone'        => $order['ship_phone'],
            'shipping_address' => [
                'first_name' => $order['ship_name'],
                'phone'      => $order['ship_phone'],
                'address'    => $order['ship_address'],
                'city'       => $order['ship_city'],
                'postal_code'=> $order['ship_postal'],
                'country_code'=> 'IDN',
            ],
        ],
        'item_details' => $item_details,
        'callbacks' => [
            'finish' => APP_URL . "/payment-finish.php?order_id=$order_id",
        ],
    ];

    $api_url = $is_sandbox
        ? 'https://app.sandbox.midtrans.com/snap/v1/transactions'
        : 'https://app.midtrans.com/snap/v1/transactions';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':'),
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (!empty($result['token'])) {
        $snap_token = $result['token'];
        $redirect_url = $result['redirect_url'] ?? '';
        db()->prepare("UPDATE payments SET snap_token=?, snap_redirect_url=?, raw_response=?, updated_at=NOW() WHERE order_id=?")
             ->execute([$snap_token, $redirect_url, $response, $order_id]);
    }
}

$snap_js = $is_sandbox
    ? 'https://app.sandbox.midtrans.com/snap/snap.js'
    : 'https://app.midtrans.com/snap/snap.js';

$page_title = 'Pembayaran – ' . $order['order_number'];
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container" style="max-width:760px">

    <div class="breadcrumb">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <a href="orders.php">Pesanan</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current">Pembayaran</span>
    </div>

    <div style="text-align:center;margin:20px 0 28px">
      <div style="width:64px;height:64px;border-radius:50%;background:var(--green-50);color:var(--green-600);display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto 14px">
        <i class="fas fa-credit-card"></i>
      </div>
      <h1 style="font-size:1.4rem;font-weight:900;color:var(--gray-900)">Selesaikan Pembayaran</h1>
      <p style="font-size:.88rem;color:var(--text-muted);margin-top:6px">
        Pesanan <strong><?= e($order['order_number']) ?></strong> menunggu pembayaran
      </p>
    </div>

    <!-- Order Detail Card -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title"><i class="fas fa-box"></i> Detail Pesanan</span>
        <span class="badge badge-warning"><?= order_status_label($order['status']) ?></span>
      </div>

      <!-- Items -->
      <?php foreach($order_items as $oi): ?>
      <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);align-items:center">
        <img src="<?= product_img($oi['product_image'] ?? '') ?>" alt=""
             style="width:52px;height:52px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);flex-shrink:0">
        <div style="flex:1">
          <div style="font-size:.88rem;font-weight:700;color:var(--gray-800)"><?= e($oi['product_name']) ?></div>
          <div style="font-size:.78rem;color:var(--text-muted)"><?= $oi['quantity'] ?> × <?= rp($oi['price']) ?></div>
        </div>
        <div style="font-weight:800;color:var(--green-700)"><?= rp($oi['subtotal']) ?></div>
      </div>
      <?php endforeach; ?>

      <!-- Totals -->
      <div style="margin-top:12px">
        <div class="summary-row"><span>Subtotal</span><span><?= rp($order['subtotal']) ?></span></div>
        <div class="summary-row">
          <span>Ongkos Kirim</span>
          <span><?= $order['shipping_cost'] > 0 ? rp($order['shipping_cost']) : '<span style="color:var(--green-600)">-</span>' ?></span>
        </div>
        <div class="summary-row total"><span>Total Bayar</span><span class="val"><?= rp($order['total']) ?></span></div>
      </div>
    </div>

    <!-- Shipping Info -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title"><i class="fas fa-map-marker-alt"></i> Alamat Pengiriman</span></div>
      <div style="font-size:.88rem;color:var(--text-body);line-height:1.7">
        <strong><?= e($order['ship_name']) ?></strong><br>
        <?= e($order['ship_phone']) ?><br>
        <?= e($order['ship_address']) ?>,<br>
        <?= e($order['ship_district']) ?>, <?= e($order['ship_city']) ?>,<br>
        <?= e($order['ship_province']) ?> <?= e($order['ship_postal']) ?>
      </div>
      <?php if($order['notes']): ?>
      <div style="margin-top:10px;padding:10px;background:var(--gray-50);border-radius:var(--radius-sm);font-size:.83rem;color:var(--text-muted)">
        <i class="fas fa-sticky-note" style="color:var(--yellow-500)"></i> Catatan: <?= e($order['notes']) ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Payment Button -->
    <div class="card" style="text-align:center">
      <?php if($snap_token): ?>
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:16px">
          Klik tombol di bawah untuk memilih metode pembayaran yang Anda inginkan
        </p>
        <button id="payBtn" class="btn btn-primary btn-lg" onclick="triggerPayment()" style="min-width:240px">
          <i class="fas fa-lock"></i> Bayar Sekarang – <?= rp($order['total']) ?>
        </button>
        <p style="font-size:.75rem;color:var(--text-muted);margin-top:12px">
          <i class="fas fa-shield-alt" style="color:var(--green-600)"></i>
          Pembayaran aman & terenkripsi via Midtrans
        </p>
      <?php elseif(!MIDTRANS_SERVER_KEY): ?>
        <!-- Demo mode – Midtrans not configured -->
        <!-- <div class="alert alert-warning" style="text-align:left;margin-bottom:16px">
          <i class="fas fa-exclamation-triangle"></i> -->
          <!-- <div>
            <strong>Mode Demo:</strong> Midtrans belum dikonfigurasi.
            Masukkan <code>MIDTRANS_SERVER_KEY</code> dan <code>MIDTRANS_CLIENT_KEY</code> di file <code>config/database.php</code> atau melalui panel admin.
          </div> -->
        <!-- </div> -->
        <!-- <p style="font-size:.88rem;color:var(--text-body);margin-bottom:16px">Untuk demo, klik tombol di bawah untuk simulasi pembayaran berhasil:</p> -->
        <a href="payment-callback.php?demo=1&order_id=<?= $order_id ?>&csrf_token=<?= csrf_token() ?>"
           class="btn btn-primary btn-lg">
          <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
        </a>
      <?php else: ?>
        <div class="alert alert-danger"><i class="fas fa-times-circle"></i> Gagal membuat token pembayaran. Silakan hubungi admin.</div>
        <a href="orders.php" class="btn btn-secondary">Kembali ke Pesanan</a>
      <?php endif; ?>

      <!-- <div style="margin-top:20px;display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/82/Visa-Logo.svg/200px-Visa-Logo.svg.png" alt="Visa" style="height:24px;object-fit:contain;filter:grayscale(.3)">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/Mastercard_2019_logo.svg/200px-Mastercard_2019_logo.svg.png" alt="Mastercard" style="height:24px;object-fit:contain;filter:grayscale(.3)">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/89/Logo_BCA.svg/200px-Logo_BCA.svg.png" alt="BCA" style="height:24px;object-fit:contain;filter:grayscale(.3)">
        <img src="https://upload.wikimedia.org/wikipedia/id/thumb/5/57/Gopay_logo.svg/200px-Gopay_logo.svg.png" alt="GoPay" style="height:24px;object-fit:contain;filter:grayscale(.3)">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e1/QRIS_Logo.svg/200px-QRIS_Logo.svg.png" alt="QRIS" style="height:24px;object-fit:contain;filter:grayscale(.3)">
      </div> -->
    </div>

  </div>
</main>

<?php if($snap_token && $midtrans_client_key): ?>
<script src="<?= $snap_js ?>" data-client-key="<?= e($midtrans_client_key) ?>"></script>
<script>
function triggerPayment() {
  snap.pay('<?= e($snap_token) ?>', {
    onSuccess: function(result) {
      window.location.href = '<?= APP_URL ?>/payment-finish.php?order_id=<?= $order_id ?>&result=' + encodeURIComponent(JSON.stringify(result));
    },
    onPending: function(result) {
      window.location.href = '<?= APP_URL ?>/payment-finish.php?order_id=<?= $order_id ?>&status=pending';
    },
    onError: function(result) {
      alert('Pembayaran gagal. Silakan coba lagi.');
    },
    onClose: function() {
      // User closed popup without paying
    }
  });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
