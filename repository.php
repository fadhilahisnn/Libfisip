<?php
session_start();
require_once 'config.php';

$db = getDB();

// 1. Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS fisip_repository (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100) NOT NULL,
    nim VARCHAR(20) NOT NULL,
    prodi VARCHAR(100) NOT NULL,
    grade VARCHAR(5) NOT NULL,
    year INT NOT NULL,
    abstract TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. Seed data if empty
$stmt = $db->query("SELECT COUNT(*) FROM fisip_repository");
if ($stmt->fetchColumn() == 0) {
    $seedData = [
        ['Analisis Strategi Komunikasi Pemasaran Digital pada Startup di Surabaya', 'Andi Pratama', '071811533001', 'Ilmu Komunikasi', 'A', 2022, 'Penelitian ini membahas mengenai strategi pemasaran digital...'],
        ['Diplomasi Budaya Indonesia melalui Gastrodiplomasi di Australia', 'Budi Santoso', '071811233002', 'Ilmu Hubungan Internasional', 'A', 2022, 'Gastrodiplomasi menjadi instrumen penting dalam diplomasi...'],
        ['Partisipasi Pemilih Pemula dalam Pemilu Serentak 2024', 'Citra Kirana', '071911333003', 'Ilmu Politik', 'AB', 2023, 'Studi tentang seberapa besar tingkat partisipasi dari pemilih...'],
        ['Evaluasi Kebijakan Publik Smart City di Pemerintah Kota Surabaya', 'Dewi Lestari', '071911133004', 'Administrasi Publik', 'A', 2023, 'Evaluasi ini berfokus pada implementasi teknologi informasi...'],
        ['Perubahan Sosial Masyarakat Petani pasca Pembangunan Infrastruktur Desa', 'Eko Purnomo', '072011433005', 'Sosiologi', 'AB', 2024, 'Menganalisis dampak pembangunan infrastruktur terhadap pola...'],
        ['Makna Ritual Adat Ruwatan pada Masyarakat Tengger', 'Fitri Handayani', '072011733006', 'Antropologi', 'A', 2024, 'Eksplorasi mendalam terhadap ritual adat dan maknanya di masa...'],
        ['Manajemen Pengetahuan di Perpustakaan Perguruan Tinggi', 'Gilang Ramadhan', '072111633007', 'Ilmu Informasi dan Perpustakaan', 'A', 2024, 'Kajian tentang bagaimana manajemen pengetahuan diterapkan...'],
        ['Pergeseran Budaya Populer Korea di Kalangan Mahasiswa', 'Hani Nabila', '072111533008', 'Ilmu Komunikasi', 'AB', 2024, 'Studi fenomena hallyu dan pergeseran preferensi budaya...'],
        ['Peran ASEAN dalam Menangani Konflik di Myanmar', 'Iqbal Fahri', '072011233009', 'Ilmu Hubungan Internasional', 'A', 2023, 'Analisis peran organisasi regional dalam konflik negara anggotanya...'],
        ['Dinamika Koalisi Partai Politik Jelang Pilkada', 'Joko Susilo', '071911333010', 'Ilmu Politik', 'A', 2023, 'Fokus pada strategi partai dalam membangun koalisi daerah...'],
    ];
    
    $insertStmt = $db->prepare("INSERT INTO fisip_repository (title, author, nim, prodi, grade, year, abstract) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($seedData as $data) {
        $insertStmt->execute($data);
    }
}

$prodi_list = [
    'Semua Prodi',
    'Ilmu Komunikasi',
    'Ilmu Hubungan Internasional',
    'Ilmu Politik',
    'Administrasi Publik',
    'Sosiologi',
    'Antropologi',
    'Ilmu Informasi dan Perpustakaan'
];

$selected_prodi = $_GET['prodi'] ?? 'Semua Prodi';
$search = $_GET['q'] ?? '';

// 3. Fetch data based on filters
$sql = "SELECT * FROM fisip_repository WHERE 1=1";
$params = [];

if ($selected_prodi !== 'Semua Prodi') {
    $sql .= " AND prodi = ?";
    $params[] = $selected_prodi;
}

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR abstract LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY year DESC, id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$repositories = $stmt->fetchAll();

$pageTitle = "FISIP Repository";
include 'includes/header.php';
?>

<style>
.repo-header {
    background: linear-gradient(135deg, var(--bg-color) 0%, var(--bg-secondary) 100%);
    padding: 60px 0;
    text-align: center;
    border-bottom: 1px solid var(--border);
}

