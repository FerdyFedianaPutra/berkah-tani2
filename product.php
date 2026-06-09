<?php
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) redirect('products.php');

// Fetch product
$stmt = db()->prepare("
    SELECT p.*, c.name AS cat_name, c.slug AS cat_slug
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.slug = ? AND p.is_active = 1
");
$stmt->execute([$slug]);
$p = $stmt->fetch();
if (!$p) { header('HTTP/1.0 404 Not Found'); redirect('products.php'); }

// Increment view
db()->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$p['id']]);

// Product images
$images = db()->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
$images->execute([$p['id']]);
$images = $images->fetchAll();

// Related products
$related = db()->prepare("
    SELECT p.*, c.name AS cat_name FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
    ORDER BY p.sold_count DESC LIMIT 6
");
$related->execute([$p['category_id'], $p['id']]);
$related = $related->fetchAll();

// Reviews
$reviews = db()->prepare("
    SELECT r.*, u.name AS user_name, u.avatar AS user_avatar
    FROM reviews r JOIN users u ON u.id = r.user_id
    WHERE r.product_id = ? AND r.is_active = 1
    ORDER BY r.created_at DESC LIMIT 10
");
$reviews->execute([$p['id']]);
$reviews = $reviews->fetchAll();

$main_img     = !empty($images) ? product_img($images[0]['image']) : product_img($p['thumbnail'] ?? '');
$sale_price   = $p['sale_price'] ? (float)$p['sale_price'] : null;
$display_price= $sale_price ?? (float)$p['price'];
$discount     = $sale_price ? round((1-$sale_price/(float)$p['price'])*100) : 0;
$in_stock     = (int)$p['stock'] > 0;

$page_title = $p['name'];
$meta_desc  = $p['short_desc'] ?? truncate(strip_tags($p['description'] ?? ''), 160);
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <a href="products.php">Produk</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <a href="products.php?cat=<?= e($p['cat_slug']) ?>"><?= e($p['cat_name']) ?></a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current"><?= e(truncate($p['name'],40)) ?></span>
    </div>

    <!-- ===== PRODUCT DETAIL ===== -->
    <div style="display:grid;grid-template-columns:1fr;gap:24px;margin-top:16px" id="productDetail">

      <!-- Images -->
      <div class="card" style="padding:16px">
        <div style="position:relative;border-radius:var(--radius-md);overflow:hidden;background:var(--gray-100);margin-bottom:12px;aspect-ratio:1">
          <img id="mainImg" src="<?= $main_img ?>" alt="<?= e($p['name']) ?>"
               style="width:100%;height:100%;object-fit:contain;padding:12px"/>
          <?php if($discount > 0): ?>
          <div style="position:absolute;top:12px;left:12px;background:var(--red-500);color:#fff;padding:4px 12px;border-radius:var(--radius-full);font-size:.8rem;font-weight:800">
            -<?= $discount ?>%
          </div>
          <?php endif; ?>
        </div>
        <!-- Thumbnails -->
        <?php if(count($images) > 1): ?>
        <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px">
          <?php foreach($images as $i => $img): ?>
          <img src="<?= product_img($img['image']) ?>" alt="<?= e($img['alt_text'] ?? $p['name']) ?>"
               class="gallery-thumb <?= $i===0?'active':'' ?>"
               data-large="<?= product_img($img['image'],'large') ?>"
               style="width:64px;height:64px;object-fit:cover;border-radius:var(--radius-sm);border:2px solid <?= $i===0?'var(--green-500)':'var(--border)' ?>;cursor:pointer;flex-shrink:0;transition:var(--transition)"
               onclick="this.parentNode.querySelectorAll('img').forEach(t=>t.style.borderColor='var(--border)');this.style.borderColor='var(--green-500)';document.getElementById('mainImg').src=this.dataset.large||this.src">
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div>
        <div class="card">
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">
            <a href="products.php?cat=<?= e($p['cat_slug']) ?>" class="badge badge-primary">
              <?= e($p['cat_name']) ?>
            </a>
            <?php if($p['is_featured']): ?><span class="badge badge-warning">⭐ Unggulan</span><?php endif; ?>
            <?php if($p['sku']): ?><span class="badge badge-secondary">SKU: <?= e($p['sku']) ?></span><?php endif; ?>
          </div>

          <h1 style="font-size:clamp(1.2rem,3vw,1.6rem);font-weight:900;color:var(--gray-900);margin-bottom:10px;line-height:1.3">
            <?= e($p['name']) ?>
          </h1>

          <?php if((float)$p['rating'] > 0): ?>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
            <div class="product-rating" style="font-size:.88rem">
              <?php for($s=0;$s<5;$s++) echo $s<round($p['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
            </div>
            <span style="font-size:.85rem;color:var(--text-muted)"><?= number_format((float)$p['rating'],1) ?> (<?= $p['review_count'] ?> ulasan)</span>
            <span style="color:var(--gray-300)">|</span>
            <span style="font-size:.85rem;color:var(--text-muted)"><?= number_format($p['sold_count']) ?> terjual</span>
          </div>
          <?php endif; ?>

          <!-- Price -->
          <div style="background:var(--green-50);border-radius:var(--radius-md);padding:14px 16px;margin-bottom:16px">
            <div style="font-size:1.8rem;font-weight:900;color:var(--green-700)"><?= rp($display_price) ?></div>
            <?php if($sale_price): ?>
            <div style="display:flex;gap:10px;align-items:center;margin-top:4px">
              <span style="font-size:.9rem;text-decoration:line-through;color:var(--text-muted)"><?= rp($p['price']) ?></span>
              <span class="badge badge-danger">Hemat <?= $discount ?>%</span>
            </div>
            <?php endif; ?>
          </div>

          <?php if($p['short_desc']): ?>
          <p style="font-size:.88rem;color:var(--text-body);margin-bottom:16px;line-height:1.6"><?= e($p['short_desc']) ?></p>
          <?php endif; ?>

          <!-- Stock & Weight -->
          <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;font-size:.85rem">
            <div style="display:flex;align-items:center;gap:6px">
              <i class="fas fa-boxes" style="color:var(--green-600)"></i>
              <span><strong>Stok:</strong>
                <?php if((int)$p['stock'] === 0): ?>
                  <span style="color:var(--red-500);font-weight:700">Habis</span>
                <?php elseif((int)$p['stock'] < 10): ?>
                  <span style="color:var(--orange-500);font-weight:700">Sisa <?= $p['stock'] ?> <?= e($p['unit']) ?></span>
                <?php else: ?>
                  <span style="color:var(--green-600);font-weight:700"><?= number_format($p['stock']) ?> <?= e($p['unit']) ?></span>
                <?php endif; ?>
              </span>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
              <i class="fas fa-weight" style="color:var(--green-600)"></i>
              <span><strong>Berat:</strong> <?= $p['weight'] ?> kg/<?= e($p['unit']) ?></span>
            </div>
          </div>

          <?php if($in_stock): ?>
          <!-- Quantity + Add to Cart -->
          <div style="display:flex;gap:12px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;border:1.5px solid var(--border);border-radius:var(--radius-full);overflow:hidden">
              <button class="qty-btn" data-qty-action="dec" style="border:none;border-radius:0;width:36px;height:40px"
                      onclick="adjQty(-1)"><i class="fas fa-minus"></i></button>
              <span class="qty-val" id="qtyVal" data-product-qty="<?= $p['id'] ?>" style="min-width:40px;text-align:center;font-weight:800;font-size:1rem">1</span>
              <button class="qty-btn" data-qty-action="inc" style="border:none;border-radius:0;width:36px;height:40px"
                      onclick="adjQty(1)"><i class="fas fa-plus"></i></button>
            </div>
            <button class="btn btn-primary" style="flex:1;min-width:160px" data-add-cart="<?= $p['id'] ?>">
              <i class="fas fa-cart-plus"></i> Tambah ke Keranjang
            </button>
          </div>
          <a href="checkout.php?buy_now=<?= $p['id'] ?>&qty=1" class="btn btn-secondary btn-block">
            <i class="fas fa-bolt"></i> Beli Sekarang
          </a>
          <?php else: ?>
          <div class="alert alert-danger"><i class="fas fa-times-circle"></i> Stok produk ini sedang habis</div>
          <?php endif; ?>

          <!-- Benefits -->
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:16px">
            <?php
            $benefits = [
              ['icon'=>'fa-shield-alt','text'=>'Kualitas Terjamin'],
              ['icon'=>'fa-truck','text'=>'Pengiriman Aman'],
              ['icon'=>'fa-undo','text'=>'Garansi Kepuasan'],
              ['icon'=>'fa-headset','text'=>'CS 7 Hari'],
            ];
            foreach($benefits as $b): ?>
            <div style="display:flex;align-items:center;gap:8px;padding:8px;background:var(--gray-50);border-radius:var(--radius-sm)">
              <i class="fas <?= $b['icon'] ?>" style="color:var(--green-600);font-size:.85rem"></i>
              <span style="font-size:.75rem;font-weight:700;color:var(--gray-700)"><?= $b['text'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div><!-- /card info -->

      </div><!-- /info col -->
    </div><!-- /product grid -->

    <!-- ===== DESCRIPTION & REVIEWS ===== -->
    <div style="margin-top:20px">
      <!-- Tabs -->
      <div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:20px;overflow-x:auto">
        <?php
        $tabs = [
          ['id'=>'desc','label'=>'Deskripsi'],
          ['id'=>'reviews','label'=>'Ulasan ('.$p['review_count'].')'],
          ['id'=>'shipping','label'=>'Info Pengiriman'],
        ];
        foreach($tabs as $i => $tab): ?>
        <button onclick="showTab('<?= $tab['id'] ?>')" id="tab-<?= $tab['id'] ?>"
                style="padding:12px 20px;border:none;background:none;font-size:.9rem;font-weight:700;color:<?= $i===0?'var(--green-600)':'var(--text-muted)' ?>;border-bottom:<?= $i===0?'2px solid var(--green-600)':'2px solid transparent' ?>;margin-bottom:-2px;cursor:pointer;white-space:nowrap;transition:var(--transition)">
          <?= $tab['label'] ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Description Tab -->
      <div id="tab-content-desc" class="card">
        <?php if($p['description']): ?>
        <div style="line-height:1.8;font-size:.92rem;color:var(--text-body)">
          <?= nl2br(e($p['description'])) ?>
        </div>
        <?php else: ?>
        <p style="color:var(--text-muted)">Belum ada deskripsi produk.</p>
        <?php endif; ?>
      </div>

      <!-- Reviews Tab -->
      <div id="tab-content-reviews" class="card" style="display:none">
        <?php if(empty($reviews)): ?>
        <div class="empty-state" style="padding:32px 20px">
          <div class="empty-icon" style="font-size:2.5rem"><i class="fas fa-star"></i></div>
          <p class="empty-title">Belum ada ulasan</p>
          <p class="empty-desc">Jadilah yang pertama memberikan ulasan untuk produk ini</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px">
          <?php foreach($reviews as $rv): ?>
          <div style="padding-bottom:16px;border-bottom:1px solid var(--border)">
            <div style="display:flex;gap:10px;align-items:flex-start">
              <div style="width:36px;height:36px;border-radius:var(--radius-full);background:var(--green-100);color:var(--green-700);display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:800;flex-shrink:0">
                <?= mb_substr($rv['user_name'],0,1) ?>
              </div>
              <div style="flex:1">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                  <strong style="font-size:.88rem"><?= e($rv['user_name']) ?></strong>
                  <div style="display:flex;gap:2px;color:var(--yellow-500);font-size:.8rem">
                    <?php for($s=0;$s<5;$s++) echo $s<$rv['rating']?'<i class="fas fa-star"></i>':'<i class="far fa-star"></i>'; ?>
                  </div>
                  <span style="font-size:.72rem;color:var(--text-muted)"><?= time_ago($rv['created_at']) ?></span>
                </div>
                <?php if($rv['comment']): ?>
                <p style="font-size:.85rem;color:var(--text-body);margin-top:6px;line-height:1.6"><?= e($rv['comment']) ?></p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Shipping Tab -->
      <div id="tab-content-shipping" class="card" style="display:none">
        <div style="display:grid;gap:12px">
          <?php
          $shipping_info = [
            ['icon'=>'fa-truck','title'=>'Pengiriman Regular','desc'=>'2-5 hari kerja. Biaya ongkir Rp '.number_format((float)setting('shipping_cost',15000),0,',','.')],
            ['icon'=>'fa-gift','title'=>'Gratis Ongkir','desc'=>'Untuk pembelian min. Rp '.number_format((float)setting('free_shipping_min',200000),0,',','.')],
            ['icon'=>'fa-box-open','title'=>'Pengemasan Aman','desc'=>'Produk dikemas dengan karung/plastik kuat agar tidak tumpah'],
            ['icon'=>'fa-map-marker-alt','title'=>'Jangkauan Pengiriman','desc'=>'Seluruh wilayah Indonesia via ekspedisi terpercaya'],
          ];
          foreach($shipping_info as $si): ?>
          <div style="display:flex;gap:14px;padding:14px;background:var(--gray-50);border-radius:var(--radius-md)">
            <div style="width:40px;height:40px;border-radius:var(--radius-md);background:var(--green-50);color:var(--green-600);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="fas <?= $si['icon'] ?>"></i>
            </div>
            <div>
              <div style="font-weight:800;font-size:.88rem;color:var(--gray-800);margin-bottom:3px"><?= $si['title'] ?></div>
              <div style="font-size:.82rem;color:var(--text-muted)"><?= $si['desc'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /tabs -->

    <!-- ===== RELATED PRODUCTS ===== -->
    <?php if($related): ?>
    <section style="margin-top:40px">
      <div class="section-header" style="margin-bottom:20px">
        <div>
          <h2 class="section-title">Produk <span>Terkait</span></h2>
          <p class="section-subtitle">Mungkin Anda juga suka</p>
        </div>
        <a href="products.php?cat=<?= e($p['cat_slug']) ?>" class="section-link">Lihat Semua <i class="fas fa-arrow-right"></i></a>
      </div>
      <div class="products-grid">
        <?php foreach($related as $rp): ?>
        <div class="product-card">
          <a href="product.php?slug=<?= e($rp['slug']) ?>">
            <div class="product-img-wrap">
              <img src="<?= product_img($rp['thumbnail'] ?? '') ?>" alt="<?= e($rp['name']) ?>" loading="lazy"/>
            </div>
          </a>
          <div class="product-info">
            <a href="product.php?slug=<?= e($rp['slug']) ?>" class="product-name"><?= e($rp['name']) ?></a>
            <span class="product-price"><?= rp($rp['sale_price'] ?? $rp['price']) ?></span>
            <span class="product-sold"><?= number_format($rp['sold_count']) ?> terjual</span>
            <button class="product-add-btn" data-add-cart="<?= $rp['id'] ?>" <?= (int)$rp['stock']===0?'disabled':'' ?>>
              <i class="fas fa-cart-plus"></i> Tambah
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </div>
</main>

<script>
let qty = 1;
const maxQty = <?= (int)$p['stock'] ?>;
function adjQty(n) {
  qty = Math.max(1, Math.min(maxQty, qty + n));
  document.getElementById('qtyVal').textContent = qty;
}

function showTab(id) {
  ['desc','reviews','shipping'].forEach(t => {
    document.getElementById('tab-content-'+t).style.display = t===id ? '' : 'none';
    const btn = document.getElementById('tab-'+t);
    btn.style.color       = t===id ? 'var(--green-600)' : 'var(--text-muted)';
    btn.style.borderBottom= t===id ? '2px solid var(--green-600)' : '2px solid transparent';
  });
}

// Responsive product detail layout
function applyLayout() {
  const el = document.getElementById('productDetail');
  if (!el) return;
  el.style.gridTemplateColumns = window.innerWidth >= 768 ? '1fr 1fr' : '1fr';
}
applyLayout();
window.addEventListener('resize', applyLayout);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
