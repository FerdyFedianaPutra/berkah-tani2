<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$uid      = $_SESSION['user_id'];
$status_f = $_GET['status'] ?? '';
$page_num = max(1,(int)($_GET['page'] ?? 1));
$per_page = 10;

$where = ['o.user_id = ?'];
$params = [$uid];
if ($status_f) { $where[] = 'o.status = ?'; $params[] = $status_f; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$total = (int)db()->prepare("SELECT COUNT(*) FROM orders o $where_sql")->execute($params) ? (function($s,$p){$s->execute($p);return $s->fetchColumn();})(db()->prepare("SELECT COUNT(*) FROM orders o $where_sql"), $params) : 0;
// cleaner count:
$cnt_s = db()->prepare("SELECT COUNT(*) FROM orders o $where_sql"); $cnt_s->execute($params); $total = (int)$cnt_s->fetchColumn();
$pag = paginate($total, $per_page, $page_num);
$pag_url = '?' . http_build_query(array_filter(['status'=>$status_f])) . '&page=%d';

$stmt = db()->prepare("
    SELECT o.*, p.status AS pay_status,
           (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS item_count,
           (SELECT product_name FROM order_items WHERE order_id=o.id LIMIT 1) AS first_item,
           (SELECT product_image FROM order_items WHERE order_id=o.id LIMIT 1) AS first_img
    FROM orders o
    LEFT JOIN payments p ON p.order_id = o.id
    $where_sql
    ORDER BY o.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$status_tabs = [
    ''           => 'Semua',
    'pending'    => 'Menunggu Bayar',
    'paid'       => 'Dibayar',
    'processing' => 'Diproses',
    'shipped'    => 'Dikirim',
    'completed'  => 'Selesai',
    'cancelled'  => 'Dibatalkan',
];

$page_title = 'Riwayat Pesanan';
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container">

    <div class="breadcrumb">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current">Riwayat Pesanan</span>
    </div>
    <h1 style="font-size:1.4rem;font-weight:900;color:var(--gray-900);margin:16px 0 20px">
      <i class="fas fa-box-open" style="color:var(--green-600)"></i> Riwayat Pesanan
    </h1>

    <!-- Status Tabs -->
    <div style="display:flex;gap:0;overflow-x:auto;border-bottom:2px solid var(--border);margin-bottom:20px;scrollbar-width:none">
      <?php foreach($status_tabs as $s => $label): ?>
      <a href="orders.php?status=<?= $s ?>"
         style="padding:10px 16px;font-size:.82rem;font-weight:700;color:<?= $status_f===$s?'var(--green-600)':'var(--text-muted)' ?>;border-bottom:<?= $status_f===$s?'2px solid var(--green-600)':'2px solid transparent' ?>;margin-bottom:-2px;white-space:nowrap;transition:var(--transition)">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if(empty($orders)): ?>
    <div class="empty-state card">
      <div class="empty-icon"><i class="fas fa-box-open"></i></div>
      <p class="empty-title">Tidak ada pesanan</p>
      <p class="empty-desc"><?= $status_f ? 'Tidak ada pesanan dengan status ini' : 'Anda belum pernah melakukan pemesanan' ?></p>
      <a href="products.php" class="btn btn-primary mt-16"><i class="fas fa-shopping-bag"></i> Mulai Belanja</a>
    </div>
    <?php else: ?>

    <div style="display:flex;flex-direction:column;gap:12px">
      <?php foreach($orders as $ord): ?>
      <div class="card" style="padding:0;overflow:hidden">
        <!-- Order header -->
        <div style="padding:12px 16px;background:var(--gray-50);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-size:.82rem;font-weight:800;color:var(--gray-700)">
              <i class="fas fa-hashtag" style="color:var(--green-600)"></i> <?= e($ord['order_number']) ?>
            </span>
            <span style="font-size:.75rem;color:var(--text-muted)"><?= date('d M Y', strtotime($ord['created_at'])) ?></span>
          </div>
          <span class="badge <?= order_status_class($ord['status']) ?>">
            <?= order_status_label($ord['status']) ?>
          </span>
        </div>

        <!-- Order body -->
        <div style="padding:14px 16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <img src="<?= product_img($ord['first_img'] ?? '') ?>" alt=""
               style="width:56px;height:56px;object-fit:cover;border-radius:var(--radius-md);border:1px solid var(--border);flex-shrink:0">
          <div style="flex:1;min-width:0">
            <div style="font-size:.9rem;font-weight:700;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= e($ord['first_item'] ?? 'Produk') ?>
            </div>
            <?php if($ord['item_count'] > 1): ?>
            <div style="font-size:.75rem;color:var(--text-muted)">+<?= $ord['item_count']-1 ?> produk lainnya</div>
            <?php endif; ?>
            <div style="font-size:.9rem;font-weight:800;color:var(--green-700);margin-top:4px"><?= rp($ord['total']) ?></div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <?php if($ord['status'] === 'pending'): ?>
            <a href="payment.php?order_id=<?= $ord['id'] ?>" class="btn btn-primary btn-sm">
              <i class="fas fa-credit-card"></i> Bayar
            </a>
            <?php endif; ?>
            <a href="order-detail.php?id=<?= $ord['id'] ?>" class="btn btn-outline btn-sm">
              <i class="fas fa-eye"></i> Detail
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?= pagination_html($pag, $pag_url) ?>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
