<?php
// applications/manage.php — Kelola pelamar oleh pemilik project.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

$pid_raw = $_GET['project_id'] ?? '';
$project_id = (ctype_digit((string) $pid_raw) && (int) $pid_raw > 0) ? (int) $pid_raw : 0;

if ($project_id === 0) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/my.php');
}

$stmt = $pdo->prepare("
  SELECT project_id, owner_id, title, status
  FROM projects
  WHERE project_id = ?
  LIMIT 1
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/my.php');
}

$user = current_user($pdo);
if (!$user) {
  redirect('projects/my.php');
}

$is_owner = ((int) $user['user_id'] === (int) $project['owner_id']);

// Hanya pemilik project yang boleh mengelola
if (!$is_owner) {
  flash('alert', 'Kamu hanya boleh mengelola lamaran pada project milikmu.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

// Ambil semua lamaran untuk project ini
$stmt = $pdo->prepare("
  SELECT a.application_id, a.project_id, a.applicant_id, a.cover_letter, a.proposed_fee, a.status, a.applied_at,
         u.full_name AS applicant_name, u.prodi AS applicant_prodi, u.role AS applicant_role
  FROM applications a
  JOIN users u ON a.applicant_id = u.user_id
  WHERE a.project_id = ?
  ORDER BY a.status DESC, a.applied_at ASC, a.application_id ASC
");
$stmt->execute([$project_id]);
$lamaran = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cek apakah sudah ada yang accepted
$sudah_accepted = false;
foreach ($lamaran as $l) {
  if ($l['status'] === 'accepted') {
    $sudah_accepted = true;
    break;
  }
}

$page_title = 'Kelola Pelamar — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <p class="breadcrumb">
    <a href="../projects/my.php">Project Saya</a> /
    <a href="../projects/show.php?id=<?= (int) $project['project_id']; ?>">Detail</a> /
    Kelola Pelamar
  </p>
  <h1>Kelola Pelamar</h1>
  <p>Project: <strong><?= e($project['title']); ?></strong> · Status: <span class="badge badge-<?= e($project['status']); ?>"><?= e(status_label($project['status'])); ?></span></p>
  <?php if ($project['status'] === 'in_progress'): ?>
    <div class="page-toolbar" style="margin-top: 0.8rem;">
      <form method="POST" action="../projects/mark_done.php" data-confirm="Tandai project ini sebagai selesai (Done)?">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
        <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
        <button class="btn btn-primary" type="submit">Tandai Selesai (Done)</button>
      </form>
    </div>
  <?php endif; ?>
</header>

<?php if (empty($lamaran)): ?>
  <p class="kosong">
    Belum ada pelamar untuk project ini.<br>
    <a class="btn btn-outline" href="../projects/show.php?id=<?= (int) $project['project_id']; ?>">Kembali ke Detail</a>
  </p>
<?php else: ?>
  <div class="project-grid">
    <?php foreach ($lamaran as $app): ?>
      <article class="project-card">
        <div class="card-body">
          <h3><?= e($app['applicant_name']); ?></h3>
          <p class="card-desc">
            <?php if ($app['applicant_role']): ?>
              <strong>Role:</strong> <?= e(role_label($app['applicant_role'])); ?>
            <?php endif; ?>
            <?php if ($app['applicant_prodi']): ?>
              · <strong>Prodi:</strong> <?= e($app['applicant_prodi']); ?>
            <?php endif; ?>
          </p>
          <p class="card-desc">
            <strong>Surat Pengantar:</strong><br>
            <?= nl2br(e($app['cover_letter'])); ?>
          </p>
          <div class="card-meta">
            <span class="card-budget"><?= e(format_rupiah($app['proposed_fee'])); ?></span>
            <span>
              <strong>Status:</strong>
              <span class="badge badge-<?= e($app['status']); ?>">
                <?php
                $label_app = ['pending' => 'Pending', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'];
                echo e($label_app[$app['status']] ?? $app['status']);
                ?>
              </span>
            </span>
            <span>Diajukan: <?= e(format_tanggal($app['applied_at'])); ?></span>
          </div>
        </div>

        <?php if ($app['status'] === 'pending'): ?>
          <?php if ($project['status'] === 'open'): ?>
            <div class="card-actions">
              <form method="POST" action="update_status.php" data-confirm="Terima lamaran ini? Setelah diterima, project akan jadi In Progress dan lamaran pending lain akan ditolak.">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="application_id" value="<?= (int) $app['application_id']; ?>">
                <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
                <input type="hidden" name="action" value="accept">
                <button class="btn btn-sm btn-primary" type="submit">Terima</button>
              </form>
              <form method="POST" action="update_status.php" data-confirm="Tolak lamaran ini?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <input type="hidden" name="application_id" value="<?= (int) $app['application_id']; ?>">
                <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn btn-sm btn-outline" type="submit">Tolak</button>
              </form>
            </div>
          <?php else: ?>
            <div class="card-actions">
              <span class="form-hint">Project sudah <?= strtolower(status_label($project['status'])); ?>, tidak bisa memilih pelamar baru.</span>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="card-actions">
            <span class="form-hint">Lamaran sudah <?= $app['status'] === 'accepted' ? 'diterima' : 'ditolak'; ?>.</span>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="section-cta" style="margin-top: 1rem;">
    <a class="btn btn-outline" href="../projects/show.php?id=<?= (int) $project['project_id']; ?>">Kembali ke Detail</a>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
