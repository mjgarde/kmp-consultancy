<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

$uploadDir = __DIR__ . '/../uploads/repository/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Cache folder para sa mga na-convert na PDF (galing sa docx/xlsx/pptx)
$pdfCacheDir = __DIR__ . '/../uploads/pdf_cache/';
if (!is_dir($pdfCacheDir)) {
    mkdir($pdfCacheDir, 0755, true);
}

function folderPath(PDO $pdo, ?int $folderId): array
{
    $trail = [];
    while ($folderId !== null) {
        $stmt = $pdo->prepare("SELECT document_id, title, parent_id FROM knowledge_documents WHERE document_id = ? AND item_type = 'folder'");
        $stmt->execute([$folderId]);
        $folder = $stmt->fetch();
        if (!$folder) break;
        array_unshift($trail, $folder);
        $folderId = $folder['parent_id'];
    }
    return $trail;
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function fileIcon(string $fileName): string
{
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => 'fa-file-pdf text-danger',
        'doc', 'docx' => 'fa-file-word text-primary',
        'xls', 'xlsx', 'csv' => 'fa-file-excel text-success',
        'ppt', 'pptx' => 'fa-file-powerpoint',
        'jpg', 'jpeg', 'png', 'gif', 'webp' => 'fa-file-image',
        'zip', 'rar' => 'fa-file-zipper',
        default => 'fa-file',
    };
}

/**
 * Decide how a file type should be previewed in-browser.
 * 'office'  -> docx/xlsx/pptx/doc/xls/ppt: server converts to PDF first, then shown natively
 * 'native'  -> browser can render it directly (pdf, images)
 * 'none'    -> no inline preview, download only
 */
function previewMode(string $ext): string
{
    $ext = strtolower($ext);
    if (in_array($ext, ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt'], true)) {
        return 'office';
    }
    if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return 'native';
    }
    return 'none';
}

/**
 * Converts an office document (docx/xlsx/pptx/doc/xls/ppt) into a PDF using
 * LibreOffice headless mode, caching the result so repeat views are instant.
 * Returns the absolute path to the resulting PDF, or null on failure.
 *
 * Requires: LibreOffice installed on the server, and exec() not disabled in php.ini.
 */
function convertToPdf(string $sourcePath, string $cacheDir, string $documentId): ?string
{
    if (!is_file($sourcePath)) {
        return null;
    }

    $cachedPdf = $cacheDir . $documentId . '.pdf';

    if (is_file($cachedPdf) && filemtime($cachedPdf) >= filemtime($sourcePath)) {
        return $cachedPdf;
    }

    if (!function_exists('exec')) {
        error_log('convertToPdf: exec() is disabled on this server.');
        return null;
    }

    // LibreOffice needs a writable HOME/profile dir to run headless.
    // The web server user (e.g. www-data) usually has none, which makes
    // the conversion fail silently. Give it a dedicated one, unique per
    // call so concurrent conversions don't fight over a profile lock.
    $loProfileDir = sys_get_temp_dir() . '/lo_profile_' . uniqid();
    if (!is_dir($loProfileDir)) {
        mkdir($loProfileDir, 0700, true);
    }

    $cmd = sprintf(
        'HOME=%s libreoffice --headless --norestore --convert-to pdf --outdir %s -env:UserInstallation=file://%s %s 2>&1',
        escapeshellarg($loProfileDir),
        escapeshellarg($cacheDir),
        escapeshellarg($loProfileDir . '/lo_config'),
        escapeshellarg($sourcePath)
    );
    exec($cmd, $output, $returnCode);

    // Log always (not just on failure) so we can see what happened.
    error_log('convertToPdf cmd: ' . $cmd);
    error_log('convertToPdf return code: ' . $returnCode . ' output: ' . implode(' | ', $output));

    exec('rm -rf ' . escapeshellarg($loProfileDir));

    $originalPdfName = pathinfo($sourcePath, PATHINFO_FILENAME) . '.pdf';
    $generatedPath = $cacheDir . $originalPdfName;

    if (is_file($generatedPath)) {
        rename($generatedPath, $cachedPdf);
        return $cachedPdf;
    }

    error_log('convertToPdf failed for ' . $sourcePath . ': ' . implode("\n", $output));
    return null;
}

