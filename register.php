<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect jika sudah login
if (is_logged_in()) redirect('index.php');

$errors = [];
$values = ['name'=>'','email'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $errors[] = 'Token tidak valid. Silakan coba lagi.'; }
    else {
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $pass     = $_POST['password'] ?? '';
        $pass2    = $_POST['password_confirm'] ?? '';
        $values   = compact('name','email');

        if (empty($name))               $errors[] = 'Nama lengkap wajib diisi.';
        elseif (mb_strlen($name) < 3)   $errors[] = 'Nama minimal 3 karakter.';
        if (empty($email))              $errors[] = 'Email wajib diisi.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
        if (empty($pass))               $errors[] = 'Password wajib diisi.';
        elseif (strlen($pass) < 8)      $errors[] = 'Password minimal 8 karakter.';
        if ($pass !== $pass2)           $errors[] = 'Konfirmasi password tidak cocok.';

        if (empty($errors)) {
            // Cek email sudah ada
            $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email sudah digunakan. Silakan gunakan email lain atau login.';
            } else {
                $hashed = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt   = db()->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hashed]);
                $uid = db()->lastInsertId();

                // Auto login setelah register
                $_SESSION['user_id']    = $uid;
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;

                flash('global', 'Selamat datang, ' . $name . '! Akun Anda berhasil dibuat.', 'success');
                redirect('index.php');
            }
        }
    }
}

$page_title = 'Daftar Akun';
require_once __DIR__ . '/includes/header.php';
?>

<main style="min-height:70vh;display:flex;align-items:center;padding:40px 0">
  <div class="container" style="max-width:480px">

    <!-- Breadcrumb -->
    <div class="breadcrumb mb-16">
      <a href="index.php">Beranda</a>
      <span class="sep"><i class="fas fa-chevron-right" style="font-size:.65rem"></i></span>
      <span class="current">Daftar Akun</span>
    </div>

    <div class="card">
      <div style="text-align:center;margin-bottom:24px">
        <div style="width:56px;height:56px;border-radius:var(--radius-lg);background:var(--green-50);color:var(--green-600);display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 14px">
          <i class="fas fa-user-plus"></i>
        </div>
        <h1 style="font-size:1.4rem;font-weight:900;color:var(--gray-900)">Buat Akun Baru</h1>
        <p style="font-size:.85rem;color:var(--text-muted);margin-top:6px">Sudah punya akun? <a href="login.php" style="color:var(--green-600);font-weight:700">Masuk di sini</a></p>
      </div>

      <?php if ($errors): ?>
      <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <ul style="padding-left:14px;margin:0">
          <?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label">Nama Lengkap <span class="required">*</span></label>
          <div class="input-group">
            <i class="input-icon fas fa-user"></i>
            <input type="text" name="name" class="form-control"
                   placeholder="Masukkan nama lengkap Anda"
                   value="<?= e($values['name']) ?>" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Email <span class="required">*</span></label>
          <div class="input-group">
            <i class="input-icon fas fa-envelope"></i>
            <input type="email" name="email" class="form-control"
                   placeholder="contoh@email.com"
                   value="<?= e($values['email']) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password <span class="required">*</span></label>
          <div class="input-group">
            <i class="input-icon fas fa-lock"></i>
            <input type="password" name="password" id="password" class="form-control"
                   placeholder="Minimal 8 karakter" required>
            <span class="input-suffix" data-toggle-pwd="password">
              <i class="fas fa-eye"></i>
            </span>
          </div>
          <p class="form-hint">Minimal 8 karakter, kombinasi huruf dan angka</p>
        </div>

        <div class="form-group">
          <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
          <div class="input-group">
            <i class="input-icon fas fa-lock"></i>
            <input type="password" name="password_confirm" id="password_confirm" class="form-control"
                   placeholder="Ulangi password Anda" required>
            <span class="input-suffix" data-toggle-pwd="password_confirm">
              <i class="fas fa-eye"></i>
            </span>
          </div>
        </div>

        <div style="margin-bottom:16px;font-size:.8rem;color:var(--text-muted)">
          Dengan mendaftar, Anda menyetujui <a href="#" style="color:var(--green-600)">Syarat & Ketentuan</a>
          dan <a href="#" style="color:var(--green-600)">Kebijakan Privasi</a> kami.
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
          <i class="fas fa-user-plus"></i> Buat Akun Sekarang
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
