<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$uid  = $_SESSION['user_id'];
$user = current_user();

// Buy Now mode
$buy_now_id  = (int)($_GET['buy_now'] ?? 0);
$buy_now_qty = max(1,(int)($_GET['qty'] ?? 1));

// Get cart items
if ($buy_now_id) {
    $ps = db()->prepare("SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.is_active=1");
    $ps->execute([$buy_now_id]);
    $bp = $ps->fetch();
    if (!$bp) redirect('products.php');
    $price = $bp['sale_price'] ? (float)$bp['sale_price'] : (float)$bp['price'];
    $items = [['product_id'=>$bp['id'],'name'=>$bp['name'],'thumbnail'=>$bp['thumbnail'],'price'=>$price,'quantity'=>$buy_now_qty,'stock'=>$bp['stock'],'unit'=>$bp['unit'],'subtotal'=>$price*$buy_now_qty]];
} else {
    $stmt = db()->prepare("
        SELECT ci.id AS cart_item_id, ci.quantity, ci.price,
               p.id AS product_id, p.name, p.thumbnail, p.stock, p.unit,
               (ci.price * ci.quantity) AS subtotal
        FROM cart_items ci
        JOIN carts c ON c.id = ci.cart_id
        JOIN products p ON p.id = ci.product_id
        WHERE c.user_id = ?
        ORDER BY ci.created_at DESC
    ");
    $stmt->execute([$uid]);
    $items = $stmt->fetchAll();
}

if (empty($items)) redirect('cart.php');

$free_min  = (float)setting('free_shipping_min', 200000);
$ship_cost = (float)setting('shipping_cost', 15000);
$subtotal  = array_sum(array_column($items, 'subtotal'));
$shipping  = $subtotal >= $free_min ? 0 : $ship_cost;
$total     = $subtotal + $shipping;

// Saved addresses
$addresses = db()->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addresses->execute([$uid]);
$addresses = $addresses->fetchAll();

// Handle form submit
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['place_order'])) {
    if (!verify_csrf()) { $errors[] = 'Token tidak valid.'; }
    else {
        $ship_name     = trim($_POST['ship_name'] ?? '');
        $ship_phone    = trim($_POST['ship_phone'] ?? '');
        $ship_province = trim($_POST['ship_province'] ?? '');
        $ship_city     = trim($_POST['ship_city'] ?? '');
        $ship_district = trim($_POST['ship_district'] ?? '');
        $ship_postal   = trim($_POST['ship_postal'] ?? '');
        $ship_address  = trim($_POST['ship_address'] ?? '');
        $notes         = trim($_POST['notes'] ?? '');
        $save_address  = !empty($_POST['save_address']);

        if (!$ship_name)     $errors[] = 'Nama penerima wajib diisi.';
        if (!$ship_phone)    $errors[] = 'Nomor telepon wajib diisi.';
        if (!$ship_province) $errors[] = 'Provinsi wajib diisi.';
        if (!$ship_city)     $errors[] = 'Kota/Kabupaten wajib diisi.';
        if (!$ship_district) $errors[] = 'Kecamatan wajib diisi.';
        if (!$ship_postal)   $errors[] = 'Kode pos wajib diisi.';
        if (!$ship_address)  $errors[] = 'Alamat lengkap wajib diisi.';

        // Validate stock again
        foreach ($items as $item) {
            if ((int)$item['quantity'] > (int)$item['stock']) {
                $errors[] = 'Stok "' . $item['name'] . '" tidak cukup.';
            }
        }

        if (empty($errors)) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $order_number = generate_order_number();
                $pdo->prepare("
                    INSERT INTO orders (user_id,order_number,subtotal,shipping_cost,total,
                      ship_name,ship_phone,ship_province,ship_city,ship_district,ship_postal,ship_address,notes)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                ")->execute([
                    $uid, $order_number, $subtotal, $shipping, $total,
                    $ship_name,$ship_phone,$ship_province,$ship_city,$ship_district,$ship_postal,$ship_address,$notes
                ]);
                $order_id = (int)$pdo->lastInsertId();

                // Insert order items & reduce stock
                foreach ($items as $item) {
                    $pdo->prepare("
                        INSERT INTO order_items (order_id,product_id,product_name,product_image,price,quantity,subtotal)
                        VALUES (?,?,?,?,?,?,?)
                    ")->execute([
                        $order_id, $item['product_id'], $item['name'],
                        $item['thumbnail'] ?? '', $item['price'],
                        $item['quantity'], $item['subtotal']
                    ]);
                    $pdo->prepare("UPDATE products SET stock=stock-?, sold_count=sold_count+? WHERE id=?")
                         ->execute([$item['quantity'], $item['quantity'], $item['product_id']]);
                }

                // Create payment record
                $pdo->prepare("INSERT INTO payments (order_id, gross_amount) VALUES (?,?)")->execute([$order_id, $total]);

                // Clear cart (if not buy_now)
                if (!$buy_now_id) {
                    $cart_s = $pdo->prepare("SELECT id FROM carts WHERE user_id=?");
                    $cart_s->execute([$uid]);
                    $cid = $cart_s->fetchColumn();
                    if ($cid) $pdo->prepare("DELETE FROM cart_items WHERE cart_id=?")->execute([$cid]);
                }

                // Save address if requested
                if ($save_address) {
                    $pdo->prepare("UPDATE addresses SET is_default=0 WHERE user_id=?")->execute([$uid]);
                    $pdo->prepare("
                        INSERT INTO addresses (user_id,recipient,phone,province,city,district,postal_code,address,is_default)
                        VALUES (?,?,?,?,?,?,?,?,1)
                    ")->execute([$uid,$ship_name,$ship_phone,$ship_province,$ship_city,$ship_district,$ship_postal,$ship_address]);
                }

                $pdo->commit();
                redirect("payment.php?order_id=$order_id");

            } catch(Exception $e) {
                $pdo->rollBack();
                error_log($e->getMessage());
                $errors[] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

$page_title = 'Checkout';
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:20px 0 60px">
  <div class="container">

    <div class="breadcrumb">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <a href="cart.php">Keranjang</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current">Checkout</span>
    </div>

    <!-- Steps -->
    <div style="display:flex;align-items:center;gap:8px;margin:16px 0 24px;overflow-x:auto;padding-bottom:4px">
      <?php
      $steps = ['Keranjang','Checkout','Pembayaran','Selesai'];
      foreach($steps as $i => $step):
        $active = $i === 1;
        $done   = $i < 1;
      ?>
      <div style="display:flex;align-items:center;gap:8px;white-space:nowrap">
        <div style="width:28px;height:28px;border-radius:50%;background:<?= $done?'var(--green-600)':($active?'var(--green-600)':'var(--gray-200)') ?>;color:<?= ($done||$active)?'#fff':'var(--gray-500)' ?>;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;flex-shrink:0">
          <?= $done ? '<i class="fas fa-check" style="font-size:.7rem"></i>' : ($i+1) ?>
        </div>
        <span style="font-size:.82rem;font-weight:<?= $active?'800':'600' ?>;color:<?= $active?'var(--green-700)':($done?'var(--green-600)':'var(--gray-400)') ?>"><?= $step ?></span>
        <?php if($i < count($steps)-1): ?><i class="fas fa-chevron-right" style="font-size:.65rem;color:var(--gray-300)"></i><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if($errors): ?>
    <div class="alert alert-danger mb-16">
      <i class="fas fa-exclamation-circle"></i>
      <ul style="padding-left:14px;margin:0">
        <?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
      <?= csrf_field() ?>
      <input type="hidden" name="place_order" value="1">
      <?php if($buy_now_id): ?>
      <input type="hidden" name="buy_now_id" value="<?= $buy_now_id ?>">
      <input type="hidden" name="buy_now_qty" value="<?= $buy_now_qty ?>">
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr;gap:20px" id="checkoutLayout">

        <!-- Left: Address & Notes -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Saved addresses -->
          <?php if($addresses): ?>
          <div class="card">
            <div class="card-header">
              <span class="card-title"><i class="fas fa-map-marker-alt"></i> Pilih Alamat Tersimpan</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px">
              <?php foreach($addresses as $addr): ?>
              <label style="display:flex;gap:10px;padding:12px;border:1.5px solid var(--border);border-radius:var(--radius-md);cursor:pointer;transition:var(--transition)"
                     onclick="fillAddress(this)"
                     data-name="<?= e($addr['recipient']) ?>"
                     data-phone="<?= e($addr['phone']) ?>"
                     data-province="<?= e($addr['province']) ?>"
                     data-city="<?= e($addr['city']) ?>"
                     data-district="<?= e($addr['district']) ?>"
                     data-postal="<?= e($addr['postal_code']) ?>"
                     data-address="<?= e($addr['address']) ?>">
                <input type="radio" name="address_id" value="<?= $addr['id'] ?>"
                       <?= $addr['is_default']?'checked':'' ?> style="margin-top:3px;accent-color:var(--green-600)">
                <div>
                  <div style="font-weight:800;font-size:.88rem;color:var(--gray-800)"><?= e($addr['recipient']) ?> <span class="badge badge-secondary"><?= e($addr['label']) ?></span></div>
                  <div style="font-size:.78rem;color:var(--text-muted);margin-top:3px"><?= e($addr['phone']) ?></div>
                  <div style="font-size:.78rem;color:var(--text-muted)"><?= e($addr['address']) ?>, <?= e($addr['district']) ?>, <?= e($addr['city']) ?>, <?= e($addr['province']) ?> <?= e($addr['postal_code']) ?></div>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Shipping Address Form -->
          <div class="card">
            <div class="card-header">
              <span class="card-title"><i class="fas fa-home"></i> Alamat Pengiriman</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
              <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Nama Penerima <span class="required">*</span></label>
                <input type="text" name="ship_name" id="ship_name" class="form-control"
                       value="<?= e($_POST['ship_name'] ?? $user['name'] ?? '') ?>"
                       placeholder="Nama lengkap penerima" required>
              </div>
              <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Nomor Telepon <span class="required">*</span></label>
                <div class="input-group">
                  <i class="input-icon fas fa-phone"></i>
                  <input type="tel" name="ship_phone" id="ship_phone" class="form-control"
                         value="<?= e($_POST['ship_phone'] ?? $user['phone'] ?? '') ?>"
                         placeholder="08xxxxxxxxxx" required>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Provinsi <span class="required">*</span></label>
                <input type="text" name="ship_province" id="ship_province" class="form-control"
                       value="<?= e($_POST['ship_province'] ?? '') ?>" placeholder="Jawa Barat" required>
              </div>
              <div class="form-group">
                <label class="form-label">Kota/Kabupaten <span class="required">*</span></label>
                <input type="text" name="ship_city" id="ship_city" class="form-control"
                       value="<?= e($_POST['ship_city'] ?? '') ?>" placeholder="Bandung" required>
              </div>
              <div class="form-group">
                <label class="form-label">Kecamatan <span class="required">*</span></label>
                <input type="text" name="ship_district" id="ship_district" class="form-control"
                       value="<?= e($_POST['ship_district'] ?? '') ?>" placeholder="Coblong" required>
              </div>
              <div class="form-group">
                <label class="form-label">Kode Pos <span class="required">*</span></label>
                <input type="text" name="ship_postal" id="ship_postal" class="form-control"
                       value="<?= e($_POST['ship_postal'] ?? '') ?>" placeholder="40135" required maxlength="10">
              </div>
              <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Alamat Lengkap <span class="required">*</span></label>
                <textarea name="ship_address" id="ship_address" class="form-control" rows="3"
                          placeholder="Nama jalan, nomor rumah, RT/RW, detail lainnya"
                          required><?= e($_POST['ship_address'] ?? '') ?></textarea>
              </div>
              <div style="grid-column:1/-1">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.85rem;color:var(--text-body)">
                  <input type="checkbox" name="save_address" value="1" style="accent-color:var(--green-600);width:16px;height:16px">
                  Simpan sebagai alamat default saya
                </label>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="card">
            <div class="card-header">
              <span class="card-title"><i class="fas fa-sticky-note"></i> Catatan Pesanan</span>
              <span style="font-size:.78rem;color:var(--text-muted)">Opsional</span>
            </div>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="Instruksi khusus untuk penjual atau kurir (opsional)"><?= e($_POST['notes'] ?? '') ?></textarea>
          </div>

        </div>

        <!-- Right: Order Summary -->
        <div>
          <div class="order-summary" style="position:sticky;top:100px">
            <h3 style="font-size:1rem;font-weight:800;color:var(--gray-900);margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border)">
              <i class="fas fa-receipt" style="color:var(--green-600)"></i> Ringkasan Pesanan
            </h3>

            <!-- Items -->
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:14px;max-height:280px;overflow-y:auto">
              <?php foreach($items as $item): ?>
              <div style="display:flex;gap:10px;align-items:center">
                <img src="<?= product_img($item['thumbnail'] ?? '') ?>" alt="<?= e($item['name']) ?>"
                     style="width:48px;height:48px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);flex-shrink:0">
                <div style="flex:1;min-width:0">
                  <div style="font-size:.82rem;font-weight:700;color:var(--gray-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($item['name']) ?></div>
                  <div style="font-size:.75rem;color:var(--text-muted)"><?= $item['quantity'] ?> × <?= rp($item['price']) ?></div>
                </div>
                <div style="font-size:.85rem;font-weight:800;color:var(--green-700);flex-shrink:0"><?= rp($item['subtotal']) ?></div>
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Totals -->
            <div class="summary-row">
              <span>Subtotal</span>
              <span><?= rp($subtotal) ?></span>
            </div>
            <div class="summary-row">
              <span>Ongkos Kirim</span>
              <span><?= $shipping > 0 ? rp($shipping) : '<span style="color:var(--green-600);font-weight:700">-</span>' ?></span>
            </div>
            <div class="summary-row total">
              <span>Total Bayar</span>
              <span class="val"><?= rp($total) ?></span>
            </div>

            <!-- Payment method note -->
            <div style="margin-top:14px;padding:10px;background:var(--blue-100);border-radius:var(--radius-md);font-size:.78rem;color:var(--blue-500);display:flex;gap:6px;align-items:flex-start">
              <i class="fas fa-info-circle" style="margin-top:1px;flex-shrink:0"></i>
              <span>Pilihan metode pembayaran tersedia di halaman berikutnya (Midtrans, transfer bank, dompet digital, dll.)</span>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:16px">
              <i class="fas fa-lock"></i> Lanjut ke Pembayaran
            </button>
          </div>
        </div>

      </div>
    </form>
  </div>
</main>

<script>
// Responsive layout
function applyLayout() {
  const el = document.getElementById('checkoutLayout');
  if (el) el.style.gridTemplateColumns = window.innerWidth >= 768 ? '1fr 340px' : '1fr';
}
applyLayout(); window.addEventListener('resize', applyLayout);

// Fill address from saved
function fillAddress(el) {
  const fields = { ship_name:'name', ship_phone:'phone', ship_province:'province',
                   ship_city:'city', ship_district:'district',
                   ship_postal:'postal', ship_address:'address' };
  Object.entries(fields).forEach(([field, attr]) => {
    const input = document.getElementById(field);
    if (input) input.value = el.dataset[attr] || '';
  });
  document.querySelectorAll('[onclick="fillAddress(this)"]').forEach(l => l.style.borderColor = 'var(--border)');
  el.style.borderColor = 'var(--green-500)';
}

// Auto fill first default address
const defAddr = document.querySelector('input[name="address_id"]:checked');
if (defAddr) { defAddr.closest('label')?.click(); }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
