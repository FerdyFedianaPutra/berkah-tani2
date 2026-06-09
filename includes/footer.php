<?php // includes/footer.php ?>

<!-- ===== FOOTER ===== -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">

      <!-- Brand -->
      <div class="footer-brand">
        <div class="nav-logo" style="margin-bottom:14px">
          <div class="logo-icon"><i class="fas fa-seedling"></i></div>
          <div class="logo-text">
            <span class="logo-name" style="color:#fff;font-size:1.2rem"><?= APP_NAME ?></span>
            <span class="logo-sub" style="color:var(--green-300)">Beras Berkualitas</span>
          </div>
        </div>
        <p class="tagline">Menyediakan beras dan produk pertanian berkualitas tinggi langsung dari petani mitra terpercaya kami.</p>
        <div class="footer-social">
          <!-- <a href="#" class="social-btn" title="Facebook"><i class="fab fa-facebook-f"></i></a> -->
          <!-- <a href="#" class="social-btn" title="Instagram"><i class="fab fa-instagram"></i></a> -->
          <a href="https://wa.me/<?= setting('site_phone') ?>" class="social-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
          <!-- <a href="#" class="social-btn" title="YouTube"><i class="fab fa-youtube"></i></a> -->
        </div>
      </div>

      <!-- Links -->
      <div>
        <p class="footer-title">Belanja</p>
        <div class="footer-links">
          <a href="products.php">Semua Produk</a>
          <?php
          $footer_cats = db()->query("SELECT name,slug FROM categories WHERE is_active=1 ORDER BY sort_order LIMIT 5")->fetchAll();
          foreach($footer_cats as $cat):
          ?>
          <a href="products.php?cat=<?= e($cat['slug']) ?>"><?= e($cat['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Akun -->
      <div>
        <p class="footer-title">Akun Saya</p>
        <div class="footer-links">
          <a href="profile.php">Profil Saya</a>
          <a href="orders.php">Riwayat Pesanan</a>
          <a href="cart.php">Keranjang Belanja</a>
          <?php if(!is_logged_in()): ?>
          <a href="login.php">Masuk</a>
          <a href="register.php">Daftar Akun</a>
          <?php else: ?>
          <a href="logout.php">Keluar</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Kontak -->
      <div>
        <p class="footer-title">Hubungi Kami</p>
        <div class="footer-contact">
          <div class="footer-contact-item">
            <i class="fas fa-map-marker-alt"></i>
            <span><?= e(setting('site_address','Jl. Sawah Indah No. 1, Jawa Barat')) ?></span>
          </div>
          <div class="footer-contact-item">
            <i class="fas fa-phone-alt"></i>
            <span>+<?= setting('site_phone') ?></span>
          </div>
          <div class="footer-contact-item">
            <i class="fas fa-envelope"></i>
            <span><?= setting('site_email') ?></span>
          </div>
          <div class="footer-contact-item">
            <i class="fas fa-clock"></i>
            <span>Senin–Sabtu, 07.00–17.00 WIB</span>
          </div>
        </div>
      </div>

    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Semua hak dilindungi.</span>
      <div style="display:flex;gap:14px">
        <a href="#">Kebijakan Privasi</a>
        <a href="#">Syarat & Ketentuan</a>
      </div>
    </div>
  </div>
</footer>

<!-- Floating Buttons -->
<div class="float-btns">
  <button class="float-btn float-top hidden" id="backToTop" title="Kembali ke atas">
    <i class="fas fa-chevron-up"></i>
  </button>
  <a href="https://wa.me/<?= setting('site_phone') ?>?text=Halo+Berkah+Tani,+saya+ingin+bertanya+tentang+produk+Anda."
     class="float-btn float-wa" target="_blank" title="Chat WhatsApp">
    <i class="fab fa-whatsapp"></i>
  </a>
</div>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
