<?php

session_name('STAFF_SESSION');
session_start();

if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = getConnection();

$totalDocuments = (int) $pdo->query('SELECT COUNT(*) FROM knowledge_documents')->fetchColumn();
$totalSowContract = (int) $pdo->query("SELECT COUNT(*) FROM knowledge_documents WHERE category IN ('SOW Template','Contract Template')")->fetchColumn();
$totalBestPractice = (int) $pdo->query("SELECT COUNT(*) FROM knowledge_documents WHERE category = 'Best Practice'")->fetchColumn();
$totalStorageUsed = (int) $pdo->query('SELECT COALESCE(SUM(file_size), 0) FROM knowledge_documents')->fetchColumn();

$searchTerm     = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';
$validCategories = ['SOW Template', 'Contract Template', 'Best Practice', 'Proposal Template', 'Reference Material', 'Other'];

$query = 'SELECT kd.*, u.firstname AS uploader_firstname, u.lastname AS uploader_lastname, a.fullname AS uploader_admin_name
          FROM knowledge_documents kd
          LEFT JOIN users u ON kd.uploaded_by = u.user_id AND kd.uploaded_by_role IN ("manager","supervisor")
          LEFT JOIN administrator a ON kd.uploaded_by = a.admin_id AND kd.uploaded_by_role = "admin"
          WHERE 1=1';
$params = [];

if ($searchTerm !== '') {
    $query .= ' AND (kd.title LIKE ? OR kd.description LIKE ?)';
    $like = '%' . $searchTerm . '%';
    $params[] = $like;
    $params[] = $like;
}

if (in_array($categoryFilter, $validCategories)) {
    $query .= ' AND kd.category = ?';
    $params[] = $categoryFilter;
}

$query .= ' ORDER BY kd.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll();

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function fileIconClass(string $fileType): string
{
    return match ($fileType) {
        'pdf' => 'fa-file-pdf',
        'doc', 'docx' => 'fa-file-word',
        'xls', 'xlsx' => 'fa-file-excel',
        'ppt', 'pptx' => 'fa-file-powerpoint',
        'txt' => 'fa-file-lines',
        default => 'fa-file',
    };
}

function fileTypePalette(string $fileType): array
{
    return match ($fileType) {
        'pdf' => ['bg' => '#F7DCD2', 'fg' => '#9C4A2E'],
        'doc', 'docx' => ['bg' => '#D6E4F0', 'fg' => '#2E5C82'],
        'xls', 'xlsx' => ['bg' => '#D7EEE3', 'fg' => '#276B4E'],
        'ppt', 'pptx' => ['bg' => '#F5E3C4', 'fg' => '#93611A'],
        'txt' => ['bg' => '#E4E7EB', 'fg' => '#4B5563'],
        default => ['bg' => '#E4E7EB', 'fg' => '#4B5563'],
    };
}

