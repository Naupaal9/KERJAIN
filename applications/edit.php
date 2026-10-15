<?php
// applications/edit.php — Edit lamaran milik sendiri, hanya jika status masih pending.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php'; // sudah berisi session_start()

require_login();

$user = current_user($pdo);
if (!$user) {
  redirect('index.php');
}

$app_id_raw = $_GET['id'] ?? ($_POST['application_id'] ?? '');
$app_id = (ctype_digit((string) $app_id_raw) && (int) $app_id_raw > 0) ? (int) $app_id_raw : 0;

if ($app_id === 0) {
  flash('alert', 'Lamaran tidak ditemukan.');
  redirect('applications/my.php');
}

// Ambil lamaran + data project
$stmt = $pdo->prepare("
  SELECT a.application_id, a.project_id, a.applicant_id, a.cover_letter, a.proposed_fee, a.status,
         p.title AS project_title, p.status AS project_status
  FROM applications a
  JOIN projects p ON a.project_id = p.project_id
  WHERE a.application_id = ?
  LIMIT 1
");
$stmt->execute([$app_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
  flash('alert', 'Lamaran tidak ditemukan.');
  redirect('applications/my.php');
}

// Cek kepemilikan
if ((int) $app['applicant_id'] !== (int) $user['user_id']) {
  flash('alert', 'Kamu tidak boleh mengedit lamaran orang lain.');
  redirect('applications/my.php');
}

// Hanya bisa edit jika status pending
if ($app['status'] !== 'pending') {
  flash('alert', 'Lamaran yang sudah diterima/ditolak tidak dapat diedit.');
  redirect('applications/my.php');
}

// Project harus masih open untuk menerima perubahan? Spec: "Tolak edit jika proyek sudah tidak menerima lamaran atau status lamaran sudah berubah."
if ($app['project_status'] !== 'open') {
  flash('alert', 'Project sudah tidak menerima lamaran baru.');
  redirect('applications/my.php');
}

$errors = [];
$input = [
  'cover_letter' => $app['cover_letter'],
  'proposed_fee' => (string) $app['proposed_fee'],
];

if (is_post()) {
  if (!csrf_check($_POST['csrf_token'] ?? '')) {
    $errors['umum'] = 'Token keamanan tidak valid. Silakan coba lagi.';
  } else {
    $input['cover_letter'] = trim($_POST['cover_letter'] ?? '');
    $input['proposed_fee'] = trim($_POST['proposed_fee'] ?? '');

    if ($input['cover_letter'] === '') {
      $errors['cover_letter'] = 'Surat pengantar wajib diisi.';
    }

    if ($input['proposed_fee'] === '') {
      $errors['proposed_fee'] = 'Penawaran biaya wajib diisi.';
    } elseif (!ctype_digit($input['proposed_fee'])) {
      $errors['proposed_fee'] = 'Penawaran biaya harus berupa angka bulat tanpa titik/koma dan tidak boleh negatif.';
    } elseif ((int) $input['proposed_fee'] < 0) {
      $errors['proposed_fee'] = 'Penawaran biaya tidak boleh negatif.';
    } elseif (strlen($input['proposed_fee']) > 12) {
      $errors['proposed_fee'] = 'Penawaran biaya terlalu besar (maksimal 12 angka).';
    }

    // Validasi ulang bahwa status belum berubah
    $stmt = $pdo->prepare("SELECT status FROM applications WHERE application_id = ?");
    $stmt->execute([$app_id]);
    $cur = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cur && $cur['status'] !== 'pending') {
      $errors['umum'] = 'Lamaran sudah tidak bisa diedit karena statusnya berubah.';
    }

    if (empty($errors)) {
      try {
        $stmt = $pdo->prepare("
          UPDATE applications
          SET cover_letter = ?, proposed_fee = ?
          WHERE application_id = ? AND applicant_id = ? AND status = 'pending'
        ");
        $stmt->execute([
          $input['cover_letter'],
          (int) $input['proposed_fee'],
          $app_id,
          (int) $user['user_id'],
        ]);
        flash('success', 'Lamaran berhasil diperbarui.');
        redirect('applications/my.php');
      } catch (PDOException $ex) {
        $errors['umum'] = 'Lamaran gagal diperbarui. Silakan coba lagi.';
      }
    }
  }
}

$page_title = 'Edit Lamaran — Kerjain!';
$base = '../';
require __DIR__ . '/../includes/header.php';
?>

<header class="page-header">
  <p class="breadcrumb">
    <a href="my.php">Lamaran Saya</a> / Edit
  </p>
  <h1>Edit Lamaran</h1>
  <p>Project: <strong><?= e($app['project_title']); ?></strong></p>
</header>

<div class="form-wrap">
  <div class="form-card">
    <?php if (!empty($errors['umum'])): ?>
      <p class="alert alert-error"><?= e($errors['umum']); ?></p>
    <?php endif; ?>

    <form method="POST" action="edit.php" data-validate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
      <input type="hidden" name="application_id" value="<?= (int) $app_id; ?>">

      <div class="form-group">
        <label class="form-label" for="cover_letter">Surat Pengantar <span class="wajib">*</span></label>
        <textarea class="form-control" id="cover_letter" name="cover_letter" maxlength="5000" required><?= e($input['cover_letter']); ?></textarea>
        <?php if (isset($errors['cover_letter'])): ?>
          <span class="form-error"><?= e($errors['cover_letter']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="proposed_fee">Penawaran Biaya (Rp) <span class="wajib">*</span></label>
        <input class="form-control" type="number" id="proposed_fee" name="proposed_fee"
               value="<?= e($input['proposed_fee']); ?>" min="0" step="1000" required>
        <?php if (isset($errors['proposed_fee'])): ?>
          <span class="form-error"><?= e($errors['proposed_fee']); ?></span>
        <?php endif; ?>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
        <a class="btn btn-outline" href="my.php">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
