<?php
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Beranda';
$meta_desc  = 'Berkah Tani – Toko beras online terpercaya. Beras premium, organik, dan medium langsung dari petani.';

// ── Fetch Banners ────────────────────────────────────────────
$banners = db()->query("
    SELECT * FROM banners
    WHERE is_active = 1 AND position = 'hero'
      AND (start_date IS NULL OR start_date <= CURDATE())
      AND (end_date IS NULL OR end_date >= CURDATE())
    ORDER BY sort_order
")->fetchAll();

// ── Fetch Categories ─────────────────────────────────────────
$categories = db()->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();

// ── Featured Products ─────────────────────────────────────────
$featured = db()->query("
    SELECT p.*, c.name AS cat_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.is_featured = 1 AND p.is_active = 1
    ORDER BY p.sold_count DESC
    LIMIT 10
")->fetchAll();

// ── Newest Products ───────────────────────────────────────────
$newest = db()->query("
    SELECT p.*, c.name AS cat_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();

// ── Best Sellers ──────────────────────────────────────────────
$bestsellers = db()->query("
    SELECT p.*, c.name AS cat_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1
    ORDER BY p.sold_count DESC
    LIMIT 10
")->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$stats = db()->query("
    SELECT
      (SELECT COUNT(*) FROM users WHERE is_active=1) AS total_users,
      (SELECT COUNT(*) FROM products WHERE is_active=1) AS total_products,
      (SELECT COUNT(*) FROM orders WHERE status='completed') AS total_orders,
      (SELECT COALESCE(SUM(p.gross_amount),0) FROM payments p WHERE p.status='settlement') AS total_revenue
")->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ===== HERO SLIDER ===== -->
<section class="hero-slider">
  <?php foreach($banners as $i => $b): ?>
  <div class="hero-slide <?= $i===0 ? 'active' : '' ?>"
       style="background-image:url('<?= banner_img($b['image']) ?>')">
    <div class="hero-overlay"></div>
    <div class="container" style="position:relative;z-index:1">
      <div class="hero-content">
        <span class="hero-badge"><i class="fas fa-leaf"></i> Produk Terpercaya</span>
        <h1 class="hero-title"><?= e($b['title']) ?><br><em><?= e($b['subtitle'] ?? '') ?></em></h1>
        <div class="hero-actions">
          <a href="<?= e($b['link_url'] ?? 'products.php') ?>" class="btn btn-primary btn-lg">
            <i class="fas fa-shopping-bag"></i> <?= e($b['link_text'] ?? 'Belanja Sekarang') ?>
          </a>
          <a href="#categories" class="btn btn-outline-white">
            <i class="fas fa-th-large"></i> Lihat Kategori
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if(count($banners) > 1): ?>
  <button class="slider-prev"><i class="fas fa-chevron-left"></i></button>
  <button class="slider-next"><i class="fas fa-chevron-right"></i></button>
  <div class="slider-dots">
    <?php foreach($banners as $i => $b): ?>
    <button class="slider-dot <?= $i===0?'active':'' ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<!-- ===== STATS BAR ===== -->
<section style="background:var(--white);border-bottom:1px solid var(--border)">
  <div class="container">
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0;padding:20px 0">
      <?php
      $stat_items = [
        ['icon'=>'fa-users','val'=>number_format($stats['total_users']).'+ Pelanggan','label'=>'Telah Mempercayai Kami'],
        ['icon'=>'fa-box-open','val'=>number_format($stats['total_products']).'+ Produk','label'=>'Pilihan Beras & Pertanian'],
        ['icon'=>'fa-star','val'=>number_format($stats['total_orders']).'+ Pesanan','label'=>'Berhasil Dikirim'],
        ['icon'=>'fa-check','val'=>'100%','label'=>'Kualitas Terjamin'],
      ];
      ?>
      <?php foreach($stat_items as $si): ?>
      <div style="display:flex;align-items:center;gap:12px;padding:16px;border-bottom:1px solid var(--border)">
        <div style="width:44px;height:44px;border-radius:var(--radius-md);background:var(--green-50);color:var(--green-600);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">
          <i class="fas <?= $si['icon'] ?>"></i>
        </div>
        <div>
          <div style="font-size:.92rem;font-weight:800;color:var(--gray-900)"><?= $si['val'] ?></div>
          <div style="font-size:.72rem;color:var(--text-muted)"><?= $si['label'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== CATEGORIES ===== -->
<section class="section" id="categories">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">Kategori <span>Produk</span></h2>
        <p class="section-subtitle">Temukan produk sesuai kebutuhan Anda</p>
      </div>
      <a href="products.php" class="section-link">Semua Produk <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="cat-grid">
      <?php foreach($categories as $cat): ?>
      <a href="products.php?cat=<?= e($cat['slug']) ?>" class="cat-card">
        <div class="cat-icon"><i class="fas <?= e($cat['icon']) ?>"></i></div>
        <span class="cat-name"><?= e($cat['name']) ?></span>
        <span style="font-size:.68rem;color:var(--text-muted)"><?= $cat['product_count'] ?> Produk</span>
      </a>
      <?php endforeach; ?>
      <a href="products.php" class="cat-card">
        <div class="cat-icon" style="background:var(--green-600);color:#fff"><i class="fas fa-th-large"></i></div>
        <span class="cat-name">Lihat Semua</span>
        <span style="font-size:.68rem;color:var(--text-muted)">Semua Kategori</span>
      </a>
    </div>
  </div>
</section>

<!-- ===== FEATURED PRODUCTS ===== -->
<?php if($featured): ?>
<section class="section" style="background:var(--white);padding-top:0">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">Produk <span>Unggulan</span></h2>
        <p class="section-subtitle">Pilihan terbaik dari kami untuk Anda</p>
      </div>
      <a href="products.php?sort=featured" class="section-link">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="products-grid">
      <?php foreach($featured as $p): echo render_product_card($p); endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===== PROMO BANNER MID ===== -->
<section style="background:linear-gradient(135deg,var(--green-700),var(--green-800));padding:40px 0">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr;gap:20px;align-items:center;text-align:center">
      <div>
        <h3 style="color:#fff;font-size:clamp(1.4rem,3vw,2rem);font-weight:900;margin-bottom:10px">
          🌾 Pesan Sekarang, Dikirim Hari Ini
        </h3>
        <p style="color:rgba(255,255,255,.8);margin-bottom:20px;font-size:.95rem">
          Gratis ongkir untuk pembelian di atas Rp <?= number_format((float)setting('free_shipping_min',200000),0,',','.') ?>.
          Garansi beras segar & berkualitas.
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
          <a href="products.php" class="btn btn-primary btn-lg">
            <i class="fas fa-shopping-bag"></i> Belanja Sekarang
          </a>
          <a href="https://wa.me/<?= setting('site_phone') ?>" class="btn btn-outline-white">
            <i class="fab fa-whatsapp"></i> Hubungi Kami
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== NEWEST PRODUCTS ===== -->
<?php if($newest): ?>
<section class="section">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">Produk <span>Terbaru</span></h2>
        <p class="section-subtitle">Koleksi terbaru yang baru kami tambahkan</p>
      </div>
      <a href="products.php?sort=newest" class="section-link">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="products-grid">
      <?php foreach($newest as $p): echo render_product_card($p, 'new'); endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===== BESTSELLERS ===== -->
<?php if($bestsellers): ?>
<section class="section" style="background:var(--white);padding-top:0">
  <div class="container">
    <div class="section-header">
      <div>
        <h2 class="section-title">Produk <span>Terlaris</span></h2>
        <p class="section-subtitle">Paling banyak dipilih pelanggan kami</p>
      </div>
      <a href="products.php?sort=bestseller" class="section-link">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="products-grid">
      <?php foreach($bestsellers as $p): echo render_product_card($p, 'hot'); endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
// ── Product Card Renderer ─────────────────────────────────────
function render_product_card(array $p, string $badge = ''): string {
    $price     = (float)$p['price'];
    $sale      = $p['sale_price'] ? (float)$p['sale_price'] : null;
    $displayed = $sale ?? $price;
    $discount  = $sale ? round((1 - $sale/$price)*100) : 0;
    $stock     = (int)$p['stock'];
    $stockClass= $stock === 0 ? 'out' : ($stock < 10 ? 'low' : 'good');
    $stockText = $stock === 0 ? 'Habis' : ($stock < 10 ? "Sisa $stock" : 'Tersedia');
    $img       = product_img($p['thumbnail'] ?? '');
    $stars     = str_repeat('<i class="fas fa-star"></i>', min(5, (int)round((float)$p['rating'])));
    $badgeHtml = match($badge) {
        'hot'  => '<span class="product-badge badge-hot">🔥 Terlaris</span>',
        'new'  => '<span class="product-badge badge-new">✨ Baru</span>',
        'sale' => '<span class="product-badge badge-sale">SALE</span>',
        default => ($p['is_featured'] ? '<span class="product-badge badge-best">⭐ Unggulan</span>' : ''),
    };

    ob_start(); ?>
    <div class="product-card">
      <a href="product.php?slug=<?= e($p['slug']) ?>">
        <div class="product-img-wrap">
          <img src="<?= $img ?>" alt="<?= e($p['name']) ?>" loading="lazy"/>
          <?= $badgeHtml ?>
          <button class="product-wishlist" title="Wishlist"><i class="far fa-heart"></i></button>
        </div>
      </a>
      <div class="product-info">
        <a href="product.php?slug=<?= e($p['slug']) ?>" class="product-name"><?= e($p['name']) ?></a>
        <div class="product-price-wrap">
          <span class="product-price"><?= rp($displayed) ?></span>
          <?php if($sale): ?>
          <div style="display:flex;gap:6px;align-items:center">
            <span class="product-price-original"><?= rp($price) ?></span>
            <span class="product-discount">-<?= $discount ?>%</span>
          </div>
          <?php endif; ?>
        </div>
        <?php if((float)$p['rating'] > 0): ?>
        <div class="product-rating">
          <?= $stars ?>
          <span>(<?= $p['review_count'] ?>)</span>
        </div>
        <?php endif; ?>
        <div class="product-meta">
          <span class="product-sold"><i class="fas fa-fire" style="color:var(--orange-500);margin-right:3px"></i><?= number_format($p['sold_count']) ?> terjual</span>
          <span class="product-stock <?= $stockClass ?>"><?= $stockText ?></span>
        </div>
        <button class="product-add-btn" data-add-cart="<?= $p['id'] ?>"
                <?= $stock === 0 ? 'disabled' : '' ?>>
          <i class="fas fa-cart-plus"></i>
          <?= $stock === 0 ? 'Stok Habis' : 'Tambah ke Keranjang' ?>
        </button>
      </div>
    </div>
    <?php return ob_get_clean();
}

require_once __DIR__ . '/includes/footer.php';
?>
