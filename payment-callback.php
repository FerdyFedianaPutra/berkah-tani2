<?php
// payment-callback.php – Midtrans server-to-server notification handler
// Also handles demo mode simulation
require_once __DIR__ . '/includes/functions.php';

// ── DEMO mode ─────────────────────────────────────────────────
if (!empty($_GET['demo']) && !empty($_GET['order_id'])) {
    if ($_GET['csrf_token'] !== csrf_token()) redirect('index.php');
    $order_id = (int)$_GET['order_id'];
    $order_s  = db()->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $order_s->execute([$order_id]);
    $order = $order_s->fetch();
    if ($order && $order['status'] === 'pending') {
        db()->prepare("UPDATE orders SET status='paid', paid_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$order_id]);
        db()->prepare("UPDATE payments SET status='settlement', settled_at=NOW(), updated_at=NOW() WHERE order_id=?")->execute([$order_id]);
    }
    redirect("order-detail.php?id=$order_id&paid=1");
}

// ── Midtrans Webhook ──────────────────────────────────────────
// This endpoint is called by Midtrans server
$raw = file_get_contents('php://input');
if (empty($raw)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No payload']);
    exit;
}

$notif = json_decode($raw, true);
if (!$notif || empty($notif['order_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

// Verify signature
// signature = SHA512(order_id + status_code + gross_amount + server_key)
$server_key   = MIDTRANS_SERVER_KEY ?: setting('midtrans_server_key');
$sig_key      = $notif['order_id'] . $notif['status_code'] . $notif['gross_amount'] . $server_key;
$expected_sig = hash('sha512', $sig_key);
if (!hash_equals($expected_sig, $notif['signature_key'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}

// Find order by order_number
$stmt = db()->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
$stmt->execute([$notif['order_id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

$transaction_status = $notif['transaction_status'] ?? '';
$fraud_status       = $notif['fraud_status'] ?? '';
$payment_type       = $notif['payment_type'] ?? '';
$transaction_id     = $notif['transaction_id'] ?? '';
$bank               = $notif['va_numbers'][0]['bank'] ?? $notif['acquirer'] ?? '';

// Map Midtrans status → payment status
$pay_status = match(true) {
    $transaction_status === 'capture' && $fraud_status === 'accept' => 'settlement',
    $transaction_status === 'settlement' => 'settlement',
    $transaction_status === 'pending'    => 'pending',
    in_array($transaction_status, ['cancel','expire','deny']) => $transaction_status,
    default => 'pending',
};

// Map → order status
$order_status = match($pay_status) {
    'settlement' => 'paid',
    'cancel','expire','deny' => 'cancelled',
    default => $order['status'], // keep current
};

$pdo = db();
$pdo->beginTransaction();
try {
    // Update payment
    $pdo->prepare("
        UPDATE payments
        SET status=?, transaction_id=?, payment_type=?, bank=?,
            raw_response=?, settled_at=?, updated_at=NOW()
        WHERE order_id=?
    ")->execute([
        $pay_status, $transaction_id, $payment_type, $bank,
        $raw,
        $pay_status === 'settlement' ? date('Y-m-d H:i:s') : null,
        $order['id'],
    ]);

    // Update order
    if ($order_status !== $order['status']) {
        $pdo->prepare("
            UPDATE orders
            SET status=?,
                paid_at      = CASE WHEN ? = 'paid' AND paid_at IS NULL THEN NOW() ELSE paid_at END,
                cancelled_at = CASE WHEN ? = 'cancelled' AND cancelled_at IS NULL THEN NOW() ELSE cancelled_at END,
                updated_at   = NOW()
            WHERE id=?
        ")->execute([$order_status, $order_status, $order_status, $order['id']]);

        // If cancelled, restore stock
        if ($order_status === 'cancelled' && $order['status'] === 'pending') {
            $items_s = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $items_s->execute([$order['id']]);
            foreach ($items_s->fetchAll() as $oi) {
                $pdo->prepare("UPDATE products SET stock=stock+?, sold_count=GREATEST(0,sold_count-?) WHERE id=?")
                     ->execute([$oi['quantity'], $oi['quantity'], $oi['product_id']]);
            }
        }
    }

    $pdo->commit();
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'order_status' => $order_status]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Midtrans callback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal error']);
}
