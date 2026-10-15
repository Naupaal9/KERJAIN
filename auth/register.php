<?php
// auth/register.php — Pendaftaran akun baru (role mahasiswa atau dosen).
// Alur: validasi input di server -> simpan user -> redirect ke login.
// Role admin TIDAK bisa didaftarkan dari halaman ini (dibuat lewat seed/DB).

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

// Kalau sudah login, tidak perlu daftar lagi.
if (isset($_SESSION['user_id'])) {
  redirect('index.php');
}

$errors = [];
// Nilai awal form (supaya isian tidak hilang saat ada error validasi).
$input = [
  'full_name' => '',
  'email'     => '',
  'role'      => 'mahasiswa',
  'nim_nip'   => '',
  'prodi'     => '',
];

// ---------------------------------------------------------------------
// Proses form (POST)
// ---------------------------------------------------------------------
if (is_post()) {
  // 1. Cek token CSRF dulu sebelum memproses apa pun.
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors['umum'] = 'Token keamanan tidak valid. Silakan isi ulang form.';
  } else {
    // 2. Ambil dan rapikan input.
    $input['full_name'] = trim($_POST['full_name'] ?? '');
    $input['email']     = trim($_POST['email'] ?? '');
    $input['role']      = $_POST['role'] ?? '';
    $input['nim_nip']   = trim($_POST['nim_nip'] ?? '');
    $input['prodi']     = trim($_POST['prodi'] ?? '');
    $password           = $_POST['password'] ?? '';
    $password_confirm   = $_POST['password_confirm'] ?? '';

    // 3. Validasi tiap kolom.
    if ($input['full_name'] === '') {
      $errors['full_name'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($input['full_name']) > 100) {
      $errors['full_name'] = 'Nama lengkap maksimal 100 karakter.';
    }

    if ($input['email'] === '') {
      $errors['email'] = 'Email wajib diisi.';
    } elseif (mb_strlen($input['email']) > 120) {
      $errors['email'] = 'Email maksimal 120 karakter.';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'Format email tidak valid.';
    }

    // Hanya mahasiswa/dosen yang boleh daftar sendiri.
    if (!in_array($input['role'], ['mahasiswa', 'dosen'], true)) {
      $errors['role'] = 'Pilih role mahasiswa atau dosen.';
    }

    if ($input['nim_nip'] === '') {
      $errors['nim_nip'] = 'NIM/NIP wajib diisi.';
    } elseif (mb_strlen($input['nim_nip']) > 30) {
      $errors['nim_nip'] = 'NIM/NIP maksimal 30 karakter.';
    }

    if ($input['prodi'] === '') {
      $errors['prodi'] = 'Program studi wajib diisi.';
    } elseif (mb_strlen($input['prodi']) > 100) {
      $errors['prodi'] = 'Program studi maksimal 100 karakter.';
    }

    if (strlen($password) < 8) {
      $errors['password'] = 'Password minimal 8 karakter.';
    }
    if ($password === '' || $password_confirm !== $password) {
      $errors['password_confirm'] = 'Konfirmasi password belum sama.';
    }

    // 4. Email harus belum terpakai (kolom email UNIQUE).
    if (!isset($errors['email'])) {
      $cek = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
      $cek->execute([$input['email']]);
      if ($cek->fetch()) {
        $errors['email'] = 'Email ini sudah terdaftar.';
      }
    }

    // 5. Kalau semua valid, simpan user baru.
    if (empty($errors)) {
      $stmt = $pdo->prepare(
        "INSERT INTO users (full_name, email, password, role, nim_nip, prodi)
         VALUES (?, ?, ?, ?, ?, ?)"
      );
      $stmt->execute([
        $input['full_name'],
        $input['email'],
        password_hash($password, PASSWORD_DEFAULT),
        $input['role'],
        $input['nim_nip'],
        $input['prodi'],
      ]);

      flash('success', 'Registrasi berhasil! Silakan masuk dengan akun barumu.');
      redirect('auth/login.php');
    }
  }
}

$page_title = 'Daftar Akun — Kerjain!';
$base = '../';   // halaman ini ada 1 level di bawah root
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="form-card auth-card">
    <h1>Daftar Akun</h1>
    <p class="auth-sub">Isi datamu, lalu mulai cari project kampus.</p>

    <?php if (!empty($errors['umum'])): ?>
      <p class="alert alert-error"><?= e($errors['umum']); ?></p>
    <?php endif; ?>

    <!-- data-validate = validasi ringan di sisi browser (assets/js/app.js).
         Validasi utama tetap di PHP di atas. -->
    <form method="POST" action="register.php" data-validate>

      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

      <div class="form-group">
        <label class="form-label" for="full_name">Nama Lengkap <span class="wajib">*</span></label>
        <input class="form-control" type="text" id="full_name" name="full_name"
               value="<?= e($input['full_name']); ?>" maxlength="100" required>
        <?php if (isset($errors['full_name'])): ?>
          <span class="form-error"><?= e($errors['full_name']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email <span class="wajib">*</span></label>
        <input class="form-control" type="email" id="email" name="email"
               value="<?= e($input['email']); ?>" maxlength="120" required>
        <?php if (isset($errors['email'])): ?>
          <span class="form-error"><?= e($errors['email']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="role">Daftar Sebagai <span class="wajib">*</span></label>
        <select class="form-control" id="role" name="role" required>
          <option value="mahasiswa" <?= $input['role'] === 'mahasiswa' ? 'selected' : ''; ?>>Mahasiswa</option>
          <option value="dosen" <?= $input['role'] === 'dosen' ? 'selected' : ''; ?>>Dosen</option>
        </select>
        <?php if (isset($errors['role'])): ?>
          <span class="form-error"><?= e($errors['role']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="nim_nip">NIM / NIP <span class="wajib">*</span></label>
          <input class="form-control" type="text" id="nim_nip" name="nim_nip"
                 value="<?= e($input['nim_nip']); ?>" maxlength="30" required>
          <?php if (isset($errors['nim_nip'])): ?>
            <span class="form-error"><?= e($errors['nim_nip']); ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="prodi">Program Studi <span class="wajib">*</span></label>
          <input class="form-control" type="text" id="prodi" name="prodi"
                 value="<?= e($input['prodi']); ?>" maxlength="100" required>
          <?php if (isset($errors['prodi'])): ?>
            <span class="form-error"><?= e($errors['prodi']); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="password">Password <span class="wajib">*</span></label>
          <input class="form-control" type="password" id="password" name="password"
                 minlength="8" autocomplete="new-password" required>
          <span class="form-hint">Minimal 8 karakter.</span>
          <?php if (isset($errors['password'])): ?>
            <span class="form-error"><?= e($errors['password']); ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="password_confirm">Ulangi Password <span class="wajib">*</span></label>
          <input class="form-control" type="password" id="password_confirm" name="password_confirm"
                 minlength="8" autocomplete="new-password" required>
          <?php if (isset($errors['password_confirm'])): ?>
            <span class="form-error"><?= e($errors['password_confirm']); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary btn-full" type="submit">Daftar</button>
      </div>
    </form>

    <p class="auth-alt">
      Sudah punya akun? <a href="login.php">Masuk di sini</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
