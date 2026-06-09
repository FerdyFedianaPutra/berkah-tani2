<?php
require_once __DIR__ . '/../includes/auth.php';
admin_check();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

// ====================== DELETE (POST) ======================
if ($action === 'delete' && $id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('admin', 'Token tidak valid.', 'danger');
    } else {
        $has = db()->prepare("SELECT COUNT(*) FROM products WHERE category_id=?");
        $has->execute([$id]);

        if ((int)$has->fetchColumn() > 0) {
            flash('admin', 'Tidak dapat menghapus: kategori memiliki produk.', 'danger');
        } else {
            db()->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
            flash('admin', 'Kategori berhasil dihapus.', 'success');
        }
    }
    redirect(APP_URL . '/admin/pages/categories.php');
}

// ====================== SAVE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'delete') {
    if (!verify_csrf()) {
        $errors[] = 'Token tidak valid.';
    } else {
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'fa-leaf');
        $sort_order  = (int)($_POST['sort_order'] ?? 0);
        $is_active   = !empty($_POST['is_active']) ? 1 : 0;

        if (!$name) $errors[] = 'Nama kategori wajib diisi.';

        if (empty($errors)) {
            $cat_slug = slug($name);

            // Unique slug
            $sc = db()->prepare("SELECT id FROM categories WHERE slug=? AND id!=?");
            $sc->execute([$cat_slug, $id]);
            if ($sc->fetch()) {
                $cat_slug .= '-' . time();
            }

            // Image upload
            $image = null;
            if (!empty($_FILES['image']['name'])) {
                $image = upload_file($_FILES['image'], 'products');
            }

            if ($action === 'add') {
                $stmt = db()->prepare("INSERT INTO categories (name,slug,description,image,icon,sort_order,is_active) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([$name, $cat_slug, $description, $image, $icon, $sort_order, $is_active]);
                flash('admin', "Kategori \"$name\" berhasil ditambahkan.", 'success');
            } else {
                $set_img = $image ? ', image=?' : '';
                $params  = [$name, $cat_slug, $description, $icon, $sort_order, $is_active];
                if ($image) $params[] = $image;
                $params[] = $id;

                db()->prepare("UPDATE categories SET name=?, slug=?, description=?, icon=?, sort_order=?, is_active=? $set_img WHERE id=?")
                     ->execute($params);
                flash('admin', "Kategori \"$name\" berhasil diperbarui.", 'success');
            }
            redirect(APP_URL . '/admin/pages/categories.php');
        }
    }
}

// Fetch for edit
$category = null;
if (in_array($action, ['edit']) && $id) {
    $s = db()->prepare("SELECT * FROM categories WHERE id=?");
    $s->execute([$id]);
    $category = $s->fetch();
    if (!$category) redirect(APP_URL . '/admin/pages/categories.php');
}

// List
$categories = db()->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
    GROUP BY c.id 
    ORDER BY c.sort_order
")->fetchAll();

$page_title = match($action) {
    'add' => 'Tambah Kategori',
    'edit' => 'Edit Kategori',
    default => 'Manajemen Kategori'
};

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'list'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <h2 style="font-size:1rem;font-weight:800;color:var(--gray-800)">
    <i class="fas fa-th-large" style="color:var(--green-600)"></i> Manajemen Kategori
  </h2>
  <a href="?action=add" class="btn btn-primary btn-sm">
    <i class="fas fa-plus"></i> Tambah Kategori
  </a>
</div>

<div class="admin-table-wrap">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ikon</th>
          <th>Nama Kategori</th>
          <th>Slug</th>
          <th>Produk</th>
          <th>Urutan</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $c): ?>
        <tr>
          <td>
            <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:var(--green-50);color:var(--green-600);display:flex;align-items:center;justify-content:center">
              <i class="fas <?= e($c['icon']) ?>"></i>
            </div>
          </td>
          <td><strong><?= e($c['name']) ?></strong></td>
          <td><code style="font-size:.78rem;background:var(--gray-100);padding:2px 6px;border-radius:4px"><?= e($c['slug']) ?></code></td>
          <td><span class="badge badge-primary"><?= $c['product_count'] ?> produk</span></td>
          <td><?= $c['sort_order'] ?></td>
          <td>
            <span class="badge <?= $c['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
              <?= $c['is_active'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" style="padding:5px 8px">
                <i class="fas fa-edit" style="color:var(--blue-500)"></i>
              </a>

              <!-- Delete Form -->
              <form method="POST" action="?action=delete&id=<?= $c['id'] ?>" style="display:inline;" onsubmit="return confirm('Hapus kategori \"<?= e(addslashes($c['name'])) ?>?\"')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-sm" style="padding:5px 8px">
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
</div>

<?php else: ?>
<!-- Add/Edit Form -->
<?php if ($errors): ?>
<div class="alert alert-danger mb-16">
  <i class="fas fa-exclamation-circle"></i>
  <ul style="margin:0;padding-left:14px">
    <?php foreach ($errors as $e): ?>
      <li><?= e($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="admin-form-panel" style="max-width:640px">
  <div class="admin-form-header">
    <span style="font-weight:800"><?= $action === 'add' ? 'Tambah Kategori Baru' : 'Edit Kategori' ?></span>
    <a href="?action=list" class="btn btn-ghost btn-sm" style="border:1px solid var(--border)">
      <i class="fas fa-arrow-left"></i> Kembali
    </a>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <!-- ... form fields tetap sama ... -->
    <div class="admin-form-body" style="display:flex;flex-direction:column;gap:14px">
      <div class="form-group">
        <label class="form-label">Nama Kategori <span class="required">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= e($category['name'] ?? '') ?>" placeholder="Beras Premium">
      </div>
      <div class="form-group">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="3" placeholder="Deskripsi singkat kategori"><?= e($category['description'] ?? '') ?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label class="form-label">Kode Ikon <span style="font-size:.72rem;color:var(--text-muted)">Font Awesome</span></label>
          <div class="input-group">
            <i class="input-icon fas fa-icons"></i>
            <input type="text" name="icon" class="form-control" value="<?= e($category['icon'] ?? 'fa-leaf') ?>" placeholder="fa-leaf">
          </div>
          <p class="form-hint">Contoh: fa-leaf, fa-box-open, fa-seedling</p>
        </div>
        <div class="form-group">
          <label class="form-label">Urutan Tampil</label>
          <input type="number" name="sort_order" class="form-control" min="0" value="<?= $category['sort_order'] ?? 0 ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Gambar Kategori <span style="font-size:.72rem;color:var(--text-muted)">(opsional)</span></label>
        <label class="img-upload-wrap" for="catImgInput" style="max-width:240px">
          <img id="catImgPreview" src="" class="img-preview">
          <i class="fas fa-cloud-upload-alt" style="font-size:1.5rem;color:var(--gray-300);display:block;margin-bottom:6px"></i>
          <div style="font-size:.82rem;color:var(--gray-500)">Klik untuk upload</div>
        </label>
        <input type="file" name="image" id="catImgInput" accept="image/*" style="display:none" data-preview="catImgPreview">
      </div>
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
        <input type="checkbox" name="is_active" value="1" <?= ($category['is_active'] ?? 1) ? 'checked' : '' ?> style="accent-color:var(--green-600);width:16px;height:16px">
        <span style="font-size:.88rem;font-weight:700">Kategori Aktif</span>
      </label>
    </div>

    <div class="admin-form-footer">
      <a href="?action=list" class="btn btn-ghost" style="border:1px solid var(--border)">Batal</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>