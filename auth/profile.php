<?php
// auth/profile.php — Lihat & edit profil user yang sedang login (Modul A).
// Bisa mengubah: nama, telepon, prodi, bio, dan foto profil (JPG/PNG maks 2 MB).
// Email, NIM/NIP, dan role tidak diubah di sini.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

require_login();   // hanya untuk user yang sudah masuk

$user = current_user($pdo);

// Sesi bisa saja menunjuk user yang sudah dihapus: bersihkan sessionnya.
if (!$user) {
  $_SESSION = [];
  session_destroy();
  session_start();
  flash('alert', 'Session kamu tidak valid lagi. Silakan login ulang.');
  redirect('auth/login.php');
}

$errors = [];
// Nilai form diambil dari data user, lalu ditimpa input POST saat ada error.
$input = [
  'full_name' => $user['full_name'],
  'phone'     => (string) $user['phone'],
  'prodi'     => (string) $user['prodi'],
  'bio'       => (string) $user['bio'],
];

// ---------------------------------------------------------------------
// Proses form (POST)
// ---------------------------------------------------------------------
if (is_post()) {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors['umum'] = 'Token keamanan tidak valid. Silakan coba lagi.';
  } else {
    $input['full_name'] = trim($_POST['full_name'] ?? '');
    $input['phone']     = trim($_POST['phone'] ?? '');
    $input['prodi']     = trim($_POST['prodi'] ?? '');
    $input['bio']       = trim($_POST['bio'] ?? '');

    // 1. Validasi teks.
    if ($input['full_name'] === '') {
      $errors['full_name'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($input['full_name']) > 100) {
      $errors['full_name'] = 'Nama lengkap maksimal 100 karakter.';
    }

    if ($input['phone'] !== '' && !preg_match('/^[0-9+\- ]{6,20}$/', $input['phone'])) {
      $errors['phone'] = 'Nomor telepon hanya boleh angka, spasi, + dan - (6-20 karakter).';
    }

    if (mb_strlen($input['prodi']) > 100) {
      $errors['prodi'] = 'Program studi maksimal 100 karakter.';
    }

    if (mb_strlen($input['bio']) > 500) {
      $errors['bio'] = 'Bio maksimal 500 karakter.';
    }

    // 2. Upload foto profil (opsional: kalau tidak pilih file, foto lama tetap dipakai).
    $nama_foto = $user['profile_photo'];
    if (!empty($_FILES['profile_photo']['name'])) {
      $file = $_FILES['profile_photo'];
      $upload_dir = __DIR__ . '/../assets/img';   // folder simpan foto

      if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['profile_photo'] = 'Upload gagal (kode ' . (int) $file['error'] . '). Coba file lain.';
      } elseif ($file['size'] > 2 * 1024 * 1024) {
        $errors['profile_photo'] = 'Ukuran foto maksimal 2 MB.';
      } else {
        // Cek isi file benar-benar gambar (jangan percaya tipe dari browser).
        $info = @getimagesize($file['tmp_name']);
        $jenis_diizinkan = [
          'image/jpeg' => 'jpg',
          'image/png'  => 'png',
        ];

        if ($info === false || !isset($jenis_diizinkan[$info['mime']])) {
          $errors['profile_photo'] = 'Foto harus berformat JPG atau PNG.';
        } else {
          if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
          }
          // Nama file acak supaya tidak bentrok dan tidak bisa ditebak.
          $nama_baru = 'avatar_' . $user['user_id'] . '_' . bin2hex(random_bytes(6))
                     . '.' . $jenis_diizinkan[$info['mime']];

          if (move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $nama_baru)) {
            $nama_foto = $nama_baru;
          } else {
            $errors['profile_photo'] = 'Foto tidak bisa disimpan. Cek izin folder assets/img.';
          }
        }
      }
    }

    // 3. Simpan kalau tidak ada error.
    if (empty($errors)) {
      $stmt = $pdo->prepare(
        "UPDATE users
         SET full_name = ?, phone = ?, prodi = ?, bio = ?, profile_photo = ?
         WHERE user_id = ?"
      );
      $stmt->execute([
        $input['full_name'],
        $input['phone'] === '' ? null : $input['phone'],
        $input['prodi'] === '' ? null : $input['prodi'],
        $input['bio'] === '' ? null : $input['bio'],
        $nama_foto,
        $user['user_id'],
      ]);

      // Hapus file avatar lama (hanya file yang dibuat aplikasi ini).
      $foto_lama = (string) $user['profile_photo'];
      if ($foto_lama !== '' && $foto_lama !== $nama_foto
          && strpos($foto_lama, 'avatar_' . $user['user_id'] . '_') === 0) {
        $path_lama = __DIR__ . '/../assets/img/' . basename($foto_lama);
        if (is_file($path_lama)) {
          unlink($path_lama);
        }
      }

      // Sinkronkan nama di session supaya navbar langsung berubah.
      $_SESSION['full_name'] = $input['full_name'];

      flash('success', 'Profil berhasil diperbarui.');
      redirect('auth/profile.php');
    }
  }
}

