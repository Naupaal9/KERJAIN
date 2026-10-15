<?php
// applications/apply.php — Mahasiswa melamar project berstatus open.
// Validasi ketat di sisi server: role, kepemilikan, status project, dan unique lamaran.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

$user = current_user($pdo);
if (!$user) {
  redirect('index.php');
}

// ID project wajib dari GET atau POST
$pid_raw = $_GET['project_id'] ?? ($_POST['project_id'] ?? '');
$project_id = (ctype_digit((string) $pid_raw) && (int) $pid_raw > 0) ? (int) $pid_raw : 0;

if ($project_id === 0) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/index.php');
}

// Ambil data project
$stmt = $pdo->prepare("
  SELECT project_id, owner_id, title, status, description, budget, deadline
  FROM projects
  WHERE project_id = ?
  LIMIT 1
");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
  flash('alert', 'Project tidak ditemukan.');
  redirect('projects/index.php');
}

$errors = [];
$input = [
  'cover_letter' => '',
  'proposed_fee' => '',
];

$is_owner = ((int) $user['user_id'] === (int) $project['owner_id']);
$is_mahasiswa = ($user['role'] === 'mahasiswa');

// Aturan akses aplikasi
if (!$is_mahasiswa) {
  flash('alert', 'Hanya mahasiswa yang dapat melamar project.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

if ($is_owner) {
  flash('alert', 'Kamu tidak boleh melamar project milikmu sendiri.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

if ($project['status'] !== 'open') {
  flash('alert', 'Project ini sudah tidak menerima lamaran baru.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

// Cek apakah sudah pernah melamar (unique project_id + applicant_id)
$cek_app = $pdo->prepare("SELECT application_id FROM applications WHERE project_id = ? AND applicant_id = ? LIMIT 1");
$cek_app->execute([$project_id, $user['user_id']]);
if ($cek_app->fetch()) {
  flash('alert', 'Kamu sudah pernah melamar project ini.');
  redirect('projects/show.php?id=' . (int) $project['project_id']);
}

// Proses form POST
if (is_post()) {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors['umum'] = 'Token keamanan tidak valid. Silakan coba lagi.';
  } else {
    $input['cover_letter'] = trim($_POST['cover_letter'] ?? '');
    $input['proposed_fee'] = trim($_POST['proposed_fee'] ?? '');

    // Validasi
    if ($input['cover_letter'] === '') {
      $errors['cover_letter'] = 'Surat pengantar wajib diisi.';
    }

    if ($input['proposed_fee'] === '') {
      $errors['proposed_fee'] = 'Penawaran biaya wajib diisi.';
    } elseif (!ctype_digit($input['proposed_fee'])) {
      $errors['proposed_fee'] = 'Penawaran biaya harus berupa angka bulat (tidak negatif, tanpa titik/koma).';
    } elseif ((int) $input['proposed_fee'] < 0) {
      $errors['proposed_fee'] = 'Penawaran biaya tidak boleh negatif.';
    } elseif (strlen($input['proposed_fee']) > 12) {
      $errors['proposed_fee'] = 'Penawaran biaya terlalu besar (maksimal 12 angka).';
    }

    if (empty($errors)) {
      try {
        $stmt = $pdo->prepare("
          INSERT INTO applications (project_id, applicant_id, cover_letter, proposed_fee, status)
          VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
          (int) $project_id,
          (int) $user['user_id'],
          $input['cover_letter'],
          (int) $input['proposed_fee'],
        ]);
        flash('success', 'Lamaran berhasil dikirim!');
        redirect('applications/my.php');
      } catch (PDOException $ex) {
        // Tangani unique constraint
        $msg = $ex->getMessage();
        if (strpos($msg, 'uq_one_application') !== false || strpos($msg, 'Duplicate entry') !== false) {
          $errors['umum'] = 'Kamu sudah pernah melamar project ini.';
        } else {
          $errors['umum'] = 'Lamaran gagal dikirim. Silakan coba lagi.';
        }
      }
    }
  }
}

$page_title = 'Lamar Project — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <p class="breadcrumb">
    <a href="../projects/index.php">Cari Project</a> /
    <a href="../projects/show.php?id=<?= (int) $project['project_id']; ?>">Detail</a> /
    Lamar
  </p>
  <h1>Lamar Project</h1>
  <p>Ajukan lamaran untuk: <strong><?= e($project['title']); ?></strong></p>
</header>

<div class="form-wrap">
  <div class="form-card">
    <?php if (!empty($errors['umum'])): ?>
      <p class="alert alert-error"><?= e($errors['umum']); ?></p>
    <?php endif; ?>

    <form method="POST" action="apply.php" data-validate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
      <input type="hidden" name="project_id" value="<?= (int) $project_id; ?>">

      <div class="form-group">
        <label class="form-label" for="cover_letter">Surat Pengantar <span class="wajib">*</span></label>
        <textarea class="form-control" id="cover_letter" name="cover_letter" maxlength="5000" required
                  placeholder="Jelaskan keahlianmu, pengalaman terkait, dan kenapa cocok untuk project ini..."><?= e($input['cover_letter']); ?></textarea>
        <span class="form-hint">Maksimal 5000 karakter.</span>
        <?php if (isset($errors['cover_letter'])): ?>
          <span class="form-error"><?= e($errors['cover_letter']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="proposed_fee">Penawaran Biaya (Rp) <span class="wajib">*</span></label>
        <input class="form-control" type="number" id="proposed_fee" name="proposed_fee"
               value="<?= e($input['proposed_fee']); ?>" min="0" step="1000" required
               placeholder="Contoh: 750000">
        <span class="form-hint">Angka bulat tanpa titik/koma.</span>
        <?php if (isset($errors['proposed_fee'])): ?>
          <span class="form-error"><?= e($errors['proposed_fee']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Kirim Lamaran</button>
        <a class="btn btn-outline" href="../projects/show.php?id=<?= (int) $project['project_id']; ?>">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
