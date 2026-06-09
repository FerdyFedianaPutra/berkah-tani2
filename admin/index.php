<?php
require_once __DIR__ . '/includes/auth.php';
admin_check();

// ── Stats ─────────────────────────────────────────────────────
$stats = db()->query("
    SELECT
      (SELECT COUNT(*) FROM users WHERE is_active=1) AS users,
      (SELECT COUNT(*) FROM products WHERE is_active=1) AS products,
      (SELECT COUNT(*) FROM orders) AS orders,
      (SELECT COUNT(*) FROM orders WHERE status='pending') AS pending_orders,
      (SELECT COUNT(*) FROM orders WHERE status='processing') AS processing_orders,
      (SELECT COALESCE(SUM(gross_amount),0) FROM payments WHERE status='settlement') AS revenue,
      (SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()) AS today_orders,
      (SELECT COALESCE(SUM(gross_amount),0) FROM payments WHERE status='settlement' AND DATE(settled_at)=CURDATE()) AS today_revenue
")->fetch();

// ── Recent Orders ─────────────────────────────────────────────
$recent_orders = db()->query("
    SELECT o.*, u.name AS user_name, p.status AS pay_status
    FROM orders o
    JOIN users u ON u.id = o.user_id
    LEFT JOIN payments p ON p.order_id = o.id
    ORDER BY o.created_at DESC LIMIT 8
")->fetchAll();

// ── Top Products ──────────────────────────────────────────────
$top_products = db()->query("
    SELECT p.name, p.sold_count, p.stock, p.thumbnail, c.name AS cat_name
    FROM products p JOIN categories c ON c.id=p.category_id
    ORDER BY p.sold_count DESC LIMIT 5
")->fetchAll();

// ── Revenue chart (last 7 days) ───────────────────────────────
$chart_data = db()->query("
    SELECT DATE(settled_at) AS d, COALESCE(SUM(gross_amount),0) AS total
    FROM payments
    WHERE status='settlement' AND settled_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY d ORDER BY d
")->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_labels = [];
$chart_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime($date));
    $chart_values[] = (float)($chart_data[$date] ?? 0);
}

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Stat Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-card-info">
      <div class="stat-card-label">Total Pengguna</div>
      <div class="stat-card-value"><?= number_format($stats['users']) ?></div>
      <div class="stat-card-sub">Akun aktif terdaftar</div>
    </div>
    <div class="stat-card-icon icon-blue"><i class="fas fa-users"></i></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-info">
      <div class="stat-card-label">Total Produk</div>
      <div class="stat-card-value"><?= number_format($stats['products']) ?></div>
      <div class="stat-card-sub">Produk aktif di toko</div>
    </div>
    <div class="stat-card-icon icon-green"><i class="fas fa-box-open"></i></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-info">
      <div class="stat-card-label">Total Pesanan</div>
      <div class="stat-card-value"><?= number_format($stats['orders']) ?></div>
      <div class="stat-card-sub">
        <span class="badge-warning" style="font-weight:700;color:var(--yellow-500)"><?= $stats['pending_orders'] ?> menunggu</span>
      </div>
    </div>
    <div class="stat-card-icon icon-yellow"><i class="fas fa-shopping-bag"></i></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-info">
      <div class="stat-card-label">Total Pendapatan</div>
      <div class="stat-card-value" style="font-size:1.2rem"><?= rp($stats['revenue']) ?></div>
      <div class="stat-card-sub">
        <span class="up"><i class="fas fa-arrow-up"></i> Hari ini: <?= rp($stats['today_revenue']) ?></span>
      </div>
    </div>
    <div class="stat-card-icon icon-green"><i class="fas fa-money-bill-wave"></i></div>
  </div>
</div>

<!-- Quick Actions -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
  <a href="pages/products.php?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah Produk</a>
  <a href="pages/orders.php?status=pending" class="btn btn-outline btn-sm"><i class="fas fa-clock"></i> Pesanan Pending (<?= $stats['pending_orders'] ?>)</a>
  <a href="pages/banners.php" class="btn btn-ghost btn-sm" style="border:1px solid var(--border)"><i class="fas fa-images"></i> Kelola Banner</a>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:20px" id="dashGrid">

  <!-- Revenue Chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-chart-line"></i> Pendapatan 7 Hari Terakhir</span>
    </div>
    <canvas id="revenueChart" height="80"></canvas>
  </div>

  <!-- Recent Orders -->
  <div class="admin-table-wrap">
    <div class="admin-table-header">
      <span class="admin-table-title"><i class="fas fa-shopping-bag" style="color:var(--green-600)"></i> Pesanan Terbaru</span>
      <a href="pages/orders.php" class="btn btn-ghost btn-sm" style="border:1px solid var(--border)">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>No. Pesanan</th>
            <th>Pelanggan</th>
            <th>Total</th>
            <th>Status</th>
            <th>Tanggal</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($recent_orders)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">Belum ada pesanan</td></tr>
          <?php endif; ?>
          <?php foreach($recent_orders as $ord): ?>
          <tr>
            <td><strong><?= e($ord['order_number']) ?></strong></td>
            <td><?= e($ord['user_name']) ?></td>
            <td><strong style="color:var(--green-700)"><?= rp($ord['total']) ?></strong></td>
            <td><span class="badge <?= order_status_class($ord['status']) ?>"><?= order_status_label($ord['status']) ?></span></td>
            <td style="font-size:.8rem;color:var(--text-muted)"><?= date('d M Y', strtotime($ord['created_at'])) ?></td>
            <td>
              <a href="pages/order-detail.php?id=<?= $ord['id'] ?>" class="btn btn-ghost btn-sm" style="padding:5px 10px">
                <i class="fas fa-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Products -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i class="fas fa-fire"></i> Produk Terlaris</span>
      <a href="pages/products.php" class="btn btn-ghost btn-sm" style="border:1px solid var(--border)">Lihat Semua</a>
    </div>
    <div style="display:flex;flex-direction:column;gap:12px">
      <?php foreach($top_products as $i => $tp): ?>
      <div style="display:flex;gap:12px;align-items:center">
        <span style="width:22px;height:22px;border-radius:50%;background:<?= $i===0?'var(--yellow-500)':($i===1?'var(--gray-400)':($i===2?'#cd7f32':'var(--gray-200)')) ?>;color:<?= $i<3?'#fff':'var(--gray-600)' ?>;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;flex-shrink:0"><?= $i+1 ?></span>
        <img src="<?= product_img($tp['thumbnail'] ?? '') ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);flex-shrink:0">
        <div style="flex:1;min-width:0">
          <div style="font-size:.85rem;font-weight:700;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($tp['name']) ?></div>
          <div style="font-size:.72rem;color:var(--text-muted)"><?= e($tp['cat_name']) ?></div>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div style="font-size:.85rem;font-weight:800;color:var(--green-700)"><?= number_format($tp['sold_count']) ?> terjual</div>
          <div style="font-size:.72rem;color:<?= (int)$tp['stock']<10?'var(--red-500)':'var(--text-muted)' ?>">Stok: <?= $tp['stock'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// Dashboard responsive
function applyDashGrid() {
  const el = document.getElementById('dashGrid');
  if (el) el.style.gridTemplateColumns = window.innerWidth >= 1024 ? '1fr 1fr' : '1fr';
}
applyDashGrid(); window.addEventListener('resize', applyDashGrid);

// Chart
const ctx = document.getElementById('revenueChart')?.getContext('2d');
if (ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chart_labels) ?>,
      datasets: [{
        label: 'Pendapatan',
        data: <?= json_encode($chart_values) ?>,
        borderColor: '#4caf00',
        backgroundColor: 'rgba(76,175,0,.08)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#4caf00',
        pointRadius: 5,
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => 'Rp ' + ctx.raw.toLocaleString('id-ID')
          }
        }
      },
      scales: {
        y: {
          ticks: { callback: v => 'Rp ' + (v/1000).toFixed(0) + 'rb' },
          grid: { color: '#f1f5f9' }
        },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
