<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) redirect('index.php');

$error  = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Token tidak valid. Silakan muat ulang halaman.';
    } else {
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $pass   = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (empty($email) || empty($pass)) {
            $error = 'Email dan password wajib diisi.';
        } else {
            $stmt = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['is_active'] && password_verify($pass, $user['password'])) {
                // Login sukses
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];

                // Remember Me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    db()->prepare("UPDATE users SET remember_token = ? WHERE id = ?")
                         ->execute([$token, $user['id']]);
                    setcookie('remember_token', $token, [
                        'expires'  => time() + SESSION_LIFETIME,
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }

                // Update last login
                db()->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")->execute([$user['id']]);

                // Redirect ke halaman asal atau index
                $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                flash('global', 'Selamat datang kembali, ' . $user['name'] . '!', 'success');
                redirect($redirect);

            } elseif ($user && !$user['is_active']) {
                $error = 'Akun Anda telah dinonaktifkan. Hubungi admin.';
            } else {
                $error = 'Email atau password salah.';
            }
        }
    }
}

$page_title = 'Masuk';
require_once __DIR__ . '/includes/header.php';
?>

<main style="min-height:70vh;display:flex;align-items:center;padding:40px 0">
  <div class="container" style="max-width:440px">

    <div class="breadcrumb mb-16">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current">Masuk</span>
    </div>

    <div class="card">
      <div style="text-align:center;margin-bottom:24px">
        <div style="width:56px;height:56px;border-radius:var(--radius-lg);background:var(--green-50);color:var(--green-600);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 14px">
          <i class="fas fa-sign-in-alt"></i>
        </div>
        <h1 style="font-size:1.4rem;font-weight:900;color:var(--gray-900)">Selamat Datang Kembali</h1>
        <p style="font-size:.85rem;color:var(--text-muted);margin-top:6px">Belum punya akun? <a href="register.php" style="color:var(--green-600);font-weight:700">Daftar gratis</a></p>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <?php $flash = get_flash('global'); if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-check-circle"></i> <?= e($flash['message']) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label">Alamat Email <span class="required">*</span></label>
          <div class="input-group">
            <i class="input-icon fas fa-envelope"></i>
            <input type="email" name="email" class="form-control"
                   placeholder="contoh@email.com"
                   value="<?= e($email) ?>" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" style="display:flex;justify-content:space-between">
            <span>Password <span class="required">*</span></span>
            <a href="forgot-password.php" style="font-size:.78rem;color:var(--green-600);font-weight:600">Lupa password?</a>
          </label>
          <div class="input-group">
            <i class="input-icon fas fa-lock"></i>
            <input type="password" name="password" id="password" class="form-control"
                   placeholder="Masukkan password Anda" required>
            <span class="input-suffix" data-toggle-pwd="password">
              <i class="fas fa-eye"></i>
            </span>
          </div>
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
          <input type="checkbox" name="remember" id="remember" value="1"
                 style="width:16px;height:16px;accent-color:var(--green-600)">
          <label for="remember" style="font-size:.85rem;color:var(--text-body);cursor:pointer">
            Ingat saya selama 30 hari
          </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-sign-in-alt"></i> Masuk ke Akun
        </button>
      </form>

      <div style="margin-top:20px;text-align:center;padding-top:16px;border-top:1px solid var(--border)">
        <a href="index.php" style="font-size:.83rem;color:var(--text-muted);display:inline-flex;align-items:center;gap:6px">
          <i class="fas fa-arrow-left"></i> Kembali ke Beranda
        </a>
      </div>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
