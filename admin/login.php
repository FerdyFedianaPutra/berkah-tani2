<?php
require_once __DIR__ . '/../includes/functions.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Token tidak valid.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';

        if ($email && $pass) {
            $s = db()->prepare("SELECT * FROM admins WHERE email = ? LIMIT 1");
            $s->execute([$email]);
            $admin = $s->fetch();

            if ($admin && password_verify($pass, $admin['password'])) {
                // Regenerate session
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];

                // Update last login
                db()->prepare("UPDATE admins SET last_login=NOW() WHERE id=?")->execute([$admin['id']]);

                // Redirect langsung pakai header()
                header('Location: ' . APP_URL . '/admin/index.php');
                exit;
            } else {
                $error = 'Email atau password salah.';
            }
        } else {
            $error = 'Email dan password wajib diisi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin Login – <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css"/>
  <style>
    body { background: linear-gradient(135deg, var(--green-800) 0%, var(--green-900) 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .login-box { background: var(--white); border-radius: 20px; width: 100%; max-width: 420px; padding: 36px; box-shadow: var(--shadow-xl); }
  </style>
</head>
<body>
<div class="login-box">
  <div style="text-align:center;margin-bottom:28px">
    <div style="width:60px;height:60px;border-radius:var(--radius-lg);background:var(--green-600);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 14px">
      <i class="fas fa-seedling"></i>
    </div>
    <h1 style="font-size:1.4rem;font-weight:900;color:var(--gray-900)"><?= APP_NAME ?> Admin</h1>
    <p style="font-size:.83rem;color:var(--text-muted);margin-top:4px">Masuk ke panel administrasi</p>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Email Admin</label>
      <div class="input-group">
        <i class="input-icon fas fa-envelope"></i>
        <input type="email" name="email" class="form-control" placeholder="admin@berkahtani.com" required autofocus>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <div class="input-group">
        <i class="input-icon fas fa-lock"></i>
        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
        <span class="input-suffix" data-toggle-pwd="password"><i class="fas fa-eye"></i></span>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:8px">
      <i class="fas fa-sign-in-alt"></i> Masuk ke Admin
    </button>
  </form>

  <div style="margin-top:16px;text-align:center">
    <a href="<?= APP_URL ?>" style="font-size:.82rem;color:var(--text-muted)"><i class="fas fa-arrow-left"></i> Kembali ke Toko</a>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
