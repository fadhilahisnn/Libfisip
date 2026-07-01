<?php
session_start();
require_once 'config.php';

if (isLoggedIn()) { header('Location: index.php'); exit; }

$error = '';
$success = '';
$prodis = [
    'Ilmu Informasi dan Perpustakaan',
    'Sosiologi','Ilmu Politik','Administrasi Publik',
    'Administrasi Bisnis','Antropologi',
    'Ilmu Komunikasi','Hubungan Internasional',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $nim      = trim($_POST['nim'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $prodi    = $_POST['prodi'] ?? '';
    $pass     = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$nama||!$nim||!$email||!$prodi||!$pass) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($pass !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $db = getDB();
        $cek = $db->prepare("SELECT id FROM users WHERE nim=? OR email=?");
        $cek->execute([$nim, $email]);
        if ($cek->fetch()) {
            $error = 'NIM atau email sudah terdaftar.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $ins  = $db->prepare("INSERT INTO users (nim,nama,email,prodi,password) VALUES (?,?,?,?,?)");
            $ins->execute([$nim,$nama,$email,$prodi,$hash]);
            redirect('login.php', 'Akun berhasil dibuat! Silakan masuk. ✦');
        }
    }
}

$pageTitle = 'Daftar Anggota';
include 'includes/header.php';
?>
<main>
<div class="auth-wrapper">
  <div class="auth-card" style="max-width:520px;">
    <div class="auth-hero">
      <div class="auth-logo"><span>Lib</span>FISIP</div>
      <div class="auth-tagline">Daftar sebagai anggota Perpustakaan FISIP UNAIR</div>
    </div>
    <div class="auth-tabs">
      <a class="auth-tab" href="login.php">Masuk</a>
      <a class="auth-tab active" href="register.php">Daftar Anggota</a>
    </div>
    <div class="auth-body">
      <?php if($error): ?>
        <div class="form-error" style="margin-bottom:1rem;">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" class="modal-form">
        <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input class="form-input" type="text" name="nama" placeholder="Nama sesuai KTM" value="<?= htmlspecialchars($_POST['nama']??'') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">NIM</label>
            <input class="form-input" style="font-family:'Space Mono',monospace;" type="text" name="nim" placeholder="071911133090" value="<?= htmlspecialchars($_POST['nim']??'') ?>" required>
            <span class="form-hint">NIM = ID anggota perpustakaan</span>
          </div>
          <div class="form-group">
            <label class="form-label">Program Studi</label>
            <select class="form-input" name="prodi" required>
              <option value="">Pilih Prodi</option>
              <?php foreach($prodis as $p): ?>
                <option value="<?= $p ?>" <?= ($_POST['prodi']??'')===$p?'selected':'' ?>><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Universitas</label>
          <input class="form-input" type="email" name="email" placeholder="nama@student.unair.ac.id" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <input class="form-input" type="password" name="password" placeholder="Min. 8 karakter" required>
          </div>
          <div class="form-group">
            <label class="form-label">Konfirmasi Password</label>
            <input class="form-input" type="password" name="confirm" placeholder="Ulangi password" required>
          </div>
        </div>
        <button type="submit" class="modal-submit">Daftar Sekarang ✦</button>
        <p style="font-size:0.72rem;color:var(--muted);text-align:center;">Dengan mendaftar, kamu menyetujui syarat & ketentuan LibFISIP</p>
      </form>
    </div>
  </div>
</div>
</main>
<?php include 'includes/footer.php'; ?>
