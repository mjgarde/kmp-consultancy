<?php
session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['manager_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();
$currentUserId = $_SESSION['manager_id'];

$uploadDir = __DIR__ . '/../uploads/knowledge_documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
$maxFileSize = 20 * 1024 * 1024;
$validCategories = ['SOW Template', 'Contract Template', 'Best Practice', 'Proposal Template', 'Reference Material', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {

        $title       = trim($_POST['title'] ?? '');
        $category    = $_POST['category'] ?? 'Other';
        $description = trim($_POST['description'] ?? '');

        $errors = [];

        if ($title === '') $errors[] = 'Document title is required.';
        if (!in_array($category, $validCategories)) $errors[] = 'Please select a valid category.';

        if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a file to upload.';
        } else {
            $fileSize = $_FILES['document_file']['size'];
            $fileExt  = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));

            if ($fileSize > $maxFileSize) {
                $errors[] = 'File size must not exceed 20MB.';
            }
            if (!in_array($fileExt, $allowedExtensions)) {
                $errors[] = 'File type not allowed. Accepted: ' . implode(', ', $allowedExtensions);
            }
        }

        if (empty($errors)) {
            $originalName = $_FILES['document_file']['name'];
            $fileExt      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName   = uniqid('doc_', true) . '.' . $fileExt;
            $destination  = $uploadDir . $storedName;

            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $destination)) {
                $stmt = $pdo->prepare(
                    'INSERT INTO knowledge_documents
                     (title, category, description, file_name, file_path, file_size, file_type, uploaded_by, uploaded_by_role)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $title,
                    $category,
                    $description,
                    $originalName,
                    'uploads/knowledge_documents/' . $storedName,
                    $_FILES['document_file']['size'],
                    $fileExt,
                    $currentUserId,
                    'manager',
                ]);

                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'Document uploaded successfully.';
            } else {
                $_SESSION['alert_type'] = 'error';
                $_SESSION['alert_message'] = 'Failed to upload the file. Please try again.';
            }
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: knowledge_repository.php');
        exit;

    } elseif ($action === 'edit') {

        $documentId  = $_POST['document_id'] ?? null;
        $title       = trim($_POST['title'] ?? '');
        $category    = $_POST['category'] ?? 'Other';
        $description = trim($_POST['description'] ?? '');

        $ownerStmt = $pdo->prepare('SELECT uploaded_by, uploaded_by_role FROM knowledge_documents WHERE document_id = ?');
        $ownerStmt->execute([$documentId]);
        $doc = $ownerStmt->fetch();

        if (!$doc || $doc['uploaded_by_role'] !== 'manager' || (int) $doc['uploaded_by'] !== (int) $currentUserId) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'You can only edit documents you uploaded yourself.';
            header('Location: knowledge_repository.php');
            exit;
        }

        $errors = [];

        if ($title === '') $errors[] = 'Document title is required.';
        if (!in_array($category, $validCategories)) $errors[] = 'Please select a valid category.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'UPDATE knowledge_documents SET title = ?, category = ?, description = ? WHERE document_id = ?'
            );
            $stmt->execute([$title, $category, $description, $documentId]);

            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Document details updated successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: knowledge_repository.php');
        exit;

    } elseif ($action === 'delete') {

        $documentId = $_POST['document_id'] ?? null;

        $stmt = $pdo->prepare('SELECT file_path, uploaded_by, uploaded_by_role FROM knowledge_documents WHERE document_id = ?');
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();

        if (!$doc || $doc['uploaded_by_role'] !== 'manager' || (int) $doc['uploaded_by'] !== (int) $currentUserId) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'You can only delete documents you uploaded yourself.';
            header('Location: knowledge_repository.php');
            exit;
        }

        $fullPath = __DIR__ . '/../' . $doc['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $delStmt = $pdo->prepare('DELETE FROM knowledge_documents WHERE document_id = ?');
        $delStmt->execute([$documentId]);

        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'Document deleted successfully.';

        header('Location: knowledge_repository.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$searchTerm     = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

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

$totalDocuments = (int) $pdo->query('SELECT COUNT(*) FROM knowledge_documents')->fetchColumn();
$totalSowContract = (int) $pdo->query("SELECT COUNT(*) FROM knowledge_documents WHERE category IN ('SOW Template','Contract Template')")->fetchColumn();
$totalBestPractice = (int) $pdo->query("SELECT COUNT(*) FROM knowledge_documents WHERE category = 'Best Practice'")->fetchColumn();
$totalStorageUsed = (int) $pdo->query('SELECT COALESCE(SUM(file_size), 0) FROM knowledge_documents')->fetchColumn();

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
        'pdf' => ['bg' => '#FAECE8', 'fg' => '#93382A'],
        'doc', 'docx' => ['bg' => '#E9ECF6', 'fg' => '#2E3E70'],
        'xls', 'xlsx' => ['bg' => '#E3F3EC', 'fg' => '#0F5F49'],
        'ppt', 'pptx' => ['bg' => '#FBF0DD', 'fg' => '#8A5A15'],
        'txt' => ['bg' => '#EEF1F6', 'fg' => '#475569'],
        default => ['bg' => '#EEF1F6', 'fg' => '#475569'],
    };
}

