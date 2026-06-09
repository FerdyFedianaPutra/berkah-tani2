<?php
require_once __DIR__ . '/../includes/auth.php';
admin_check();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

// ── Categories for select ────────────────────────────────────
$all_cats = db()->query("SELECT id,name FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

// ── DELETE ───────────────────────────────────────────────────
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('admin','Token tidak valid.','danger'); }
    else {
        // Check if product has orders
        $s = db()->prepare("SELECT COUNT(*) FROM order_items WHERE product_id=?");
        $s->execute([$id]); $has_orders = (int)$s->fetchColumn();
        if ($has_orders) {
            db()->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
            flash('admin','Produk dinonaktifkan karena sudah ada pesanan.','warning');
        } else {
            db()->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$id]);
            db()->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            flash('admin','Produk berhasil dihapus.','success');
        }
    }
    header('Location: ' . APP_URL . '/admin/pages/products.php');
    exit;
}

// ── SAVE (Add/Edit) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'])) {
    if (!verify_csrf()) { $errors[] = 'Token tidak valid.'; }
    else {
        $cat_id      = (int)($_POST['category_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $short_desc  = trim($_POST['short_desc'] ?? '');
        $price       = (float)str_replace(['.','Rp',' '],'',$_POST['price'] ?? 0);
        $sale_price  = !empty($_POST['sale_price']) ? (float)str_replace(['.','Rp',' '],'',$_POST['sale_price']) : null;
        $stock       = (int)($_POST['stock'] ?? 0);
        $unit        = trim($_POST['unit'] ?? 'kg');
        $weight      = (float)($_POST['weight'] ?? 1);
        $sku         = trim($_POST['sku'] ?? '') ?: null;
        $is_featured = !empty($_POST['is_featured']) ? 1 : 0;
        $is_active   = !empty($_POST['is_active']) ? 1 : 0;

        if (!$name)    $errors[] = 'Nama produk wajib diisi.';
        if (!$cat_id)  $errors[] = 'Kategori wajib dipilih.';
        if ($price <= 0) $errors[] = 'Harga harus lebih dari 0.';
        if ($stock < 0)  $errors[] = 'Stok tidak boleh negatif.';

        // SKU unique check
        if ($sku) {
            $ss = db()->prepare("SELECT id FROM products WHERE sku=? AND id != ?");
            $ss->execute([$sku, $id]);
            if ($ss->fetch()) $errors[] = 'SKU sudah digunakan produk lain.';
        }

        if (empty($errors)) {
            $product_slug = slug($name);
            // Ensure slug unique
            $slug_check = db()->prepare("SELECT id FROM products WHERE slug=? AND id!=?");
            $slug_check->execute([$product_slug, $id]);
            if ($slug_check->fetch()) $product_slug = $product_slug . '-' . time();

            // Upload thumbnail
            $thumbnail = null;
            if (!empty($_FILES['thumbnail']['name'])) {
                $uploaded = upload_file($_FILES['thumbnail'], 'products');
                if ($uploaded) $thumbnail = $uploaded;
                else $errors[] = 'Gagal upload gambar. Format harus jpg/png/webp max 5MB.';
            }

            if (empty($errors)) {
                if ($action === 'add') {
                    $stmt = db()->prepare("
                        INSERT INTO products (category_id,name,slug,description,short_desc,price,sale_price,stock,unit,weight,sku,thumbnail,is_featured,is_active)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    $stmt->execute([$cat_id,$name,$product_slug,$description,$short_desc,$price,$sale_price,$stock,$unit,$weight,$sku,$thumbnail,$is_featured,$is_active]);
                    $new_id = db()->lastInsertId();
                    flash('admin',"Produk \"$name\" berhasil ditambahkan.",'success');
                    header('Location: ' . APP_URL . '/admin/pages/products.php?action=edit&id=' . $new_id);
                    exit;
                } else {
                    $set_thumb = $thumbnail ? ', thumbnail=?' : '';
                    $params = [$cat_id,$name,$product_slug,$description,$short_desc,$price,$sale_price,$stock,$unit,$weight,$sku,$is_featured,$is_active];
                    if ($thumbnail) $params[] = $thumbnail;
                    $params[] = $id;
                    db()->prepare("
                        UPDATE products SET category_id=?,name=?,slug=?,description=?,short_desc=?,price=?,sale_price=?,stock=?,unit=?,weight=?,sku=?,is_featured=?,is_active=? $set_thumb WHERE id=?
                    ")->execute($params);
                    flash('admin',"Produk \"$name\" berhasil diperbarui.",'success');
                    header('Location: ' . APP_URL . '/admin/pages/products.php?action=edit&id=' . $id);
                    exit;
                }
            }
        }
    }
}

// ── Fetch product for edit ────────────────────────────────────
$product = null;
if (in_array($action, ['edit']) && $id) {
    $s = db()->prepare("SELECT * FROM products WHERE id=?"); $s->execute([$id]);
    $product = $s->fetch();
    if (!$product) { flash('admin','Produk tidak ditemukan.','danger'); header('Location: ' . APP_URL . '/admin/pages/products.php'); exit; }
}

// ── List ─────────────────────────────────────────────────────
$q        = trim($_GET['q'] ?? '');
$cat_f    = (int)($_GET['cat'] ?? 0);
$page_num = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15;

if ($action === 'list') {
    $where  = ['1=1'];
    $params = [];
    if ($q)     { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($cat_f) { $where[] = 'p.category_id=?'; $params[] = $cat_f; }
    $w = 'WHERE '.implode(' AND ',$where);
    $cnt_s = db()->prepare("SELECT COUNT(*) FROM products p $w"); $cnt_s->execute($params);
    $total = (int)$cnt_s->fetchColumn();
    $pag   = paginate($total,$per_page,$page_num);
    $pag_url = '?'.http_build_query(array_filter(['q'=>$q,'cat'=>$cat_f])).'&page=%d';
    $stmt  = db()->prepare("SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id $w ORDER BY p.created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
}

$page_title = match($action) { 'add'=>'Tambah Produk','edit'=>'Edit Produk',default=>'Manajemen Produk' };
require_once __DIR__ . '/../includes/header.php';
?>

<?php if($action === 'list'): ?>
<!-- ===== LIST ===== -->
<div class="admin-table-wrap">
  <div class="admin-table-header">
    <span class="admin-table-title"><i class="fas fa-box-open" style="color:var(--green-600)"></i> Manajemen Produk</span>
    <div class="admin-table-actions">
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="admin-search">
          <i class="fas fa-search"></i>
          <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari produk…" onchange="this.form.submit()">
        </div>
        <select name="cat" class="form-control" style="font-size:.82rem;padding:8px;width:auto" onchange="this.form.submit()">
          <option value="">Semua Kategori</option>
          <?php foreach($all_cats as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cat_f==(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <a href="?action=add" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Tambah Produk</a>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:60px">Gambar</th>
          <th>Nama Produk</th>
          <th>Kategori</th>
          <th>Harga</th>
          <th>Stok</th>
          <th>Terjual</th>
          <th>Status</th>
          <th style="width:100px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($products)): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px">Tidak ada produk ditemukan</td></tr>
        <?php endif; ?>
        <?php foreach($products as $p): ?>
        <tr>
          <td>
            <img src="<?= product_img($p['thumbnail'] ?? '') ?>" alt=""
                 style="width:48px;height:48px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border)">
          </td>
          <td>
            <div style="font-weight:700;font-size:.88rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($p['name']) ?></div>
            <?php if($p['sku']): ?><span style="font-size:.72rem;color:var(--text-muted)">SKU: <?= e($p['sku']) ?></span><?php endif; ?>
          </td>
          <td><span class="badge badge-primary"><?= e($p['cat_name']) ?></span></td>
          <td>
            <div style="font-weight:800;color:var(--green-700)"><?= rp($p['sale_price'] ?? $p['price']) ?></div>
            <?php if($p['sale_price']): ?><div style="font-size:.75rem;text-decoration:line-through;color:var(--text-muted)"><?= rp($p['price']) ?></div><?php endif; ?>
          </td>
          <td>
            <span style="font-weight:700;color:<?= (int)$p['stock']===0?'var(--red-500)':((int)$p['stock']<10?'var(--orange-500)':'var(--green-600)') ?>">
              <?= number_format($p['stock']) ?>
            </span>
          </td>
          <td><?= number_format($p['sold_count']) ?></td>
          <td>
            <?php if($p['is_active']): ?>
            <span class="badge badge-success">Aktif</span>
            <?php else: ?>
            <span class="badge badge-secondary">Nonaktif</span>
            <?php endif; ?>
            <?php if($p['is_featured']): ?><span class="badge badge-warning" style="margin-top:3px;display:block">Unggulan</span><?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:4px;align-items:center">
              <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" style="padding:5px 8px" title="Edit">
                <i class="fas fa-edit" style="color:var(--blue-500)"></i>
              </a>
              <a href="<?= APP_URL ?>/product.php?slug=<?= e($p['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="padding:5px 8px" title="Lihat">
                <i class="fas fa-eye" style="color:var(--green-600)"></i>
              </a>
              <!-- FIXED: pakai form POST agar CSRF token tidak lewat URL -->
              <form method="POST" action="?action=delete&id=<?= $p['id'] ?>" style="display:inline;margin:0"
                    onsubmit="return confirm('Hapus produk \"<?= e(addslashes($p['name'])) ?>\"?\nAksi ini tidak bisa dibatalkan.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-sm" style="padding:5px 8px" title="Hapus">
                  <i class="fas fa-trash" style="color:var(--red-500)"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="padding:14px 18px;border-top:1px solid var(--border)">
    <?= pagination_html($pag, $pag_url) ?>
    <p style="font-size:.78rem;color:var(--text-muted);margin-top:8px">Total: <?= $total ?> produk</p>
  </div>
</div>

<?php elseif(in_array($action,['add','edit'])): ?>
<!-- ===== ADD / EDIT FORM ===== -->
<?php if($errors): ?>
<div class="alert alert-danger mb-16">
  <i class="fas fa-exclamation-circle"></i>
  <ul style="margin:0;padding-left:14px"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div style="display:grid;grid-template-columns:1fr;gap:20px" id="productForm">

    <!-- Main Info -->
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="admin-form-panel">
        <div class="admin-form-header">
          <span style="font-weight:800;color:var(--gray-800)"><i class="fas fa-info-circle" style="color:var(--green-600)"></i> Informasi Produk</span>
        </div>
        <div class="admin-form-body">
          <div class="form-group">
            <label class="form-label">Nama Produk <span class="required">*</span></label>
            <input type="text" name="name" class="form-control" required
                   value="<?= e($product['name'] ?? '') ?>" placeholder="Contoh: Beras Premium Pandan Wangi 5kg">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="form-group">
              <label class="form-label">Kategori <span class="required">*</span></label>
              <select name="category_id" class="form-control" required>
                <option value="">Pilih Kategori</option>
                <?php foreach($all_cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($product['category_id'] ?? 0)==(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">SKU</label>
              <input type="text" name="sku" class="form-control"
                     value="<?= e($product['sku'] ?? '') ?>" placeholder="BT-BP-001">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Deskripsi Singkat</label>
            <input type="text" name="short_desc" class="form-control"
                   value="<?= e($product['short_desc'] ?? '') ?>" placeholder="Ringkasan produk (max 300 karakter)" maxlength="300">
          </div>
          <div class="form-group">
            <label class="form-label">Deskripsi Lengkap</label>
            <textarea name="description" class="form-control" rows="5"
                      placeholder="Deskripsi detail produk…"><?= e($product['description'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <div class="admin-form-panel">
        <div class="admin-form-header"><span style="font-weight:800;color:var(--gray-800)"><i class="fas fa-tags" style="color:var(--green-600)"></i> Harga & Stok</span></div>
        <div class="admin-form-body">
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px">
            <div class="form-group">
              <label class="form-label">Harga Normal <span class="required">*</span></label>
              <div class="input-group">
                <i class="input-icon fas fa-rupiah-sign" style="font-size:.8rem"></i>
                <input type="number" name="price" class="form-control" required min="0" step="500"
                       value="<?= $product['price'] ?? '' ?>" placeholder="75000">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Harga Sale <span style="font-size:.72rem;color:var(--text-muted)">(opsional)</span></label>
              <div class="input-group">
                <i class="input-icon fas fa-rupiah-sign" style="font-size:.8rem"></i>
                <input type="number" name="sale_price" class="form-control" min="0" step="500"
                       value="<?= $product['sale_price'] ?? '' ?>" placeholder="0">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Stok <span class="required">*</span></label>
              <input type="number" name="stock" class="form-control" required min="0"
                     value="<?= $product['stock'] ?? 0 ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Satuan</label>
              <select name="unit" class="form-control">
                <?php foreach(['kg','pack','karung','liter','gram','pcs'] as $u): ?>
                <option value="<?= $u ?>" <?= ($product['unit'] ?? 'kg')===$u?'selected':'' ?>><?= $u ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Berat (kg/satuan)</label>
              <input type="number" name="weight" class="form-control" min="0.01" step="0.01"
                     value="<?= $product['weight'] ?? 1 ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar: Image & Status -->
    <div style="display:flex;flex-direction:column;gap:16px">
      <div class="admin-form-panel">
        <div class="admin-form-header"><span style="font-weight:800;color:var(--gray-800)"><i class="fas fa-image" style="color:var(--green-600)"></i> Gambar Produk</span></div>
        <div class="admin-form-body">
          <label class="img-upload-wrap" for="thumbnailInput">
            <img id="imgPreview" src="<?= product_img($product['thumbnail'] ?? '') ?>" class="img-preview"
                 style="<?= $product['thumbnail'] ? 'display:block' : '' ?>"/>
            <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:var(--gray-300);display:block;margin-bottom:8px"></i>
            <div style="font-size:.85rem;font-weight:700;color:var(--gray-600)">Klik untuk upload gambar</div>
            <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px">JPG, PNG, WEBP maks. 5MB</div>
          </label>
          <input type="file" name="thumbnail" id="thumbnailInput" accept="image/*" style="display:none" data-preview="imgPreview">
        </div>
      </div>

      <div class="admin-form-panel">
        <div class="admin-form-header"><span style="font-weight:800;color:var(--gray-800)"><i class="fas fa-toggle-on" style="color:var(--green-600)"></i> Status Produk</span></div>
        <div class="admin-form-body" style="display:flex;flex-direction:column;gap:12px">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <input type="checkbox" name="is_active" value="1" <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?>
                   style="accent-color:var(--green-600);width:18px;height:18px">
            <div>
              <div style="font-weight:700;font-size:.88rem">Produk Aktif</div>
              <div style="font-size:.75rem;color:var(--text-muted)">Tampilkan di toko</div>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <input type="checkbox" name="is_featured" value="1" <?= ($product['is_featured'] ?? 0) ? 'checked' : '' ?>
                   style="accent-color:var(--yellow-500);width:18px;height:18px">
            <div>
              <div style="font-weight:700;font-size:.88rem">Produk Unggulan</div>
              <div style="font-size:.75rem;color:var(--text-muted)">Tampilkan di halaman utama</div>
            </div>
          </label>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">
          <i class="fas fa-save"></i> <?= $action==='add'?'Simpan Produk':'Update Produk' ?>
        </button>
        <a href="?action=list" class="btn btn-ghost" style="border:1px solid var(--border)">Batal</a>
      </div>
    </div>

  </div>
</form>

<script>
function applyProductForm() {
  const el = document.getElementById('productForm');
  if (el) el.style.gridTemplateColumns = window.innerWidth >= 1024 ? '1fr 300px' : '1fr';
}
applyProductForm(); window.addEventListener('resize', applyProductForm);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>