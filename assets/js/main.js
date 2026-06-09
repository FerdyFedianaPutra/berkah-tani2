/* ============================================================
   BERKAH TANI E-COMMERCE – MAIN JS
   ============================================================ */

'use strict';

// ── Toast Notifications ──────────────────────────────────────
const Toast = {
  show(message, type = 'success', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const toast = document.createElement('div');
    toast.className = `toast ${type !== 'success' ? type : ''}`;
    toast.innerHTML = `<i class="fas ${icons[type] || icons.success}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = '.3s ease'; setTimeout(() => toast.remove(), 300); }, duration);
  }
};

// ── Hero Slider ──────────────────────────────────────────────
function initSlider() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots   = document.querySelectorAll('.slider-dot');
  if (!slides.length) return;

  let current = 0, timer;

  function go(n) {
    slides[current].classList.remove('active');
    dots[current]?.classList.remove('active');
    current = (n + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current]?.classList.add('active');
  }

  function start() { timer = setInterval(() => go(current + 1), 5000); }
  function stop()  { clearInterval(timer); }

  document.querySelector('.slider-next')?.addEventListener('click', () => { stop(); go(current + 1); start(); });
  document.querySelector('.slider-prev')?.addEventListener('click', () => { stop(); go(current - 1); start(); });
  dots.forEach((dot, i) => dot.addEventListener('click', () => { stop(); go(i); start(); }));

  go(0); start();
}

// ── Mobile Menu ──────────────────────────────────────────────
function initMobileMenu() {
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  const menuClose  = document.getElementById('menuClose');
  if (!hamburger || !mobileMenu) return;

  hamburger.addEventListener('click', () => mobileMenu.classList.add('open'));
  menuClose?.addEventListener('click', () => mobileMenu.classList.remove('open'));
  mobileMenu.addEventListener('click', (e) => { if (e.target === mobileMenu) mobileMenu.classList.remove('open'); });
}

// ── Cart AJAX ────────────────────────────────────────────────
const Cart = {
  async add(productId, quantity = 1) {
    try {
      const res = await fetch('cart-action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=add&product_id=${productId}&quantity=${quantity}&csrf_token=${window.CSRF_TOKEN}`
      });
      const data = await res.json();
      if (data.success) {
        this.updateBadge(data.cart_count);
        Toast.show(data.message || 'Produk ditambahkan ke keranjang!');
      } else {
        Toast.show(data.message || 'Gagal menambahkan produk.', 'error');
      }
      return data;
    } catch (e) {
      Toast.show('Terjadi kesalahan. Coba lagi.', 'error');
    }
  },

  async update(cartItemId, quantity) {
    const res = await fetch('cart-action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: `action=update&cart_item_id=${cartItemId}&quantity=${quantity}&csrf_token=${window.CSRF_TOKEN}`
    });
    return res.json();
  },

  async remove(cartItemId) {
    const res = await fetch('cart-action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: `action=remove&cart_item_id=${cartItemId}&csrf_token=${window.CSRF_TOKEN}`
    });
    return res.json();
  },

  updateBadge(count) {
    document.querySelectorAll('.cart-badge').forEach(el => {
      el.textContent = count;
      el.classList.toggle('hidden', count === 0);
    });
  }
};

// ── Quantity Controls ────────────────────────────────────────
function initQtyControls() {
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-qty-action]');
    if (!btn) return;

    const action     = btn.dataset.qtyAction;
    const wrap       = btn.closest('[data-cart-item]');
    const itemId     = wrap?.dataset.cartItem;
    const valEl      = wrap?.querySelector('.qty-val');
    const currentVal = parseInt(valEl?.textContent || 1);
    const max        = parseInt(wrap?.dataset.stock || 999);

    let newVal = action === 'inc' ? Math.min(currentVal + 1, max) : Math.max(1, currentVal - 1);
    if (newVal === currentVal) return;

    if (itemId) {
      const data = await Cart.update(itemId, newVal);
      if (data.success) {
        valEl.textContent = newVal;
        // Update subtotals if present
        const priceEl = wrap.querySelector('[data-unit-price]');
        if (priceEl) {
          const unitPrice = parseInt(priceEl.dataset.unitPrice);
          const subEl = wrap.querySelector('.cart-subtotal');
          if (subEl) subEl.textContent = 'Rp ' + (unitPrice * newVal).toLocaleString('id-ID');
        }
        updateCartSummary(data.summary);
      }
    } else {
      if (valEl) valEl.textContent = newVal;
      // Update hidden qty input
      const qtyInput = wrap?.querySelector('input[name="quantity"]');
      if (qtyInput) qtyInput.value = newVal;
    }
  });
}