function categoryPalette(string $category): array
{
    return match ($category) {
        'SOW Template' => ['bg' => '#E9ECF6', 'fg' => '#2E3E70'],
        'Contract Template' => ['bg' => '#FAECE8', 'fg' => '#93382A'],
        'Best Practice' => ['bg' => '#E3F3EC', 'fg' => '#0F5F49'],
        'Proposal Template' => ['bg' => '#FBF0DD', 'fg' => '#8A5A15'],
        'Reference Material' => ['bg' => '#EEF1F6', 'fg' => '#475569'],
        default => ['bg' => '#EEF1F6', 'fg' => '#475569'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Knowledge Repository | Manager</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/repository.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
  --navy: #1E293B;
  --navy-deep: #0F172A;
  --navy-soft: #EEF1F6;
  --indigo: #3B4E8A;
  --indigo-soft: #E9ECF6;
  --indigo-text: #2E3E70;
  --slate: #475569;
  --slate-soft: #64748B;

  --success: #157A5F;
  --success-soft: #E3F3EC;
  --success-text: #0F5F49;
  --success-border: #BFE3D3;

  --warn: #B7791F;
  --warn-soft: #FBF0DD;
  --warn-text: #8A5A15;
  --warn-border: #EFD8A8;

  --danger: #B4432F;
  --danger-soft: #FAECE8;
  --danger-text: #93382A;
  --danger-border: #EDC7BC;

  --ink: #1A2233;
  --ink-soft: #667085;
  --line: #E2E5EB;
  --canvas: #FFFFFF;
  --card: #FFFFFF;
}

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-layout, .dashboard-main, .dashboard-content {
  background-color: var(--canvas) !important;
}

.dashboard-title, h1, h2, h3 {
  font-family: 'Lexend', 'Inter', sans-serif;
}

.dashboard-title { color: var(--navy-deep); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background-color: #fff; }

.card { border-radius: 12px; border: 1px solid var(--line) !important; box-shadow: none !important; }

.form-control:focus, .form-select:focus {
  border-color: var(--indigo);
  box-shadow: 0 0 0 .2rem rgba(59,78,138,.13);
  outline: none;
}
.form-control:hover, .form-select:hover { border-color: #C6CCD8; }

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.15rem;
  display: flex;
  align-items: center;
  gap: .85rem;
  height: 100%;
}
.stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1rem;
}
.stat-label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); }
.stat-value { font-family: 'Lexend', sans-serif; font-size: 1.35rem; font-weight: 700; margin-top: .1rem; }

