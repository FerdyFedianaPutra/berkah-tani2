<?php
// ============================================================
//  BERKAH TANI – Helper Functions
// ============================================================

require_once __DIR__ . '/../config/database.php';

// ── Session Init ─────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ── Remember Me ─────────────────────────────────────────────
function check_remember_me(): void {
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt  = db()->prepare("SELECT * FROM users WHERE remember_token = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$token]);
        $user  = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email']= $user['email'];
        }
    }
}
check_remember_me();

// ── Auth Helpers ─────────────────────────────────────────────
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = current_url();
        redirect('login.php');
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare("SELECT id,name,email,phone,avatar FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

// ── Redirect ─────────────────────────────────────────────────
function redirect(string $url): void {
    // If relative, prepend APP_URL
    if (!str_starts_with($url, 'http')) {
        $url = APP_URL . '/' . ltrim($url, '/');
    }
    header('Location: ' . $url);
    exit;
}

function current_url(): string {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $proto . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// ── Formatting ───────────────────────────────────────────────
function rp(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function slug(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function truncate(string $text, int $limit = 80): string {
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit) . '…';
}

function time_ago(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0)  return $diff->y . ' tahun lalu';
    if ($diff->m > 0)  return $diff->m . ' bulan lalu';
    if ($diff->d > 0)  return $diff->d . ' hari lalu';
    if ($diff->h > 0)  return $diff->h . ' jam lalu';
    if ($diff->i > 0)  return $diff->i . ' menit lalu';
    return 'Baru saja';
}

function order_status_label(string $status): string {
    return match($status) {
        'pending'    => 'Menunggu Pembayaran',
        'paid'       => 'Dibayar',
        'processing' => 'Diproses',
        'shipped'    => 'Dikirim',
        'completed'  => 'Selesai',
        'cancelled'  => 'Dibatalkan',
        default      => ucfirst($status),
    };
}

function order_status_class(string $status): string {
    return match($status) {
        'pending'    => 'badge-warning',
        'paid'       => 'badge-info',
        'processing' => 'badge-primary',
        'shipped'    => 'badge-info',
        'completed'  => 'badge-success',
        'cancelled'  => 'badge-danger',
        default      => 'badge-secondary',
    };
}

// ── Order Number Generator ───────────────────────────────────
function generate_order_number(): string {
    return 'BT-' . strtoupper(date('Ymd')) . '-' . strtoupper(substr(uniqid(), -6));
}

// ── CSRF ─────────────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), $token);
}

// ── Flash Messages ───────────────────────────────────────────
function flash(string $key, string $message, string $type = 'success'): void {
    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function get_flash(string $key): ?array {
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

// ── Cart Count ───────────────────────────────────────────────
function cart_count(): int {
    if (!is_logged_in()) return 0;
    $stmt = db()->prepare("
        SELECT COALESCE(SUM(ci.quantity),0)
        FROM carts c
        JOIN cart_items ci ON ci.cart_id = c.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return (int)$stmt->fetchColumn();
}

// ── Product Image URL ────────────────────────────────────────
function product_img(string $img = '', string $size = 'medium'): string {
    if ($img && file_exists(UPLOAD_DIR . 'products/' . $img)) {
        return UPLOAD_URL . 'products/' . $img;
    }
    // Fallback placeholder
    $w = match($size) { 'thumb' => 200, 'large' => 800, default => 400 };
    return "https://placehold.co/{$w}x{$w}/e8f5e9/2D5A00?text=Berkah+Tani";
}

function banner_img(string $img = ''): string {
    if ($img && file_exists(UPLOAD_DIR . 'banners/' . $img)) {
        return UPLOAD_URL . 'banners/' . $img;
    }
    return $img; // allow external URL
}

// ── Upload File ──────────────────────────────────────────────
function upload_file(array $file, string $dir, array $allowed = ['jpg','jpeg','png','webp']): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return false;
    if ($file['size'] > 5 * 1024 * 1024) return false; // 5MB max

    $name = uniqid() . '_' . time() . '.' . $ext;
    $dest = UPLOAD_DIR . $dir . '/' . $name;
    if (!is_dir(UPLOAD_DIR . $dir)) mkdir(UPLOAD_DIR . $dir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;
    return $name;
}

// ── Settings ─────────────────────────────────────────────────
function setting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $rows = db()->query("SELECT `key`, `value` FROM settings")->fetchAll();
        $settings = array_column($rows, 'value', 'key');
    }
    return $settings[$key] ?? $default;
}

// ── Pagination ───────────────────────────────────────────────
function paginate(int $total, int $per_page, int $current_page, string $url_pattern = '?page=%d'): array {
    $total_pages = max(1, (int)ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    return compact('total', 'per_page', 'current_page', 'total_pages', 'offset');
}

function pagination_html(array $p, string $url_pattern = '?page=%d'): string {
    if ($p['total_pages'] <= 1) return '';
    $html = '<nav class="pagination-nav"><ul class="pagination">';
    
    // Prev
    if ($p['current_page'] > 1) {
        $html .= '<li><a href="' . sprintf($url_pattern, $p['current_page']-1) . '" class="page-btn"><i class="fas fa-chevron-left"></i></a></li>';
    }
    
    // Pages
    $start = max(1, $p['current_page'] - 2);
    $end   = min($p['total_pages'], $p['current_page'] + 2);
    if ($start > 1) $html .= '<li><a href="' . sprintf($url_pattern, 1) . '" class="page-btn">1</a></li>' . ($start > 2 ? '<li class="dots">…</li>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $p['current_page'] ? ' active' : '';
        $html .= '<li><a href="' . sprintf($url_pattern, $i) . '" class="page-btn' . $active . '">' . $i . '</a></li>';
    }
    if ($end < $p['total_pages']) {
        $html .= ($end < $p['total_pages'] - 1 ? '<li class="dots">…</li>' : '');
        $html .= '<li><a href="' . sprintf($url_pattern, $p['total_pages']) . '" class="page-btn">' . $p['total_pages'] . '</a></li>';
    }

    // Next
    if ($p['current_page'] < $p['total_pages']) {
        $html .= '<li><a href="' . sprintf($url_pattern, $p['current_page']+1) . '" class="page-btn"><i class="fas fa-chevron-right"></i></a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}
