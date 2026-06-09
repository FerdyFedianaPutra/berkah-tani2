<?php
require_once __DIR__ . '/includes/functions.php';

// ── Query Params ─────────────────────────────────────────────
$q        = trim($_GET['q'] ?? '');
$cat_slug = trim($_GET['cat'] ?? '');
$sort     = $_GET['sort'] ?? 'newest';
$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$min_price = (float)($_GET['min'] ?? 0);
$max_price = (float)($_GET['max'] ?? 0);

// ── Get category ─────────────────────────────────────────────
$category = null;
if ($cat_slug) {
    $stmt = db()->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$cat_slug]);
    $category = $stmt->fetch();
}

// ── Build WHERE clause ────────────────────────────────────────
$where  = ['p.is_active = 1'];
$params = [];

if ($category) {
    $where[]  = 'p.category_id = ?';
    $params[] = $category['id'];
}
if ($q) {
    $where[]  = '(p.name LIKE ? OR p.short_desc LIKE ? OR p.sku LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($min_price > 0) { $where[] = 'p.price >= ?'; $params[] = $min_price; }
if ($max_price > 0) { $where[] = 'p.price <= ?'; $params[] = $max_price; }

$where_sql = 'WHERE ' . implode(' AND ', $where);

$order = match($sort) {
    'price_asc'   => 'p.price ASC',
    'price_desc'  => 'p.price DESC',
    'bestseller'  => 'p.sold_count DESC',
    'featured'    => 'p.is_featured DESC, p.sold_count DESC',
    'rating'      => 'p.rating DESC',
    default       => 'p.created_at DESC',
};

// ── Count total ───────────────────────────────────────────────
$count_stmt = db()->prepare("SELECT COUNT(*) FROM products p $where_sql");
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

$pag = paginate($total, $per_page, $page_num);

// Build URL for pagination
$pag_url = '?' . http_build_query(array_filter(['q'=>$q,'cat'=>$cat_slug,'sort'=>$sort,'min'=>$min_price,'max'=>$max_price])) . '&page=%d';

// ── Fetch products ────────────────────────────────────────────
$stmt = db()->prepare("
    SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
    FROM products p
    JOIN categories c ON c.id = p.category_id
    $where_sql
    ORDER BY $order
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// ── Sidebar categories ────────────────────────────────────────
$all_cats = db()->query("
    SELECT c.*, COUNT(p.id) AS cnt
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();

// ── Price range ───────────────────────────────────────────────
$price_range = db()->query("SELECT MIN(price) as min_p, MAX(price) as max_p FROM products WHERE is_active=1")->fetch();

$page_title = $category ? $category['name'] : ($q ? "Hasil pencarian: $q" : 'Semua Produk');
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <?php if($category): ?>
        <a href="products.php">Produk</a>
        <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
        <span class="current"><?= e($category['name']) ?></span>
      <?php elseif($q): ?>
        <span class="current">Pencarian: "<?= e($q) ?>"</span>
      <?php else: ?>
        <span class="current">Semua Produk</span>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr;gap:20px;margin-top:16px">
      <!-- On desktop: sidebar + content -->
      <div style="display:grid;grid-template-columns:1fr;gap:20px" id="mainLayout">

        <!-- ===== SIDEBAR ===== -->
        <aside id="sidebar" style="display:none">
          <!-- Category filter -->
          <div class="card" style="margin-bottom:12px">
            <div class="card-header" style="margin-bottom:12px;padding-bottom:10px">
              <span class="card-title"><i class="fas fa-th-large"></i> Kategori</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px">
              <a href="products.php" style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-radius:var(--radius-sm);font-size:.85rem;font-weight:600;color:<?= !$cat_slug ? 'var(--green-700)' : 'var(--text-body)' ?>;background:<?= !$cat_slug ? 'var(--green-50)' : 'transparent' ?>;transition:var(--transition)">
                <span>Semua Produk</span>
                <span style="font-size:.72rem;background:var(--gray-100);border-radius:var(--radius-full);padding:2px 8px;color:var(--text-muted)"><?= $total ?></span>
              </a>
              <?php foreach($all_cats as $c): ?>
              <a href="products.php?cat=<?= e($c['slug']) ?>" style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-radius:var(--radius-sm);font-size:.85rem;font-weight:600;color:<?= $cat_slug===$c['slug'] ? 'var(--green-700)' : 'var(--text-body)' ?>;background:<?= $cat_slug===$c['slug'] ? 'var(--green-50)' : 'transparent' ?>;transition:var(--transition)">
                <span><?= e($c['name']) ?></span>
                <span style="font-size:.72rem;background:var(--gray-100);border-radius:var(--radius-full);padding:2px 8px;color:var(--text-muted)"><?= $c['cnt'] ?></span>
              </a>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Price filter -->
          <div class="card" style="margin-bottom:12px">
            <div class="card-header" style="margin-bottom:12px;padding-bottom:10px">
              <span class="card-title"><i class="fas fa-tags"></i> Harga</span>
            </div>
            <form method="GET" action="products.php">
              <?php if($cat_slug): ?><input type="hidden" name="cat" value="<?= e($cat_slug) ?>"><?php endif; ?>
              <?php if($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
              <input type="hidden" name="sort" value="<?= e($sort) ?>">
              <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
                <input type="number" name="min" placeholder="Min" class="form-control" style="font-size:.82rem;padding:8px"
                       value="<?= $min_price ?: '' ?>" min="0" max="<?= $price_range['max_p'] ?>">
                <span style="color:var(--text-muted)">–</span>
                <input type="number" name="max" placeholder="Max" class="form-control" style="font-size:.82rem;padding:8px"
                       value="<?= $max_price ?: '' ?>" min="0" max="<?= $price_range['max_p'] ?>">
              </div>
              <button type="submit" class="btn btn-outline btn-sm btn-block">Terapkan</button>
              <?php if($min_price || $max_price): ?>
              <a href="products.php?<?= http_build_query(array_filter(['cat'=>$cat_slug,'q'=>$q,'sort'=>$sort])) ?>"
                 class="btn btn-ghost btn-sm btn-block" style="margin-top:6px">Reset Harga</a>
              <?php endif; ?>
            </form>
          </div>
        </aside>

        <!-- ===== PRODUCTS AREA ===== -->
        <div>

          <!-- Toolbar -->
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px">
            <div style="display:flex;align-items:center;gap:8px">
              <button onclick="toggleSidebar()" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:6px;border:1px solid var(--border)">
                <i class="fas fa-filter"></i> Filter
              </button>
              <span style="font-size:.82rem;color:var(--text-muted)">
                <?= $total ?> produk ditemukan<?= $q ? ' untuk "<strong>' . e($q) . '</strong>"' : '' ?>
              </span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <label style="font-size:.82rem;color:var(--text-muted);white-space:nowrap">Urutkan:</label>
              <select class="form-control" style="padding:7px 12px;font-size:.82rem;width:auto"
                      onchange="window.location='products.php?<?= http_build_query(array_filter(['q'=>$q,'cat'=>$cat_slug,'min'=>$min_price,'max'=>$max_price])) ?>&sort='+this.value">
                <option value="newest"    <?= $sort==='newest'?'selected':'' ?>>Terbaru</option>
                <option value="bestseller"<?= $sort==='bestseller'?'selected':'' ?>>Terlaris</option>
                <option value="featured"  <?= $sort==='featured'?'selected':'' ?>>Unggulan</option>
                <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Harga Terendah</option>
                <option value="price_desc"<?= $sort==='price_desc'?'selected':'' ?>>Harga Tertinggi</option>
                <option value="rating"    <?= $sort==='rating'?'selected':'' ?>>Rating Tertinggi</option>
              </select>
            </div>
          </div>

          <!-- Results -->
          <?php if(empty($products)): ?>
          <div class="empty-state" style="background:var(--white);border-radius:var(--radius-lg);border:1.5px solid var(--border)">
            <div class="empty-icon"><i class="fas fa-search"></i></div>
            <p class="empty-title">Produk tidak ditemukan</p>
            <p class="empty-desc">Coba kata kunci lain atau hapus filter yang aktif</p>
            <a href="products.php" class="btn btn-primary mt-16">Lihat Semua Produk</a>
          </div>
          <?php else: ?>
          <div class="products-grid">
            <?php foreach($products as $p): ?>
            <div class="product-card">
              <a href="product.php?slug=<?= e($p['slug']) ?>">
                <div class="product-img-wrap">
                  <img src="<?= product_img($p['thumbnail'] ?? '') ?>" alt="<?= e($p['name']) ?>" loading="lazy"/>
                  <?php if($p['is_featured']): ?><span class="product-badge badge-best">⭐ Unggulan</span><?php endif; ?>
                  <?php if($p['sale_price']): ?><span class="product-badge badge-sale" style="top:36px">SALE</span><?php endif; ?>
                </div>
              </a>
              <div class="product-info">
                <a href="product.php?slug=<?= e($p['slug']) ?>" class="product-name"><?= e($p['name']) ?></a>
                <span style="font-size:.72rem;color:var(--text-muted)"><?= e($p['cat_name']) ?></span>
                <div class="product-price-wrap">
                  <?php $dp = $p['sale_price'] ? (float)$p['sale_price'] : (float)$p['price']; ?>
                  <span class="product-price"><?= rp($dp) ?></span>
                  <?php if($p['sale_price']): ?>
                  <div style="display:flex;gap:6px;align-items:center">
                    <span class="product-price-original"><?= rp($p['price']) ?></span>
                    <span class="product-discount">-<?= round((1-(float)$p['sale_price']/(float)$p['price'])*100) ?>%</span>
                  </div>
                  <?php endif; ?>
                </div>
                <?php if((float)$p['rating'] > 0): ?>
                <div class="product-rating">
                  <?php for($s=0;$s<5;$s++) echo $s<round($p['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                  <span>(<?= $p['review_count'] ?>)</span>
                </div>
                <?php endif; ?>
                <div class="product-meta">
                  <span class="product-sold"><?= number_format($p['sold_count']) ?> terjual</span>
                  <?php $stk = (int)$p['stock']; ?>
                  <span class="product-stock <?= $stk===0?'out':($stk<10?'low':'good') ?>">
                    <?= $stk===0?'Habis':($stk<10?"Sisa $stk":'Tersedia') ?>
                  </span>
                </div>
                <button class="product-add-btn" data-add-cart="<?= $p['id'] ?>" <?= $stk===0?'disabled':'' ?>>
                  <i class="fas fa-cart-plus"></i> <?= $stk===0?'Stok Habis':'Tambah' ?>
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Pagination -->
          <?= pagination_html($pag, $pag_url) ?>
          <?php endif; ?>

        </div><!-- /products area -->
      </div><!-- /grid -->
    </div>

  </div>
</main>

<script>
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const l = document.getElementById('mainLayout');
  if (s.style.display === 'none' || !s.style.display) {
    s.style.display = 'block';
    if (window.innerWidth >= 768) {
      l.style.gridTemplateColumns = '240px 1fr';
    }
  } else {
    s.style.display = 'none';
    l.style.gridTemplateColumns = '1fr';
  }
}
// Show sidebar on desktop by default
if (window.innerWidth >= 1024) toggleSidebar();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
