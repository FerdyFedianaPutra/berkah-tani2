<?php
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

// Non-AJAX fallback (clear action via GET)
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    if (!is_logged_in()) redirect('login.php');
    if ($_GET['csrf_token'] !== csrf_token()) redirect('cart.php');
    $stmt = db()->prepare("SELECT id FROM carts WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart = $stmt->fetchColumn();
    if ($cart) db()->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cart]);
    redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

if (!is_logged_in()) {
    echo json_encode(['success'=>false,'message'=>'Silakan login terlebih dahulu.','redirect'=>'login.php']);
    exit;
}

if (!verify_csrf()) {
    echo json_encode(['success'=>false,'message'=>'Token tidak valid.']);
    exit;
}

$action = $_POST['action'] ?? '';
$uid    = $_SESSION['user_id'];

// Helper: get or create cart
function get_cart_id(int $uid): int {
    $s = db()->prepare("SELECT id FROM carts WHERE user_id = ?");
    $s->execute([$uid]);
    $id = $s->fetchColumn();
    if (!$id) {
        db()->prepare("INSERT INTO carts (user_id) VALUES (?)")->execute([$uid]);
        $id = db()->lastInsertId();
    }
    return (int)$id;
}

// Helper: cart summary
function cart_summary(int $uid): array {
    $free_min = (float)setting('free_shipping_min', 200000);
    $ship_cost = (float)setting('shipping_cost', 15000);
    $s = db()->prepare("
        SELECT COALESCE(SUM(ci.price * ci.quantity),0) AS subtotal,
               COALESCE(SUM(ci.quantity),0) AS count
        FROM carts c JOIN cart_items ci ON ci.cart_id = c.id
        WHERE c.user_id = ?
    ");
    $s->execute([$uid]);
    $row = $s->fetch();
    $subtotal = (float)$row['subtotal'];
    $shipping = $subtotal >= $free_min ? 0 : $ship_cost;
    return [
        'subtotal'      => $subtotal,
        'shipping_cost' => $shipping,
        'total'         => $subtotal + $shipping,
        'count'         => (int)$row['count'],
    ];
}

switch($action) {

    // ── ADD ──────────────────────────────────────────────────
    case 'add':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty        = max(1, (int)($_POST['quantity'] ?? 1));

        // Validate product
        $ps = db()->prepare("SELECT id, price, sale_price, stock, is_active FROM products WHERE id = ?");
        $ps->execute([$product_id]);
        $product = $ps->fetch();

        if (!$product || !$product['is_active']) {
            echo json_encode(['success'=>false,'message'=>'Produk tidak ditemukan.']);
            exit;
        }
        if ((int)$product['stock'] < $qty) {
            echo json_encode(['success'=>false,'message'=>'Stok tidak cukup. Tersisa '.$product['stock'].' item.']);
            exit;
        }

        $cart_id  = get_cart_id($uid);
        $price    = $product['sale_price'] ? (float)$product['sale_price'] : (float)$product['price'];

        // Check if already in cart
        $cs = db()->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $cs->execute([$cart_id, $product_id]);
        $existing = $cs->fetch();

        if ($existing) {
            $new_qty = min($existing['quantity'] + $qty, (int)$product['stock']);
            db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
                 ->execute([$new_qty, $existing['id']]);
        } else {
            db()->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (?,?,?,?)")
                 ->execute([$cart_id, $product_id, $qty, $price]);
        }

        $summary = cart_summary($uid);
        echo json_encode([
            'success'    => true,
            'message'    => 'Produk berhasil ditambahkan ke keranjang!',
            'cart_count' => $summary['count'],
            'summary'    => $summary,
        ]);
        break;

    // ── UPDATE ───────────────────────────────────────────────
    case 'update':
        $item_id = (int)($_POST['cart_item_id'] ?? 0);
        $qty     = max(1, (int)($_POST['quantity'] ?? 1));

        // Verify ownership
        $s = db()->prepare("
            SELECT ci.id, p.stock FROM cart_items ci
            JOIN carts c ON c.id = ci.cart_id
            JOIN products p ON p.id = ci.product_id
            WHERE ci.id = ? AND c.user_id = ?
        ");
        $s->execute([$item_id, $uid]);
        $item = $s->fetch();

        if (!$item) { echo json_encode(['success'=>false,'message'=>'Item tidak ditemukan.']); exit; }
        if ($qty > (int)$item['stock']) {
            echo json_encode(['success'=>false,'message'=>'Stok tidak cukup. Tersisa '.$item['stock'].' item.']);
            exit;
        }

        db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?")->execute([$qty, $item_id]);
        $summary = cart_summary($uid);
        echo json_encode(['success'=>true,'message'=>'Jumlah diperbarui.','summary'=>$summary,'cart_count'=>$summary['count']]);
        break;

    // ── REMOVE ───────────────────────────────────────────────
    case 'remove':
        $item_id = (int)($_POST['cart_item_id'] ?? 0);
        $s = db()->prepare("
            DELETE ci FROM cart_items ci
            JOIN carts c ON c.id = ci.cart_id
            WHERE ci.id = ? AND c.user_id = ?
        ");
        $s->execute([$item_id, $uid]);

        $summary = cart_summary($uid);
        echo json_encode(['success'=>true,'message'=>'Produk dihapus dari keranjang.','cart_count'=>$summary['count'],'summary'=>$summary]);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenali.']);
}
