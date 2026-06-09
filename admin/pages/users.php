<?php
require_once __DIR__ . '/../includes/auth.php';
admin_check();

// Toggle user active status - DIUBAH KE POST
if (isset($_POST['action']) && $_POST['action'] === 'toggle' && isset($_POST['id'])) {
    if (!verify_csrf()) { 
        flash('admin','Token tidak valid.','danger'); 
    }
    else {
        $uid = (int)$_POST['id'];
        db()->prepare("UPDATE users SET is_active = NOT is_active WHERE id=?")->execute([$uid]);
        flash('admin','Status pengguna diperbarui.','success');
    }
    redirect(APP_URL . '/admin/pages/users.php');
}

$q        = trim($_GET['q'] ?? '');
$status_f = $_GET['status'] ?? '';
$page_num = max(1,(int)($_GET['page'] ?? 1));
$per_page = 20;

$where  = ['1=1'];
$params = [];
if ($q) {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($status_f !== '') { 
    $where[] = 'is_active=?'; 
    $params[] = (int)$status_f; 
}
$w = 'WHERE '.implode(' AND ',$where);

$cnt_s = db()->prepare("SELECT COUNT(*) FROM users $w"); 
$cnt_s->execute($params);
$total = (int)$cnt_s->fetchColumn();

$pag   = paginate($total,$per_page,$page_num);
$pag_url = '?'.http_build_query(array_filter(['q'=>$q,'status'=>$status_f])).'&page=%d';

$stmt = db()->prepare("
    SELECT u.*,
           COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(CASE WHEN o.status='completed' THEN o.total ELSE 0 END),0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id=u.id
    $w
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'Manajemen Pelanggan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-table-wrap">
  <div class="admin-table-header">
    <span class="admin-table-title"><i class="fas fa-users" style="color:var(--green-600)"></i> Manajemen Pelanggan</span>
    <div class="admin-table-actions">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <div class="admin-search">
          <i class="fas fa-search"></i>
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama / email…" onchange="this.form.submit()">
        </div>
        <select name="status" class="form-control" style="font-size:.82rem;padding:8px;width:auto" onchange="this.form.submit()">
          <option value="">Semua Status</option>
          <option value="1" <?= $status_f==='1'?'selected':'' ?>>Aktif</option>
          <option value="0" <?= $status_f==='0'?'selected':'' ?>>Nonaktif</option>
        </select>
      </form>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Pengguna</th>
          <th>Telepon</th>
          <th>Pesanan</th>
          <th>Total Belanja</th>
          <th>Bergabung</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($users)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">Tidak ada pengguna ditemukan</td></tr>
        <?php endif; ?>
        
        <?php foreach($users as $u): ?>
        <tr>
          <td>
            <div style="display:flex;gap:10px;align-items:center">
              <div style="width:36px;height:36px;border-radius:50%;background:var(--green-100);color:var(--green-700);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:800;flex-shrink:0">
                <?= mb_substr($u['name'],0,1) ?>
              </div>
              <div>
                <div style="font-weight:700;font-size:.88rem"><?= e($u['name']) ?></div>
                <div style="font-size:.75rem;color:var(--text-muted)"><?= e($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:.85rem"><?= e($u['phone'] ?: '–') ?></td>
          <td>
            <?php if($u['order_count'] > 0): ?>
            <a href="orders.php?q=<?= urlencode($u['email']) ?>" class="badge badge-primary" style="text-decoration:none">
              <?= $u['order_count'] ?> pesanan
            </a>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:.82rem">Belum pernah</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($u['total_spent'] > 0): ?>
            <strong style="color:var(--green-700);font-size:.88rem"><?= rp($u['total_spent']) ?></strong>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:.82rem">–</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:var(--text-muted);white-space:nowrap"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
          <td>
            <span class="badge <?= $u['is_active']?'badge-success':'badge-danger' ?>">
              <?= $u['is_active']?'Aktif':'Nonaktif' ?>
            </span>
          </td>
          <td>
            <!-- Form POST untuk toggle (lebih aman) -->
            <form method="POST" style="display:inline;" 
                  onsubmit="return confirm('<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> pengguna ini?')">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              
              <button type="submit" class="btn btn-ghost btn-sm" 
                      style="padding:5px 10px;border:1px solid var(--border)">
                <i class="fas fa-<?= $u['is_active']?'ban':'check' ?>" 
                   style="color:<?= $u['is_active']?'var(--red-500)':'var(--green-600)' ?>"></i>
                <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  
  <div style="padding:14px 18px;border-top:1px solid var(--border)">
    <?= pagination_html($pag,$pag_url) ?>
    <p style="font-size:.78rem;color:var(--text-muted);margin-top:8px">Total: <?= $total ?> pengguna</p>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>