// ── Cart Item Remove ─────────────────────────────────────────
function initCartRemove() {
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-remove-item]');
    if (!btn) return;
    const itemId = btn.dataset.removeItem;
    const wrap   = document.querySelector(`[data-cart-item="${itemId}"]`);
    if (!confirm('Hapus produk dari keranjang?')) return;

    const data = await Cart.remove(itemId);
    if (data.success) {
      wrap?.remove();
      Cart.updateBadge(data.cart_count);
      updateCartSummary(data.summary);
      if (data.cart_count === 0) {
        document.getElementById('cartItems')?.closest('.card')?.replaceWith(
          Object.assign(document.createElement('div'), { className: 'empty-state', innerHTML: '<i class="fas fa-shopping-cart empty-icon"></i><p class="empty-title">Keranjang kosong</p><a href="products.php" class="btn btn-primary mt-12">Mulai Belanja</a>' })
        );
      }
    }
  });
}

function updateCartSummary(summary) {
  if (!summary) return;
  const el = (id) => document.getElementById(id);
  if (el('summarySubtotal')) el('summarySubtotal').textContent = 'Rp ' + parseInt(summary.subtotal).toLocaleString('id-ID');
  if (el('summaryShipping')) el('summaryShipping').textContent = summary.shipping_cost > 0 ? 'Rp ' + parseInt(summary.shipping_cost).toLocaleString('id-ID') : 'Gratis';
  if (el('summaryTotal'))    el('summaryTotal').textContent    = 'Rp ' + parseInt(summary.total).toLocaleString('id-ID');
}

// ── Add to Cart Buttons ──────────────────────────────────────
function initAddToCart() {
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-add-cart]');
    if (!btn) return;

    if (!window.USER_LOGGED_IN) {
      window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
      return;
    }

    const productId = btn.dataset.addCart;
    const qtyEl     = document.querySelector(`[data-product-qty="${productId}"]`);
    const qty       = qtyEl ? parseInt(qtyEl.textContent) : 1;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menambahkan…';
    const data = await Cart.add(productId, qty);
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-cart-plus"></i> Tambah ke Keranjang';
  });
}

// ── Search ───────────────────────────────────────────────────
function initSearch() {
  const form = document.getElementById('searchForm');
  form?.addEventListener('submit', (e) => {
    const q = form.querySelector('input')?.value.trim();
    if (!q) { e.preventDefault(); return; }
    // Allow normal submit
  });
}

// ── Back to Top ──────────────────────────────────────────────
function initBackToTop() {
  const btn = document.getElementById('backToTop');
  if (!btn) return;
  window.addEventListener('scroll', () => btn.classList.toggle('hidden', window.scrollY < 300));
  btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

// ── Image Gallery ────────────────────────────────────────────
function initProductGallery() {
  const thumbs = document.querySelectorAll('.gallery-thumb');
  const mainImg = document.getElementById('mainImg');
  if (!thumbs.length || !mainImg) return;

  thumbs.forEach(thumb => {
    thumb.addEventListener('click', () => {
      mainImg.src = thumb.dataset.large || thumb.src;
      thumbs.forEach(t => t.classList.remove('active'));
      thumb.classList.add('active');
    });
  });
}

// ── Password Toggle ──────────────────────────────────────────
function initPasswordToggle() {
  document.querySelectorAll('[data-toggle-pwd]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePwd);
      if (!input) return;
      const isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';
      btn.querySelector('i')?.classList.toggle('fa-eye', !isPass);
      btn.querySelector('i')?.classList.toggle('fa-eye-slash', isPass);
    });
  });
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initSlider();
  initMobileMenu();
  initQtyControls();
  initCartRemove();
  initAddToCart();
  initSearch();
  initBackToTop();
  initProductGallery();
  initPasswordToggle();
});
