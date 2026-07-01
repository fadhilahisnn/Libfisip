<?php
session_start();
require_once 'config.php';
require_once 'includes/lang.php';

$pageTitle = t('nav_about_structure', 'Struktur Organisasi');
include 'includes/header.php';
?>
<main style="padding: 0 !important; max-width: 100% !important;">
    <!-- Page Header -->
    <div class="hero modern-hero">
      <div class="hero-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
      </div>
      <div class="hero-inner" style="text-align: center; max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 40vh;">
        <div class="hero-tag" style="margin-bottom: 1.5rem;">✦ TENTANG KAMI</div>
        <h1 class="hero-title">
            <?= $currentLang === 'en' ? 'Organizational Structure' : 'Struktur Organisasi' ?>
        </h1>
        <p class="hero-subtitle">
            <?= $currentLang === 'en' ? 'Meet the management team behind FISIP UNAIR Reading Room.' : 'Berikut adalah struktur organisasi pengelola Ruang Baca FISIP UNAIR.' ?>
        </p>
      </div>
    </div>

    <!-- Image Card -->
    <div style="max-width: 900px; margin: -2rem auto 4rem; padding: 0 20px; position: relative; z-index: 10;">
        <div style="background: var(--card-bg); padding: 2.5rem; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border: 1px solid var(--border); overflow: hidden; position: relative;">
            
            <div style="border-radius: 16px; overflow: hidden; background: var(--bg-secondary); border: 1px solid var(--border); padding: 1rem; display: flex; justify-content: center; align-items: center; min-height: 300px;">
                <img src="assets/Struktur%20organisasi/img_struktur%20organisasi.png" alt="Struktur Organisasi" style="max-width: 100%; height: auto; display: block; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: transform 0.3s ease;" class="zoom-img">
            </div>
            
            <div style="text-align: center; margin-top: 2rem; padding-bottom: 0.5rem;">
                <p style="font-size: 1.1rem; color: var(--text-main); font-weight: 500;">
                    <?= $currentLang === 'en' ? 'We are always ready to serve your information and literature needs.' : 'Kami selalu siap melayani kebutuhan informasi dan literatur Anda.' ?>
                </p>
                <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap;">
                     <a href="tentang_profil.php" style="padding: 10px 20px; background: var(--bg-secondary); color: var(--text-main); border-radius: 12px; font-weight: 600; text-decoration: none; border: 1px solid var(--border); transition: all 0.2s;">Profil & Identitas</a>
                     <a href="visi_misi.php" style="padding: 10px 20px; background: rgba(255, 77, 109, 0.1); color: var(--accent); border-radius: 12px; font-weight: 600; text-decoration: none; border: 1px solid rgba(255, 77, 109, 0.2); transition: all 0.2s;">Visi & Misi</a>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    @media (max-width: 900px) {
        .profile-header h1 { font-size: 2.2rem !important; }
        .profile-header { padding: 3rem 1.5rem !important; }
    }
    .zoom-img:hover { transform: scale(1.02); }
    </style>
</main>
<?php include 'includes/footer.php'; ?>
