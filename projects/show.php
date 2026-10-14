<?php
// projects/show.php — Detail lengkap sebuah project.
// Menampilkan tombol aksi sesuai hak akses: owner, admin, mahasiswa lain, atau guest.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

// ---------------------------------------------------------------------
// 1. Validasi ID dari URL (harus angka positif)
// ---------------------------------------------------------------------
$id_raw = $_GET['id'] ?? '';
$id     = (ctype_digit((string) $id_raw) && (int) $id_raw > 0) ? (int) $id_raw : 0;

$project = null;
if ($id > 0) {
  $stmt = $pdo->prepare("
    SELECT p.project_id, p.owner_id, p.title, p.description, p.budget, p.deadline,
           p.status, p.created_at,
           u.full_name AS owner_name, u.role AS owner_role, u.prodi AS owner_prodi,
           c.category_name,
           (SELECT COUNT(*) FROM applications a WHERE a.project_id = p.project_id) AS jumlah_pelamar,
           (SELECT COUNT(*) FROM applications a
             WHERE a.project_id = p.project_id AND a.status = 'pending') AS pelamar_pending
    FROM projects p
    JOIN users u      ON p.owner_id    = u.user_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE p.project_id = ?
    LIMIT 1
  ");
  $stmt->execute([$id]);
  $project = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------------------
// 2. Project tidak ditemukan → tampilkan halaman 404 sederhana
// ---------------------------------------------------------------------
if (!$project) {
  http_response_code(404);
  $page_title = 'Project Tidak Ditemukan — Kerjain!';
  $base = '../';
  require __DIR__ . '/../includes/header.php';
  ?>
  <header class="page-header">
    <h1>Project Tidak Ditemukan</h1>
    <p>Project yang kamu cari tidak ada atau sudah dihapus.</p>
  </header>
  <p class="kosong">
    ID project tidak valid atau datanya sudah tidak tersedia.<br>
    <a class="btn btn-primary" href="index.php">Kembali ke Daftar Project</a>
  </p>
  <?php
  require __DIR__ . '/../includes/footer.php';
  exit;
}

// ---------------------------------------------------------------------
// 3. Tentukan hak akses (dipakai untuk menampilkan tombol aksi)
// ---------------------------------------------------------------------
$user     = current_user($pdo);   // null kalau belum login
$is_owner = $user && ((int) $user['user_id'] === (int) $project['owner_id']);
$is_admin = $user && ($user['role'] === 'admin');

// Mahasiswa (bukan pemilik) boleh melamar project yang masih open.
$sudah_melamar = false;
if ($user && $user['role'] === 'mahasiswa') {
  $stmt = $pdo->prepare("SELECT 1 FROM applications WHERE project_id = ? AND applicant_id = ? LIMIT 1");
  $stmt->execute([$project['project_id'], $user['user_id']]);
  $sudah_melamar = (bool) $stmt->fetch();
}

$bisa_lamar = $user
  && $user['role'] === 'mahasiswa'
  && !$is_owner
  && $project['status'] === 'open'
  && !$sudah_melamar;

$page_title = $project['title'] . ' — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <p class="breadcrumb"><a href="index.php">Cari Project</a> / Detail</p>
  <h1><?= e($project['title']); ?></h1>
  <p>
    <span class="badge badge-<?= e($project['status']); ?>">
      <?= e(status_label($project['status'])); ?>
    </span>
    <span class="text-muted">· Diposting <?= e(format_tanggal($project['created_at'])); ?></span>
  </p>
</header>

<div class="detail-grid">

  <!-- Kolom utama: deskripsi project -->
  <section class="detail-card">
    <div class="card-tab card-tab-static"><?= e($project['category_name']); ?></div>

    <h2 class="card-title">Deskripsi Project</h2>
    <p class="detail-desc"><?= nl2br(e($project['description'])); ?></p>

    <dl class="meta-list">
      <div>
        <dt>Budget</dt>
        <dd class="card-budget"><?= e(format_rupiah($project['budget'])); ?></dd>
      </div>
      <div>
        <dt>Deadline</dt>
        <dd><?= $project['deadline'] ? e(format_tanggal($project['deadline'])) : 'Bebas / belum ditentukan'; ?></dd>
      </div>
      <div>
        <dt>Kategori</dt>
        <dd><?= e($project['category_name']); ?></dd>
      </div>
      <div>
        <dt>Jumlah Pelamar</dt>
        <dd><?= (int) $project['jumlah_pelamar']; ?> orang (<?= (int) $project['pelamar_pending']; ?> masih pending)</dd>
      </div>
    </dl>
  </section>

  <!-- Kolom samping: pemilik + tombol aksi -->
  <aside class="detail-side">
    <div class="detail-card">
      <h2 class="card-title">Pemilik Project</h2>
      <p class="owner-name"><?= e($project['owner_name']); ?></p>
      <p class="text-muted">
        <?= e(role_label($project['owner_role'])); ?>
        <?= $project['owner_prodi'] ? ' · ' . e($project['owner_prodi']) : ''; ?>
      </p>
    </div>

    <div class="detail-card">
      <h2 class="card-title">Aksi</h2>

      <div class="action-stack">
        <?php if ($bisa_lamar): ?>
          <!-- Form lamaran ada di Modul C. -->
          <a class="btn btn-primary btn-full" href="../applications/apply.php?project_id=<?= (int) $project['project_id']; ?>">
            Lamar Project
          </a>
        <?php elseif ($user && $user['role'] === 'mahasiswa' && !$is_owner && $sudah_melamar): ?>
          <p class="form-hint">Kamu sudah pernah melamar project ini.</p>
        <?php elseif ($user && !$is_owner && $project['status'] !== 'open'): ?>
          <p class="form-hint">Project ini sudah tidak menerima lamaran baru.</p>
        <?php elseif ($user && $user['role'] === 'dosen' && !$is_owner): ?>
          <p class="form-hint">Dosen hanya memposting project, tidak melamar.</p>
        <?php elseif (!$user): ?>
          <p class="form-hint">Masuk sebagai mahasiswa untuk melamar project ini.</p>
          <a class="btn btn-primary btn-full" href="../auth/login.php">Masuk untuk Melamar</a>
        <?php endif; ?>

        <?php if ($is_owner): ?>
          <a class="btn btn-outline btn-full" href="../applications/manage.php?project_id=<?= (int) $project['project_id']; ?>">
            Lihat &amp; Kelola Pelamar
          </a>
          <a class="btn btn-outline btn-full" href="edit.php?id=<?= (int) $project['project_id']; ?>">
            Edit Project
          </a>

          <?php if ($project['status'] === 'open'): ?>
            <!-- Menutup project: hanya boleh dari status open. -->
            <form method="POST" action="edit.php" data-confirm="Tutup project ini? Project tidak akan menerima lamaran baru.">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
              <input type="hidden" name="aksi" value="close">
              <button class="btn btn-outline btn-full" type="submit">Tutup Project</button>
            </form>
          <?php elseif ($project['status'] === 'closed'): ?>
            <form method="POST" action="edit.php" data-confirm="Buka kembali project ini supaya bisa dilamar lagi?">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
              <input type="hidden" name="aksi" value="reopen">
              <button class="btn btn-outline btn-full" type="submit">Buka Kembali</button>
            </form>
          <?php elseif ($project['status'] === 'in_progress'): ?>
            <form method="POST" action="mark_done.php" data-confirm="Tandai project ini sebagai selesai (Done)?">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
              <button class="btn btn-primary btn-full" type="submit">Tandai Selesai</button>
            </form>
            <p class="form-hint">Project bisa ditutup manual hanya saat masih open.</p>
          <?php else: // done ?>
            <p class="form-hint">Project ini sudah selesai.</p>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($is_owner || $is_admin): ?>
          <!-- Hapus project: POST + CSRF, dikonfirmasi lewat JS (data-confirm). -->
          <form method="POST" action="delete.php"
                data-confirm="Hapus project ini beserta seluruh lamarannya? Tindakan ini tidak bisa dibatalkan.">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
            <button class="btn btn-danger btn-full" type="submit">Hapus Project</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </aside>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
