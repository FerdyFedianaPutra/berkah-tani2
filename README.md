# 🌾 Berkah Tani E-Commerce

Platform e-commerce modern untuk penjualan beras dan produk pertanian.

---

## ⚡ Instalasi Cepat

### 1. Prasyarat
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx dengan mod_rewrite aktif
- Ekstensi PHP: pdo_mysql, curl, fileinfo, gd

### 2. Setup Database
```sql
mysql -u root -p < database.sql
```
Atau import file `database.sql` via phpMyAdmin.

**Akun Admin Default:**
- Email: `admin@berkahtani.com`
- Password: `password` *(ganti segera setelah login!)*

### 3. Konfigurasi

Edit file `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'berkah_tani');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('APP_URL', 'http://localhost/berkah-tani');

// Midtrans (isi dari dashboard Midtrans)
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-xxxxxxxx');
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-xxxxxxxx');
```

Atau gunakan environment variables:
```bash
DB_HOST=localhost
DB_NAME=berkah_tani
DB_USER=root
DB_PASS=secret
APP_URL=https://berkahtani.com
MIDTRANS_SERVER_KEY=Mid-server-xxxx
MIDTRANS_CLIENT_KEY=Mid-client-xxxx
```

### 4. Upload ke Server

```bash
# Upload semua file ke folder web
# Pastikan folder uploads/ bisa ditulis
chmod -R 755 uploads/
```

### 5. Midtrans Setup

1. Daftar di [https://midtrans.com](https://midtrans.com)
2. Masuk ke Sandbox Dashboard
3. Ambil Server Key & Client Key
4. Set Notification URL: `https://domain-anda.com/berkah-tani/payment-callback.php`
5. Masukkan key di Admin Panel → Pengaturan

---

## 📁 Struktur Direktori

```
berkah-tani/
├── admin/                  # Panel Admin
│   ├── includes/           # Header, Footer, Auth admin
│   ├── pages/              # Halaman admin (produk, pesanan, dll)
│   ├── index.php           # Dashboard admin
│   ├── login.php           # Login admin
│   └── logout.php
├── assets/
│   ├── css/
│   │   ├── main.css        # CSS utama (frontend)
│   │   └── admin.css       # CSS admin panel
│   └── js/
│       └── main.js         # JavaScript utama
├── config/
│   └── database.php        # Konfigurasi DB & konstanta
├── includes/
│   ├── functions.php       # Helper functions
│   ├── header.php          # Header frontend
│   └── footer.php          # Footer frontend
├── uploads/                # File upload (produk, banner, dll)
│   ├── products/
│   ├── banners/
│   └── avatars/
├── index.php               # Halaman utama / Homepage
├── products.php            # Daftar produk
├── product.php             # Detail produk
├── cart.php                # Keranjang belanja
├── cart-action.php         # AJAX cart handler
├── checkout.php            # Halaman checkout
├── payment.php             # Halaman pembayaran (Midtrans)
├── payment-callback.php    # Webhook Midtrans
├── payment-finish.php      # Redirect setelah bayar
├── orders.php              # Riwayat pesanan user
├── order-detail.php        # Detail pesanan user
├── profile.php             # Profil pengguna
├── login.php               # Login
├── register.php            # Register
├── logout.php              # Logout
├── database.sql            # Schema database lengkap
└── .htaccess               # Apache config
```

---

## 🔐 Keamanan

- Password di-hash dengan `password_hash()` + BCRYPT (cost 12)
- CSRF token pada semua form POST
- PDO prepared statements (SQL injection protection)
- Session regenerate ID setelah login
- HTTPOnly cookies
- Input sanitization dengan `e()` / `htmlspecialchars()`
- Direktori `includes/` dan `config/` dilindungi `.htaccess`

---

## 🎨 Kustomisasi Warna

Edit `assets/css/main.css`:
```css
:root {
  --green-600: #3e7b00;  /* Warna utama */
  --green-700: #2d5a00;  /* Warna hover */
  --green-400: #6dc200;  /* Aksen */
}
```

---

## 📞 Support

- WhatsApp: Sesuai pengaturan di Admin Panel → Pengaturan
- Email: Sesuai pengaturan di Admin Panel → Pengaturan
