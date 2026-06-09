<?php
require_once __DIR__ . '/../includes/auth.php';
admin_check();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// Update order status
if ($action === 'update_status' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('admin','Token tidak valid.','danger'); }
    else {
        $new_status     = $_POST['status'] ?? '';
        $tracking       = trim($_POST['tracking_number'] ?? '');
        $ship_method    = trim($_POST['shipping_method'] ?? '');
        $cancel_reason  = trim($_POST['cancel_reason'] ?? '');
        $valid_statuses = ['pending','paid','processing','shipped','completed','cancelled'];

        if (in_array($new_status, $valid_statuses)) {
            $s = db()->prepare("SELECT status FROM orders WHERE id=?"); $s->execute([$id]);
            $old = $s->fetchColumn();

            $extra = [];
            $params = [];
            if ($new_status === 'paid'      && $old !== 'paid')      { $extra[] = 'paid_at=NOW()'; }
            if ($new_status === 'shipped'   && $old !== 'shipped')   { $extra[] = 'shipped_at=NOW()'; }
            if ($new_status === 'completed' && $old !== 'completed') { $extra[] = 'completed_at=NOW()'; }
            if ($new_status === 'cancelled' && $old !== 'cancelled') { $extra[] = 'cancelled_at=NOW()'; }
            if ($tracking)    { $extra[] = 'tracking_number=?'; $params[] = $tracking; }
            if ($ship_method) { $extra[] = 'shipping_method=?'; $params[] = $ship_method; }
            if ($cancel_reason && $new_status === 'cancelled') { $extra[] = 'cancel_reason=?'; $params[] = $cancel_reason; }

            $set = array_merge(["status='$new_status'",'updated_at=NOW()'], $extra);
            $params[] = $id;
            db()->prepare("UPDATE orders SET " . implode(',',$set) . " WHERE id=?")->execute($params);

            // Sync payment status
            if ($new_status === 'paid') {
                db()->prepare("UPDATE payments SET status='settlement', settled_at=NOW() WHERE order_id=? AND status='pending'")->execute([$id]);
            }
            // Restore stock if cancelled
            if ($new_status === 'cancelled' && !in_array($old,['cancelled','completed'])) {
                $items = db()->prepare("SELECT * FROM order_items WHERE order_id=?"); $items->execute([$id]);
                foreach ($items->fetchAll() as $oi) {
                    db()->prepare("UPDATE products SET stock=stock+?, sold_count=GREATEST(0,sold_count-?) WHERE id=?")->execute([$oi['quantity'],$oi['quantity'],$oi['product_id']]);
                }
            }

            flash('admin',"Status pesanan berhasil diubah ke: " . order_status_label($new_status),'success');
        }
    }
    redirect(APP_URL . '/admin/pages/order-detail.php?id=' . $id);
}

// List with filters
$status_f = $_GET['status'] ?? '';
$q        = trim($_GET['q'] ?? '');
$page_num = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15;

