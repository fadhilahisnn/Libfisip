<?php
// includes/header.php

// Include language system if not already included
if (!isset($currentLang)) {
    require_once __DIR__ . '/lang.php';
}

// Set HTML lang attribute based on current language
$htmlLang = $currentLang === 'en' ? 'en' : 'id';
?>
<!DOCTYPE html>
<html lang="<?= $htmlLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>Ruang Baca FISIP</title>
    <!-- Prevent flash of wrong theme -->
    <script>
      (function(){
        var t = localStorage.getItem('libfisip-theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
      })();
    </script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="style.css?v=<?= filemtime('style.css') ?>">
    
    <style>
    /* Language Switcher Styles */
    .lang-switcher-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    
    .lang-switcher-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 20px;
        color: inherit;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }
    
    .lang-switcher-btn:hover {
        background: rgba(255,255,255,0.15);
        border-color: rgba(255,255,255,0.3);
    }
    
    .lang-switcher-flag {
        font-size: 1.1rem;
    }
    
    .lang-switcher-arrow {
        font-size: 0.7rem;
        transition: transform 0.3s ease;
    }
    
    .lang-switcher-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        background: var(--card-bg, #fff);
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 12px;
        padding: 6px;
        min-width: auto;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1000;
        backdrop-filter: blur(20px);
    }
    
    [data-theme="dark"] .lang-switcher-dropdown {
        background: var(--card-bg, #1a1a2e);
        border: 1px solid rgba(255,255,255,0.15);
        box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    }
    
    .lang-switcher-wrap.active .lang-switcher-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .lang-switcher-wrap.active .lang-switcher-arrow {
        transform: rotate(180deg);
    }
    
    .lang-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 8px;
        color: var(--text-main, #333);
        text-decoration: none;
        transition: background 0.2s ease;
        cursor: pointer;
        font-size: 0.9rem;
    }
    
    .lang-option:hover {
        background: rgba(255,255,255,0.1);
    }
    
    .lang-option.active {
        background: rgba(255,255,255,0.15);
        font-weight: 500;
    }
    
    .lang-option-flag {
        font-size: 1.2rem;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .lang-switcher-btn {
            padding: 6px 8px;
        }
        
        .lang-switcher-text {
            display: none;
        }
    }
    
    /* Preloader Styles */
    #site-preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: var(--bg-color, #ffffff);
        z-index: 999999;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.6s cubic-bezier(0.8, 0, 0.2, 1), visibility 0.6s;
    }
    [data-theme="dark"] #site-preloader { background: #0f111a; }
    
    .preloader-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 25px;
    }
    
    .preloader-logo {
        width: 100px;
        height: auto;
        animation: pulse-logo 1.5s infinite ease-in-out alternate;
        filter: drop-shadow(0 10px 15px rgba(255, 77, 109, 0.2));
    }
    
    .preloader-bar {
        width: 180px;
        height: 6px;
        background: rgba(255, 77, 109, 0.15);
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    
    .preloader-progress {
        position: absolute;
        top: 0;
        left: -50%;
        height: 100%;
        width: 50%;
        background: linear-gradient(90deg, var(--accent, #FF4D6D), var(--accent2, #8338EC));
        border-radius: 10px;
        animation: loading-bar 1.5s cubic-bezier(0.65, 0, 0.35, 1) infinite;
        box-shadow: 0 0 10px rgba(255, 77, 109, 0.5);
    }
    
    .preloader-text {
        font-family: 'Space Mono', monospace;
        font-size: 0.85rem;
        color: var(--text-main, #333);
        letter-spacing: 3px;
        text-transform: uppercase;
        font-weight: 700;
        animation: pulse-text 1.5s infinite ease-in-out alternate;
    }
    [data-theme="dark"] .preloader-text { color: #f8f9fa; }
    
    @keyframes pulse-logo {
        0% { transform: scale(0.95) translateY(0); }
        100% { transform: scale(1.05) translateY(-5px); }
    }
    @keyframes pulse-text {
        0% { opacity: 0.5; }
        100% { opacity: 1; }
    }
    @keyframes loading-bar {
        0% { left: -50%; width: 30%; }
        50% { left: 30%; width: 70%; }
        100% { left: 100%; width: 30%; }
    }
    
    /* Hide preloader */
    body.loaded #site-preloader {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    body.loaded .preloader-logo {
        animation: none;
        transform: scale(1.2) translateY(-20px);
        opacity: 0;
        transition: all 0.5s ease;
    }
    </style>
</head>
<body>

<!-- Preloader -->
<div id="site-preloader">
    <div class="preloader-content">
        <img src="assets/logo/img_logo_rbc.png?v=1" alt="RBC Logo" class="preloader-logo">
        <div class="preloader-bar">
            <div class="preloader-progress"></div>
        </div>
        <div class="preloader-text">Memuat...</div>
    </div>
</div>

<header class="navbar">
    <div class="nav-container">
        <a href="index.php" class="nav-logo">
            <img src="assets/logo/img_logo_rbc.png?v=<?= time() ?>" alt="RBC Logo" style="height: 48px; width: auto; display: block; margin-right: 4px;">
            <div>
                <div class="logo-text">Ruang Baca</div>
                <div class="logo-sub">FISIP UNAIR</div>
            </div>
        </a>
        <nav class="nav-links">
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"><?= t('nav_home', 'Beranda') ?></a>
            
            <div class="nav-item-dropdown">
                <a href="#" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['tentang_profil.php', 'visi_misi.php', 'struktur_organisasi.php']) ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 4px;">
                    <?= t('nav_about', 'Tentang Kami') ?> <span style="font-size: 0.7em;">▼</span>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="tentang_profil.php" class="<?= basename($_SERVER['PHP_SELF']) == 'tentang_profil.php' ? 'active' : '' ?>"><?= t('nav_about_profile', 'Profil & Identitas') ?></a>
                    <a href="visi_misi.php" class="<?= basename($_SERVER['PHP_SELF']) == 'visi_misi.php' ? 'active' : '' ?>"><?= t('nav_about_vision', 'Visi dan Misi') ?></a>
                    <a href="struktur_organisasi.php" class="<?= basename($_SERVER['PHP_SELF']) == 'struktur_organisasi.php' ? 'active' : '' ?>"><?= t('nav_about_structure', 'Struktur Organisasi') ?></a>
                </div>
            </div>

            <div class="nav-item-dropdown">
                <a href="#" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['search.php', 'booktalk.php', 'usulan.php', 'repository.php']) ? 'active' : '' ?>" style="display: flex; align-items: center; gap: 4px;">
                    <?= t('nav_services', 'Layanan Pustaka') ?> <span style="font-size: 0.7em;">▼</span>
                </a>
                <div class="nav-dropdown-menu">
                    <a href="search.php" class="<?= basename($_SERVER['PHP_SELF']) == 'search.php' ? 'active' : '' ?>"><?= t('nav_collection', 'Koleksi') ?></a>
                    <a href="repository.php" class="<?= basename($_SERVER['PHP_SELF']) == 'repository.php' ? 'active' : '' ?>"><?= t('nav_repository', 'FISIP Repository') ?> <span style="background:var(--accent); color:white; padding:1px 5px; border-radius:8px; font-size:0.6rem; margin-left:3px;">New</span></a>
                    <a href="booktalk.php" class="<?= basename($_SERVER['PHP_SELF']) == 'booktalk.php' ? 'active' : '' ?>"><?= t('nav_book_talk', 'Book Talk') ?></a>
                    <a href="usulan.php" class="<?= basename($_SERVER['PHP_SELF']) == 'usulan.php' ? 'active' : '' ?>"><?= t('nav_suggestion', 'Usulan') ?></a>
                </div>
            </div>
            
            <a href="ruangan.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ruangan.php' ? 'active' : '' ?>"><?= t('nav_borrow_room', 'Pinjam Ruang') ?></a>
        </nav>
        <div class="nav-actions">
            <!-- Search Icon Toggle -->
            <button type="button" class="header-search-btn" id="headerSearchIconBtn" aria-label="Search" style="background:transparent; border:none; color:var(--text-main); cursor:pointer; font-size:1.2rem; padding: 6px; display: flex; align-items: center; justify-content: center; transition: color 0.3s;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path></svg>
            </button>
            
            <!-- Language Switcher -->
            <div class="lang-switcher-wrap" id="langSwitcher">
                <button type="button" class="lang-switcher-btn" id="langSwitcherBtn" aria-label="<?= t('lang_switch', 'Bahasa') ?>" title="<?= getLangName($currentLang) ?>">
                    <span class="lang-switcher-flag" style="font-size: 1.3rem;"><?= getLangFlag($currentLang) ?></span>
                    <span class="lang-switcher-arrow">▼</span>
                </button>
                <div class="lang-switcher-dropdown">
                    <?php foreach (AVAILABLE_LANGS as $l): ?>
                        <a href="<?= langUrl($l) ?>" class="lang-option <?= $l === $currentLang ? 'active' : '' ?>" title="<?= getLangName($l) ?>" style="justify-content: center;">
                            <span class="lang-option-flag" style="font-size: 1.3rem;"><?= getLangFlag($l) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Theme Toggle -->
            <div class="theme-toggle-wrap" data-tooltip="<?= t('header_theme_tooltip', 'Mode Terang') ?>" id="themeToggleWrap">
                <button type="button" class="theme-toggle" id="themeToggle" aria-label="Ganti tema gelap/terang">
                    <span class="theme-toggle-thumb"></span>
                </button>
            </div>
            <?php if(isLoggedIn()): $u = currentUser(); ?>
                <a href="rakku.php" class="nav-icon" title="<?= t('nav_my_shelf', 'Rak Buku Saya') ?>">🎒</a>
                <div class="user-popup-wrap" id="userPopupWrap" style="position: relative;">
                    <button type="button" class="nav-user-badge" id="userPopupBtn" style="border:none; background:transparent; cursor: pointer; display: flex; align-items: center; gap: 8px; padding: 4px 8px; border-radius: 20px; transition: background 0.2s;">
                        <?php if(!empty($u['foto']) && file_exists($u['foto'])): ?>
                            <img src="<?= htmlspecialchars($u['foto']) ?>" class="nav-user-avatar" style="object-fit: cover; width: 32px; height: 32px; border-radius: 50%;" alt="Profile">
                        <?php else: ?>
                            <span class="nav-user-avatar" style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--accent); color: white; font-weight: bold; font-size: 0.9rem;"><?= strtoupper(substr($u['nama'],0,1)) ?></span>
                        <?php endif; ?>
                        <span class="nav-user-name" style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars(explode(' ',$u['nama'])[0]) ?></span>
                    </button>
                    
                    <div class="user-popup-menu" id="userPopupMenu" style="position: absolute; top: calc(100% + 15px); right: 0; background: var(--card-bg, #fff); border: 1px solid rgba(0,0,0,0.1); border-radius: 16px; padding: 16px; min-width: 260px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); opacity: 0; visibility: hidden; transition: all 0.2s ease; z-index: 1000; display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; flex-direction: column; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--border); margin-bottom: 8px;">
                            <?php if(!empty($u['foto']) && file_exists($u['foto'])): ?>
                                <img src="<?= htmlspecialchars($u['foto']) ?>" style="object-fit: cover; width: 64px; height: 64px; border-radius: 50%; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" alt="Profile">
                            <?php else: ?>
                                <div style="width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--accent); color: white; font-size: 1.8rem; font-weight: bold; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                    <?= strtoupper(substr($u['nama'],0,1)) ?>
                                </div>
                            <?php endif; ?>
                            <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-main); text-align: center; word-break: break-word;"><?= htmlspecialchars($u['nama']) ?></div>
                            <?php if(!empty($u['email'])): ?>
                                <div style="font-size: 0.9rem; color: var(--text-secondary); text-align: center; margin-top: 2px; word-break: break-all;"><?= htmlspecialchars($u['email']) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($u['nim'])): ?>
                                <div style="font-size: 0.85rem; color: var(--muted); text-align: center; margin-top: 2px;"><?= htmlspecialchars($u['nim']) ?></div>
                            <?php endif; ?>
                            <div style="font-size: 0.85rem; background: var(--bg-secondary); padding: 4px 12px; border-radius: 20px; font-weight: 600; color: var(--text-secondary); text-transform: capitalize; margin-top: 8px;"><?= htmlspecialchars($u['role']) ?></div>
                        </div>
                        
                        <a href="profile.php" class="user-popup-link" style="display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: var(--text-main); text-decoration: none; border-radius: 8px; transition: background 0.2s; font-weight: 500;">
                            <span style="font-size: 1.2rem; opacity: 0.8;">⚙️</span>
                            <span><?= t('nav_profile_settings', 'Pengaturan Profil') ?></span>
                        </a>
                        <a href="logout.php" class="user-popup-link" style="display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: #e74c3c; text-decoration: none; border-radius: 8px; transition: background 0.2s; font-weight: 500;">
                            <span style="font-size: 1.2rem; opacity: 0.8;">🚪</span>
                            <span><?= t('nav_logout', 'Keluar') ?></span>
                        </a>
                    </div>
                </div>
                <style>
                    .user-popup-wrap.active .user-popup-menu {
                        opacity: 1 !important;
                        visibility: visible !important;
                        top: calc(100% + 5px) !important;
                    }
                    .user-popup-wrap.active #userPopupBtn {
                        background: var(--bg-secondary) !important;
                    }
                    .user-popup-link:hover {
                        background: var(--bg-secondary);
                    }
                </style>
            <?php else: ?>
                <a href="login.php" class="btn btn-glass"><?= t('nav_login', 'Masuk') ?></a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Full Width Search Panel -->
    <div class="header-search-panel" id="headerSearchPanel">
        <div class="nav-container" style="padding-top: 15px; padding-bottom: 15px;">
            <form action="search.php" method="GET" class="header-search-form">
                <input type="text" name="q" placeholder="<?= t('search_placeholder_header', 'Ketik kata kunci...') ?>" class="header-search-input" id="headerSearchInput">
                <button type="submit" class="header-search-submit" aria-label="Search" style="color: #fff;">
                    <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="M21 21l-4.35-4.35"></path></svg>
                </button>
                <button type="button" class="header-search-close" id="headerSearchCloseBtn" aria-label="Close" style="color: #fff;">
                    <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"></path></svg>
                </button>
            </form>
        </div>
    </div>
