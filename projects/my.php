<?php
// projects/my.php — Daftar project milik user yang sedang login.
// Tersedia filter status dan tombol kelola (detail, edit, tutup, hapus).

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';   // sudah berisi session_start()

require_login();

$user_id = (int) $_SESSION['user_id'];

// Filter status (opsional): hanya nilai status yang dikenal.
$status_valid = ['open', 'in_progress', 'done', 'closed'];
$status = $_GET['status'] ?? '';
if (!in_array($status, $status_valid, true)) {
  $status = '';
}

// ---------------------------------------------------------------------
// Ringkasan jumlah project per status (untuk kartu statistik)
// ---------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT status, COUNT(*) AS jumlah FROM projects WHERE owner_id = ? GROUP BY status");
$stmt->execute([$user_id]);
$ringkasan = ['open' => 0, 'in_progress' => 0, 'done' => 0, 'closed' => 0];
$total_semua = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $ringkasan[$row['status']] = (int) $row['jumlah'];
  $total_semua += (int) $row['jumlah'];
}

// ---------------------------------------------------------------------
// Daftar project milik user (dengan filter status opsional)
// ---------------------------------------------------------------------
$sql = "
  SELECT p.project_id, p.title, p.description, p.budget, p.deadline, p.status, p.created_at,
         c.category_name,
         (SELECT COUNT(*) FROM applications a WHERE a.project_id = p.project_id) AS jumlah_pelamar,
         (SELECT COUNT(*) FROM applications a
           WHERE a.project_id = p.project_id AND a.status = 'pending') AS pelamar_pending
  FROM projects p
  JOIN categories c ON p.category_id = c.category_id
  WHERE p.owner_id = ?
";
$params = [$user_id];
if ($status !== '') {
  $sql .= " AND p.status = ?";
  $params[] = $status;
}
$sql .= " ORDER BY p.created_at DESC, p.project_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftar_project = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Project Saya — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <h1>Project Saya</h1>
  <p>Kelola project yang kamu posting: edit, tutup, atau hapus.</p>
</header>

<div class="page-toolbar">
  <a class="btn btn-primary" href="create.php">+ Buat Project Baru</a>
</div>

<!-- Ringkasan status -->
<div class="stat-grid">
  <a class="stat-card <?= $status === '' ? 'is-active' : ''; ?>" href="my.php">
    <span class="stat-number"><?= $total_semua; ?></span>
    <span class="stat-label">Semua</span>
  </a>
  <a class="stat-card <?= $status === 'open' ? 'is-active' : ''; ?>" href="my.php?status=open">
    <span class="stat-number"><?= (int) $ringkasan['open']; ?></span>
    <span class="stat-label">Open</span>
  </a>
  <a class="stat-card <?= $status === 'in_progress' ? 'is-active' : ''; ?>" href="my.php?status=in_progress">
    <span class="stat-number"><?= (int) $ringkasan['in_progress']; ?></span>
    <span class="stat-label">In Progress</span>
  </a>
  <a class="stat-card <?= $status === 'done' ? 'is-active' : ''; ?>" href="my.php?status=done">
    <span class="stat-number"><?= (int) $ringkasan['done']; ?></span>
    <span class="stat-label">Selesai</span>
  </a>
  <a class="stat-card <?= $status === 'closed' ? 'is-active' : ''; ?>" href="my.php?status=closed">
    <span class="stat-number"><?= (int) $ringkasan['closed']; ?></span>
    <span class="stat-label">Ditutup</span>
  </a>
</div>

<?php if (empty($daftar_project)): ?>
  <p class="kosong">
    <?= $status === '' ? 'Kamu belum punya project.' : 'Tidak ada project dengan status ini.'; ?><br>
    <a class="btn btn-primary" href="create.php">Buat project pertamamu</a>
  </p>
<?php else: ?>
  <div class="project-grid">
    <?php foreach ($daftar_project as $project): ?>
      <article class="project-card">
        <a class="card-link" href="show.php?id=<?= (int) $project['project_id']; ?>">
          <span class="card-tab"><?= e($project['category_name']); ?></span>

          <div class="card-body">
            <h3><?= e($project['title']); ?></h3>
            <p class="card-desc"><?= e($project['description']); ?></p>

            <div class="card-meta">
              <span class="card-budget"><?= e(format_rupiah($project['budget'])); ?></span>
              <span>
                <span class="badge badge-<?= e($project['status']); ?>">
                  <?= e(status_label($project['status'])); ?>
                </span>
                · <?= (int) $project['jumlah_pelamar']; ?> pelamar
                (<?= (int) $project['pelamar_pending']; ?> pending)
              </span>
              <span>Deadline: <?= $project['deadline'] ? e(format_tanggal($project['deadline'])) : 'bebas'; ?></span>
            </div>
          </div>
        </a>

        <!-- Tombol aksi owner (terpisah dari kartu supaya tidak nested link) -->
        <div class="card-actions">
          <a class="btn btn-sm btn-outline" href="edit.php?id=<?= (int) $project['project_id']; ?>">Edit</a>

          <?php if ($project['status'] === 'open'): ?>
            <form method="POST" action="edit.php" data-confirm="Tutup project ini?">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
              <input type="hidden" name="aksi" value="close">
              <button class="btn btn-sm btn-outline" type="submit">Tutup</button>
            </form>
          <?php elseif ($project['status'] === 'closed'): ?>
            <form method="POST" action="edit.php" data-confirm="Buka kembali project ini?">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
              <input type="hidden" name="aksi" value="reopen">
              <button class="btn btn-sm btn-outline" type="submit">Buka</button>
            </form>
          <?php elseif ($project['status'] === 'in_progress'): ?>
            <form method="POST" action="mark_done.php" data-confirm="Tandai project ini sebagai selesai (Done)?">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
              <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
              <button class="btn btn-sm btn-primary" type="submit">Tandai Selesai</button>
            </form>
          <?php endif; ?>

          <form method="POST" action="delete.php"
                data-confirm="Hapus project ini beserta seluruh lamarannya?">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <input type="hidden" name="project_id" value="<?= (int) $project['project_id']; ?>">
            <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