$where  = ['1=1'];
$params = [];
if ($status_f) { $where[] = 'o.status=?'; $params[] = $status_f; }
if ($q) {
    $where[] = '(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$w = 'WHERE '.implode(' AND ',$where);

$cnt_s = db()->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON u.id=o.user_id $w");
$cnt_s->execute($params); $total = (int)$cnt_s->fetchColumn();
$pag = paginate($total,$per_page,$page_num);
$pag_url = '?'.http_build_query(array_filter(['status'=>$status_f,'q'=>$q])).'&page=%d';

$stmt = db()->prepare("
    SELECT o.*, u.name AS user_name, u.email AS user_email,
           p.status AS pay_status, p.payment_type, p.bank
    FROM orders o
    JOIN users u ON u.id=o.user_id
    LEFT JOIN payments p ON p.order_id=o.id
    $w
    ORDER BY o.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status counts
$status_counts = [];
foreach (['','pending','paid','processing','shipped','completed','cancelled'] as $s) {
    $sc = db()->prepare("SELECT COUNT(*) FROM orders" . ($s ? " WHERE status='$s'" : ""));
    $sc->execute(); $status_counts[$s] = (int)$sc->fetchColumn();
}

$page_title = 'Manajemen Pesanan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-table-wrap">
  <div class="admin-table-header">
    <span class="admin-table-title"><i class="fas fa-shopping-bag" style="color:var(--green-600)"></i> Manajemen Pesanan</span>
    <div class="admin-table-actions">
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="status" value="<?= e($status_f) ?>">
        <div class="admin-search">
          <i class="fas fa-search"></i>
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="No. pesanan / nama..." onchange="this.form.submit()">
        </div>
      </form>
    </div>
  </div>

  <!-- Status Tabs -->
  <div style="display:flex;overflow-x:auto;border-bottom:1px solid var(--border);scrollbar-width:none;padding:0 18px">
    <?php
    $tab_labels = [''=> 'Semua','pending'=>'Pending','paid'=>'Dibayar','processing'=>'Diproses','shipped'=>'Dikirim','completed'=>'Selesai','cancelled'=>'Dibatalkan'];
    foreach($tab_labels as $s => $label): ?>
    <a href="?status=<?= $s ?><?= $q ? '&q='.urlencode($q) : '' ?>"
       style="padding:10px 14px;font-size:.8rem;font-weight:700;white-space:nowrap;color:<?= $status_f===$s?'var(--green-600)':'var(--text-muted)' ?>;border-bottom:2px solid <?= $status_f===$s?'var(--green-600)':'transparent' ?>;margin-bottom:-1px;display:inline-flex;align-items:center;gap:5px">
      <?= $label ?>
      <span style="background:<?= $status_f===$s?'var(--green-600)':'var(--gray-200)' ?>;color:<?= $status_f===$s?'#fff':'var(--gray-600)' ?>;border-radius:var(--radius-full);font-size:.65rem;padding:1px 6px"><?= $status_counts[$s] ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>No. Pesanan</th>
          <th>Pelanggan</th>
          <th>Total</th>
          <th>Pembayaran</th>
          <th>Status</th>
          <th>Tanggal</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($orders)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px">Tidak ada pesanan</td></tr>
        <?php endif; ?>
        <?php foreach($orders as $ord): ?>
        <tr>
          <td><strong style="font-size:.85rem"><?= e($ord['order_number']) ?></strong></td>
          <td>
            <div style="font-size:.85rem;font-weight:700"><?= e($ord['user_name']) ?></div>
            <div style="font-size:.72rem;color:var(--text-muted)"><?= e($ord['user_email']) ?></div>
          </td>
          <td><strong style="color:var(--green-700)"><?= rp($ord['total']) ?></strong></td>
          <td>
            <span class="badge <?= $ord['pay_status']==='settlement'?'badge-success':($ord['pay_status']==='pending'?'badge-warning':'badge-secondary') ?>">
              <?= match($ord['pay_status']) { 'settlement'=>'Lunas','pending'=>'Belum Bayar',default=>'–' } ?>
            </span>
            <?php if($ord['payment_type']): ?>
            <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px"><?= ucwords(str_replace('_',' ',$ord['payment_type'])) . ($ord['bank']?' - '.strtoupper($ord['bank']):'') ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge <?= order_status_class($ord['status']) ?>"><?= order_status_label($ord['status']) ?></span></td>
          <td style="font-size:.8rem;color:var(--text-muted);white-space:nowrap"><?= date('d M Y H:i',strtotime($ord['created_at'])) ?></td>
          <td>
            <a href="order-detail.php?id=<?= $ord['id'] ?>" class="btn btn-primary btn-sm">
              <i class="fas fa-eye"></i> Detail
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="padding:14px 18px;border-top:1px solid var(--border)">
    <?= pagination_html($pag, $pag_url) ?>
    <p style="font-size:.78rem;color:var(--text-muted);margin-top:8px">Total: <?= $total ?> pesanan</p>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
