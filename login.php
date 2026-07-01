<?php
session_start();
require_once 'config.php';
require_once 'includes/lang.php';

if (isLoggedIn()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nim  = trim($_POST['nim'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!$nim || !$pass) {
        $error = 'NIM dan password wajib diisi.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE nim = ? OR email = ?");
        $stmt->execute([$nim, $nim]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['nama']    = $user['nama'];
            $welcomeMsg = ($currentLang === 'en' ? 'Welcome, ' : 'Selamat datang, ') . $user['nama'] . '! 👋';
            if ($user['role'] === 'admin') {
                redirect('admin/index.php', $welcomeMsg);
            }
            $redirect = $_GET['redirect'] ?? 'index.php';
            redirect($redirect, $welcomeMsg);
        } else {
            $error = 'NIM atau password salah. Coba lagi.';
        }
    }
}

// Config for random books
$bookColors = ['#FF4D6D', '#FF758F', '#C9184A', '#800F2F', '#FFAFCC', '#FFC8DD', '#590D22'];
$bookTypes = ['normal', 'tall', 'short', 'thin', 'thick', 'stacked'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Ruang Baca FISIP UNAIR</title>
    <link rel="icon" href="assets/images/logo-rbc.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; background: #2b2b2b; font-family: 'Inter', sans-serif; }

        .auth-wrapper {
          position: relative;
          width: 100vw; height: 100vh;
          display: flex;
        }

        /* =========================================
           BACKGROUND: VECTOR BOOKSHELF 
           ========================================= */
        .bookshelf-bg {
          position: absolute; right: 0; top: 0; bottom: 0; width: 80vw;
          display: flex; flex-direction: column;
          background: #2b141e; /* dark burgundy background behind books */
          z-index: 1;
        }

        .shelf-row {
          flex: 1;
          display: flex; flex-direction: column; justify-content: flex-end;
          position: relative;
        }

        .shelf-plank {
          height: 18px;
          background: linear-gradient(to bottom, #a3415c 0%, #822e44 100%);
          border-bottom: 6px solid #5e1c30;
          position: relative; z-index: 2;
          box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        /* Wood grain */
        .shelf-plank::after {
          content: ''; position: absolute; inset: 0;
          background-image: repeating-linear-gradient(90deg, transparent, transparent 40px, rgba(0,0,0,0.1) 40px, rgba(0,0,0,0.1) 80px);
          opacity: 0.5;
        }

        .shelf-books {
          display: flex; align-items: flex-end; padding: 0 20px;
          gap: 2px; position: relative; z-index: 1;
          height: 100%; overflow: hidden;
        }

        .bk {
          border-radius: 3px 3px 0 0;
          position: relative;
          box-shadow: inset 4px 0 0 rgba(255,255,255,0.1), inset -4px 0 0 rgba(0,0,0,0.1);
          display: flex; flex-direction: column; align-items: center; justify-content: space-evenly;
        }
        .bk::after {
          /* Book shadow */
          content: ''; position: absolute; left: 100%; bottom: 0; top: 20%; width: 10px;
          background: linear-gradient(to right, rgba(0,0,0,0.3), transparent);
          pointer-events: none;
        }

        /* Decor inside books */
        .bk-line { width: 100%; height: 4px; background: rgba(0,0,0,0.15); }
        .bk-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.3); }

        /* Types */
        .bk-normal { width: 35px; height: 70%; }
        .bk-tall { width: 38px; height: 85%; }
        .bk-short { width: 30px; height: 55%; }
        .bk-thin { width: 22px; height: 75%; }
        .bk-thick { width: 48px; height: 80%; }

        .bk-stacked-wrap {
          display: flex; flex-direction: column; justify-content: flex-end; gap: 2px;
          margin: 0 5px;
        }
        .bk-h {
          height: 25px; width: 60px; border-radius: 2px;
          box-shadow: inset 0 4px 0 rgba(255,255,255,0.1), inset 0 -4px 0 rgba(0,0,0,0.1);
          display: flex; align-items: center; justify-content: space-evenly;
        }
        .bk-h .bk-line { width: 4px; height: 100%; }

        /* =========================================
           FOREGROUND: WAVY WHITE OVERLAY 
           ========================================= */
        .wavy-wrapper {
          position: absolute; left: 0; top: 0; bottom: 0; width: 100vw;
          filter: drop-shadow(10px 0 20px rgba(0,0,0,0.3));
          z-index: 10; pointer-events: none;
        }
        
        .fill-top {
          position: absolute; left: -10vw; top: 0; height: 5.1vh; width: 45vw;
          background: #FFF; pointer-events: auto;
        }
        
        .finger {
          position: absolute; left: -10vw; background: #FFF; pointer-events: auto;
          border-radius: 0 999px 999px 0; /* Perfect half-circle protrusion */
        }
        
        .gap {
          position: absolute; left: -10vw; pointer-events: auto;
        }
        .gap::before {
          content: ''; position: absolute; left: 0; top: 0; bottom: 0;
          right: var(--bite-radius);
          background: #FFF;
        }
        .gap::after {
          content: ''; position: absolute; right: 0; top: 0; bottom: 0;
          width: var(--bite-radius);
          /* Perfect half-circle inward cutout */
          background: radial-gradient(circle at 100% 50%, transparent calc(var(--bite-radius) - 0.5px), #FFF var(--bite-radius));
        }

        /* Floating white decor circles */
        .decor-circle {
          position: absolute; background: #FFF; border-radius: 50%;
        }

        /* =========================================
           LOGIN FORM 
           ========================================= */
        .auth-form-container {
          position: absolute; left: 6vw; top: 50%; transform: translateY(-50%);
          width: 100%; max-width: 440px; z-index: 20; pointer-events: auto;
        }

        .auth-logo { margin-bottom: 0.8rem; display: flex; align-items: center; gap: 15px; }
        .auth-logo img { height: 60px; width: auto; }
        .auth-logo-text { display: flex; flex-direction: column; line-height: 1.1; }
        .auth-logo-text .title { font-size: 2.1rem; font-weight: 900; color: #800F2F; letter-spacing: -0.5px; }
        .auth-logo-text .subtitle { font-size: 1rem; font-weight: 700; color: #FF4D6D; letter-spacing: 1.5px; }

        .auth-tagline { font-size: 1.05rem; color: #666; line-height: 1.5; margin-bottom: 3rem; font-weight: 500; }

        .form-error {
          background: #fff0f3; color: #c9184a; padding: 14px 18px;
          border-radius: 8px; font-size: 0.95rem; font-weight: 600;
          margin-bottom: 1.8rem; border-left: 4px solid #c9184a;
        }
        .form-group { margin-bottom: 1.6rem; }
        .form-label { display: block; font-size: 0.95rem; font-weight: 700; color: #333; margin-bottom: 8px; }
        .form-input {
          width: 100%; padding: 15px 18px; border: 2px solid #EEE;
          border-radius: 12px; font-family: inherit; font-size: 1.1rem;
          color: #333; background: #FAFAFA; transition: all 0.2s;
          box-sizing: border-box; font-weight: 600;
        }
        .form-input:focus {
          outline: none; border-color: #FF4D6D; background: white;
        }
        .form-input::placeholder { color: #AAA; font-weight: 500; }

        .auth-remember { display: flex; align-items: center; gap: 10px; font-size: 0.95rem; color: #555; cursor: pointer; margin-bottom: 2.5rem; font-weight: 600; }
        .auth-remember input { accent-color: #FF4D6D; width: 18px; height: 18px; cursor: pointer; }

        .auth-submit {
          width: 100%; padding: 16px; background: #FF4D6D;
          color: white; border: none; border-radius: 12px; font-family: inherit;
          font-weight: 800; font-size: 1.15rem; cursor: pointer; transition: all 0.2s;
        }
        .auth-submit:hover { background: #c9184a; transform: translateY(-2px); }

        @media (max-width: 900px) {
          .bookshelf-bg { width: 100vw; }
          .wavy-wrapper { display: none; }
          .auth-form-container { left: 50%; transform: translate(-50%, -50%); max-width: 450px; padding: 40px; background: white; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        }
    </style>
</head>
<body>

<div class="auth-wrapper">
  
  <!-- The Background Bookshelf -->
  <div class="bookshelf-bg">
    <?php for($row=0; $row<5; $row++): ?>
      <div class="shelf-row">
        <div class="shelf-books">
          <?php 
          // Generate ~30 books per row
          for($b=0; $b<30; $b++): 
            $color = $bookColors[array_rand($bookColors)];
            $type = $bookTypes[array_rand($bookTypes)];
            
            if($type === 'stacked'):
              $c1 = $bookColors[array_rand($bookColors)];
              $c2 = $bookColors[array_rand($bookColors)];
              $c3 = $bookColors[array_rand($bookColors)];
          ?>
              <div class="bk-stacked-wrap">
                <div class="bk-h" style="background:<?= $c1 ?>;"><div class="bk-line"></div></div>
                <div class="bk-h" style="background:<?= $c2 ?>;"><div class="bk-dot"></div></div>
                <div class="bk-h" style="background:<?= $c3 ?>;"><div class="bk-line"></div></div>
              </div>
          <?php else: ?>
              <div class="bk bk-<?= $type ?>" style="background:<?= $color ?>;">
                <?php if(rand(0,1)): ?><div class="bk-line"></div><?php endif; ?>
                <?php if(rand(0,1)): ?><div class="bk-dot"></div><?php endif; ?>
              </div>
          <?php endif; ?>
          <?php endfor; ?>
        </div>
        <div class="shelf-plank"></div>
      </div>
    <?php endfor; ?>
  </div>

  <!-- True Half-Circle Wavy Overlay -->
  <div class="wavy-wrapper">
    <div class="fill-top" style="height: 5.1vh;"></div>
    
    <div class="finger" style="top: 5vh; height: 12.1vh; width: 49vw;"></div>
    <div class="gap" style="top: 17vh; height: 10.1vh; width: 45vw; --bite-radius: 5.05vh;"></div>
    
    <div class="finger" style="top: 27vh; height: 16.1vh; width: 55vw;"></div>
    <div class="gap" style="top: 43vh; height: 12.1vh; width: 45vw; --bite-radius: 6.05vh;"></div>
    
    <div class="finger" style="top: 55vh; height: 18.1vh; width: 65vw;"></div>
    <div class="gap" style="top: 73vh; height: 10.1vh; width: 45vw; --bite-radius: 5.05vh;"></div>
    
    <div class="finger" style="top: 83vh; height: 17.1vh; width: 50vw;"></div>

    <div class="decor-circle" style="top: 20vh; left: 47vw; width: 45px; height: 45px;"></div>
    <div class="decor-circle" style="top: 75vh; left: 70vw; width: 65px; height: 65px;"></div>
  </div>

  <!-- The Login Form -->
  <div class="auth-form-container">
    <div class="auth-logo">
      <img src="assets/logo/img_logo_rbc.png" alt="RBC Logo">
      <div class="auth-logo-text">
        <span class="title">Ruang Baca</span>
        <span class="subtitle">FISIP UNAIR</span>
      </div>
    </div>
    <div class="auth-tagline">Masuk untuk mengakses layanan perpustakaan dan koleksi digital.</div>

    <?php if($error): ?>
      <div class="form-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">NIM / ID Anggota</label>
        <input class="form-input" style="font-family:'Space Mono',monospace;"
          type="text" name="nim"
          placeholder="Contoh: 071911133090"
          value="<?= htmlspecialchars($_POST['nim']??'') ?>"
          required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input class="form-input" type="password" name="password"
          placeholder="Masukkan password Anda" required>
      </div>
      <label class="auth-remember">
        <input type="checkbox" name="remember"> Ingat saya
      </label>
      <button type="submit" class="auth-submit">Masuk →</button>
    </form>
  </div>

</div>

<!-- Accessibility Widget -->
<script src="assets/accessibility.js"></script>

</body>
</html>