.btn-repo {
  border-radius: 7px;
  font-weight: 600;
  font-size: .82rem;
  border: 1px solid transparent;
}
.btn-repo-primary { background-color: var(--indigo); color: #fff; }
.btn-repo-primary:hover { background-color: var(--indigo-text); color: #fff; }
.btn-repo-success { background-color: var(--success); color: #fff; }
.btn-repo-success:hover { background-color: #0F5F49; color: #fff; }
.btn-repo-danger { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }
.btn-repo-danger:hover { background-color: var(--danger); color: #fff; border-color: var(--danger); }

.repo-filter-pills a {
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: .35rem .9rem;
  font-size: .78rem;
  color: var(--ink-soft);
  text-decoration: none;
  background-color: #fff;
}
.repo-filter-pills a:hover { border-color: var(--indigo); color: var(--indigo-text); }

.document-list { margin: 0; }
.document-row {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem 1.15rem;
  border-bottom: 1px solid var(--line);
}
.document-row:last-child { border-bottom: none; }
.document-row:hover { background-color: var(--navy-soft); }

.document-thumb {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1.05rem;
}

.document-body { flex: 1; min-width: 0; }
.document-title { font-size: .92rem; font-weight: 700; color: var(--ink); font-family: 'Lexend', sans-serif; }
.document-meta {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: .55rem;
  align-items: center;
  padding: 0;
  margin: 0 0 .35rem 0;
  font-size: .76rem;
  color: var(--ink-soft);
}
.document-meta li:not(:first-child)::before { content: '\2022'; margin-right: .55rem; color: var(--line); }
.category-pill {
  font-size: .68rem;
  font-weight: 700;
  padding: .2rem .6rem;
  border-radius: 999px;
  white-space: nowrap;
}
.document-description { font-size: .82rem; color: var(--ink-soft); margin: 0; }

.document-actions {
  display: flex;
  gap: .4rem;
  flex-shrink: 0;
}
.document-actions .btn-repo { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; padding: 0; }

.dropzone-upload {
  border: 1.5px dashed var(--line);
  border-radius: 10px;
  padding: 1.5rem 1rem;
  text-align: center;
  cursor: pointer;
  background-color: var(--navy-soft);
}
.dropzone-upload:hover, .dropzone-upload.dragover { border-color: var(--indigo); background-color: var(--indigo-soft); }
.dropzone-upload.has-file { border-color: var(--success); background-color: var(--success-soft); }

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }

.form-label.fw-semibold { font-size: .8rem; font-weight: 700 !important; color: var(--slate); text-transform: uppercase; letter-spacing: .02em; }
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/manager/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Knowledge Repository</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Manage consultancy templates, SOW/contract documents, and best practices.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="row g-2 g-md-3 mb-3" aria-label="Repository summary">
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--indigo-soft);">
              <i class="fa-solid fa-folder-open" style="color:var(--indigo-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Total Documents</div>
              <div class="stat-value" style="color:var(--indigo-text);"><?= $totalDocuments ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--danger-soft);">
              <i class="fa-solid fa-file-contract" style="color:var(--danger-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">SOW &amp; Contracts</div>
              <div class="stat-value" style="color:var(--danger-text);"><?= $totalSowContract ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--success-soft);">
              <i class="fa-solid fa-star" style="color:var(--success-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Best Practices</div>
              <div class="stat-value" style="color:var(--success-text);"><?= $totalBestPractice ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--warn-soft);">
              <i class="fa-solid fa-database" style="color:var(--warn-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Storage Used</div>
              <div class="stat-value" style="color:var(--warn-text);"><?= formatFileSize($totalStorageUsed) ?></div>
            </div>
          </div>
        </div>
      </section>

      <section class="card mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="row g-2 align-items-center mb-2" method="GET" id="repoFilterForm">
            <div class="col-12 col-md-7">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
                <input type="text" name="search" id="liveSearchInput" class="form-control" placeholder="Search documents by title or description" value="<?= htmlspecialchars($searchTerm) ?>" autocomplete="off">
                <?php if ($categoryFilter !== ''): ?>
                  <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
                <?php endif; ?>
              </div>
            </div>
            <div class="col-12 col-md-5 text-md-end">
              <button type="button" class="btn btn-repo btn-repo-primary w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload Document
              </button>
            </div>
          </form>

          <nav class="repo-filter-pills d-flex flex-wrap gap-2" aria-label="Filter by category">
            <a href="?search=<?= urlencode($searchTerm) ?>" style="<?= $categoryFilter === '' ? 'background-color:var(--indigo-soft); border-color:var(--indigo-soft); color:var(--indigo-text); font-weight:700;' : '' ?>">All</a>
            <?php foreach ($validCategories as $cat): ?>
              <?php $catColors = categoryPalette($cat); ?>
              <a href="?search=<?= urlencode($searchTerm) ?>&category=<?= urlencode($cat) ?>" style="<?= $categoryFilter === $cat ? 'background-color:' . $catColors['bg'] . '; border-color:' . $catColors['bg'] . '; color:' . $catColors['fg'] . '; font-weight:700;' : '' ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
          </nav>
        </div>
      </section>

      <section class="card" aria-label="Document list">
        <?php if (empty($documents)): ?>
          <div class="text-center py-5" style="color:var(--ink-soft);">
            <i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>
            <p class="mb-0 small">No documents found. Upload your first template or reference file.</p>
          </div>
        <?php else: ?>
          <ul class="document-list list-unstyled" id="documentListUl">
            <?php foreach ($documents as $doc): ?>
              <?php
                $uploaderName = $doc['uploaded_by_role'] === 'admin'
                    ? ($doc['uploader_admin_name'] ?? 'Administrator')
                    : trim(($doc['uploader_firstname'] ?? '') . ' ' . ($doc['uploader_lastname'] ?? ''));
                $thumbColors = fileTypePalette($doc['file_type']);
                $catColors = categoryPalette($doc['category']);
                $isOwner = $doc['uploaded_by_role'] === 'manager' && (int) $doc['uploaded_by'] === (int) $currentUserId;
              ?>
              <li class="document-row" data-title="<?= htmlspecialchars(mb_strtolower($doc['title'])) ?>" data-description="<?= htmlspecialchars(mb_strtolower($doc['description'] ?? '')) ?>">
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
                  <?php if ($isOwner): ?>
                    <button type="button" class="btn btn-repo btn-repo-primary" title="Edit"
                      data-bs-toggle="modal" data-bs-target="#editDocumentModal"
                      data-id="<?= $doc['document_id'] ?>"
                      data-title="<?= htmlspecialchars($doc['title']) ?>"
                      data-category="<?= htmlspecialchars($doc['category']) ?>"
                      data-description="<?= htmlspecialchars($doc['description'] ?? '') ?>">
                      <i class="fa-regular fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-repo btn-repo-danger" title="Delete"
                      data-bs-toggle="modal" data-bs-target="#deleteDocumentModal"
                      data-id="<?= $doc['document_id'] ?>"
                      data-title="<?= htmlspecialchars($doc['title']) ?>">
                      <i class="fa-regular fa-trash-can"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="text-center py-5 d-none" id="noSearchResults" style="color:var(--ink-soft);">
            <i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>
            <p class="mb-0 small">No documents match your search.</p>
          </div>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data" novalidate id="uploadForm">
        <input type="hidden" name="action" value="upload">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Upload Document</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Document Title</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Standard SOW Template" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Category</label>
              <select name="category" class="form-select" required>
                <option value="SOW Template">SOW Template</option>
                <option value="Contract Template">Contract Template</option>
                <option value="Proposal Template">Proposal Template</option>
                <option value="Best Practice">Best Practice</option>
                <option value="Reference Material">Reference Material</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Optional short description of this document"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">File</label>
              <div class="dropzone-upload" id="dropzone">
                <i class="fa-solid fa-cloud-arrow-up fs-3 mb-2 d-block" style="color:var(--ink-soft);"></i>
                <p class="small mb-1" id="dropzoneText">Click to browse or drag and drop a file here</p>
                <p style="font-size:.7rem; color:var(--ink-soft);">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT — up to 20MB</p>
                <input type="file" name="document_file" id="document_file" class="d-none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-repo btn-repo-primary">Upload Document</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editDocumentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="document_id" id="edit_document_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Edit Document Details</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Document Title</label>
              <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Category</label>
              <select name="category" id="edit_category" class="form-select" required>
                <option value="SOW Template">SOW Template</option>
                <option value="Contract Template">Contract Template</option>
                <option value="Proposal Template">Proposal Template</option>
                <option value="Best Practice">Best Practice</option>
                <option value="Reference Material">Reference Material</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <p class="small mt-2 mb-0" style="color:var(--ink-soft);"><i class="fa-solid fa-circle-info"></i> To replace the file itself, delete this document and upload a new version.</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-repo btn-repo-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteDocumentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="document_id" id="delete_document_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Delete Document</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="delete_document_title"></strong>? This will permanently remove the file and cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-repo btn-repo-danger">Delete Document</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('document_file');
