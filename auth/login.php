<?php
// auth/login.php — Masuk ke aplikasi.
// Alur: cek CSRF -> cari user by email -> password_verify -> set session -> redirect.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

// Kalau sudah login, langsung ke beranda.
if (isset($_SESSION['user_id'])) {
  redirect('index.php');
}

$errors = [];
$email  = '';

// ---------------------------------------------------------------------
// Proses form (POST)
// ---------------------------------------------------------------------
if (is_post()) {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors['umum'] = 'Token keamanan tidak valid. Silakan coba lagi.';
  } else {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
      $errors['umum'] = 'Email dan password wajib diisi.';
    } else {
      // Ambil user berdasarkan email (prepared statement).
      $stmt = $pdo->prepare(
        "SELECT user_id, full_name, password, role FROM users WHERE email = ? LIMIT 1"
      );
      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($user && password_verify($password, $user['password'])) {
        // Ganti ID session supaya aman dari session fixation.
        session_regenerate_id(true);

        $_SESSION['user_id']   = (int) $user['user_id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        flash('success', 'Selamat datang kembali, ' . $user['full_name'] . '!');
        redirect('index.php');
      }

      // Pesan sengaja disamakan: jangan bocorkan email mana yang terdaftar.
      $errors['umum'] = 'Email atau password salah.';
    }
  }
}

$page_title = 'Masuk — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="form-card auth-card">
    <h1>Masuk</h1>
    <p class="auth-sub">Lanjutkan cari dan kerjakan project kampusmu.</p>

    <?php if (!empty($errors['umum'])): ?>
      <p class="alert alert-error"><?= e($errors['umum']); ?></p>
    <?php endif; ?>

    <form method="POST" action="login.php" data-validate>

      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

      <div class="form-group">
        <label class="form-label" for="email">Email <span class="wajib">*</span></label>
        <input class="form-control" type="email" id="email" name="email"
               value="<?= e($email); ?>" maxlength="120" autocomplete="email" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password <span class="wajib">*</span></label>
        <input class="form-control" type="password" id="password" name="password"
               autocomplete="current-password" required>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary btn-full" type="submit">Masuk</button>
      </div>
    </form>

    <p class="auth-alt">
      Belum punya akun? <a href="register.php">Daftar sekarang</a>
    </p>
    <p class="form-hint">
      Akun demo (semua password: <strong>password123</strong>):
      admin@kerjain.test, dosen1@kerjain.test, mahasiswa1@kerjain.test
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
