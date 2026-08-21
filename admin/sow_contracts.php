<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

$uploadDir = __DIR__ . '/../uploads/knowledge_documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
$maxFileSize = 20 * 1024 * 1024; // 20MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {

        $title       = trim($_POST['title'] ?? '');
        $category    = $_POST['category'] ?? 'Other';
        $description = trim($_POST['description'] ?? '');

        $errors = [];
        $validCategories = ['SOW Template', 'Contract Template', 'Best Practice', 'Proposal Template', 'Reference Material', 'Other'];

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
                    $_SESSION['admin_id'],
                    'admin',
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

        $errors = [];
        $validCategories = ['SOW Template', 'Contract Template', 'Best Practice', 'Proposal Template', 'Reference Material', 'Other'];

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

        $stmt = $pdo->prepare('SELECT file_path FROM knowledge_documents WHERE document_id = ?');
        $stmt->execute([$documentId]);
        $doc = $stmt->fetch();

        if ($doc) {
            $fullPath = __DIR__ . '/../' . $doc['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            $delStmt = $pdo->prepare('DELETE FROM knowledge_documents WHERE document_id = ?');
            $delStmt->execute([$documentId]);

            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Document deleted successfully.';
        }

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

$validCategories = ['SOW Template', 'Contract Template', 'Best Practice', 'Proposal Template', 'Reference Material', 'Other'];
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

function fileIconColor(string $fileType): string
{
    return match ($fileType) {
        'pdf' => '#DDA391',
        'doc', 'docx' => '#8FA8BF',
        'xls', 'xlsx' => '#8FBFAE',
        'ppt', 'pptx' => '#E0B67F',
        default => '#A9AFB5',
    };
}

function categoryBadgeColor(string $category): string
{
    return match ($category) {
        'SOW Template' => '#AFC4D6',
        'Contract Template' => '#EFC3B6',
        'Best Practice' => '#A9D4C6',
        'Proposal Template' => '#F2D9A8',
        'Reference Material' => '#D3C5E8',
        default => '#DCE1E4',
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
<style>
.form-control:focus, .form-select:focus, .form-control:hover, .form-select:hover {
  border-color:#2F4858;
  box-shadow:0 0 0 .2rem rgba(47,72,88,.15);
  outline:none;
}
.repo-stat-body { padding:.85rem; }
.repo-stat-icon { width:40px; height:40px; font-size:.95rem; }
.repo-stat-label { font-size:.72rem; }
.repo-stat-value { font-size:1.25rem; }

@media (min-width:768px) {
  .repo-stat-body { padding:1rem; }
  .repo-stat-icon { width:44px; height:44px; font-size:1.05rem; }
  .repo-stat-label { font-size:.8rem; }
  .repo-stat-value { font-size:1.5rem; }
}

.document-card {
  border:1px solid rgba(0,0,0,.06);
  border-radius:.5rem;
  transition:box-shadow .15s ease, border-color .15s ease;
  height:100%;
}
.document-card:hover {
  border-color:#A8C5BC;
  box-shadow:0 4px 14px rgba(0,0,0,.05);
}
.document-file-icon {
  width:46px;
  height:46px;
  font-size:1.3rem;
  border-radius:.45rem;
  flex-shrink:0;
}
.category-pill {
  font-size:.65rem;
  font-weight:600;
  padding:.25rem .6rem;
  border-radius:.35rem;
  color:#4A5A63;
  display:inline-block;
}
.repo-filter-pills .btn {
  font-size:.75rem;
  padding:.35rem .75rem;
  border-radius:.4rem;
  border:1px solid #E2E8E6;
}
.btn-repo {
  border-radius:.4rem;
  border:none;
  font-weight:500;
}
.btn-repo-primary {
  background-color:#AFC4D6;
  color:#2F4858;
}
.btn-repo-primary:hover {
  background-color:#9DB6CB;
  color:#2F4858;
}
.btn-repo-success {
  background-color:#A9D4C6;
  color:#20544A;
}
.btn-repo-success:hover {
  background-color:#96C7B7;
  color:#20544A;
}
.btn-repo-danger {
  background-color:#EFC3B6;
  color:#8A3F2C;
}
.btn-repo-danger:hover {
  background-color:#E8AF9E;
  color:#8A3F2C;
}
.dropzone-upload {
  border:2px dashed #cbd5e1;
  border-radius:.5rem;
  padding:2rem 1rem;
  text-align:center;
  cursor:pointer;
  transition:border-color .15s ease, background-color .15s ease;
}
.dropzone-upload:hover, .dropzone-upload.dragover {
  border-color:#A9D4C6;
  background-color:#A9D4C608;
}
.dropzone-upload.has-file {
  border-color:#AFC4D6;
  background-color:#AFC4D608;
}
</style>
</head>
<body class="bg-light">

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white border-bottom d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Knowledge Repository</h1>
          <p class="dashboard-subtitle text-secondary small mb-0 d-none d-sm-block">Manage consultancy templates, SOW/contract documents, and best practices.</p>
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
              <a href="../config/logout.php?role=admin" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#AFC4D630;">
                <i class="fa-solid fa-folder-open" style="color:#5C7A93;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">Total Documents</div>
                <div class="repo-stat-value fw-bold" style="color:#5C7A93;"><?= $totalDocuments ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#EFC3B630;">
                <i class="fa-solid fa-file-contract" style="color:#B8735A;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">SOW &amp; Contracts</div>
                <div class="repo-stat-value fw-bold" style="color:#B8735A;"><?= $totalSowContract ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#A9D4C630;">
                <i class="fa-solid fa-star" style="color:#4E9483;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">Best Practices</div>
                <div class="repo-stat-value fw-bold" style="color:#4E9483;"><?= $totalBestPractice ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body repo-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="repo-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#F2D9A830;">
                <i class="fa-solid fa-database" style="color:#B8925A;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="repo-stat-label text-secondary text-truncate">Storage Used</div>
                <div class="repo-stat-value fw-bold" style="color:#B8925A;"><?= formatFileSize($totalStorageUsed) ?></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="row g-2 align-items-center mb-2" method="GET">
            <div class="col-12 col-md-7">
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
            <div class="col-12 col-md-5 text-md-end">
              <button type="button" class="btn btn-repo btn-repo-primary w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload Document
              </button>
            </div>
          </form>

          <div class="repo-filter-pills d-flex flex-wrap gap-2">
            <a href="?search=<?= urlencode($searchTerm) ?>" class="btn <?= $categoryFilter === '' ? '' : 'btn-outline-secondary' ?>" style="<?= $categoryFilter === '' ? 'background-color:#DCE1E4; color:#4A5A63; border-color:#DCE1E4;' : '' ?>">All</a>
            <?php foreach ($validCategories as $cat): ?>
              <a href="?search=<?= urlencode($searchTerm) ?>&category=<?= urlencode($cat) ?>" class="btn <?= $categoryFilter === $cat ? '' : 'btn-outline-secondary' ?>" style="<?= $categoryFilter === $cat ? 'background-color:' . categoryBadgeColor($cat) . '; color:#4A5A63; border-color:' . categoryBadgeColor($cat) . ';' : '' ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section>
        <?php if (empty($documents)): ?>
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-secondary py-5">
              <i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>
              <p class="mb-0 small">No documents found. Upload your first template or reference file.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($documents as $doc): ?>
              <?php
                $uploaderName = $doc['uploaded_by_role'] === 'admin'
                    ? ($doc['uploader_admin_name'] ?? 'Administrator')
                    : trim(($doc['uploader_firstname'] ?? '') . ' ' . ($doc['uploader_lastname'] ?? ''));
              ?>
              <div class="col-12 col-sm-6 col-lg-4">
                <div class="document-card card border-0 shadow-sm p-3">
                  <div class="d-flex gap-3 mb-2">
                    <span class="document-file-icon d-flex align-items-center justify-content-center flex-shrink-0" style="background-color:<?= fileIconColor($doc['file_type']) ?>15;">
                      <i class="fa-solid <?= fileIconClass($doc['file_type']) ?>" style="color:<?= fileIconColor($doc['file_type']) ?>;"></i>
                    </span>
                    <div class="overflow-hidden flex-grow-1">
                      <div class="fw-semibold small text-truncate" title="<?= htmlspecialchars($doc['title']) ?>"><?= htmlspecialchars($doc['title']) ?></div>
                      <div class="text-secondary" style="font-size:.72rem;"><?= strtoupper($doc['file_type']) ?> &middot; <?= formatFileSize($doc['file_size']) ?></div>
                    </div>
                  </div>

                  <span class="category-pill mb-2" style="background-color:<?= categoryBadgeColor($doc['category']) ?>; width:fit-content;"><?= htmlspecialchars($doc['category']) ?></span>

                  <p class="text-secondary small mb-2" style="min-height:2.4em;">
                    <?= $doc['description'] ? htmlspecialchars(mb_strimwidth($doc['description'], 0, 90, '...')) : 'No description provided.' ?>
                  </p>

                  <div class="text-secondary mb-3" style="font-size:.7rem;">
                    Uploaded by <?= htmlspecialchars($uploaderName ?: 'Unknown') ?><br>
                    <?= date('M d, Y', strtotime($doc['created_at'])) ?>
                  </div>

                  <div class="d-flex gap-2 mt-auto">
                    <a href="../<?= htmlspecialchars($doc['file_path']) ?>" download="<?= htmlspecialchars($doc['file_name']) ?>" class="btn btn-repo btn-repo-success btn-sm flex-grow-1">
                      <i class="fa-solid fa-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-repo btn-repo-primary btn-sm" title="Edit"
                      data-bs-toggle="modal" data-bs-target="#editDocumentModal"
                      data-id="<?= $doc['document_id'] ?>"
                      data-title="<?= htmlspecialchars($doc['title']) ?>"
                      data-category="<?= htmlspecialchars($doc['category']) ?>"
                      data-description="<?= htmlspecialchars($doc['description'] ?? '') ?>">
                      <i class="fa-regular fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn btn-repo btn-repo-danger btn-sm" title="Delete"
                      data-bs-toggle="modal" data-bs-target="#deleteDocumentModal"
                      data-id="<?= $doc['document_id'] ?>"
                      data-title="<?= htmlspecialchars($doc['title']) ?>">
                      <i class="fa-regular fa-trash-can"></i>
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
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
                <i class="fa-solid fa-cloud-arrow-up fs-3 mb-2 d-block text-secondary"></i>
                <p class="small mb-1" id="dropzoneText">Click to browse or drag and drop a file here</p>
                <p class="text-secondary" style="font-size:.7rem;">PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT — up to 20MB</p>
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
          <p class="text-secondary small mt-2 mb-0"><i class="fa-solid fa-circle-info"></i> To replace the file itself, delete this document and upload a new version.</p>
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
          <button type="button" class="btn btn-outline-secondary btn-repo" data-bs-dismiss="modal">Cancel</button>
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

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>