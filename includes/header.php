<?php
// includes/header.php — Bagian <head> + navbar pembuka layout bersama.
// Halaman pemanggil wajib sudah require config/db.php, includes/functions.php,
// dan includes/auth.php (yang berisi session_start).
//
// Pakai:
//   $page_title = 'Judul Halaman';   // opsional
//   require __DIR__ . '/../includes/header.php';
//   ... konten halaman ...
//   require __DIR__ . '/../includes/footer.php';

// Path dasar: '' untuk halaman di root, '../' untuk halaman 1 level di bawah root.
// Normalisasi: pastikan $base selalu diakhiri garis miring (atau tetap kosong),
// supaya hasil gabungan $base + 'assets/css/style.css' selalu menjadi URL yang
// benar: 'assets/...' untuk halaman root, '../assets/...' untuk halaman subfolder.
// CATATAN: jangan menulis tanda tutup tag PHP di dalam komentar satu baris,
// karena itu akan mengakhiri mode PHP lebih awal.
$base = $base ?? '';
if ($base !== '' && substr($base, -1) !== '/') {
  $base .= '/';
}

$page_title = $page_title ?? 'Kerjain! — Platform Freelance Kampus';
// Kelas <main>: default "container page". Halaman landing menggantinya
// dengan "landing" (tanpa container) supaya hero bisa membentang penuh.
$main_class = $main_class ?? 'container page';
$flash_alert = flash('alert');
$flash_success = flash('success');
$current_user = current_user($pdo);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title); ?></title>
  <!-- Font lokal Plus Jakarta Sans sudah dideklarasikan di style.css (@font-face) -->
  <link rel="stylesheet" href="<?= $base; ?>assets/css/style.css">
  <script src="<?= $base; ?>assets/js/app.js" defer></script>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="<?= $base; ?>index.php" title="Kerjain! — Beranda">
      <!-- Logo wordmark asli dari assets/img (1123x341); atribut width/height
           menjaga rasio supaya tidak gepeng dan layout tidak bergeser. -->
      <img src="<?= $base; ?>assets/img/kerjain-logo-type.png"
           alt="Kerjain!" width="1123" height="341">
    </a>

    <button class="nav-toggle" type="button" aria-label="Buka menu navigasi">
      <span></span><span></span><span></span>
    </button>

    <ul class="nav-links">
      <li><a href="<?= $base; ?>index.php">Beranda</a></li>
      <li><a href="<?= $base; ?>projects/index.php">Cari Project</a></li>

      <?php if ($current_user): ?>
        <li><a href="<?= $base; ?>dashboard/index.php">Dashboard</a></li>
        <li><a href="<?= $base; ?>projects/my.php">Project Saya</a></li>
        <?php if ($current_user['role'] === 'mahasiswa'): ?>
        <li><a href="<?= $base; ?>applications/my.php">Lamaran Saya</a></li>
        <?php endif; ?>
        <?php if ($current_user['role'] === 'admin'): ?>
          <li><a href="<?= $base; ?>admin/categories.php">Kategori</a></li>
        <?php endif; ?>
        <li class="nav-user">
          Halo, <a href="<?= $base; ?>auth/profile.php"><strong><?= e($current_user['full_name']); ?></strong></a>
          <span class="badge badge-role"><?= e(role_label($current_user['role'])); ?></span>
        </li>
        <li>
          <form class="nav-form" method="POST" action="<?= $base; ?>auth/logout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <button class="btn btn-outline" type="submit">Keluar</button>
          </form>
        </li>
      <?php else: ?>
        <li><a href="<?= $base; ?>auth/login.php">Masuk</a></li>
        <li><a class="btn btn-primary" href="<?= $base; ?>auth/register.php">Daftar</a></li>
      <?php endif; ?>
    </ul>
  </div>
</nav>

<!-- Pesan flash (sekali tampil, lalu hilang) -->
<?php if ($flash_alert): ?>
  <div class="container">
    <p class="alert alert-error"><?= e($flash_alert); ?></p>
  </div>
<?php endif; ?>
<?php if ($flash_success): ?>
  <div class="container">
    <p class="alert alert-success"><?= e($flash_success); ?></p>
  </div>
<?php endif; ?>

<main class="<?= e($main_class); ?>">
