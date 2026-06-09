<?php
// admin/includes/header.php
// Requires: $page_title, admin_check() already called, current_admin() available
$admin        = current_admin();
$pending_orders = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$base         = APP_URL . '/admin';

// Active page detection
$current_page = basename($_SERVER['PHP_SELF'], '.php');

function is_active(string $page, string $current): string {
    return $page === $current ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= e($page_title ?? 'Dashboard') ?> – Admin <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css"/>
</head>
<body>

<div id="toast-container"></div>

<div class="admin-wrapper">

  <!-- Sidebar Overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
      <div class="logo-icon"><i class="fas fa-seedling"></i></div>
      <div>
        <div class="logo-name"><?= APP_NAME ?></div>
        <span class="logo-badge">Admin Panel</span>
      </div>
    </div>

    <!-- Main Nav -->
    <div class="sidebar-section-title">Main</div>
    <a href="<?= $base ?>/index.php" class="sidebar-link<?= is_active('index',$current_page) ?>">
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>

    <div class="sidebar-section-title">Toko</div>
    <a href="<?= $base ?>/pages/products.php" class="sidebar-link<?= is_active('products',$current_page) ?>">
      <i class="fas fa-box-open"></i> Produk
    </a>
    <a href="<?= $base ?>/pages/categories.php" class="sidebar-link<?= is_active('categories',$current_page) ?>">
      <i class="fas fa-th-large"></i> Kategori
    </a>
    <a href="<?= $base ?>/pages/banners.php" class="sidebar-link<?= is_active('banners',$current_page) ?>">
      <i class="fas fa-images"></i> Banner
    </a>

    <div class="sidebar-section-title">Penjualan</div>
    <a href="<?= $base ?>/pages/orders.php" class="sidebar-link<?= is_active('orders',$current_page) ?>">
      <i class="fas fa-shopping-bag"></i> Pesanan
      <?php if($pending_orders > 0): ?>
      <span class="badge badge-red"><?= $pending_orders ?></span>
      <?php endif; ?>
    </a>

    <div class="sidebar-section-title">Pengguna</div>
    <a href="<?= $base ?>/pages/users.php" class="sidebar-link<?= is_active('users',$current_page) ?>">
      <i class="fas fa-users"></i> Pelanggan
    </a>

    <div class="sidebar-section-title">Pengaturan</div>
    <a href="<?= $base ?>/pages/settings.php" class="sidebar-link<?= is_active('settings',$current_page) ?>">
      <i class="fas fa-cog"></i> Pengaturan
    </a>
    <a href="<?= APP_URL ?>" class="sidebar-link" target="_blank">
      <i class="fas fa-external-link-alt"></i> Lihat Toko
    </a>

    <!-- User info -->
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= mb_substr($admin['name'] ?? 'A', 0, 1) ?></div>
      <div>
        <div class="sidebar-user-name"><?= e($admin['name'] ?? '') ?></div>
        <div class="sidebar-user-role"><?= ucfirst($admin['role'] ?? '') ?></div>
      </div>
      <a href="<?= $base ?>/logout.php" title="Keluar"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <div class="admin-main">

    <!-- Top Bar -->
    <div class="admin-topbar">
      <button class="topbar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
      </button>
      <span class="topbar-title"><?= e($page_title ?? 'Dashboard') ?></span>
      <div class="topbar-right">
        <a href="<?= $base ?>/pages/orders.php?status=pending" class="topbar-btn" title="Pesanan Baru">
          <i class="fas fa-bell"></i>
          <?php if($pending_orders > 0): ?>
          <span class="topbar-badge"><?= $pending_orders ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= APP_URL ?>" class="topbar-btn" title="Lihat Toko" target="_blank">
          <i class="fas fa-store"></i>
        </a>
        <a href="<?= $base ?>/logout.php" class="topbar-btn" title="Keluar" style="color:var(--red-500)">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>

    <!-- Page content injected here -->
    <div class="admin-content">

<?php
// Flash messages
$flash = get_flash('admin');
if ($flash):
?>
<div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom:16px">
  <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
  <?= e($flash['message']) ?>
</div>
<?php endif; ?>
