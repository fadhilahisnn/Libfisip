<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak. Anda bukan admin.','error'); }

$db = getDB();

// Fetch data
$conversations = $db->query("SELECT * FROM chatbot_conversations ORDER BY created_at DESC LIMIT 200")->fetchAll();

// Stats
$stats = [
    'total_conversations' => $db->query("SELECT COUNT(*) FROM chatbot_conversations")->fetchColumn(),
    'today_conversations' => $db->query("SELECT COUNT(*) FROM chatbot_conversations WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];

$pageTitle = 'Riwayat Chatbot AI';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-content">
  <!-- Page Title -->
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">🤖 Riwayat Chatbot AI</h1>
      <p class="page-sub">Lihat log percakapan pengguna dengan AI Asisten Virtual</p>
    </div>
    <div class="page-actions">
      <a href="../index.php" class="adm-btn adm-btn-ghost">🌐 Lihat Situs</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="stat-grid" style="grid-template-columns: repeat(2, 1fr);">
    <div class="stat-card stat-blue">
      <div class="stat-icon">📊</div>
      <div class="stat-body">
        <div class="stat-val"><?= $stats['total_conversations'] ?></div>
        <div class="stat-lbl">Total Percakapan</div>
      </div>
    </div>
    <div class="stat-card stat-green">
      <div class="stat-icon">📅</div>
      <div class="stat-body">
        <div class="stat-val"><?= $stats['today_conversations'] ?></div>
        <div class="stat-lbl">Percakapan Hari Ini</div>
      </div>
    </div>
  </div>

  <!-- Conversations -->
  <div class="adm-form-card">
    <h3 class="adm-form-title">Log Percakapan Terakhir</h3>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th width="150">Waktu</th>
            <th>Pertanyaan User</th>
            <th>Jawaban AI</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($conversations as $conv): ?>
          <tr>
            <td class="cell-sub"><?= date('d M Y H:i', strtotime($conv['created_at'])) ?></td>
            <td style="max-width:300px; white-space: pre-wrap;"><strong><?= htmlspecialchars($conv['user_message']) ?></strong></td>
            <td style="max-width:400px; white-space: pre-wrap;" class="cell-sub"><?= nl2br(htmlspecialchars($conv['bot_response'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($conversations)): ?>
            <tr><td colspan="3" class="empty-row">Belum ada percakapan dengan AI</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>