$page_title = 'Profil Saya — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';

// Data untuk tampilan ringkasan profil.
$foto    = ($user['profile_photo'] !== null && $user['profile_photo'] !== '') ? $user['profile_photo'] : null;
$inisial = mb_strtoupper(mb_substr($user['full_name'], 0, 1, 'UTF-8'), 'UTF-8');
?>

<header class="page-header">
  <h1>Profil Saya</h1>
  <p>Kelola data diri dan foto profilmu di sini.</p>
</header>

<div class="profile-grid">

  <!-- Kolom kiri: ringkasan profil -->
  <aside class="profile-card profile-summary">
    <?php if ($foto): ?>
      <img class="avatar" src="../assets/img/<?= e($foto); ?>" alt="Foto profil <?= e($user['full_name']); ?>">
    <?php else: ?>
      <div class="avatar-placeholder" aria-hidden="true"><?= e($inisial); ?></div>
    <?php endif; ?>

    <h2 class="profile-name"><?= e($user['full_name']); ?></h2>
    <span class="badge badge-role profile-role"><?= e(role_label($user['role'])); ?></span>

    <dl class="info-list">
      <div>
        <dt>Email</dt>
        <dd><?= e($user['email']); ?></dd>
      </div>
      <div>
        <dt>NIM / NIP</dt>
        <dd><?= e($user['nim_nip'] ?: '—'); ?></dd>
      </div>
      <div>
        <dt>Program Studi</dt>
        <dd><?= e($user['prodi'] ?: '—'); ?></dd>
      </div>
      <div>
        <dt>Telepon</dt>
        <dd><?= e($user['phone'] ?: '—'); ?></dd>
      </div>
      <div>
        <dt>Bio</dt>
        <dd class="bio-text"><?= e($user['bio'] ?: 'Belum ada bio.'); ?></dd>
      </div>
    </dl>
  </aside>

  <!-- Kolom kanan: form edit profil -->
  <section class="profile-card">
    <h2 class="card-title">Edit Profil</h2>

    <?php if (!empty($errors['umum'])): ?>
      <p class="alert alert-error"><?= e($errors['umum']); ?></p>
    <?php endif; ?>

    <!-- enctype multipart wajib ada karena ada upload file -->
    <form method="POST" action="profile.php" enctype="multipart/form-data" data-validate>

      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">

      <div class="form-group">
        <label class="form-label" for="full_name">Nama Lengkap <span class="wajib">*</span></label>
        <input class="form-control" type="text" id="full_name" name="full_name"
               value="<?= e($input['full_name']); ?>" maxlength="100" required>
        <?php if (isset($errors['full_name'])): ?>
          <span class="form-error"><?= e($errors['full_name']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="phone">Telepon</label>
          <input class="form-control" type="tel" id="phone" name="phone"
                 value="<?= e($input['phone']); ?>" maxlength="20" placeholder="08xxxxxxxxxx">
          <?php if (isset($errors['phone'])): ?>
            <span class="form-error"><?= e($errors['phone']); ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="prodi">Program Studi</label>
          <input class="form-control" type="text" id="prodi" name="prodi"
                 value="<?= e($input['prodi']); ?>" maxlength="100">
          <?php if (isset($errors['prodi'])): ?>
            <span class="form-error"><?= e($errors['prodi']); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="bio">Bio</label>
        <textarea class="form-control" id="bio" name="bio" maxlength="500"
                  placeholder="Ceritakan singkat keahlianmu..."><?= e($input['bio']); ?></textarea>
        <span class="form-hint">Maksimal 500 karakter.</span>
        <?php if (isset($errors['bio'])): ?>
          <span class="form-error"><?= e($errors['bio']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="profile_photo">Foto Profil</label>
        <input class="form-control" type="file" id="profile_photo" name="profile_photo"
               accept="image/jpeg,image/png">
        <span class="form-hint">Format JPG atau PNG, maksimal 2 MB. Kosongkan kalau tidak ingin ganti.</span>
        <?php if (isset($errors['profile_photo'])): ?>
          <span class="form-error"><?= e($errors['profile_photo']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
        <a class="btn btn-outline" href="../index.php">Batal</a>
      </div>
    </form>
  </section>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
