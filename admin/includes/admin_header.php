<?php
// admin/includes/admin_header.php
if (!defined('ABSPATH')) define('ABSPATH', true);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Prevent flash of wrong theme -->
  <script>
    (function(){
      var t = localStorage.getItem('libfisip-theme');
      if (t === 'dark') document.documentElement.setAttribute('data-theme','dark');
    })();
  </script>
  <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Admin LibFISIP' : 'Admin LibFISIP' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    /* ===== ADMIN LAYOUT ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #F3F4F6; color: #111827; display: flex; min-height: 100vh; overflow-x: hidden; }

    :root {
      --adm-sidebar: #0F172A;
      --adm-sidebar-hover: rgba(255,255,255,0.06);
      --adm-sidebar-active: rgba(255,77,109,0.15);
      --adm-accent: #FF4D6D;
      --adm-accent2: #590D22;
      --adm-white: #FFFFFF;
      --adm-border: #E5E7EB;
      --adm-muted: #6B7280;
      --adm-radius: 12px;
      --adm-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }

    /* Sidebar */
    .adm-sidebar {
      width: 260px;
      min-height: 100vh;
      background: var(--adm-sidebar);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0;
      z-index: 200;
      transition: transform 0.3s;
    }
    .adm-sidebar-brand {
      padding: 1.8rem 1.5rem 1.2rem;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .adm-brand-logo {
      font-size: 1.4rem;
      font-weight: 900;
      color: #fff;
      letter-spacing: -0.5px;
      line-height: 1;
    }
    .adm-brand-logo span { color: var(--adm-accent); }
    .adm-brand-sub {
      font-size: 0.68rem;
      color: rgba(255,255,255,0.4);
      font-weight: 600;
      letter-spacing: 1.5px;
      margin-top: 4px;
    }
    .adm-nav { padding: 0.5rem 0; flex: 1; overflow-y: auto; }

    /* === Standalone nav item (Dashboard, Lihat Situs) === */
    .adm-nav-standalone {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0.65rem 1.5rem;
      color: rgba(255,255,255,0.65);
      font-size: 0.875rem;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.2s;
      border-left: 3px solid transparent;
      cursor: pointer;
    }
    .adm-nav-standalone:hover { background: var(--adm-sidebar-hover); color: #fff; }
    .adm-nav-standalone.active { background: var(--adm-sidebar-active); color: var(--adm-accent); border-left-color: var(--adm-accent); font-weight: 700; }
    .adm-nav-standalone .ns-icon { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }
    .adm-nav-standalone .ns-label { flex: 1; }
    .adm-nav-standalone .ns-arrow {
      font-style: normal; font-size: 0.8rem;
      color: rgba(255,255,255,0.2);
      transition: transform 0.2s, color 0.2s;
    }
    .adm-nav-standalone:hover .ns-arrow { color: rgba(255,255,255,0.5); transform: translateX(2px); }
    .adm-nav-standalone.active .ns-arrow { color: var(--adm-accent); transform: translateX(2px); }

    /* === Collapsible group === */
    .nav-group { margin-bottom: 2px; }
    .nav-group-header {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0.7rem 1.5rem;
      color: rgba(255,255,255,0.75);
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      user-select: none;
      transition: all 0.2s;
      border-left: 3px solid transparent;
    }
    .nav-group-header:hover { background: var(--adm-sidebar-hover); color: #fff; }
    .nav-group-header.open { color: #fff; }
    .nav-group-header.has-active { border-left-color: rgba(255,77,109,0.4); }
    .nav-group-icon { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; }
    .nav-group-label { flex: 1; }
    .nav-group-chevron {
      font-style: normal;
      font-size: 0.75rem;
      color: rgba(255,255,255,0.3);
      transition: transform 0.25s ease, color 0.2s;
      line-height: 1;
    }
    .nav-group-header.open .nav-group-chevron { transform: rotate(90deg); color: rgba(255,255,255,0.6); }
    .nav-group-header.has-active .nav-group-chevron { color: rgba(255,77,109,0.6); }

    /* Sub-menu container */
    .nav-group-body {
      overflow: hidden;
      max-height: 0;
      transition: max-height 0.3s ease;
      background: rgba(0,0,0,0.15);
    }
    .nav-group-body.open { max-height: 600px; }

    /* Sub-menu item */
    .nav-sub-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 0.5rem 1.5rem 0.5rem 2.8rem;
      color: rgba(255,255,255,0.5);
      font-size: 0.82rem;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.15s;
      border-left: 3px solid transparent;
      position: relative;
    }
    .nav-sub-item::before {
      content: '»';
      font-size: 0.7rem;
      color: rgba(255,255,255,0.2);
      flex-shrink: 0;
      transition: color 0.15s;
    }
    .nav-sub-item:hover { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.85); }
    .nav-sub-item:hover::before { color: rgba(255,255,255,0.5); }
    .nav-sub-item.active {
      color: var(--adm-accent);
      background: rgba(255,77,109,0.08);
      border-left-color: var(--adm-accent);
      font-weight: 700;
    }
    .nav-sub-item.active::before { color: var(--adm-accent); }
    .adm-sidebar-footer {
      padding: 1rem 1.5rem;
      border-top: 1px solid rgba(255,255,255,0.06);
    }
    .adm-user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .adm-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--adm-accent), var(--adm-accent2)); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 0.85rem; flex-shrink: 0; }
    .adm-user-name { font-size: 0.82rem; font-weight: 700; color: #fff; }
    .adm-user-role { font-size: 0.7rem; color: rgba(255,255,255,0.4); }
    .adm-logout { display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.5); font-size: 0.82rem; text-decoration: none; transition: color 0.2s; padding: 6px 0; }
    .adm-logout:hover { color: var(--adm-accent); }

    /* Main area */
    .adm-main { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    /* Top bar */
    .adm-topbar {
      background: #fff;
      border-bottom: 1px solid var(--adm-border);
      padding: 0.9rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .adm-topbar-left { display: flex; align-items: center; gap: 12px; }
    .adm-topbar-right { display: flex; align-items: center; gap: 12px; }
    .adm-search { display: flex; align-items: center; background: #F9FAFB; border: 1px solid var(--adm-border); border-radius: 8px; padding: 6px 12px; gap: 8px; }
    .adm-search input { border: none; background: transparent; outline: none; font-size: 0.85rem; width: 200px; font-family: inherit; color: #111827; }
    .adm-topbar-date { font-size: 0.8rem; color: var(--adm-muted); }

    /* Content */
    .admin-content { flex: 1; padding: 2rem; }
    .page-title-bar { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 2rem; gap: 1rem; flex-wrap: wrap; }
    .page-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.5px; }
    .page-sub { color: var(--adm-muted); font-size: 0.88rem; margin-top: 4px; }
    .page-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

    /* Stat Grid */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.2rem; margin-bottom: 2rem; }
    .stat-card { background: #fff; border-radius: var(--adm-radius); padding: 1.4rem; display: flex; align-items: center; gap: 1rem; box-shadow: var(--adm-shadow); border: 1px solid var(--adm-border); transition: transform 0.2s, box-shadow 0.2s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
    .stat-icon { font-size: 2rem; width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-val { font-size: 1.7rem; font-weight: 800; line-height: 1; font-family: 'Space Mono', monospace; }
    .stat-lbl { font-size: 0.75rem; color: var(--adm-muted); font-weight: 600; margin-top: 3px; }
    .stat-blue .stat-icon { background: rgba(59,130,246,0.1); }
    .stat-blue .stat-val { color: #2563EB; }
    .stat-green .stat-icon { background: rgba(16,185,129,0.1); }
    .stat-green .stat-val { color: #059669; }
    .stat-yellow .stat-icon { background: rgba(245,158,11,0.1); }
    .stat-yellow .stat-val { color: #D97706; }
    .stat-red .stat-icon { background: rgba(239,68,68,0.1); }
    .stat-red .stat-val { color: #DC2626; }
    .stat-purple .stat-icon { background: rgba(139,92,246,0.1); }
    .stat-purple .stat-val { color: #7C3AED; }
    .stat-rose .stat-icon { background: rgba(255,77,109,0.1); }
    .stat-rose .stat-val { color: var(--adm-accent); }

    /* Dashboard columns */
    .dash-cols { display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem; }
    .dash-card { background: #fff; border-radius: var(--adm-radius); border: 1px solid var(--adm-border); box-shadow: var(--adm-shadow); overflow: hidden; }
    .dash-card-header { display: flex; align-items: center; justify-content: space-between; padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--adm-border); }
    .dash-card-title { font-size: 0.95rem; font-weight: 700; }
    .top-book-row { display: flex; align-items: center; gap: 10px; }
    .top-rank { width: 24px; height: 24px; border-radius: 50%; background: var(--adm-accent); color: #fff; font-size: 0.7rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

    /* Table */
    .adm-table-wrap { overflow-x: auto; }
    .adm-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
    .adm-table thead tr { background: #F9FAFB; }
    .adm-table th { padding: 10px 16px; text-align: left; font-size: 0.75rem; font-weight: 700; color: var(--adm-muted); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
    .adm-table td { padding: 12px 16px; border-top: 1px solid #F3F4F6; vertical-align: middle; }
    .adm-table tr:hover td { background: #FAFAFA; }
    .cell-title { font-weight: 600; font-size: 0.875rem; color: #111827; }
    .cell-sub { font-size: 0.75rem; color: var(--adm-muted); margin-top: 2px; }
    .empty-row { text-align: center; color: var(--adm-muted); padding: 2rem !important; font-style: italic; }
    .mini-cov { width: 28px; height: 38px; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 0.42rem; font-weight: 700; text-align: center; flex-shrink: 0; padding: 2px; line-height: 1.1; }

    /* Buttons */
    .adm-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; border: none; text-decoration: none; transition: all 0.2s; }
    .adm-btn-primary { background: var(--adm-accent); color: #fff; box-shadow: 0 4px 10px rgba(255,77,109,0.3); }
    .adm-btn-primary:hover { background: var(--adm-accent2); transform: translateY(-1px); }
    .adm-btn-secondary { background: #F3F4F6; color: #374151; }
    .adm-btn-secondary:hover { background: #E5E7EB; }
    .adm-btn-ghost { background: transparent; color: var(--adm-accent); font-size: 0.8rem; padding: 6px 12px; }
    .adm-btn-ghost:hover { background: rgba(255,77,109,0.08); }
    .adm-btn-danger { background: rgba(239,68,68,0.1); color: #DC2626; }
    .adm-btn-danger:hover { background: rgba(239,68,68,0.2); }
    .adm-btn-green { background: rgba(16,185,129,0.1); color: #059669; }
    .adm-btn-green:hover { background: rgba(16,185,129,0.2); }
    .adm-btn-sm { padding: 5px 10px; font-size: 0.78rem; }

    /* Badges */
    .adm-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
    .adm-badge-green { background: rgba(16,185,129,0.1); color: #059669; }
    .adm-badge-blue { background: rgba(59,130,246,0.1); color: #2563EB; }
    .adm-badge-red { background: rgba(239,68,68,0.1); color: #DC2626; }
    .adm-badge-yellow { background: rgba(245,158,11,0.1); color: #D97706; }
    .adm-badge-purple { background: rgba(139,92,246,0.1); color: #7C3AED; }
    .admin-badge { background: rgba(16,185,129,0.1); color: #059669; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 20px; }

    /* Forms */
    .adm-form-card { background: #fff; border-radius: var(--adm-radius); border: 1px solid var(--adm-border); box-shadow: var(--adm-shadow); padding: 2rem; }
    .adm-form-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--adm-border); }
    .adm-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .adm-form-group { margin-bottom: 1rem; }
    .adm-form-group.full { grid-column: 1 / -1; }
    .adm-label { display: block; font-size: 0.8rem; font-weight: 700; color: #374151; margin-bottom: 6px; }
    .adm-input { width: 100%; padding: 10px 12px; border: 1px solid var(--adm-border); border-radius: 8px; font-family: inherit; font-size: 0.88rem; transition: all 0.2s; background: #F9FAFB; color: #111827; }
    .adm-input:focus { outline: none; border-color: var(--adm-accent); box-shadow: 0 0 0 3px rgba(255,77,109,0.1); background: #fff; }
    .adm-hint { font-size: 0.72rem; color: var(--adm-muted); margin-top: 4px; }

    /* Filter bar */
    .filter-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 1.2rem; flex-wrap: wrap; }
    .filter-input { padding: 8px 14px; border: 1px solid var(--adm-border); border-radius: 8px; font-family: inherit; font-size: 0.85rem; background: #fff; color: #111827; outline: none; transition: border-color 0.2s; }
    .filter-input:focus { border-color: var(--adm-accent); }

    /* Alert / Flash */
    .adm-alert { padding: 12px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; margin-bottom: 1.2rem; }
    .adm-alert-success { background: rgba(16,185,129,0.1); color: #059669; border: 1px solid rgba(16,185,129,0.2); }
    .adm-alert-error { background: rgba(239,68,68,0.1); color: #DC2626; border: 1px solid rgba(239,68,68,0.2); }

    /* Responsive */
    @media (max-width: 1024px) {
      .dash-cols { grid-template-columns: 1fr; }
      .adm-form-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .adm-sidebar { transform: translateX(-100%); }
      .adm-main { margin-left: 0; }
      .stat-grid { grid-template-columns: repeat(2, 1fr); }
    }

    /* ===== ADMIN DARK THEME ===== */
    [data-theme="dark"] body {
      background: var(--bg-color);
      color: var(--text-main);
    }

    /* Sidebar — already dark, minor tweaks for consistency */
    [data-theme="dark"] .adm-sidebar {
      background: #0a0c14;
      border-right: 1px solid rgba(255,255,255,0.04);
    }

    /* Main area */
    [data-theme="dark"] .adm-main {
      background: var(--bg-color);
    }

    /* Top bar */
    [data-theme="dark"] .adm-topbar {
      background: var(--card-bg);
      border-bottom-color: var(--border);
    }
    [data-theme="dark"] .adm-search {
      background: var(--bg-secondary);
      border-color: var(--border);
    }
    [data-theme="dark"] .adm-search input {
      color: var(--text-main);
    }
    [data-theme="dark"] .adm-search input::placeholder {
      color: var(--muted);
    }
    [data-theme="dark"] .adm-topbar-date {
      color: var(--muted);
    }

    /* Content text */
    [data-theme="dark"] .admin-content {
      color: var(--text-main);
    }
    [data-theme="dark"] .page-title {
      color: var(--text-main);
    }
    [data-theme="dark"] .page-sub {
      color: var(--muted);
    }

    /* Stat cards */
    [data-theme="dark"] .stat-card {
      background: var(--card-bg);
      border-color: var(--border);
      box-shadow: 0 2px 12px rgba(0,0,0,0.2);
    }
    [data-theme="dark"] .stat-card:hover {
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
    }
    [data-theme="dark"] .stat-lbl {
      color: var(--muted);
    }
    /* Brighten stat values for dark bg */
    [data-theme="dark"] .stat-blue .stat-val { color: #60A5FA; }
    [data-theme="dark"] .stat-green .stat-val { color: #34D399; }
    [data-theme="dark"] .stat-yellow .stat-val { color: #FBBF24; }
    [data-theme="dark"] .stat-red .stat-val { color: #F87171; }
    [data-theme="dark"] .stat-purple .stat-val { color: #A78BFA; }
    [data-theme="dark"] .stat-rose .stat-val { color: #FF8FA3; }
    [data-theme="dark"] .stat-blue .stat-icon { background: rgba(59,130,246,0.15); }
    [data-theme="dark"] .stat-green .stat-icon { background: rgba(16,185,129,0.15); }
    [data-theme="dark"] .stat-yellow .stat-icon { background: rgba(245,158,11,0.15); }
    [data-theme="dark"] .stat-red .stat-icon { background: rgba(239,68,68,0.15); }
    [data-theme="dark"] .stat-purple .stat-icon { background: rgba(139,92,246,0.15); }
    [data-theme="dark"] .stat-rose .stat-icon { background: rgba(255,77,109,0.15); }

    /* Dashboard cards */
    [data-theme="dark"] .dash-card {
      background: var(--card-bg);
      border-color: var(--border);
      box-shadow: 0 2px 12px rgba(0,0,0,0.2);
    }
    [data-theme="dark"] .dash-card-header {
      border-bottom-color: var(--border);
    }
    [data-theme="dark"] .dash-card-title {
      color: var(--text-main);
    }

    /* Tables */
    [data-theme="dark"] .adm-table thead tr {
      background: var(--bg-secondary);
    }
    [data-theme="dark"] .adm-table th {
      color: var(--muted);
    }
    [data-theme="dark"] .adm-table td {
      border-top-color: var(--border);
      color: var(--text-main);
    }
    [data-theme="dark"] .adm-table tr:hover td {
      background: rgba(255,255,255,0.03);
    }
    [data-theme="dark"] .cell-title {
      color: var(--text-main);
    }
    [data-theme="dark"] .cell-sub {
      color: var(--muted);
    }
    [data-theme="dark"] .empty-row {
      color: var(--muted);
    }

    /* Badges — brighten for dark bg */
    [data-theme="dark"] .adm-badge-green { background: rgba(16,185,129,0.18); color: #34D399; }
    [data-theme="dark"] .adm-badge-blue { background: rgba(59,130,246,0.18); color: #60A5FA; }
    [data-theme="dark"] .adm-badge-red { background: rgba(239,68,68,0.18); color: #F87171; }
    [data-theme="dark"] .adm-badge-yellow { background: rgba(245,158,11,0.18); color: #FBBF24; }
    [data-theme="dark"] .adm-badge-purple { background: rgba(139,92,246,0.18); color: #A78BFA; }
    [data-theme="dark"] .admin-badge { background: rgba(16,185,129,0.18); color: #34D399; }
    [data-theme="dark"] .status-available { background: rgba(16,185,129,0.18); color: #34D399; }
    [data-theme="dark"] .status-borrowed { background: rgba(239,68,68,0.18); color: #F87171; }

    /* Buttons */
    [data-theme="dark"] .adm-btn-secondary {
      background: var(--bg-secondary);
      color: var(--text-secondary, #DDE1E6);
    }
    [data-theme="dark"] .adm-btn-secondary:hover {
      background: var(--border);
    }
    [data-theme="dark"] .adm-btn-danger { background: rgba(239,68,68,0.15); color: #F87171; }
    [data-theme="dark"] .adm-btn-danger:hover { background: rgba(239,68,68,0.25); }
    [data-theme="dark"] .adm-btn-green { background: rgba(16,185,129,0.15); color: #34D399; }
    [data-theme="dark"] .adm-btn-green:hover { background: rgba(16,185,129,0.25); }

    /* Form fields */
    [data-theme="dark"] .adm-form-card {
      background: var(--card-bg);
      border-color: var(--border);
    }
    [data-theme="dark"] .adm-form-title {
      color: var(--text-main);
      border-bottom-color: var(--border);
    }
    [data-theme="dark"] .adm-label {
      color: var(--text-secondary, #DDE1E6);
    }
    [data-theme="dark"] .adm-input {
      background: var(--input-bg);
      border-color: var(--border);
      color: var(--text-main);
    }
    [data-theme="dark"] .adm-input:focus {
      background: var(--bg-secondary);
    }
    [data-theme="dark"] .adm-hint {
      color: var(--muted);
    }

    /* Filter bar */
    [data-theme="dark"] .filter-bar .filter-input {
      background: var(--bg-secondary);
      border-color: var(--border);
      color: var(--text-main);
    }

    /* Alerts */
    [data-theme="dark"] .adm-alert-success {
      background: rgba(16,185,129,0.12);
      color: #34D399;
      border-color: rgba(16,185,129,0.25);
    }
    [data-theme="dark"] .adm-alert-error {
      background: rgba(239,68,68,0.12);
      color: #F87171;
      border-color: rgba(239,68,68,0.25);
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="adm-sidebar">
  <div class="adm-sidebar-brand">
    <div class="adm-brand-logo" style="display: flex; align-items: center; gap: 8px;">
      <img src="../assets/logo/img_logo_rbc.png?v=<?= time() ?>" alt="Logo" style="height: 48px; width: auto; display: block;">
      <div><span>Lib</span>FISIP</div>
    </div>
    <div class="adm-brand-sub">ADMIN PANEL</div>
  </div>
<?php
  $inAkuisisi  = in_array($currentPage, ['akuisisi.php','tambah_buku.php','usulan_koleksi.php']);
  $inKatalog   = in_array($currentPage, ['entri_katalog.php','koleksi.php']);
  $inLayanan   = in_array($currentPage, ['sirkulasi.php','anggota.php','denda.php']);
  $inKonten    = in_array($currentPage, ['reviews.php','chatbot.php','livechat.php']);
  $inRuangan   = in_array($currentPage, ['ruangan.php','tambah_ruangan.php','edit_ruangan.php']);
?>
  <nav class="adm-nav">

    <!-- Dashboard (standalone) -->
    <a href="index.php" class="adm-nav-standalone <?= $currentPage==='index.php'?'active':'' ?>">
      <span class="ns-icon">📊</span>
      <span class="ns-label">Dashboard</span>
      <i class="ns-arrow">›</i>
    </a>
    
    <!-- Presensi (standalone) -->
    <a href="presensi.php" class="adm-nav-standalone <?= $currentPage==='presensi.php'?'active':'' ?>">
      <span class="ns-icon">📸</span>
      <span class="ns-label">Presensi Jaga</span>
      <i class="ns-arrow">›</i>
    </a>

    <!-- Akuisisi group -->
    <div class="nav-group">
      <div class="nav-group-header <?= $inAkuisisi ? 'open has-active' : '' ?>" onclick="toggleGroup(this)">
        <span class="nav-group-icon">📥</span>
        <span class="nav-group-label">Akuisisi</span>
        <i class="nav-group-chevron">›</i>
      </div>
      <div class="nav-group-body <?= $inAkuisisi ? 'open' : '' ?>">
        <a href="akuisisi.php" class="nav-sub-item <?= $currentPage==='akuisisi.php'?'active':'' ?>">Daftar Koleksi</a>
        <a href="tambah_buku.php" class="nav-sub-item <?= $currentPage==='tambah_buku.php'?'active':'' ?>">Entri Koleksi</a>
        <a href="usulan_koleksi.php" class="nav-sub-item <?= $currentPage==='usulan_koleksi.php'?'active':'' ?>">Usulan Koleksi</a>
      </div>
    </div>

    <!-- Katalog group -->
    <div class="nav-group">
      <div class="nav-group-header <?= $inKatalog ? 'open has-active' : '' ?>" onclick="toggleGroup(this)">
        <span class="nav-group-icon">📖</span>
        <span class="nav-group-label">Katalog</span>
        <i class="nav-group-chevron">›</i>
      </div>
      <div class="nav-group-body <?= $inKatalog ? 'open' : '' ?>">
        <a href="entri_katalog.php" class="nav-sub-item <?= $currentPage==='entri_katalog.php'?'active':'' ?>">Entri Katalog (RDA)</a>
        <a href="koleksi.php" class="nav-sub-item <?= $currentPage==='koleksi.php'?'active':'' ?>">Daftar Katalog</a>
      </div>
    </div>

    <!-- Layanan / Sirkulasi group -->
    <div class="nav-group">
      <div class="nav-group-header <?= $inLayanan ? 'open has-active' : '' ?>" onclick="toggleGroup(this)">
        <span class="nav-group-icon">🔄</span>
        <span class="nav-group-label">Sirkulasi</span>
        <i class="nav-group-chevron">›</i>
      </div>
      <div class="nav-group-body <?= $inLayanan ? 'open' : '' ?>">
        <a href="sirkulasi.php" class="nav-sub-item <?= $currentPage==='sirkulasi.php'?'active':'' ?>">Peminjaman Buku</a>
        <a href="anggota.php" class="nav-sub-item <?= $currentPage==='anggota.php'?'active':'' ?>">Data Anggota</a>
        <a href="denda.php" class="nav-sub-item <?= $currentPage==='denda.php'?'active':'' ?>">Laporan Denda</a>
      </div>
    </div>

    <!-- Peminjaman Ruang (standalone) -->
    <a href="ruangan.php" class="adm-nav-standalone <?= $inRuangan ? 'active' : '' ?>">
      <span class="ns-icon">🏛️</span>
      <span class="ns-label">Peminjaman Ruang</span>
      <i class="ns-arrow">›</i>
    </a>

    <!-- Konten group -->
    <div class="nav-group">
      <div class="nav-group-header <?= $inKonten ? 'open has-active' : '' ?>" onclick="toggleGroup(this)">
        <span class="nav-group-icon">💬</span>
        <span class="nav-group-label">Konten</span>
        <i class="nav-group-chevron">›</i>
      </div>
      <div class="nav-group-body <?= $inKonten ? 'open' : '' ?>">
        <a href="reviews.php" class="nav-sub-item <?= $currentPage==='reviews.php'?'active':'' ?>">Moderasi Review</a>
        <a href="chatbot.php" class="nav-sub-item <?= $currentPage==='chatbot.php'?'active':'' ?>">Kelola Chatbot</a>
        <a href="livechat.php" class="nav-sub-item <?= $currentPage==='livechat.php'?'active':'' ?>">Live Chat <span style="background:#ef4444;color:white;padding:2px 6px;border-radius:10px;font-size:0.7rem;margin-left:5px;">New</span></a>
      </div>
    </div>

    <!-- Lihat Situs (standalone) -->
    <a href="../index.php" class="adm-nav-standalone">
      <span class="ns-icon">🌐</span>
      <span class="ns-label">Lihat Situs</span>
      <i class="ns-arrow">›</i>
    </a>

  </nav>

  <script>
  function toggleGroup(header) {
    const body = header.nextElementSibling;
    const isOpen = header.classList.contains('open');
    header.classList.toggle('open', !isOpen);
    body.classList.toggle('open', !isOpen);
  }
  </script>

  <div class="adm-sidebar-footer">
    <a href="../profile.php" class="adm-user-info" style="text-decoration: none; cursor: pointer;">
      <div class="adm-avatar"><?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div class="adm-user-name"><?= htmlspecialchars($_SESSION['nama'] ?? 'Admin') ?></div>
        <div class="adm-user-role">Administrator</div>
      </div>
    </a>
    <a href="../logout.php" class="adm-logout">🚪 Logout dari Admin</a>
  </div>
</aside>

<!-- Main -->
<div class="adm-main">
  <!-- Top Bar -->
  <div class="adm-topbar">
    <div class="adm-topbar-left">
      <div class="adm-search">
        <span>🔍</span>
        <input type="text" placeholder="Cari buku, anggota..." id="topbarSearch">
      </div>
    </div>
    <div class="adm-topbar-right">
      <div class="adm-topbar-date"><?= date('l, d M Y') ?></div>
    </div>
  </div>

<?php
// Flash message
if (isset($_SESSION['flash_msg'])) {
    $type = $_SESSION['flash_type'] ?? 'success';
    $cls = $type === 'success' ? 'adm-alert-success' : 'adm-alert-error';
    echo "<div class='admin-content' style='padding-bottom:0'><div class='adm-alert $cls'>".htmlspecialchars($_SESSION['flash_msg'])."</div></div>";
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}
?>
