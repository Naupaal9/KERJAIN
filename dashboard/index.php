<?php
// dashboard/index.php — Dashboard pengguna sesuai role.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

$user = current_user($pdo);
if (!$user) {
  redirect('index.php');
}

$user_id = (int) $user['user_id'];
$role = $user['role'];

$page_title = 'Dashboard — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <h1>Dashboard</h1>
  <p>Ringkasan aktivitas kamu di Kerjain!</p>
</header>

<?php if ($role === 'mahasiswa' || $role === 'dosen'): ?>
  <!-- Ringkasan proyek milik pengguna berdasarkan status -->
  <?php
  $stmt = $pdo->prepare("SELECT status, COUNT(*) AS jumlah FROM projects WHERE owner_id = ? GROUP BY status");
  $stmt->execute([$user_id]);
  $ringkasan_proj = ['open' => 0, 'in_progress' => 0, 'done' => 0, 'closed' => 0];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $ringkasan_proj[$row['status']] = (int) $row['jumlah'];
  }
  $total_proj = array_sum($ringkasan_proj);
  ?>

  <h2 class="card-title">Proyek Saya</h2>
  <div class="stat-grid" style="margin-bottom: 1.6rem;">
    <div class="stat-card">
      <span class="stat-number"><?= (int) $ringkasan_proj['open']; ?></span>
      <span class="stat-label">Open</span>
    </div>
    <div class="stat-card">
      <span class="stat-number"><?= (int) $ringkasan_proj['in_progress']; ?></span>
      <span class="stat-label">In Progress</span>
    </div>
    <div class="stat-card">
      <span class="stat-number"><?= (int) $ringkasan_proj['done']; ?></span>
      <span class="stat-label">Selesai</span>
    </div>
    <div class="stat-card">
      <span class="stat-number"><?= (int) $ringkasan_proj['closed']; ?></span>
      <span class="stat-label">Ditutup</span>
    </div>
    <div class="stat-card">
      <span class="stat-number"><?= $total_proj; ?></span>
      <span class="stat-label">Total</span>
    </div>
  </div>

  <!-- Proyek terbaru milik pengguna -->
  <?php
  $stmt = $pdo->prepare("
    SELECT project_id, title, status, created_at
    FROM projects
    WHERE owner_id = ?
    ORDER BY created_at DESC
    LIMIT 5
  ");
  $stmt->execute([$user_id]);
  $proj_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <div class="form-card" style="margin-bottom: 1.6rem;">
    <h3 class="card-title">Proyek Terbaru Saya</h3>
    <?php if (empty($proj_terbaru)): ?>
      <p class="form-hint">Belum ada proyek.</p>
    <?php else: ?>
      <ul style="display: flex; flex-direction: column; gap: 0.6rem;">
        <?php foreach ($proj_terbaru as $p): ?>
          <li>
            <a href="../projects/show.php?id=<?= (int) $p['project_id']; ?>">
              <strong><?= e($p['title']); ?></strong>
            </a>
            · <span class="badge badge-<?= e($p['status']); ?>"><?= e(status_label($p['status'])); ?></span>
            · <?= e(format_tanggal($p['created_at'])); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Proyek in_progress milik pengguna -->
  <?php
  $stmt = $pdo->prepare("
    SELECT project_id, title, created_at
    FROM projects
    WHERE owner_id = ? AND status = 'in_progress'
    ORDER BY created_at DESC
  ");
  $stmt->execute([$user_id]);
  $proj_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
  ?>

  <div class="form-card" style="margin-bottom: 1.6rem;">
    <h3 class="card-title">Proyek Sedang Dikerjakan (In Progress)</h3>
    <?php if (empty($proj_progress)): ?>
      <p class="form-hint">Tidak ada proyek in_progress.</p>
    <?php else: ?>
      <ul style="display: flex; flex-direction: column; gap: 0.6rem;">
        <?php foreach ($proj_progress as $p): ?>
          <li>
            <a href="../projects/show.php?id=<?= (int) $p['project_id']; ?>">
              <strong><?= e($p['title']); ?></strong>
            </a>
            · <?= e(format_tanggal($p['created_at'])); ?>
            · <a class="btn btn-sm btn-outline" href="../applications/manage.php?project_id=<?= (int) $p['project_id']; ?>">Kelola Pelamar</a>
            <form method="POST" action="../projects/mark_done.php" style="display: inline;" data-confirm="Tandai project ini sebagai selesai (Done)?">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="project_id" value="<?= (int) $p['project_id']; ?>">
              <button class="btn btn-sm btn-primary" type="submit">Tandai Selesai</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <?php if ($role === 'mahasiswa'): ?>
    <!-- Ringkasan lamaran mahasiswa -->
    <?php
    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS jumlah FROM applications WHERE applicant_id = ? GROUP BY status");
    $stmt->execute([$user_id]);
    $ringkasan_app = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $ringkasan_app[$row['status']] = (int) $row['jumlah'];
    }
    $total_app = array_sum($ringkasan_app);
    ?>

    <h2 class="card-title">Lamaran Saya</h2>
    <div class="stat-grid" style="margin-bottom: 1.6rem;">
      <div class="stat-card">
        <span class="stat-number"><?= (int) $ringkasan_app['pending']; ?></span>
        <span class="stat-label">Pending</span>
      </div>
      <div class="stat-card">
        <span class="stat-number"><?= (int) $ringkasan_app['accepted']; ?></span>
        <span class="stat-label">Diterima</span>
      </div>
      <div class="stat-card">
        <span class="stat-number"><?= (int) $ringkasan_app['rejected']; ?></span>
        <span class="stat-label">Ditolak</span>
      </div>
      <div class="stat-card">
        <span class="stat-number"><?= $total_app; ?></span>
        <span class="stat-label">Total</span>
      </div>
    </div>

    <!-- Proyek yang sedang dikerjakan bersamaku (lamaran accepted dan project in_progress) -->
    <?php
    $stmt = $pdo->prepare("
      SELECT a.application_id, a.project_id, p.title, p.status
      FROM applications a
      JOIN projects p ON a.project_id = p.project_id
      WHERE a.applicant_id = ? AND a.status = 'accepted' AND p.status = 'in_progress'
      ORDER BY a.applied_at DESC
    ");
    $stmt->execute([$user_id]);
    $dikerjakan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="form-card" style="margin-bottom: 1.6rem;">
      <h3 class="card-title">Proyek yang Sedang Dikerjakan Bersamaku</h3>
      <?php if (empty($dikerjakan)): ?>
        <p class="form-hint">Belum ada proyek yang sedang dikerjakan.</p>
      <?php else: ?>
        <ul style="display: flex; flex-direction: column; gap: 0.6rem;">
          <?php foreach ($dikerjakan as $d): ?>
            <li>
              <a href="../projects/show.php?id=<?= (int) $d['project_id']; ?>">
                <strong><?= e($d['title']); ?></strong>
              </a>
              · <span class="badge badge-<?= e($d['status']); ?>"><?= e(status_label($d['status'])); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php elseif ($role === 'admin'): ?>
  <div class="form-card" style="margin-bottom: 1.6rem;">
    <h3 class="card-title">Ringkasan Admin</h3>
    <div class="action-stack">
      <a class="btn btn-outline btn-full" href="../admin/categories.php">Kelola Kategori</a>
      <a class="btn btn-outline btn-full" href="../projects/index.php">Lihat Semua Project</a>
      <a class="btn btn-outline btn-full" href="../projects/my.php">Project Saya</a>
      <a class="btn btn-outline btn-full" href="../applications/my.php">Lamaran Saya</a>
    </div>
  </div>
<?php endif; ?>

<!-- Tautan cepat umum -->
<div class="form-card">
  <h3 class="card-title">Tautan Cepat</h3>
  <div class="action-stack">
    <a class="btn btn-primary btn-full" href="../projects/create.php">+ Buat Project Baru</a>
    <a class="btn btn-outline btn-full" href="../projects/my.php">Project Saya</a>
    <?php if ($role === 'mahasiswa'): ?>
    <a class="btn btn-outline btn-full" href="../applications/my.php">Lamaran Saya</a>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