function deleteFolderRecursive(PDO $pdo, int $folderId, string $uploadDir, string $pdfCacheDir): void
{
    $stmt = $pdo->prepare("SELECT document_id, item_type, file_path FROM knowledge_documents WHERE parent_id = ?");
    $stmt->execute([$folderId]);
    foreach ($stmt->fetchAll() as $child) {
        if ($child['item_type'] === 'folder') {
            deleteFolderRecursive($pdo, (int) $child['document_id'], $uploadDir, $pdfCacheDir);
        } else {
            if ($child['file_path'] && is_file($uploadDir . basename($child['file_path']))) {
                unlink($uploadDir . basename($child['file_path']));
            }
            $cachedPdf = $pdfCacheDir . $child['document_id'] . '.pdf';
            if (is_file($cachedPdf)) {
                unlink($cachedPdf);
            }
        }
    }
    $pdo->prepare("DELETE FROM knowledge_documents WHERE document_id = ?")->execute([$folderId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $currentFolderId = isset($_POST['current_folder']) && $_POST['current_folder'] !== '' ? (int) $_POST['current_folder'] : null;

    if ($action === 'add_folder') {

        $folderName = trim($_POST['folder_name'] ?? '');
        if ($folderName !== '') {
            $stmt = $pdo->prepare("INSERT INTO knowledge_documents (item_type, parent_id, title, uploaded_by, uploaded_by_role) VALUES ('folder', ?, ?, ?, ?)");
            $stmt->execute([$currentFolderId, $folderName, $userId, $userRole]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Folder created successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Folder name is required.';
        }

    } elseif ($action === 'rename_folder') {

        $folderId = (int) ($_POST['folder_id'] ?? 0);
        $folderName = trim($_POST['folder_name'] ?? '');
        if ($folderName !== '' && $folderId > 0) {
            $stmt = $pdo->prepare("UPDATE knowledge_documents SET title = ? WHERE document_id = ? AND item_type = 'folder'");
            $stmt->execute([$folderName, $folderId]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Folder renamed successfully.';
        }

    } elseif ($action === 'delete_folder') {

        $folderId = (int) ($_POST['folder_id'] ?? 0);
        if ($folderId > 0) {
            deleteFolderRecursive($pdo, $folderId, $uploadDir, $pdfCacheDir);
        }
        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'Folder deleted successfully.';

    } elseif ($action === 'move_folder') {

        $folderId = (int) ($_POST['folder_id'] ?? 0);
        $destination = $_POST['destination'] !== '' ? (int) $_POST['destination'] : null;
        $stmt = $pdo->prepare("UPDATE knowledge_documents SET parent_id = ? WHERE document_id = ? AND item_type = 'folder'");
        $stmt->execute([$destination, $folderId]);
        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'Folder moved successfully.';

    } elseif ($action === 'rename_file') {

        $fileId = (int) ($_POST['file_id'] ?? 0);
        $title = trim($_POST['file_title'] ?? '');
        if ($title !== '' && $fileId > 0) {
            $stmt = $pdo->prepare("UPDATE knowledge_documents SET title = ? WHERE document_id = ? AND item_type = 'file'");
            $stmt->execute([$title, $fileId]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'File renamed successfully.';
        }

    } elseif ($action === 'delete_file') {

        $fileId = (int) ($_POST['file_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT file_path FROM knowledge_documents WHERE document_id = ? AND item_type = 'file'");
        $stmt->execute([$fileId]);
        $filePath = $stmt->fetchColumn();
        if ($filePath && is_file($uploadDir . basename($filePath))) {
            unlink($uploadDir . basename($filePath));
        }
        $cachedPdf = $pdfCacheDir . $fileId . '.pdf';
        if (is_file($cachedPdf)) {
            unlink($cachedPdf);
        }
        $pdo->prepare("DELETE FROM knowledge_documents WHERE document_id = ?")->execute([$fileId]);
        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'File deleted successfully.';

    } elseif ($action === 'move_file') {

        $fileId = (int) ($_POST['file_id'] ?? 0);
        $destination = $_POST['destination'] !== '' ? (int) $_POST['destination'] : null;
        $stmt = $pdo->prepare("UPDATE knowledge_documents SET parent_id = ? WHERE document_id = ? AND item_type = 'file'");
        $stmt->execute([$destination, $fileId]);
        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'File moved successfully.';

    } elseif ($action === 'upload_file') {

        if (!empty($_FILES['files']['name'][0])) {
            $count = count($_FILES['files']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $originalName = $_FILES['files']['name'][$i];
                $storedName = uniqid('doc_', true) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
                $tmpPath = $_FILES['files']['tmp_name'][$i];
                $size = $_FILES['files']['size'][$i];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (move_uploaded_file($tmpPath, $uploadDir . $storedName)) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO knowledge_documents
                         (item_type, parent_id, title, file_name, file_path, file_size, file_type, uploaded_by, uploaded_by_role)
                         VALUES ('file', ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$currentFolderId, $originalName, $originalName, 'repository/' . $storedName, $size, $ext, $userId, $userRole]);
                }
            }
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'File(s) uploaded successfully.';
        }
    }

    $redirect = 'knowledge_repository.php';
    if ($currentFolderId !== null) {
        $redirect .= '?folder=' . $currentFolderId;
    }
    header('Location: ' . $redirect);
    exit;
}

if (isset($_GET['download'])) {
    $fileId = (int) $_GET['download'];
    $stmt = $pdo->prepare("SELECT title, file_path FROM knowledge_documents WHERE document_id = ? AND item_type = 'file'");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if ($file && is_file($uploadDir . basename($file['file_path']))) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['title'] . '"');
        header('Content-Length: ' . filesize($uploadDir . basename($file['file_path'])));
        readfile($uploadDir . basename($file['file_path']));
        exit;
    }
}

/**
 * ?view=<id>  — opens inline in a new tab.
 * - pdf / images: served as-is, browser renders natively.
 * - docx / xlsx / pptx / doc / xls / ppt: converted to PDF on the server
 *   (cached after first conversion), then served as a PDF so the browser's
 *   own built-in PDF viewer displays it — no external viewer needed.
 */
if (isset($_GET['view'])) {
    $fileId = (int) $_GET['view'];
    $stmt = $pdo->prepare("SELECT title, file_path, file_type FROM knowledge_documents WHERE document_id = ? AND item_type = 'file'");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    // TEMP DEBUG — remove after
    error_log('VIEW debug: fileId=' . $fileId
        . ' row=' . json_encode($file)
        . ' expected_path=' . ($file ? $uploadDir . basename($file['file_path']) : 'n/a')
        . ' exists=' . ($file ? var_export(is_file($uploadDir . basename($file['file_path'])), true) : 'n/a'));

if ($file && is_file($uploadDir . basename($file['file_path']))) {
        $sourcePath = $uploadDir . basename($file['file_path']);
        $ext = strtolower($file['file_type'] ?? '');
        $baseTitle = pathinfo($file['title'], PATHINFO_FILENAME);

        $officeExts = ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'ppt'];

if (in_array($ext, $officeExts, true)) {
            $pdfPath = convertToPdf($sourcePath, $pdfCacheDir, (string) $fileId);

if ($pdfPath === null || !is_file($pdfPath)) {
http_response_code(500);
header('Content-Type: text/plain');
echo "Could not generate a preview for this file. You can still download it.";
exit;
            }

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $baseTitle . '.pdf"');
header('Content-Length: ' . filesize($pdfPath));
readfile($pdfPath);
exit;
        }

        $mimeTypes = [
'pdf'  => 'application/pdf',
'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
'gif'  => 'image/gif', 'webp' => 'image/webp',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $file['title'] . '"');
header('Content-Length: ' . filesize($sourcePath));
readfile($sourcePath);
exit;
    }

http_response_code(404);
header('Content-Type: text/plain');
echo 'File not found.';
exit;
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$currentFolderId = isset($_GET['folder']) && $_GET['folder'] !== '' ? (int) $_GET['folder'] : null;
$breadcrumb = folderPath($pdo, $currentFolderId);

$sortOption = $_GET['sort'] ?? 'name_asc';
$dateFilter = trim($_GET['date'] ?? '');

$orderBy = match ($sortOption) {
    'newest' => 'created_at DESC',
    'oldest' => 'created_at ASC',
    'name_desc' => 'title DESC',
    default => 'title ASC',
};

$dateCondition = '';
$dateParams = [];
if ($dateFilter !== '') {
    $dateCondition = ' AND DATE(created_at) = ?';
    $dateParams[] = $dateFilter;
}

if ($currentFolderId === null) {
    $stmt = $pdo->prepare("SELECT * FROM knowledge_documents WHERE item_type = 'folder' AND parent_id IS NULL$dateCondition ORDER BY $orderBy");
    $stmt->execute($dateParams);
    $subfolders = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM knowledge_documents WHERE item_type = 'file' AND parent_id IS NULL$dateCondition ORDER BY $orderBy");
    $stmt->execute($dateParams);
    $files = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM knowledge_documents WHERE item_type = 'folder' AND parent_id = ?$dateCondition ORDER BY $orderBy");
    $stmt->execute(array_merge([$currentFolderId], $dateParams));
    $subfolders = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM knowledge_documents WHERE item_type = 'file' AND parent_id = ?$dateCondition ORDER BY $orderBy");
    $stmt->execute(array_merge([$currentFolderId], $dateParams));
    $files = $stmt->fetchAll();
}

$allFolders = $pdo->query("SELECT document_id, title, parent_id FROM knowledge_documents WHERE item_type = 'folder' ORDER BY title ASC")->fetchAll();

$totalFolders = (int) $pdo->query("SELECT COUNT(*) FROM knowledge_documents WHERE item_type = 'folder'")->fetchColumn();
$totalFiles   = (int) $pdo->query("SELECT COUNT(*) FROM knowledge_documents WHERE item_type = 'file'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Repository</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --navy-deep: #0F172A;
  --indigo: #3B4E8A;
  --indigo-soft: #E9ECF6;
  --indigo-text: #2E3E70;
  --slate: #475569;
  --ink: #1A2233;
  --ink-soft: #667085;
  --line: #E2E5EB;
  --canvas: #FFFFFF;
}
body { background-color: var(--canvas); color: var(--ink); font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
.dashboard-layout, .dashboard-main, .dashboard-content { background-color: var(--canvas) !important; }
.dashboard-title, h1, h2, h3 { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--navy-deep); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background-color: #fff; }
.card { border-radius: 12px; border: 1px solid var(--line) !important; box-shadow: none !important; }
.form-control:focus, .form-select:focus { border-color: var(--indigo); box-shadow: 0 0 0 .2rem rgba(59,78,138,.13); outline: none; }
.form-label { font-size: .8rem; font-weight: 700; color: var(--slate); text-transform: uppercase; letter-spacing: .02em; }
.btn-teal-solid { background-color: var(--indigo); color: #fff; border: none; border-radius: 8px; font-weight: 600; }
.btn-teal-solid:hover { background-color: var(--indigo-text); color: #fff; }
.btn-outline-soft { border: 1px solid var(--line); color: var(--slate); border-radius: 8px; font-weight: 600; background: #fff; }
.btn-outline-soft:hover { background-color: #F5F6F9; color: var(--ink); }

.stat-card { background-color: #fff; border: 1px solid var(--line); border-radius: 12px; padding: .85rem 1rem; display: flex; align-items: center; gap: .7rem; height: 100%; }
.stat-icon { width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .95rem; }
.stat-label { font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); }
.stat-value { font-family: 'Lexend', sans-serif; font-size: 1.25rem; font-weight: 700; margin-top: .1rem; }

.repo-breadcrumb { font-size: .85rem; }
.repo-breadcrumb a { color: var(--ink-soft); text-decoration: none; }
.repo-breadcrumb a:hover { color: var(--indigo-text); }
.repo-breadcrumb .current { color: var(--ink); font-weight: 600; }

.repo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .75rem; }
.repo-item {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .9rem .75rem;
  text-align: center;
  position: relative;
  cursor: pointer;
  transition: border-color .12s ease, background-color .12s ease;
}
.repo-item.is-loading { opacity: .6; pointer-events: none; }
.repo-item:hover { border-color: var(--indigo); background-color: #FAFAFB; }
.repo-item i.repo-icon { font-size: 1.9rem; color: var(--indigo-text); }
.repo-item .repo-name { font-size: .78rem; font-weight: 600; color: var(--ink); margin-top: .5rem; word-break: break-word; }
.repo-item .repo-meta { font-size: .68rem; color: var(--ink-soft); margin-top: .15rem; }
.repo-item .repo-category { font-size: .64rem; color: var(--indigo-text); background: var(--indigo-soft); border-radius: 999px; padding: .05rem .5rem; display: inline-block; margin-top: .3rem; }
.repo-item .repo-menu-btn {
  position: absolute; top: 6px; right: 6px; border: none; background: transparent;
  color: var(--ink-soft); width: 24px; height: 24px; border-radius: 6px; font-size: .8rem;
}
.repo-item .repo-menu-btn:hover { background-color: var(--indigo-soft); color: var(--indigo-text); }
.repo-section-label { font-size: .72rem; font-weight: 700; color: var(--ink-soft); text-transform: uppercase; letter-spacing: .04em; margin: 1.25rem 0 .6rem; }
.repo-empty { color: var(--ink-soft); text-align: center; padding: 2.5rem 1rem; }
.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 px-md-4 py-2">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Repository</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Organize client files and company templates.</p>
        </div>
      </div>

      <form method="GET" class="d-flex align-items-center gap-2 flex-wrap" id="repoFilterForm">
        <?php if ($currentFolderId !== null): ?>
          <input type="hidden" name="folder" value="<?= $currentFolderId ?>">
        <?php endif; ?>
        <div class="input-group input-group-sm" style="width:180px;">
          <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
          <input type="text" id="repoLiveSearch" class="form-control" placeholder="Search">
        </div>
        <select name="sort" class="form-select form-select-sm" style="width:150px;" onchange="this.form.submit()">
          <option value="name_asc" <?= $sortOption === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
          <option value="name_desc" <?= $sortOption === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
          <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
          <option value="oldest" <?= $sortOption === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
        </select>
        <input type="date" name="date" class="form-control form-control-sm" style="width:140px;" value="<?= htmlspecialchars($dateFilter) ?>" onchange="this.form.submit()">
        <?php if ($dateFilter !== ''): ?>
          <a href="knowledge_repository.php<?= $currentFolderId !== null ? '?folder=' . $currentFolderId : '' ?>" class="btn btn-outline-soft btn-sm" title="Clear date filter">
            <i class="fa-solid fa-xmark"></i>
          </a>
        <?php endif; ?>
      </form>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--indigo-soft);">
              <i class="fa-solid fa-folder" style="color:var(--indigo-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Folders</div>
              <div class="stat-value" style="color:var(--indigo-text);"><?= $totalFolders ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--indigo-soft);">
              <i class="fa-solid fa-file" style="color:var(--indigo-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Files</div>
              <div class="stat-value" style="color:var(--indigo-text);"><?= $totalFiles ?></div>
            </div>
          </div>
        </div>
      </section>

      <section class="card">
        <div class="card-body p-3">

          <nav class="repo-breadcrumb d-flex align-items-center gap-2 flex-wrap mb-3">
            <a href="knowledge_repository.php"><i class="fa-solid fa-house"></i> Repository</a>
            <?php foreach ($breadcrumb as $index => $crumb): ?>
              <span style="color:var(--ink-soft);">/</span>
              <?php if ($index === count($breadcrumb) - 1): ?>
                <span class="current"><?= htmlspecialchars($crumb['title']) ?></span>
              <?php else: ?>
                <a href="knowledge_repository.php?folder=<?= $crumb['document_id'] ?>"><?= htmlspecialchars($crumb['title']) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </nav>

          <div class="d-flex justify-content-end gap-2 mb-3">
            <button type="button" class="btn btn-outline-soft btn-sm" data-bs-toggle="modal" data-bs-target="#newFolderModal">
              <i class="fa-solid fa-folder-plus"></i> New Folder
            </button>
            <button type="button" class="btn btn-teal-solid btn-sm" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
              <i class="fa-solid fa-upload"></i> Upload
            </button>
          </div>

          <?php if (empty($subfolders) && empty($files)): ?>
            <div class="repo-empty">
              <i class="fa-regular fa-folder-open fs-2 d-block mb-2"></i>
              This folder is empty.
            </div>
          <?php else: ?>

            <?php if (!empty($subfolders)): ?>
              <div class="repo-section-label">Folders</div>
              <div class="repo-grid mb-3">
                <?php foreach ($subfolders as $folder): ?>
                  <div class="repo-item" data-name="<?= htmlspecialchars(strtolower($folder['title'])) ?>" onclick="if(!event.target.closest('.repo-menu-btn')) window.location='knowledge_repository.php?folder=<?= $folder['document_id'] ?>'">
                    <button type="button" class="repo-menu-btn dropdown-toggle" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                      <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <ul class="dropdown-menu" onclick="event.stopPropagation()">
                      <li><a class="dropdown-item" href="#" onclick="openRenameFolder(<?= $folder['document_id'] ?>, '<?= htmlspecialchars($folder['title'], ENT_QUOTES) ?>'); return false;"><i class="fa-regular fa-pen-to-square me-2"></i>Rename</a></li>
                      <li><a class="dropdown-item" href="#" onclick="openMoveFolder(<?= $folder['document_id'] ?>); return false;"><i class="fa-solid fa-arrows-up-down-left-right me-2"></i>Move</a></li>
                      <li><a class="dropdown-item text-danger" href="#" onclick="openDeleteFolder(<?= $folder['document_id'] ?>, '<?= htmlspecialchars($folder['title'], ENT_QUOTES) ?>'); return false;"><i class="fa-regular fa-trash-can me-2"></i>Delete</a></li>
                    </ul>
                    <i class="fa-solid fa-folder repo-icon"></i>
                    <div class="repo-name text-truncate" title="<?= htmlspecialchars($folder['title']) ?>"><?= htmlspecialchars($folder['title']) ?></div>
                    <div class="repo-meta"><?= date('M d, Y', strtotime($folder['created_at'])) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($files)): ?>
              <div class="repo-section-label">Files</div>
              <div class="repo-grid">
                <?php foreach ($files as $file): ?>
                  <?php
                    $ext = strtolower($file['file_type'] ?? '');
                    $mode = previewMode($ext);
                    $viewUrl = 'knowledge_repository.php?view=' . $file['document_id'];
                    $downloadUrl = 'knowledge_repository.php?download=' . $file['document_id'];

                    if ($mode === 'office') {
                        // Convert-then-open: JS shows a brief loading state, opens the
                        // view URL (server converts to PDF) in a new tab.
                        $clickAction = "previewFile(this, " . $file['document_id'] . ");";
                    } elseif ($mode === 'native') {
                        // Browser renders pdf/images directly — open the view URL in a new tab.
                        $clickAction = "window.open('" . $viewUrl . "', '_blank', 'noopener,noreferrer');";
                    } else {
                        $clickAction = "window.location='" . $downloadUrl . "';";
                    }
                  ?>
                  <div class="repo-item" data-name="<?= htmlspecialchars(strtolower($file['title'])) ?>"
                    onclick="if(event.target.closest('.repo-menu-btn')) return; <?= $clickAction ?>">
                    <button type="button" class="repo-menu-btn dropdown-toggle" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                      <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <ul class="dropdown-menu" onclick="event.stopPropagation()">
                      <?php if ($mode === 'office'): ?>
                        <li><a class="dropdown-item" href="#" onclick="previewFile(null, <?= $file['document_id'] ?>); return false;"><i class="fa-regular fa-eye me-2"></i>View</a></li>
                      <?php elseif ($mode === 'native'): ?>
                        <li><a class="dropdown-item" href="<?= $viewUrl ?>" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-eye me-2"></i>View</a></li>
                      <?php endif; ?>
                      <li><a class="dropdown-item" href="<?= $downloadUrl ?>"><i class="fa-solid fa-download me-2"></i>Download</a></li>
                      <li><a class="dropdown-item" href="#" onclick="openRenameFile(<?= $file['document_id'] ?>, '<?= htmlspecialchars($file['title'], ENT_QUOTES) ?>'); return false;"><i class="fa-regular fa-pen-to-square me-2"></i>Rename</a></li>
                      <li><a class="dropdown-item" href="#" onclick="openMoveFile(<?= $file['document_id'] ?>); return false;"><i class="fa-solid fa-arrows-up-down-left-right me-2"></i>Move</a></li>
                      <li><a class="dropdown-item text-danger" href="#" onclick="openDeleteFile(<?= $file['document_id'] ?>, '<?= htmlspecialchars($file['title'], ENT_QUOTES) ?>'); return false;"><i class="fa-regular fa-trash-can me-2"></i>Delete</a></li>
                    </ul>
                    <i class="fa-solid <?= fileIcon($file['file_name'] ?? $file['title']) ?> repo-icon"></i>
                    <div class="repo-name text-truncate" title="<?= htmlspecialchars($file['title']) ?>"><?= htmlspecialchars($file['title']) ?></div>
                    <div class="repo-meta"><?= formatFileSize((int) $file['file_size']) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          <?php endif; ?>

        </div>
      </section>

    </main>
  </div>
</div>

<div class="modal fade" id="newFolderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_folder">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">New Folder</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Folder Name</label>
          <input type="text" name="folder_name" class="form-control" required autofocus>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Create Folder</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_file">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Upload Files</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Files</label>
          <input type="file" name="files[]" class="form-control" multiple required>
          <div class="form-text mt-2">Files will be uploaded to the current folder.</div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="renameFolderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="rename_folder">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <input type="hidden" name="folder_id" id="rename_folder_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Rename Folder</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Folder Name</label>
          <input type="text" name="folder_name" id="rename_folder_name" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteFolderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete_folder">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <input type="hidden" name="folder_id" id="delete_folder_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Delete Folder</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="delete_folder_name"></strong>? This will also delete everything inside it.</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid" style="background-color:#B4432F;">Delete Folder</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="moveFolderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="move_folder">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <input type="hidden" name="folder_id" id="move_folder_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Move Folder</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Destination</label>
          <select name="destination" class="form-select">
            <option value="">Root</option>
            <?php foreach ($allFolders as $folder): ?>
              <option value="<?= $folder['document_id'] ?>"><?= htmlspecialchars($folder['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Move</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="renameFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="rename_file">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <input type="hidden" name="file_id" id="rename_file_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Rename File</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Display Name</label>
          <input type="text" name="file_title" id="rename_file_title" class="form-control" required>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete_file">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <input type="hidden" name="file_id" id="delete_file_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Delete File</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="delete_file_name"></strong>?</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid" style="background-color:#B4432F;">Delete File</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="moveFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="move_file">
        <input type="hidden" name="current_folder" value="<?= $currentFolderId ?? '' ?>">
        <input type="hidden" name="file_id" id="move_file_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Move File</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Destination</label>
          <select name="destination" class="form-select">
            <option value="">Root</option>
            <?php foreach ($allFolders as $folder): ?>
              <option value="<?= $folder['document_id'] ?>"><?= htmlspecialchars($folder['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Move</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
function openRenameFolder(id, name) {
  document.getElementById('rename_folder_id').value = id;
  document.getElementById('rename_folder_name').value = name;
  new bootstrap.Modal(document.getElementById('renameFolderModal')).show();
}
function openDeleteFolder(id, name) {
  document.getElementById('delete_folder_id').value = id;
  document.getElementById('delete_folder_name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteFolderModal')).show();
}
function openMoveFolder(id) {
  document.getElementById('move_folder_id').value = id;
  new bootstrap.Modal(document.getElementById('moveFolderModal')).show();
}
function openRenameFile(id, name) {
  document.getElementById('rename_file_id').value = id;
  document.getElementById('rename_file_title').value = name;
  new bootstrap.Modal(document.getElementById('renameFileModal')).show();
}
function openDeleteFile(id, name) {
  document.getElementById('delete_file_id').value = id;
  document.getElementById('delete_file_name').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteFileModal')).show();
}
function openMoveFile(id) {
  document.getElementById('move_file_id').value = id;
  new bootstrap.Modal(document.getElementById('moveFileModal')).show();
}

const repoSearchInput = document.getElementById('repoLiveSearch');
if (repoSearchInput) {
  repoSearchInput.addEventListener('input', function () {
    const query = this.value.trim().toLowerCase();
    document.querySelectorAll('.repo-item').forEach(function (item) {
      const name = item.getAttribute('data-name') || '';
      item.style.display = name.includes(query) ? '' : 'none';
    });
    document.querySelectorAll('.repo-section-label').forEach(function (label) {
      const grid = label.nextElementSibling;
      if (!grid || !grid.classList.contains('repo-grid')) return;
      const anyVisible = Array.from(grid.children).some(function (item) {
        return item.style.display !== 'none';
      });
      label.style.display = anyVisible ? '' : 'none';
      grid.style.display = anyVisible ? '' : 'none';
    });
  });
}

/**
 * docx / xlsx / pptx (and legacy doc/xls/ppt) don't render natively in the browser.
 * The server (?view=<id>) converts them to PDF on the fly using LibreOffice and
 * caches the result, so we just open that same URL in a new tab — the browser's
 * own built-in PDF viewer takes it from there. No external service needed, and
 * it works on localhost too.
 *
 * Since the actual conversion happens server-side and can take a second or two
 * on first view (cached afterward), we show a brief loading state on the clicked
 * card while the new tab opens and starts fetching.
 */
function previewFile(cardEl, id) {
  const viewUrl = 'knowledge_repository.php?view=' + id;

  if (cardEl) {
    cardEl.classList.add('is-loading');
    setTimeout(function () {
      cardEl.classList.remove('is-loading');
    }, 1500);
  }

  window.open(viewUrl, '_blank', 'noopener,noreferrer');
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>