.repo-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 15px;
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.repo-subtitle {
    color: var(--text-secondary);
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

.repo-filters {
    background: var(--card-bg);
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    margin-top: -30px;
    position: relative;
    z-index: 10;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
    border: 1px solid var(--border);
}

.repo-filter-select, .repo-filter-input {
    padding: 12px 16px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--input-bg, #fff);
    color: var(--text-main);
    font-family: inherit;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s;
}

.repo-filter-select:focus, .repo-filter-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(255, 77, 109, 0.1);
}

.repo-filter-input {
    flex: 1;
    min-width: 250px;
}

.repo-filter-btn {
    background: var(--accent);
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.repo-filter-btn:hover {
    background: var(--accent2);
    transform: translateY(-2px);
}

.repo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-top: 40px;
    padding-bottom: 60px;
}

.repo-card {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
}

.repo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border-color: var(--accent);
}

.repo-grade-badge {
    position: absolute;
    top: 24px;
    right: 24px;
    background: linear-gradient(135deg, #10B981 0%, #059669 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-weight: 800;
    font-size: 0.85rem;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2);
}

.repo-card-inner {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.repo-prodi {
    color: var(--accent);
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    padding-right: 40px;
}

.repo-item-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.4;
    margin-bottom: 12px;
}

.repo-author {
    color: var(--text-secondary);
    font-size: 0.95rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.repo-abstract {
    color: var(--muted);
    font-size: 0.9rem;
    line-height: 1.6;
    margin-bottom: 20px;
    flex: 1;
}

.repo-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border);
    padding-top: 16px;
    margin-top: auto;
}

.repo-year {
    font-weight: 600;
    color: var(--text-secondary);
    background: var(--bg-secondary);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
}

.repo-action {
    color: var(--accent);
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
}

.repo-action:hover {
    color: var(--accent2);
    text-decoration: underline;
}

/* Animations */
.repo-card {
    animation: fadeIn 0.5s ease backwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Modal Styles */
.repo-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.repo-modal-overlay.active { display: flex; }
.repo-modal {
    background: var(--card-bg);
    border-radius: 16px;
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    position: relative;
    animation: modalSlide 0.3s ease;
}
@keyframes modalSlide {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.repo-modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.repo-modal-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.3;
    padding-right: 20px;
}
.repo-modal-close {
    background: var(--bg-secondary);
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    color: var(--text-main);
    transition: all 0.2s;
}
.repo-modal-close:hover {
    background: #ef4444;
    color: white;
}
.repo-modal-body {
    padding: 24px;
}
.repo-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 12px;
}
.repo-meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.repo-meta-label {
    font-size: 0.8rem;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
}
.repo-meta-value {
    font-size: 0.95rem;
    color: var(--text-main);
    font-weight: 500;
}
.repo-modal-abstract {
    line-height: 1.6;
    color: var(--text-secondary);
    font-size: 0.95rem;
    text-align: justify;
}
</style>

<div class="repo-header">
    <div class="container">
        <h1 class="repo-title">FISIP Repository</h1>
        <p class="repo-subtitle">Koleksi Skripsi Berprestasi (Nilai AB - A) dari ke-7 Program Studi di Fakultas Ilmu Sosial dan Ilmu Politik Universitas Airlangga.</p>
    </div>
</div>