</header>

<script>
// Language Switcher Toggle
(function() {
    var switcher = document.getElementById('langSwitcher');
    var btn = document.getElementById('langSwitcherBtn');
    
    // Toggle dropdown on click
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        switcher.classList.toggle('active');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!switcher.contains(e.target)) {
            switcher.classList.remove('active');
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            switcher.classList.remove('active');
        }
    });
})();

// User Popup Toggle
(function() {
    var wrap = document.getElementById('userPopupWrap');
    if(!wrap) return;
    var btn = document.getElementById('userPopupBtn');
    
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        wrap.classList.toggle('active');
    });
    
    document.addEventListener('click', function(e) {
        if (!wrap.contains(e.target)) {
            wrap.classList.remove('active');
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            wrap.classList.remove('active');
        }
    });
})();

// Search Panel Toggle
(function() {
    var searchBtn = document.getElementById('headerSearchIconBtn');
    var searchPanel = document.getElementById('headerSearchPanel');
    var searchClose = document.getElementById('headerSearchCloseBtn');
    var searchInput = document.getElementById('headerSearchInput');

    if(searchBtn && searchPanel && searchClose) {
        searchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchPanel.classList.toggle('active');
            if(searchPanel.classList.contains('active')) {
                setTimeout(function(){ searchInput.focus(); }, 100);
            }
        });

        searchClose.addEventListener('click', function(e) {
            e.preventDefault();
            searchPanel.classList.remove('active');
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchPanel.classList.remove('active');
            }
        });
    }
})();

// Theme Toggle
(function() {
  var toggle = document.getElementById('themeToggle');
  var wrap = document.getElementById('themeToggleWrap');

  function updateTooltip() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    wrap.setAttribute('data-tooltip', isDark ? '<?= addslashes(t('header_theme_dark_tooltip')) ?>' : '<?= addslashes(t('header_theme_light_tooltip')) ?>');
  }

  toggle.addEventListener('click', function() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('libfisip-theme', 'light');
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('libfisip-theme', 'dark');
    }
    updateTooltip();
  });

  updateTooltip();
})();

// Preloader Logic
window.addEventListener('load', function() {
    // Artificial delay set to 3 seconds as requested
    setTimeout(function() {
        document.body.classList.add('loaded');
        // Remove from DOM completely after fade out animation
        setTimeout(function() {
            var preloader = document.getElementById('site-preloader');
            if (preloader) preloader.remove();
        }, 600);
    }, 3000); // 3 detik
});
</script>