const dropzoneText = document.getElementById('dropzoneText');

dropzone.addEventListener('click', function () {
  fileInput.click();
});

fileInput.addEventListener('change', function () {
  if (fileInput.files.length > 0) {
    dropzoneText.textContent = fileInput.files[0].name;
    dropzone.classList.add('has-file');
  }
});

['dragover', 'dragenter'].forEach(function (evt) {
  dropzone.addEventListener(evt, function (e) {
    e.preventDefault();
    dropzone.classList.add('dragover');
  });
});

['dragleave', 'drop'].forEach(function (evt) {
  dropzone.addEventListener(evt, function (e) {
    e.preventDefault();
    dropzone.classList.remove('dragover');
  });
});

dropzone.addEventListener('drop', function (e) {
  if (e.dataTransfer.files.length > 0) {
    fileInput.files = e.dataTransfer.files;
    dropzoneText.textContent = e.dataTransfer.files[0].name;
    dropzone.classList.add('has-file');
  }
});

document.getElementById('uploadDocumentModal').addEventListener('hidden.bs.modal', function () {
  document.getElementById('uploadForm').reset();
  dropzoneText.textContent = 'Click to browse or drag and drop a file here';
  dropzone.classList.remove('has-file');
});

document.getElementById('editDocumentModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('edit_document_id').value = btn.dataset.id;
  document.getElementById('edit_title').value = btn.dataset.title;
  document.getElementById('edit_category').value = btn.dataset.category;
  document.getElementById('edit_description').value = btn.dataset.description;
});

document.getElementById('deleteDocumentModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('delete_document_id').value = btn.dataset.id;
  document.getElementById('delete_document_title').textContent = btn.dataset.title;
});

const liveSearchInput = document.getElementById('liveSearchInput');
const documentListUl = document.getElementById('documentListUl');
const noSearchResults = document.getElementById('noSearchResults');

if (liveSearchInput && documentListUl) {
  liveSearchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') e.preventDefault();
  });

  liveSearchInput.addEventListener('input', function () {
    const query = liveSearchInput.value.trim().toLowerCase();
    const rows = documentListUl.querySelectorAll('.document-row');
    let visibleCount = 0;

    rows.forEach(function (row) {
      const title = row.dataset.title || '';
      const description = row.dataset.description || '';
      const matches = query === '' || title.includes(query) || description.includes(query);
      row.style.display = matches ? '' : 'none';
      if (matches) visibleCount++;
    });

    if (noSearchResults) {
      noSearchResults.classList.toggle('d-none', visibleCount !== 0);
    }
  });
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>