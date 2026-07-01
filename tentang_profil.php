<?php
session_start();
require_once 'config.php';
require_once 'includes/lang.php';

$pageTitle = t('nav_about_profile', 'Profil & Identitas');
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
            <?= $currentLang === 'en' ? 'Profile & Identity' : 'Profil & Identitas Perpustakaan' ?>
        </h1>
        <p class="hero-subtitle">
            <?= $currentLang === 'en' ? 'Get to know Ruang Baca FISIP Universitas Airlangga closer as a center for academic literature and an inclusive learning environment.' : 'Mengenal lebih dekat Ruang Baca FISIP Universitas Airlangga sebagai pusat literatur akademik mahasiswa dan ruang belajar yang inklusif bagi seluruh sivitas akademika.' ?>
        </p>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="profile-grid" style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 2rem; align-items: start; max-width: 1200px; margin: 0 auto 4rem; padding: 0 20px;">
        
        <!-- Left: Main Profile Text -->
        <div style="background: var(--card-bg); padding: 3rem; border-radius: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border: 1px solid var(--border); position: relative; overflow: hidden;">
            <div style="font-size: 1.15rem; line-height: 1.8; color: var(--text-secondary); text-align: center; position: relative; z-index: 1;">
                <?php if ($currentLang === 'en'): ?>
                    <p style="margin-bottom: 1.5rem;"><strong style="color: var(--accent); font-weight: 700; font-size: 1.2rem;">The Reading Room of the Faculty of Social and Political Sciences (FISIP), Universitas Airlangga</strong> is an academic support facility that provides comprehensive literature collections in the fields of social and political sciences. Since its establishment, it has served as the intellectual heart of the faculty, nurturing critical thinking and academic excellence.</p>
                    <p style="margin-bottom: 1.5rem;">Located strategically on the 2nd floor of Building A201 FISIP UNAIR, this library offers a modern, comfortable, and highly inclusive environment. We are dedicated to supporting the university's tridharma activities by providing fast, precise, and accurate access to information, while ensuring that all users, regardless of background or physical abilities, have equitable access to our resources.</p>
                    <p style="margin-bottom: 1.5rem;">Our collections encompass thousands of titles ranging from classical sociological theories, modern political science, international relations, communication studies, anthropology, to public administration. Furthermore, we provide access to high-impact international journals and digital repositories that are crucial for student research and faculty publications.</p>
                    <p style="margin-bottom: 0;">Beyond just books, the FISIP Reading Room is a collaborative space. We continuously strive to innovate, adopting the latest digital library technologies, organizing academic discussions, and fostering a vibrant scholarly community. Whether you are seeking a quiet corner for deep work or a dynamic space for group discussions, you will find your place here.</p>
                <?php else: ?>
                    <p style="margin-bottom: 1.5rem;"><strong style="color: var(--accent); font-weight: 700; font-size: 1.2rem;">Ruang Baca FISIP Universitas Airlangga</strong> merupakan fasilitas penunjang akademik utama yang menyediakan koleksi literatur komprehensif di bidang ilmu sosial dan ilmu politik. Sejak awal berdirinya, Ruang Baca ini telah berfungsi sebagai jantung intelektual fakultas, menumbuhkan pemikiran kritis dan keunggulan akademik bagi para mahasiswanya.</p>
                    <p style="margin-bottom: 1.5rem;">Berlokasi strategis di lantai 2 Gedung A201 FISIP UNAIR, perpustakaan ini menawarkan lingkungan yang modern, nyaman, dan sangat inklusif. Kami berdedikasi tinggi untuk mendukung kegiatan tridharma perguruan tinggi dengan menyediakan akses informasi yang cepat, akurat, dan terpercaya. Kami senantiasa memastikan bahwa seluruh pengguna, tanpa memandang latar belakang maupun kondisi fisik, mendapatkan akses dan pelayanan yang setara.</p>
                    <p style="margin-bottom: 1.5rem;">Koleksi kami mencakup ribuan judul yang membentang dari teori sosiologi klasik, ilmu politik modern, hubungan internasional, ilmu komunikasi, antropologi, hingga administrasi publik. Lebih dari itu, kami memfasilitasi akses ke jurnal-jurnal internasional bereputasi tinggi serta repositori digital yang sangat vital untuk keperluan riset mahasiswa dan publikasi dosen.</p>
                    <p style="margin-bottom: 0;">Ruang Baca FISIP bukan sekadar tempat menyimpan buku, melainkan ruang kolaborasi yang hidup. Kami terus berupaya berinovasi dengan mengadopsi teknologi perpustakaan digital terkini, menyelenggarakan diskusi akademik mingguan, dan membangun komunitas ilmiah yang dinamis. Baik Anda mencari sudut yang tenang untuk fokus belajar, maupun ruang interaktif untuk diskusi kelompok, Ruang Baca FISIP selalu siap menyambut Anda.</p>
                <?php endif; ?>
            </div>
            <!-- Decorative Accent line -->
            <div style="position: absolute; left: 0; top: 0; width: 8px; height: 100%; background: linear-gradient(to bottom, var(--accent), var(--accent2)); border-radius: 24px 0 0 24px;"></div>
        </div>

        <!-- Right: Operational Hours -->
        <div style="background: var(--card-bg); padding: 2.5rem; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid var(--border); display: flex; flex-direction: column; gap: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 15px; border-bottom: 1px solid var(--border); padding-bottom: 1.2rem;">
                <div style="width: 55px; height: 55px; background: rgba(255, 77, 109, 0.1); color: var(--accent); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; box-shadow: inset 0 0 0 1px rgba(255, 77, 109, 0.2);">⏱️</div>
                <div>
                    <h3 style="font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0; letter-spacing: -0.5px;">
                        <?= $currentLang === 'en' ? 'Operational Hours' : 'Jam Operasional' ?>
                    </h3>
                    <div style="font-size: 0.85rem; color: var(--muted); margin-top: 2px;">
                        <?= $currentLang === 'en' ? 'Visit us during these times' : 'Kunjungi kami pada jam berikut' ?>
                    </div>
                </div>
            </div>
            
            <?php if ($currentLang === 'en'): ?>
            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: var(--bg-secondary); border-radius: 14px; border: 1px solid var(--border); transition: transform 0.2s;">
                    <strong style="color: var(--text-main); font-size: 0.95rem;">Monday - Friday</strong>
                    <span style="background: var(--text-main); color: var(--card-bg); padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">08:00 - 16:30</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: var(--bg-secondary); border-radius: 14px; border: 1px solid var(--border); transition: transform 0.2s;">
                    <strong style="color: var(--text-main); font-size: 0.95rem;">Saturday</strong>
                    <span style="background: var(--text-main); color: var(--card-bg); padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">08:00 - 12:00</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: rgba(231, 76, 60, 0.05); border-radius: 14px; border: 1px solid rgba(231, 76, 60, 0.2);">
                    <strong style="color: #e74c3c; font-size: 0.95rem;">Sunday & Holidays</strong>
                    <span style="background: rgba(231, 76, 60, 0.1); color: #e74c3c; padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">Closed</span>
                </div>
            </div>
            <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: var(--bg-secondary); border-radius: 14px; border: 1px solid var(--border); transition: transform 0.2s;">
                    <strong style="color: var(--text-main); font-size: 0.95rem;">Senin - Jumat</strong>
                    <span style="background: var(--text-main); color: var(--card-bg); padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">08.00 - 16.30</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: var(--bg-secondary); border-radius: 14px; border: 1px solid var(--border); transition: transform 0.2s;">
                    <strong style="color: var(--text-main); font-size: 0.95rem;">Sabtu</strong>
                    <span style="background: var(--text-main); color: var(--card-bg); padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">08.00 - 12.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; background: rgba(231, 76, 60, 0.05); border-radius: 14px; border: 1px solid rgba(231, 76, 60, 0.2);">
                    <strong style="color: #e74c3c; font-size: 0.95rem;">Minggu & Libur Nasional</strong>
                    <span style="background: rgba(231, 76, 60, 0.1); color: #e74c3c; padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">Tutup</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    @media (max-width: 900px) {
        .profile-grid { grid-template-columns: 1fr !important; }
        .profile-header h1 { font-size: 2.2rem !important; }
        .profile-header { padding: 3rem 1.5rem !important; }
    }
    .profile-grid > div:hover {
        transform: translateY(-5px);
        transition: transform 0.3s ease;
    }
    </style>
</main>
<?php include 'includes/footer.php'; ?>