function categoryPalette(string $category): array
{
    return match ($category) {
        'SOW Template' => ['bg' => '#D6E4F0', 'fg' => '#2E5C82'],
        'Contract Template' => ['bg' => '#F7DCD2', 'fg' => '#9C4A2E'],
        'Best Practice' => ['bg' => '#D7EEE3', 'fg' => '#276B4E'],
        'Proposal Template' => ['bg' => '#F5E3C4', 'fg' => '#93611A'],
        'Reference Material' => ['bg' => '#E5DBF2', 'fg' => '#5F3E8C'],
        default => ['bg' => '#E4E7EB', 'fg' => '#4B5563'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Knowledge Repository</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/repository.css">
</head>
<body class="bg-light">

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white border-bottom d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Knowledge Repository</h1>
          <p class="dashboard-subtitle text-secondary small mb-0 d-none d-sm-block">Browse and download consultancy templates, SOW/contract documents, and best practices.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <button type="button" class="btn btn-link text-secondary p-0">
          <i class="fa-regular fa-bell fs-5"></i>
        </button>
        <div class="dropdown">
          <button type="button" class="btn btn-link p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="dashboard-user-icon d-flex align-items-center justify-content-center rounded-circle bg-secondary bg-opacity-10 flex-shrink-0" style="width:36px; height:36px;">
              <i class="fa-solid fa-user text-secondary"></i>
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
              <a href="../config/logout.php?role=staff" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="row g-2 g-md-3 mb-3" aria-label="Repository summary">
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#D6E4F0;">
                <i class="fa-solid fa-folder-open" style="color:#2E5C82;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">Total Documents</div>
                <div class="repo-stat-value fw-bold" style="color:#2E5C82;"><?= $totalDocuments ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#F7DCD2;">
                <i class="fa-solid fa-file-contract" style="color:#9C4A2E;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">SOW &amp; Contracts</div>
                <div class="repo-stat-value fw-bold" style="color:#9C4A2E;"><?= $totalSowContract ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#D7EEE3;">
                <i class="fa-solid fa-star" style="color:#276B4E;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">Best Practices</div>
                <div class="repo-stat-value fw-bold" style="color:#276B4E;"><?= $totalBestPractice ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#F5E3C4;">
                <i class="fa-solid fa-database" style="color:#93611A;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">Storage Used</div>
                <div class="repo-stat-value fw-bold" style="color:#93611A;"><?= formatFileSize($totalStorageUsed) ?></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="row g-2 align-items-center mb-2" method="GET">
            <div class="col-12">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search documents by title or description" value="<?= htmlspecialchars($searchTerm) ?>">
                <?php if ($categoryFilter !== ''): ?>
                  <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn-repo btn-repo-success">
                  <i class="fa-solid fa-filter"></i> <span class="d-none d-sm-inline">Search</span>
                </button>
              </div>
            </div>
          </form>

          <nav class="repo-filter-pills d-flex flex-wrap gap-2" aria-label="Filter by category">
            <a href="?search=<?= urlencode($searchTerm) ?>" class="btn <?= $categoryFilter === '' ? 'fw-semibold' : '' ?>" style="<?= $categoryFilter === '' ? 'background-color:#E4E7EB; border-color:#E4E7EB; color:#374151;' : '' ?>">All</a>
            <?php foreach ($validCategories as $cat): ?>
              <?php $catColors = categoryPalette($cat); ?>
              <a href="?search=<?= urlencode($searchTerm) ?>&category=<?= urlencode($cat) ?>" class="btn <?= $categoryFilter === $cat ? 'fw-semibold' : '' ?>" style="<?= $categoryFilter === $cat ? 'background-color:' . $catColors['bg'] . '; border-color:' . $catColors['bg'] . '; color:' . $catColors['fg'] . ';' : '' ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
          </nav>
        </div>
      </section>

      <section class="card border-0 shadow-sm" aria-label="Document list">
        <?php if (empty($documents)): ?>
          <div class="text-center text-secondary py-5">
            <i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>
            <p class="mb-0 small">No documents found.</p>
          </div>
        <?php else: ?>
          <ul class="document-list list-unstyled">
            <?php foreach ($documents as $doc): ?>
              <?php
                $uploaderName = $doc['uploaded_by_role'] === 'admin'
                    ? ($doc['uploader_admin_name'] ?? 'Administrator')
                    : trim(($doc['uploader_firstname'] ?? '') . ' ' . ($doc['uploader_lastname'] ?? ''));
                $thumbColors = fileTypePalette($doc['file_type']);
                $catColors = categoryPalette($doc['category']);
              ?>
              <li class="document-row">
                <span class="document-thumb" style="background-color:<?= $thumbColors['bg'] ?>; color:<?= $thumbColors['fg'] ?>;" aria-hidden="true">
                  <i class="fa-solid <?= fileIconClass($doc['file_type']) ?>"></i>
                </span>
                <article class="document-body">
                  <h2 class="document-title text-truncate mb-1"><?= htmlspecialchars($doc['title']) ?></h2>
                  <ul class="document-meta">
                    <li><span class="category-pill" style="background-color:<?= $catColors['bg'] ?>; color:<?= $catColors['fg'] ?>;"><?= htmlspecialchars($doc['category']) ?></span></li>
                    <li><?= strtoupper($doc['file_type']) ?> &middot; <?= formatFileSize($doc['file_size']) ?></li>
                    <li>Uploaded by <?= htmlspecialchars($uploaderName ?: 'Unknown') ?></li>
                    <li><time datetime="<?= date('Y-m-d', strtotime($doc['created_at'])) ?>"><?= date('M d, Y', strtotime($doc['created_at'])) ?></time></li>
                  </ul>
                  <?php if (!empty($doc['description'])): ?>
                    <p class="document-description"><?= htmlspecialchars(mb_strimwidth($doc['description'], 0, 140, '...')) ?></p>
                  <?php endif; ?>
                </article>
                <div class="document-actions">
                  <a href="../<?= htmlspecialchars($doc['file_path']) ?>" download="<?= htmlspecialchars($doc['file_name']) ?>" class="btn btn-repo btn-repo-success" title="Download">
                    <i class="fa-solid fa-download"></i>
                  </a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>

</body>
</html>