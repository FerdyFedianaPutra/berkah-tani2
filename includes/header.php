<?php
// includes/header.php
// Usage: require_once 'includes/header.php';
// Set $page_title before including

$page_title  = $page_title  ?? 'Berkah Tani';
$meta_desc   = $meta_desc   ?? 'Beras berkualitas langsung dari petani. Tersedia beras premium, medium, organik, dan produk pertanian lainnya.';
$cart_count  = is_logged_in() ? cart_count() : 0;
$current_url = current_url();

// Fetch categories for nav
$nav_categories = db()->query("SELECT id,name,slug FROM categories WHERE is_active=1 ORDER BY sort_order LIMIT 8")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="description" content="<?= e($meta_desc) ?>"/>
  <title><?= e($page_title) ?> – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css"/>
</head>
<body>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- ===== NAVBAR ===== -->
<header class="navbar" id="navbar">

  <!-- Top bar -->
  <div class="nav-top">
    <div class="container">
      <span><i class="fas fa-truck" style="color:var(--green-300);margin-right:6px"></i>Gratis ongkir untuk pembelian min. Rp <?= number_format((float)setting('free_shipping_min',200000),0,',','.') ?></span>
      <div style="display:flex;gap:16px">
        <a href="tel:+<?= setting('site_phone') ?>"><i class="fas fa-phone-alt"></i> <?= setting('site_phone') ?></a>
        <?php if(is_logged_in()): ?>
          <a href="orders.php"><i class="fas fa-box"></i> Pesanan Saya</a>
          <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        <?php else: ?>
          <a href="login.php">Masuk</a>
          <a href="register.php">Daftar</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Main nav -->
  <div class="nav-main">
    <div class="container">
      <!-- Logo -->
      <a href="<?= APP_URL ?>" class="nav-logo">
        <div class="logo-icon"><i class="fas fa-seedling"></i></div>
        <div class="logo-text">
          <span class="logo-name"><?= APP_NAME ?></span>
          <span class="logo-sub">Beras Berkualitas</span>
        </div>
      </a>

      <!-- Search -->
      <form class="nav-search" id="searchForm" action="products.php" method="GET">
        <input type="text" name="q" placeholder="Cari beras, gabah, dedak…"
               value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off"/>
        <button type="submit"><i class="fas fa-search"></i></button>
      </form>

      <!-- Actions + Hamburger (dibungkus bersama untuk mobile grid) -->
      <div class="nav-actions-wrap">
        <div class="nav-actions">
          <?php if(is_logged_in()): ?>
            <a href="cart.php" class="nav-action-btn">
              <i class="fas fa-shopping-cart"></i>
              <span>Keranjang</span>
              <?php if($cart_count > 0): ?>
              <span class="nav-badge cart-badge"><?= $cart_count ?></span>
              <?php endif; ?>
            </a>
            <a href="profile.php" class="nav-action-btn">
              <i class="fas fa-user-circle"></i>
              <span><?= e(explode(' ', $_SESSION['user_name'] ?? 'Akun')[0]) ?></span>
            </a>
          <?php else: ?>
            <a href="login.php" class="nav-action-btn">
              <i class="fas fa-user"></i>
              <span>Masuk</span>
            </a>
            <a href="cart.php" class="nav-action-btn">
              <i class="fas fa-shopping-cart"></i>
              <span>Keranjang</span>
              <span class="nav-badge cart-badge hidden">0</span>
            </a>
          <?php endif; ?>
        </div>

        <!-- Hamburger -->
        <button class="hamburger" id="hamburger" aria-label="Menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </div>

  <!-- Category Nav -->
  <nav class="nav-cats">
    <div class="container">
      <a href="products.php"<?= (!isset($_GET['cat']) && basename($_SERVER['PHP_SELF'])=='products.php') ? ' class="active"' : '' ?>>
        <i class="fas fa-th-large" style="margin-right:5px;font-size:.75rem"></i>Semua Produk
      </a>
      <?php foreach($nav_categories as $cat): ?>
      <a href="products.php?cat=<?= e($cat['slug']) ?>"
         <?= (($_GET['cat'] ?? '') === $cat['slug']) ? ' class="active"' : '' ?>>
        <?= e($cat['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </nav>

</header>

<!-- ===== MOBILE MENU ===== -->
<div class="mobile-menu" id="mobileMenu">
  <div class="mobile-menu-panel">
    <div class="mobile-menu-header">
      <div class="nav-logo">
        <div class="logo-icon"><i class="fas fa-seedling"></i></div>
        <div class="logo-text">
          <span class="logo-name" style="font-size:.95rem"><?= APP_NAME ?></span>
        </div>
      </div>
      <button class="menu-close" id="menuClose"><i class="fas fa-times"></i></button>
    </div>
    <?php if(is_logged_in()): ?>
    <a href="profile.php"><i class="fas fa-user-circle"></i> <?= e($_SESSION['user_name'] ?? 'Akun Saya') ?></a>
    <?php else: ?>
    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Masuk</a>
    <a href="register.php"><i class="fas fa-user-plus"></i> Daftar</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>"><i class="fas fa-home"></i> Beranda</a>
    <a href="products.php"><i class="fas fa-store"></i> Semua Produk</a>
    <?php foreach($nav_categories as $cat): ?>
    <a href="products.php?cat=<?= e($cat['slug']) ?>" style="padding-left:36px;font-size:.85rem">
      <i class="fas fa-leaf"></i> <?= e($cat['name']) ?>
    </a>
    <?php endforeach; ?>
    <a href="cart.php"><i class="fas fa-shopping-cart"></i> Keranjang <?php if($cart_count>0): ?>(<?=$cart_count?>)<?php endif; ?></a>
    <a href="orders.php"><i class="fas fa-box-open"></i> Pesanan Saya</a>
    <?php if(is_logged_in()): ?>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    <?php endif; ?>
  </div>
</div>

<!-- Promo strip -->
<div class="promo-strip">
  <div class="container">
    <div class="promo-strip-inner" id="promoStrip">
      <?php for($i=0;$i<2;$i++): ?>
      <span class="promo-strip-item"><i class="fas fa-truck"></i> Gratis Ongkir min. Rp 200.000</span>
      <span class="promo-strip-item"><i class="fas fa-shield-alt"></i> Produk Terjamin Kualitasnya</span>
      <span class="promo-strip-item"><i class="fas fa-leaf"></i> Langsung dari Petani Mitra</span>
      <span class="promo-strip-item"><i class="fas fa-headset"></i> CS Siap 7 Hari × 12 Jam</span>
      <span class="promo-strip-item"><i class="fas fa-undo"></i> Garansi Kepuasan Pelanggan</span>
      <?php endfor; ?>
    </div>
  </div>
</div>

<script>
window.CSRF_TOKEN     = '<?= csrf_token() ?>';
window.USER_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
window.APP_URL        = '<?= APP_URL ?>';
</script>