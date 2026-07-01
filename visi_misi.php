<?php
session_start();
require_once 'config.php';
require_once 'includes/lang.php';

$pageTitle = t('nav_about_vision', 'Visi dan Misi');
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
            <?= $currentLang === 'en' ? 'Vision and Mission' : 'Visi dan Misi' ?>
        </h1>
        <p class="hero-subtitle">
            <?= $currentLang === 'en' ? 'Our goals and commitments to building an inclusive and excellent digital library service.' : 'Tujuan dan komitmen kami dalam membangun layanan perpustakaan digital yang inklusif dan unggul.' ?>
        </p>
      </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 2.5rem; max-width: 900px; margin: 0 auto 4rem; padding: 0 20px;">
        
        <!-- Vision Card -->
        <div style="background: var(--card-bg); padding: 3rem; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border: 1px solid var(--border); position: relative; overflow: hidden; transition: transform 0.3s ease;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 1.5rem; position: relative; z-index: 1;">
                <div style="width: 55px; height: 55px; background: rgba(255, 77, 109, 0.1); color: var(--accent); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: inset 0 0 0 1px rgba(255, 77, 109, 0.2);">🔭</div>
                <h2 style="font-size: 2rem; font-weight: 800; color: var(--text-main); margin: 0; letter-spacing: -0.5px;"><?= $currentLang === 'en' ? 'Vision' : 'Visi' ?></h2>
            </div>
            
            <p style="font-size: 1.25rem; line-height: 1.8; color: var(--text-secondary); margin: 0; text-align: center; position: relative; z-index: 1; font-weight: 500;">
                <?php if ($currentLang === 'en'): ?>
                To become a highly inclusive, leading information and knowledge service center in the fields of social and political sciences, supporting the realization of FISIP UNAIR as a center of educational excellence at national and international levels by serving all users without exception.
                <?php else: ?>
                Menjadi pusat layanan informasi dan pengetahuan yang sangat inklusif dan terkemuka di bidang ilmu sosial dan ilmu politik, mendukung terwujudnya FISIP UNAIR sebagai institusi pendidikan unggul di tingkat nasional maupun internasional dengan melayani pengguna tanpa terkecuali.
                <?php endif; ?>
            </p>
            <!-- Accent Background -->
            <div style="position: absolute; right: -5%; top: -10%; width: 200px; height: 200px; background: radial-gradient(circle, rgba(255,77,109,0.05) 0%, transparent 70%); z-index: 0;"></div>
            <div style="position: absolute; left: 0; top: 0; width: 8px; height: 100%; background: var(--accent); border-radius: 24px 0 0 24px;"></div>
        </div>

        <!-- Mission Card -->
        <div style="background: var(--card-bg); padding: 3rem; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border: 1px solid var(--border); position: relative; overflow: hidden; transition: transform 0.3s ease;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 1.5rem; position: relative; z-index: 1;">
                <div style="width: 55px; height: 55px; background: rgba(131, 56, 236, 0.1); color: var(--accent2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: inset 0 0 0 1px rgba(131, 56, 236, 0.2);">🚀</div>
                <h2 style="font-size: 2rem; font-weight: 800; color: var(--text-main); margin: 0; letter-spacing: -0.5px;"><?= $currentLang === 'en' ? 'Mission' : 'Misi' ?></h2>
            </div>
            
            <ol class="mission-list" style="font-size: 1.15rem; line-height: 1.8; color: var(--text-secondary); margin: 0; padding-left: 1.5rem; position: relative; z-index: 1;">
                <?php if ($currentLang === 'en'): ?>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Providing relevant, up-to-date, and high-quality library collections.</li>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Delivering excellent and highly inclusive information services to all library users regardless of their background and abilities.</li>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Delivering information services based on cutting-edge information and communication technology.</li>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Creating an equitable, comfortable and conducive reading room environment for learning activities.</li>
                    <li style="margin-bottom: 0; padding-left: 0.5rem;">Establishing partnerships with various institutions to develop information networks.</li>
                <?php else: ?>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Menyediakan koleksi bahan pustaka yang relevan, mutakhir, dan berkualitas tinggi.</li>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Berupaya untuk menyediakan dan melayani pengguna tanpa terkecuali, mengedepankan nilai inklusivitas dan aksesibilitas untuk semua.</li>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Memberikan layanan informasi prima berbasis teknologi informasi dan komunikasi mutakhir.</li>
                    <li style="margin-bottom: 1rem; padding-left: 0.5rem;">Menciptakan lingkungan ruang baca yang adil, nyaman, dan kondusif untuk segala kegiatan akademik sivitas.</li>
                    <li style="margin-bottom: 0; padding-left: 0.5rem;">Menjalin kerja sama yang kuat dengan berbagai pihak untuk mengembangkan jejaring pengetahuan dan informasi.</li>
                <?php endif; ?>
            </ol>
            <!-- Accent Background -->
            <div style="position: absolute; right: -5%; bottom: -10%; width: 200px; height: 200px; background: radial-gradient(circle, rgba(131,56,236,0.05) 0%, transparent 70%); z-index: 0;"></div>
            <div style="position: absolute; left: 0; top: 0; width: 8px; height: 100%; background: var(--accent2); border-radius: 24px 0 0 24px;"></div>
        </div>

    </div>
    
    <style>
    @media (max-width: 900px) {
        .profile-header h1 { font-size: 2.2rem !important; }
        .profile-header { padding: 3rem 1.5rem !important; }
    }
    .mission-list li::marker {
        color: var(--accent2);
        font-weight: bold;
    }
    </style>
</main>
<?php include 'includes/footer.php'; ?>