<div class="container">
    <form method="GET" action="repository.php" class="repo-filters">
        <select name="prodi" class="repo-filter-select">
            <?php foreach ($prodi_list as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $selected_prodi === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" class="repo-filter-input" placeholder="Cari judul, penulis, atau kata kunci..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="repo-filter-btn">Cari Skripsi</button>
    </form>

    <div class="repo-grid">
        <?php foreach ($repositories as $index => $repo): ?>
        <div class="repo-card" style="animation-delay: <?= $index * 0.05 ?>s">
            <div class="repo-card-inner">
                <div class="repo-grade-badge" title="Nilai Skripsi"><?= htmlspecialchars($repo['grade']) ?></div>
                <div class="repo-prodi"><?= htmlspecialchars($repo['prodi']) ?></div>
                <h3 class="repo-item-title"><?= htmlspecialchars($repo['title']) ?></h3>
                <div class="repo-author">
                    <span>👤</span> <?= htmlspecialchars($repo['author']) ?> <span style="font-size:0.8rem; color:var(--muted)"> (<?= htmlspecialchars($repo['nim']) ?>)</span>
                </div>
                <div class="repo-abstract">
                    <?= htmlspecialchars(substr($repo['abstract'], 0, 150)) ?>...
                </div>
                <div class="repo-footer">
                    <div class="repo-year"><?= htmlspecialchars($repo['year']) ?></div>
                    <?php
                        $departemen = [
                            'Ilmu Komunikasi' => 'Departemen Komunikasi',
                            'Ilmu Hubungan Internasional' => 'Departemen Hubungan Internasional',
                            'Ilmu Politik' => 'Departemen Politik',
                            'Administrasi Publik' => 'Departemen Administrasi',
                            'Sosiologi' => 'Departemen Sosiologi',
                            'Antropologi' => 'Departemen Antropologi',
                            'Ilmu Informasi dan Perpustakaan' => 'Departemen Informasi dan Perpustakaan'
                        ][$repo['prodi']] ?? 'Departemen Lainnya';
                        
                        $modalData = htmlspecialchars(json_encode([
                            'title' => $repo['title'],
                            'author' => $repo['author'],
                            'nim' => $repo['nim'],
                            'prodi' => $repo['prodi'],
                            'year' => $repo['year'],
                            'semester' => $repo['semester'] ?? 'Genap',
                            'grade' => $repo['grade'],
                            'jenis' => $repo['jenis_tugas_akhir'] ?? 'Skripsi',
                            'departemen' => $departemen,
                            'fakultas' => 'Fakultas Ilmu Sosial dan Ilmu Politik',
                            'universitas' => 'Universitas Airlangga',
                            'abstract' => $repo['abstract']
                        ]));
                    ?>
                    <button onclick="openRepoModal(this)" data-info="<?= $modalData ?>" class="repo-action" style="background:none;border:none;cursor:pointer;">Baca Detail →</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($repositories)): ?>
    <div style="text-align: center; padding: 60px 0; color: var(--muted);">
        <h1 style="font-size: 3rem; margin-bottom: 16px;">🔍</h1>
        <h3>Skripsi tidak ditemukan</h3>
        <p>Coba gunakan kata kunci atau pilih program studi yang berbeda.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="repo-modal-overlay" id="repoModalOverlay">
    <div class="repo-modal">
        <div class="repo-modal-header">
            <h2 class="repo-modal-title" id="mTitle">Judul Skripsi</h2>
            <button class="repo-modal-close" onclick="closeRepoModal()">✕</button>
        </div>
        <div class="repo-modal-body">
            <div class="repo-meta-grid">
                <div class="repo-meta-item">
                    <span class="repo-meta-label">Penulis</span>
                    <span class="repo-meta-value" id="mAuthor"></span>
                </div>
                <div class="repo-meta-item">
                    <span class="repo-meta-label">NIM</span>
                    <span class="repo-meta-value" id="mNim"></span>
                </div>
                <div class="repo-meta-item">
                    <span class="repo-meta-label">Program Studi</span>
                    <span class="repo-meta-value" id="mProdi"></span>
                </div>
                <div class="repo-meta-item">
                    <span class="repo-meta-label">Departemen</span>
                    <span class="repo-meta-value" id="mDept"></span>
                </div>
                <div class="repo-meta-item">
                    <span class="repo-meta-label">Tahun Ajaran / Semester</span>
                    <span class="repo-meta-value" id="mYearSem"></span>
                </div>
                <div class="repo-meta-item">
                    <span class="repo-meta-label">Fakultas / Universitas</span>
                    <span class="repo-meta-value" id="mFakUniv"></span>
                </div>
                <div class="repo-meta-item">
                    <span class="repo-meta-label">Jenis Tugas Akhir</span>
                    <span class="repo-meta-value" id="mJenis"></span>
                </div>
                <div class="repo-meta-item">
                    <span class="repo-meta-label">Nilai</span>
                    <span class="repo-meta-value" style="color: #059669; font-weight:800; font-size:1.1rem;" id="mGrade"></span>
                </div>
            </div>
            
            <h4 style="margin-bottom: 10px; color: var(--text-main);">Abstrak</h4>
            <div class="repo-modal-abstract" id="mAbstract"></div>
        </div>
    </div>
</div>

<script>
function openRepoModal(btn) {
    const data = JSON.parse(btn.getAttribute('data-info'));
    document.getElementById('mTitle').innerText = data.title;
    document.getElementById('mAuthor').innerText = data.author;
    document.getElementById('mNim').innerText = data.nim;
    document.getElementById('mProdi').innerText = data.prodi;
    document.getElementById('mDept').innerText = data.departemen;
    document.getElementById('mYearSem').innerText = data.year + ' / ' + data.semester;
    document.getElementById('mFakUniv').innerText = data.fakultas + ', ' + data.universitas;
    document.getElementById('mJenis').innerText = data.jenis;
    document.getElementById('mGrade').innerText = data.grade;
    document.getElementById('mAbstract').innerText = data.abstract;
    
    document.getElementById('repoModalOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeRepoModal() {
    document.getElementById('repoModalOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('repoModalOverlay').addEventListener('click', function(e) {
    if(e.target === this) closeRepoModal();
});
</script>

<?php include 'includes/footer.php'; ?>
