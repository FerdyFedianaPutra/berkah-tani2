-- ============================================================
--  BERKAH TANI E-COMMERCE – DATABASE SCHEMA
--  MySQL 5.7+ / MariaDB 10.3+
--  Dibuat: 2025
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `berkah_tani`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `berkah_tani`;

-- ============================================================
-- 1. ADMINS
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `avatar`     VARCHAR(255) DEFAULT NULL,
  `role`       ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: password = BerkahTani2025!
INSERT INTO `admins` (`name`, `email`, `password`, `role`) VALUES
('Super Admin', 'admin@berkahtani.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin');

-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(100) NOT NULL,
  `email`           VARCHAR(150) NOT NULL UNIQUE,
  `password`        VARCHAR(255) NOT NULL,
  `phone`           VARCHAR(20) DEFAULT NULL,
  `avatar`          VARCHAR(255) DEFAULT NULL,
  `email_verified`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `remember_token`  VARCHAR(100) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ADDRESSES
-- ============================================================
CREATE TABLE IF NOT EXISTS `addresses` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `label`        VARCHAR(50) NOT NULL DEFAULT 'Rumah',
  `recipient`    VARCHAR(100) NOT NULL,
  `phone`        VARCHAR(20) NOT NULL,
  `province`     VARCHAR(100) NOT NULL,
  `city`         VARCHAR(100) NOT NULL,
  `district`     VARCHAR(100) NOT NULL,
  `postal_code`  VARCHAR(10) NOT NULL,
  `address`      TEXT NOT NULL,
  `is_default`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_address_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. CATEGORIES
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `image`       VARCHAR(255) DEFAULT NULL,
  `icon`        VARCHAR(50) DEFAULT 'fa-leaf',
  `sort_order`  INT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`, `slug`, `description`, `icon`, `sort_order`) VALUES
('Beras Premium',  'beras-premium',  'Beras berkualitas premium hasil penggilingan terbaik', 'fa-bowl-rice', 1),
('Beras Medium',   'beras-medium',   'Beras medium untuk kebutuhan sehari-hari',              'fa-bowl-food', 2),
('Beras Organik',  'beras-organik',  'Beras organik bebas pestisida',                         'fa-seedling',  3),
('Sekam & Dedak',  'sekam-dedak',    'Produk samping penggilingan berkualitas',               'fa-wheat-awn', 4),
('Gabah',          'gabah',          'Gabah pilihan dari petani mitra',                       'fa-plant-wilt',5);

-- ============================================================
-- 5. PRODUCTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`     INT UNSIGNED NOT NULL,
  `name`            VARCHAR(200) NOT NULL,
  `slug`            VARCHAR(220) NOT NULL UNIQUE,
  `description`     TEXT DEFAULT NULL,
  `short_desc`      VARCHAR(300) DEFAULT NULL,
  `price`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sale_price`      DECIMAL(12,2) DEFAULT NULL,
  `stock`           INT NOT NULL DEFAULT 0,
  `unit`            VARCHAR(20) NOT NULL DEFAULT 'kg',
  `weight`          DECIMAL(8,2) NOT NULL DEFAULT 1.00 COMMENT 'berat dalam kg',
  `sku`             VARCHAR(50) UNIQUE DEFAULT NULL,
  `thumbnail`       VARCHAR(255) DEFAULT NULL,
  `sold_count`      INT NOT NULL DEFAULT 0,
  `view_count`      INT NOT NULL DEFAULT 0,
  `rating`          DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `review_count`    INT NOT NULL DEFAULT 0,
  `is_featured`     TINYINT(1) NOT NULL DEFAULT 0,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `meta_title`      VARCHAR(200) DEFAULT NULL,
  `meta_desc`       VARCHAR(300) DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category`   (`category_id`),
  KEY `idx_slug`       (`slug`),
  KEY `idx_featured`   (`is_featured`),
  KEY `idx_active`     (`is_active`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample products
INSERT INTO `products` (`category_id`,`name`,`slug`,`description`,`short_desc`,`price`,`stock`,`unit`,`weight`,`sku`,`is_featured`,`sold_count`) VALUES
(1,'Beras Premium Pandan Wangi 5kg','beras-premium-pandan-wangi-5kg','Beras Pandan Wangi pilihan dari sawah organik terbaik. Pulen, harum, dan lezat untuk keluarga Anda.','Beras pulen harum khas pandan, 5kg',75000,500,'pack',5,'BT-BP-001',1,234),
(1,'Beras Super Premium 10kg','beras-super-premium-10kg','Beras super premium grade A untuk restoran dan hotel. Bulir panjang, putih bersih, minim patah.','Beras grade A untuk profesional, 10kg',140000,300,'pack',10,'BT-BP-002',1,187),
(2,'Beras Medium IR64 5kg','beras-medium-ir64-5kg','Beras IR64 medium berkualitas, cocok untuk konsumsi sehari-hari dengan harga terjangkau.','Beras medium ekonomis, 5kg',52000,800,'pack',5,'BT-BM-001',0,456),
(2,'Beras Medium Cianjur 10kg','beras-medium-cianjur-10kg','Beras Cianjur asli dengan cita rasa khas yang pulen dan lembut.','Beras Cianjur asli, 10kg',98000,400,'pack',10,'BT-BM-002',1,312),
(3,'Beras Organik Merah 1kg','beras-organik-merah-1kg','Beras merah organik tanpa pestisida, kaya serat dan antioksidan untuk hidup sehat.','Beras merah organik, 1kg',28000,200,'pack',1,'BT-BO-001',1,98),
(4,'Dedak Padi Halus 25kg','dedak-padi-halus-25kg','Dedak padi halus berkualitas untuk pakan ternak dan bahan pupuk organik.','Dedak halus 25kg',45000,600,'karung',25,'BT-SD-001',0,167),
(4,'Sekam Padi 50kg','sekam-padi-50kg','Sekam padi kering untuk media tanam, bahan arang, dan kebutuhan pertanian lainnya.','Sekam padi kering 50kg',35000,1000,'karung',50,'BT-SD-002',0,289);

-- ============================================================
-- 6. PRODUCT IMAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image`      VARCHAR(255) NOT NULL,
  `alt_text`   VARCHAR(200) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_pimage_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. BANNERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `banners` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `subtitle`    VARCHAR(300) DEFAULT NULL,
  `image`       VARCHAR(255) NOT NULL,
  `link_url`    VARCHAR(300) DEFAULT NULL,
  `link_text`   VARCHAR(100) DEFAULT 'Lihat Produk',
  `position`    ENUM('hero','mid','bottom') NOT NULL DEFAULT 'hero',
  `sort_order`  INT NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `start_date`  DATE DEFAULT NULL,
  `end_date`    DATE DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `banners` (`title`,`subtitle`,`image`,`link_url`,`link_text`,`position`,`sort_order`) VALUES
('Beras Premium Berkualitas','Langsung dari sawah petani mitra terpercaya kami','https://images.unsplash.com/photo-1586201375761-83865001e31c?w=1400&q=80','products.php','Belanja Sekarang','hero',1),
('Promo Akhir Bulan','Diskon hingga 20% untuk pembelian min. 50kg','https://images.unsplash.com/photo-1574323347407-f5e1ad6d020b?w=1400&q=80','products.php?promo=1','Lihat Promo','hero',2),
('Beras Organik Sehat','Bebas pestisida, kaya nutrisi, baik untuk keluarga','https://images.unsplash.com/photo-1536304993881-ff86e0c9b589?w=1400&q=80','products.php?cat=beras-organik','Belanja Sekarang','hero',3);

-- ============================================================
-- 8. CARTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `carts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `session_id` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. CART ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id`    INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity`   INT NOT NULL DEFAULT 1,
  `price`      DECIMAL(12,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_product` (`cart_id`,`product_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_cartitem_cart`    FOREIGN KEY (`cart_id`)    REFERENCES `carts`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cartitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. ORDERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `order_number`    VARCHAR(30) NOT NULL UNIQUE,
  `status`          ENUM('pending','paid','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  `subtotal`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_cost`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount`        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  -- Shipping address snapshot
  `ship_name`       VARCHAR(100) NOT NULL,
  `ship_phone`      VARCHAR(20)  NOT NULL,
  `ship_province`   VARCHAR(100) NOT NULL,
  `ship_city`       VARCHAR(100) NOT NULL,
  `ship_district`   VARCHAR(100) NOT NULL,
  `ship_postal`     VARCHAR(10)  NOT NULL,
  `ship_address`    TEXT NOT NULL,
  `notes`           TEXT DEFAULT NULL,
  `shipping_method` VARCHAR(100) DEFAULT NULL,
  `tracking_number` VARCHAR(100) DEFAULT NULL,
  `paid_at`         DATETIME DEFAULT NULL,
  `shipped_at`      DATETIME DEFAULT NULL,
  `completed_at`    DATETIME DEFAULT NULL,
  `cancelled_at`    DATETIME DEFAULT NULL,
  `cancel_reason`   TEXT DEFAULT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id`      (`user_id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_status`       (`status`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. ORDER ITEMS
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`      INT UNSIGNED NOT NULL,
  `product_id`    INT UNSIGNED NOT NULL,
  `product_name`  VARCHAR(200) NOT NULL,
  `product_image` VARCHAR(255) DEFAULT NULL,
  `price`         DECIMAL(12,2) NOT NULL,
  `quantity`      INT NOT NULL DEFAULT 1,
  `subtotal`      DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_id`   (`order_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_oitem_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_oitem_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`          INT UNSIGNED NOT NULL UNIQUE,
  `transaction_id`    VARCHAR(100) DEFAULT NULL COMMENT 'Midtrans transaction ID',
  `payment_type`      VARCHAR(50)  DEFAULT NULL COMMENT 'bank_transfer, gopay, credit_card etc',
  `payment_code`      VARCHAR(100) DEFAULT NULL COMMENT 'VA number or payment code',
  `bank`              VARCHAR(30)  DEFAULT NULL,
  `gross_amount`      DECIMAL(12,2) NOT NULL,
  `status`            ENUM('pending','settlement','expire','cancel','deny','failure') NOT NULL DEFAULT 'pending',
  `snap_token`        TEXT DEFAULT NULL,
  `snap_redirect_url` TEXT DEFAULT NULL,
  `raw_response`      LONGTEXT DEFAULT NULL COMMENT 'Full Midtrans response JSON',
  `expired_at`        DATETIME DEFAULT NULL,
  `settled_at`        DATETIME DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id`       (`order_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. PRODUCT REVIEWS
-- ============================================================
CREATE TABLE IF NOT EXISTS `reviews` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `order_id`   INT UNSIGNED NOT NULL,
  `rating`     TINYINT NOT NULL DEFAULT 5,
  `comment`    TEXT DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_review` (`product_id`,`user_id`,`order_id`),
  KEY `idx_product_id` (`product_id`),
  CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_review_order`   FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100) NOT NULL,
  `value`      TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`) VALUES
('site_name',           'Berkah Tani'),
('site_tagline',        'Beras Berkualitas Langsung dari Petani'),
('site_email',          'info@berkahtani.com'),
('site_phone',          '6285601372013'),
('site_address',        'Jl. Sawah Indah No. 1, Jawa Barat'),
('shipping_cost',       '15000'),
('free_shipping_min',   '200000'),
('midtrans_server_key', ''),
('midtrans_client_key', ''),
('midtrans_is_sandbox', '1'),
('currency',            'IDR'),
('tax_rate',            '0');

SET FOREIGN_KEY_CHECKS = 1;
-- END OF SCHEMA
