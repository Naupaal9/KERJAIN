<?php
// applications/my.php — Daftar lamaran milik mahasiswa yang sedang login.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

$user = current_user($pdo);
if (!$user || $user['role'] !== 'mahasiswa') {
  // Biar lebih aman: dosen/admin tetap boleh lihat halaman ini, tapi sesuai aturan
  // "Lamaran saya" cocok untuk mahasiswa. Kalau bukan mahasiswa, arahkan.
  flash('alert', 'Halaman ini khusus untuk mahasiswa.');
  redirect('dashboard/index.php');
}

$user_id = (int) $user['user_id'];

// Ambil lamaran milik user
$stmt = $pdo->prepare("
  SELECT a.application_id, a.project_id, a.cover_letter, a.proposed_fee, a.status, a.applied_at,
         p.title AS project_title, p.status AS project_status,
         u.full_name AS owner_name,
         c.category_name
  FROM applications a
  JOIN projects p ON a.project_id = p.project_id
  JOIN users u    ON p.owner_id   = u.user_id
  JOIN categories c ON p.category_id = c.category_id
  WHERE a.applicant_id = ?
  ORDER BY a.applied_at DESC, a.application_id DESC
");
$stmt->execute([$user_id]);
$lamaran = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Lamaran Saya — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <h1>Lamaran Saya</h1>
  <p>Daftar seluruh lamaran yang kamu ajukan.</p>
</header>

<?php if (empty($lamaran)): ?>
  <p class="kosong">
    Kamu belum pernah melamar project apapun.<br>
    <a class="btn btn-primary" href="../projects/index.php">Cari Project</a>
  </p>
<?php else: ?>
  <div class="project-grid">
    <?php foreach ($lamaran as $app): ?>
      <article class="project-card">
        <span class="card-tab"><?= e($app['category_name']); ?></span>
        <div class="card-body">
          <h3>
            <a href="../projects/show.php?id=<?= (int) $app['project_id']; ?>">
              <?= e($app['project_title']); ?>
            </a>
          </h3>
          <p class="card-desc">
            <strong>Owner:</strong> <?= e($app['owner_name']); ?> · 
            <strong>Status Project:</strong> <?= e(status_label($app['project_status'])); ?>
          </p>
          <p class="card-desc">
            <strong>Surat Pengantar:</strong><br>
            <?= nl2br(e($app['cover_letter'])); ?>
          </p>
          <div class="card-meta">
            <span class="card-budget"><?= e(format_rupiah($app['proposed_fee'])); ?></span>
            <span>
              <strong>Status Lamaran:</strong>
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
          <div class="card-actions">
            <a class="btn btn-sm btn-outline" href="edit.php?id=<?= (int) $app['application_id']; ?>">Edit</a>
            <form method="POST" action="cancel.php" data-confirm="Batalkan lamaran ini?">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="application_id" value="<?= (int) $app['application_id']; ?>">
              <button class="btn btn-sm btn-danger" type="submit">Batalkan</button>
            </form>
          </div>
        <?php else: ?>
          <div class="card-actions">
            <span class="form-hint">Lamaran yang sudah diterima/ditolak tidak dapat diubah.</span>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
