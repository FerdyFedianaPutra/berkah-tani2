<?php
require_once __DIR__ . '/includes/functions.php';

// Fetch or create cart
function get_or_create_cart(): int {
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return 0;
    $stmt = db()->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$uid]);
    $cart = $stmt->fetchColumn();
    if (!$cart) {
        db()->prepare("INSERT INTO carts (user_id) VALUES (?)")->execute([$uid]);
        $cart = db()->lastInsertId();
    }
    return (int)$cart;
}

$cart_id = 0;
$items   = [];
$subtotal= 0;
$shipping= (float)setting('shipping_cost', 15000);
$free_min= (float)setting('free_shipping_min', 200000);

if (is_logged_in()) {
    $cart_id = get_or_create_cart();
    $stmt = db()->prepare("
        SELECT ci.id AS cart_item_id, ci.quantity, ci.price,
               p.id AS product_id, p.name, p.slug, p.thumbnail,
               p.price AS current_price, p.sale_price, p.stock, p.unit, p.weight
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        WHERE ci.cart_id = ?
        ORDER BY ci.created_at DESC
    ");
    $stmt->execute([$cart_id]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    if ($subtotal >= $free_min) $shipping = 0;
}

$total = $subtotal + $shipping;

$page_title = 'Keranjang Belanja';
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container">

    <div class="breadcrumb">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current">Keranjang Belanja</span>
    </div>

    <h1 style="font-size:1.4rem;font-weight:900;color:var(--gray-900);margin:16px 0 20px">
      <i class="fas fa-shopping-cart" style="color:var(--green-600)"></i> Keranjang Belanja
    </h1>

    <?php if(!is_logged_in()): ?>
    <!-- Not logged in -->
    <div class="empty-state card">
      <div class="empty-icon"><i class="fas fa-user-lock"></i></div>
      <p class="empty-title">Silakan masuk terlebih dahulu</p>
      <p class="empty-desc">Login untuk melihat dan mengelola keranjang belanja Anda</p>
      <a href="login.php?redirect=<?= urlencode(current_url()) ?>" class="btn btn-primary mt-16">
        <i class="fas fa-sign-in-alt"></i> Masuk Sekarang
      </a>
    </div>

    <?php elseif(empty($items)): ?>
    <!-- Empty cart -->
    <div class="empty-state card">
      <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
      <p class="empty-title">Keranjang Anda masih kosong</p>
      <p class="empty-desc">Yuk temukan produk beras berkualitas pilihan kami!</p>
      <a href="products.php" class="btn btn-primary mt-16">
        <i class="fas fa-store"></i> Mulai Belanja
      </a>
    </div>

    <?php else: ?>
    <!-- Cart has items -->
    <div style="display:grid;grid-template-columns:1fr;gap:20px" id="cartLayout">

      <!-- Cart Items -->
      <div class="card" style="padding:0;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
          <span style="font-weight:800;color:var(--gray-800);font-size:.95rem">
            <i class="fas fa-box" style="color:var(--green-600);margin-right:6px"></i>
            <?= count($items) ?> Produk di Keranjang
          </span>
          <button onclick="if(confirm('Kosongkan keranjang?'))location.href='cart-action.php?action=clear&csrf_token=<?= csrf_token() ?>'"
                  style="font-size:.78rem;color:var(--red-500);background:none;border:none;cursor:pointer;font-weight:700">
            <i class="fas fa-trash-alt"></i> Kosongkan
          </button>
        </div>

        <!-- Free shipping progress -->
        <?php if($subtotal < $free_min): ?>
        <div style="padding:12px 20px;background:var(--green-50);border-bottom:1px solid var(--border)">
          <?php $remain = $free_min - $subtotal; $pct = min(100, $subtotal/$free_min*100); ?>
          <p style="font-size:.78rem;color:var(--green-700);font-weight:700;margin-bottom:6px">
            <i class="fas fa-truck"></i> Tambah Rp <?= number_format($remain,0,',','.') ?> lagi untuk gratis ongkir!
          </p>
          <div style="height:6px;background:var(--gray-200);border-radius:var(--radius-full);overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--green-500);border-radius:var(--radius-full);transition:width .4s ease"></div>
          </div>
        </div>
        <?php else: ?>
        <div style="padding:10px 20px;background:var(--green-50);border-bottom:1px solid var(--border)">
          <span style="font-size:.8rem;color:var(--green-700);font-weight:700">
            <i class="fas fa-check-circle"></i> Selamat! Anda mendapat gratis ongkir 🎉
          </span>
        </div>
        <?php endif; ?>

        <!-- Items -->
        <div id="cartItems" style="padding:0 20px">
          <?php foreach($items as $item): ?>
          <?php
            $disp_price = $item['sale_price'] ? (float)$item['sale_price'] : (float)$item['current_price'];
            $cart_price = (float)$item['price']; // price at time of adding
            $sub_item   = $cart_price * $item['quantity'];
          ?>
          <div class="cart-item" data-cart-item="<?= $item['cart_item_id'] ?>"
               data-stock="<?= $item['stock'] ?>">
            <a href="product.php?slug=<?= e($item['slug']) ?>">
              <img class="cart-img" src="<?= product_img($item['thumbnail'] ?? '') ?>"
                   alt="<?= e($item['name']) ?>"/>
            </a>
            <div class="cart-details">
              <a href="product.php?slug=<?= e($item['slug']) ?>" class="cart-name"><?= e($item['name']) ?></a>
              <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:4px">
                <?= rp($cart_price) ?>/<?= e($item['unit']) ?>
              </div>

              <?php if((int)$item['stock'] === 0): ?>
              <div style="font-size:.75rem;color:var(--red-500);font-weight:700"><i class="fas fa-exclamation-circle"></i> Stok habis</div>
              <?php elseif((int)$item['stock'] < $item['quantity']): ?>
              <div style="font-size:.75rem;color:var(--orange-500);font-weight:700"><i class="fas fa-exclamation-triangle"></i> Stok tersisa <?= $item['stock'] ?> saja</div>
              <?php endif; ?>

              <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:8px">
                <div class="cart-qty-ctrl">
                  <button class="qty-btn" data-qty-action="dec"><i class="fas fa-minus"></i></button>
                  <span class="qty-val"><?= $item['quantity'] ?></span>
                  <button class="qty-btn" data-qty-action="inc"><i class="fas fa-plus"></i></button>
                </div>
                <div>
                  <span class="cart-subtotal" style="font-size:.95rem;font-weight:800;color:var(--green-700)"
                        data-unit-price="<?= $cart_price ?>"><?= rp($sub_item) ?></span>
                </div>
              </div>
              <button class="cart-remove" data-remove-item="<?= $item['cart_item_id'] ?>">
                <i class="fas fa-trash"></i> Hapus
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Order Summary -->
      <div>
        <div class="order-summary" style="position:sticky;top:100px">
          <h3 style="font-size:1rem;font-weight:800;color:var(--gray-900);margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            <i class="fas fa-receipt" style="color:var(--green-600)"></i> Ringkasan Pesanan
          </h3>

          <div class="summary-row">
            <span>Subtotal (<?= count($items) ?> produk)</span>
            <span id="summarySubtotal"><?= rp($subtotal) ?></span>
          </div>
          <div class="summary-row">
            <span>Ongkos Kirim</span>
            <span id="summaryShipping"><?= $shipping > 0 ? rp($shipping) : '<span style="color:var(--green-600);font-weight:700">-</span>' ?></span>
          </div>
          <div class="summary-row total">
            <span>Total</span>
            <span class="val" id="summaryTotal"><?= rp($total) ?></span>
          </div>

          <a href="checkout.php" class="btn btn-primary btn-block btn-lg" style="margin-top:16px">
            <i class="fas fa-lock"></i> Lanjut ke Checkout
          </a>
          <a href="products.php" class="btn btn-ghost btn-block" style="margin-top:8px">
            <i class="fas fa-arrow-left"></i> Lanjut Belanja
          </a>

          <!-- Trust badges -->
          <div style="display:flex;gap:12px;justify-content:center;margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
            <div style="text-align:center;font-size:.7rem;color:var(--text-muted)">
              <i class="fas fa-shield-alt" style="font-size:1.2rem;color:var(--green-600);display:block;margin-bottom:4px"></i>
              Pembayaran Aman
            </div>
            <div style="text-align:center;font-size:.7rem;color:var(--text-muted)">
              <i class="fas fa-truck" style="font-size:1.2rem;color:var(--green-600);display:block;margin-bottom:4px"></i>
              Pengiriman Cepat
            </div>
            <div style="text-align:center;font-size:.7rem;color:var(--text-muted)">
              <i class="fas fa-undo" style="font-size:1.2rem;color:var(--green-600);display:block;margin-bottom:4px"></i>
              Garansi Puas
            </div>
          </div>
        </div>
      </div>

    </div><!-- /cart layout -->
    <?php endif; ?>

  </div>
</main>

<script>
// Responsive cart layout
function applyCartLayout() {
  const el = document.getElementById('cartLayout');
  if (!el) return;
  el.style.gridTemplateColumns = window.innerWidth >= 768 ? '1fr 340px' : '1fr';
}
applyCartLayout();
window.addEventListener('resize', applyCartLayout);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
