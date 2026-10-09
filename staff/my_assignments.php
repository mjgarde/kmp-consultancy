<?php
session_name('STAFF_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo    = getConnection();
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        $allowed = [
            'New'         => ['In Progress'],
            'In Progress' => ['Completed', 'Cancelled'],
        ];

        $stmt = $pdo->prepare('SELECT status FROM service_requests WHERE request_id = ? AND assigned_to = ?');
        $stmt->execute([$requestId, $userId]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            $_SESSION['alert_type']    = 'error';
            $_SESSION['alert_message'] = 'This request is not assigned to you.';
        } elseif (!in_array($newStatus, $allowed[$current] ?? [], true)) {
            $_SESSION['alert_type']    = 'warning';
            $_SESSION['alert_message'] = 'This status change is not allowed.';
        } else {
            try {
                $upd = $pdo->prepare('UPDATE service_requests SET status = ? WHERE request_id = ? AND assigned_to = ?');
                $upd->execute([$newStatus, $requestId, $userId]);
                $_SESSION['alert_type']    = 'success';
                $_SESSION['alert_message'] = 'Request status updated to ' . $newStatus . '.';
            } catch (Throwable $e) {
                error_log('update_status failed: ' . $e->getMessage());
                $_SESSION['alert_type']    = 'error';
                $_SESSION['alert_message'] = 'Failed to update status. Please try again.';
            }
        }

        $back = ['tab' => 'assignments'];
        foreach (['search', 'status', 'sort', 'page'] as $key) {
            if (isset($_POST['back_' . $key]) && $_POST['back_' . $key] !== '') {
                $back[$key] = $_POST['back_' . $key];
            }
        }
        header('Location: my_assignments.php?' . http_build_query($back));
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$searchTerm   = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sortOrder    = $_GET['sort'] ?? 'newest';
$sortSql      = $sortOrder === 'oldest' ? 'ASC' : 'DESC';
$perPage      = 25;
$page         = max(1, (int) ($_GET['page'] ?? 1));

$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'New'), 0) AS new_count,
            COALESCE(SUM(status = 'In Progress'), 0) AS in_progress_count,
            COALESCE(SUM(status = 'Completed'), 0) AS completed_count,
            COALESCE(SUM(status = 'Cancelled'), 0) AS cancelled_count
     FROM service_requests
     WHERE assigned_to = ?"
);
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

$totalAssignments = (int) $stats['total'];
$newCount         = (int) $stats['new_count'];
$inProgressCount  = (int) $stats['in_progress_count'];
$completedCount   = (int) $stats['completed_count'];
$cancelledCount   = (int) $stats['cancelled_count'];

$where  = 'WHERE sr.assigned_to = ?';
$params = [$userId];

if ($searchTerm !== '') {
    $where .= ' AND (sr.request_title LIKE ? OR c.company_name LIKE ? OR sr.required_skill LIKE ?)';
    $like = '%' . $searchTerm . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (in_array($statusFilter, ['New', 'In Progress', 'Completed', 'Cancelled'], true)) {
    $where .= ' AND sr.status = ?';
    $params[] = $statusFilter;
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM service_requests sr JOIN clients c ON c.client_id = sr.client_id $where"
);
$countStmt->execute($params);
$filteredCount = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($filteredCount / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT sr.request_id, sr.request_title, sr.required_skill, sr.status, sr.created_at, sr.updated_at,
            c.company_name, c.contact_person, c.email, c.contact_number, c.industry
     FROM service_requests sr
     JOIN clients c ON c.client_id = sr.client_id
     $where
     ORDER BY sr.created_at $sortSql
     LIMIT $perPage OFFSET $offset"
);
$listStmt->execute($params);
$assignments = $listStmt->fetchAll();

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'New' => 'status-draft',
        'In Progress' => 'status-warn',
        'Completed' => 'status-approved',
        'Cancelled' => 'status-rejected',
        default => 'status-draft',
    };
}

function statusIcon(string $status): string
{
    return match ($status) {
        'New' => 'fa-circle-plus',
        'In Progress' => 'fa-hourglass-half',
        'Completed' => 'fa-circle-check',
        'Cancelled' => 'fa-circle-xmark',
        default => 'fa-circle',
    };
}

function buildPageUrl(int $targetPage, string $searchTerm, string $sortOrder, string $statusFilter): string
{
    $params = [
        'page' => $targetPage,
        'search' => $searchTerm,
        'sort' => $sortOrder,
    ];
    if ($statusFilter !== '') {
        $params['status'] = $statusFilter;
    }
    return '?' . http_build_query($params);
}

function pageWindow(int $current, int $total): array
{
    if ($total <= 7) {
        return range(1, $total);
    }
    $pages = [1];
    $start = max(2, $current - 1);
    $end = min($total - 1, $current + 1);
    if ($start > 2) {
        $pages[] = '...';
    }
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }
    if ($end < $total - 1) {
        $pages[] = '...';
    }
    $pages[] = $total;
    return $pages;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Assignments</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
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
}

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  -webkit-font-smoothing: antialiased;
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
.card:hover { box-shadow: none !important; transform: none !important; }

.form-control, .form-select {
  border-color: #D5DAE3;
  border-radius: 8px;
  color: var(--ink);
}
.form-control:focus, .form-select:focus {
  border-color: var(--indigo);
  box-shadow: 0 0 0 .2rem #DDE2F1;
  outline: none;
}
.input-group-text { border-color: #D5DAE3; border-radius: 8px 0 0 8px; }
.input-group .form-control { border-radius: 0 8px 8px 0; }

.form-label { font-size: .85rem; font-weight: 600; color: var(--slate); }

.btn { box-shadow: none !important; transform: none !important; }

.btn-brand {
  background-color: var(--indigo);
  color: #fff;
  border: 1px solid var(--indigo);
  border-radius: 8px;
  font-weight: 600;
}
.btn-brand:hover, .btn-brand:focus, .btn-brand:active {
  background-color: var(--indigo);
  border-color: var(--indigo);
  color: #fff;
}

.btn-success-solid {
  background-color: var(--success);
  color: #fff;
  border: 1px solid var(--success);
  border-radius: 8px;
  font-weight: 600;
}
.btn-success-solid:hover, .btn-success-solid:focus, .btn-success-solid:active {
  background-color: var(--success);
  border-color: var(--success);
  color: #fff;
}

.btn-danger-solid {
  background-color: var(--danger);
  color: #fff;
  border: 1px solid var(--danger);
  border-radius: 8px;
  font-weight: 600;
}
.btn-danger-solid:hover, .btn-danger-solid:focus, .btn-danger-solid:active {
  background-color: var(--danger);
  border-color: var(--danger);
  color: #fff;
}

.stat-bar {
  display: flex;
  align-items: center;
  justify-content: space-around;
  background-color: #FFFFFF;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 1rem 1.15rem;
}

.stat-item {
  flex: 1 1 0;
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: .65rem;
  min-width: 0;
  text-align: center;
}

.stat-item .stat-label {
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--stat-color);
  white-space: nowrap;
}

.stat-item .stat-value {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1;
  color: var(--stat-color);
}

.stat-divider {
  width: 1px;
  align-self: stretch;
  min-height: 28px;
  background-color: var(--line);
  flex-shrink: 0;
}

.stat-total { --stat-color: #0F172A; }
.stat-new { --stat-color: #475569; }
.stat-progress { --stat-color: #8A5A15; }
.stat-completed { --stat-color: #0F5F49; }
.stat-cancelled { --stat-color: #93382A; }

.status-filter {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
}

.status-filter .filter-btn {
  --fc: #475569;
  display: inline-flex;
  align-items: center;
  padding: .45rem 1rem;
  border-radius: 8px;
  border: 2px solid var(--fc);
  background-color: #fff;
  color: var(--fc);
  font-size: .82rem;
  font-weight: 700;
  line-height: 1.2;
  box-shadow: none;
}

.status-filter .filter-btn.fc-all { --fc: #0F172A; }
.status-filter .filter-btn.fc-new { --fc: #475569; }
.status-filter .filter-btn.fc-progress { --fc: #B7791F; }
.status-filter .filter-btn.fc-completed { --fc: #157A5F; }
.status-filter .filter-btn.fc-cancelled { --fc: #B4432F; }

.status-filter .filter-btn.active {
  background-color: var(--fc);
  color: #fff;
}

.status-filter .filter-btn:hover,
.status-filter .filter-btn:focus,
.status-filter .filter-btn:active {
  background-color: #fff;
  color: var(--fc);
  border-color: var(--fc);
  box-shadow: none;
}

.status-filter .filter-btn.active:hover,
.status-filter .filter-btn.active:focus,
.status-filter .filter-btn.active:active {
  background-color: var(--fc);
  color: #fff;
}

.table > :not(caption) > * > * { background-color: transparent; box-shadow: none; }
.table-hover > tbody > tr:hover > * { --bs-table-accent-bg: transparent; background-color: transparent; }

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .7rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  background-color: #FAFBFD !important;
  padding-top: .8rem;
  padding-bottom: .8rem;
}
.table td { border-bottom: 1px solid var(--line); color: var(--ink); vertical-align: middle; padding-top: .8rem; padding-bottom: .8rem; }
.table tbody tr:last-child td { border-bottom: none; }

.status-stack {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .3rem;
  font-size: .76rem;
  font-weight: 700;
  line-height: 1.1;
  text-align: center;
}
.status-stack i { font-size: 1.1rem; }
.status-stack.status-draft { color: var(--slate); }
.status-stack.status-warn { color: var(--warn-text); }
.status-stack.status-approved { color: var(--success-text); }
.status-stack.status-rejected { color: var(--danger-text); }

.skill-tag-static {
  background-color: var(--indigo-soft);
  color: var(--indigo-text);
  font-size: .68rem;
  font-weight: 700;
  padding: .2rem .55rem;
  border-radius: 999px;
  display: inline-block;
}

.assignment-row { cursor: pointer; }

.pagination { gap: .25rem; flex-wrap: wrap; }
.pagination .page-link {
  color: var(--indigo-text);
  border: 1px solid var(--line);
  border-radius: 7px !important;
  font-weight: 600;
  min-width: 32px;
  text-align: center;
  font-variant-numeric: tabular-nums;
  background-color: #fff;
}
.pagination .page-link:hover { background-color: #fff; color: var(--indigo-text); }
.pagination .page-item.active .page-link { background-color: var(--indigo); border-color: var(--indigo); color: #fff; }
.pagination .page-item.disabled .page-link { color: #adb5bd; background-color: transparent; }

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }

#alertModal .modal-content { text-align: center; }
#alertModal .modal-body { padding: 1.75rem 1.5rem 1rem; }
#alertModal .modal-footer { justify-content: center; border-top: none; padding: .25rem 1.5rem 1.5rem; }
.alert-modal-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  font-size: 1.6rem;
}
.alert-modal-icon.is-success { background-color: var(--success-soft); color: var(--success-text); }
.alert-modal-icon.is-error { background-color: var(--danger-soft); color: var(--danger-text); }
.alert-modal-icon.is-warning { background-color: var(--warn-soft); color: var(--warn-text); }

.modal-wide.modal-dialog {
  width: 62vw;
  max-width: 880px;
  margin: auto;
}
.modal-wide .modal-content {
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  background-color: #FFFFFF;
}
.modal-wide .modal-header {
  flex: 0 0 auto;
  padding: 1.05rem 1.5rem;
  background-color: #FFFFFF;
}
.modal-wide .modal-title {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--navy-deep);
}
.modal-wide .btn-close {
  width: 36px;
  height: 36px;
  padding: 0;
  margin: 0 0 0 auto;
  flex-shrink: 0;
  background-size: 15px;
  border: none;
  border-radius: 8px;
  opacity: 1;
}
.modal-wide .btn-close:focus { box-shadow: none; }
.modal-wide .modal-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  padding: 1.4rem 1.5rem;
  background-color: #FAFBFD;
}
.modal-wide .modal-body .row {
  --bs-gutter-x: 1.25rem;
  --bs-gutter-y: 1.1rem;
}
.modal-wide .modal-footer {
  flex: 0 0 auto;
  padding: 1rem 1.5rem;
  gap: .6rem;
  background-color: #FFFFFF;
}
.modal-wide .modal-footer .btn {
  font-size: .95rem;
  padding: .55rem 1.4rem;
  border-radius: 9px;
}
.modal-wide .modal-footer form { margin: 0; }
.view-field {
  height: 100%;
  background-color: #FFFFFF;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .85rem 1.1rem;
}
.view-label {
  font-size: .8rem;
  font-weight: 600;
  color: var(--ink-soft);
  margin-bottom: .3rem;
}
.view-value {
  font-size: 1.1rem;
  font-weight: 600;
  line-height: 1.4;
  color: var(--ink);
  word-break: break-word;
}

@media (max-width: 1199.98px) {
  .modal-wide.modal-dialog { width: 72vw; }
}

@media (max-width: 991.98px) {
  .modal-wide.modal-dialog { width: 84vw; max-width: 84vw; }
  .modal-wide .modal-header { padding: .95rem 1.25rem; }
  .modal-wide .modal-title { font-size: 1.15rem; }
  .modal-wide .modal-body { padding: 1.2rem 1.25rem; }
  .modal-wide .modal-body .row { --bs-gutter-x: 1rem; --bs-gutter-y: 1rem; }
  .modal-wide .modal-footer { padding: .9rem 1.25rem; }
  .modal-wide .modal-footer .btn { font-size: .9rem; padding: .5rem 1.25rem; }
  .view-field { padding: .75rem 1rem; }
  .view-label { font-size: .76rem; }
  .view-value { font-size: 1.02rem; }
}

@media (max-width: 767.98px) {
  .stat-bar { padding: .7rem .3rem; }
  .stat-item { flex-direction: column; align-items: center; gap: .2rem; }
  .stat-item .stat-label { font-size: .5rem; letter-spacing: .02em; }
  .stat-item .stat-value { font-size: 1.05rem; }
  .stat-divider { min-height: 24px; }

  .status-filter { gap: .4rem; }
  .status-filter .filter-btn { padding: .4rem .75rem; font-size: .76rem; }

  .btn { font-size: .82rem; padding: .4rem .7rem; }
  .form-control, .form-select { font-size: .85rem; padding: .4rem .65rem; }

  .table td, .table th { padding: .55rem .6rem; }
  .table thead th { font-size: .62rem; }
  .status-stack { font-size: .68rem; }

  .pagination .page-link { padding: .25rem .5rem; font-size: .75rem; min-width: 28px; }

  .modal-wide.modal-dialog { width: 94vw; max-width: 94vw; }
  .modal-wide .modal-content { max-height: 90vh; border-radius: 12px; }
  .modal-wide .modal-header { padding: .8rem 1rem; }
  .modal-wide .modal-title { font-size: 1rem; }
  .modal-wide .btn-close { width: 34px; height: 34px; background-size: 13px; }
  .modal-wide .modal-body { padding: 1rem; }
  .modal-wide .modal-body .row { --bs-gutter-x: .75rem; --bs-gutter-y: .8rem; }
  .modal-wide .modal-footer { padding: .75rem 1rem; gap: .5rem; }
  .modal-wide .modal-footer .btn { font-size: .85rem; padding: .48rem 1.1rem; border-radius: 8px; }
  .view-field { padding: .65rem .8rem; border-radius: 9px; }
  .view-label { font-size: .7rem; margin-bottom: .15rem; }
  .view-value { font-size: .92rem; }
}

@media (max-width: 575.98px) {
  .dashboard-title { font-size: 1rem; }
}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">My Assignments</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Service requests assigned to you.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="stat-bar mb-3">
        <div class="stat-item stat-total">
          <span class="stat-label">Total</span>
          <span class="stat-value"><?= $totalAssignments ?></span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item stat-new">
          <span class="stat-label">New</span>
          <span class="stat-value"><?= $newCount ?></span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item stat-progress">
          <span class="stat-label">In Progress</span>
          <span class="stat-value"><?= $inProgressCount ?></span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item stat-completed">
          <span class="stat-label">Completed</span>
          <span class="stat-value"><?= $completedCount ?></span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item stat-cancelled">
          <span class="stat-label">Cancelled</span>
          <span class="stat-value"><?= $cancelledCount ?></span>
        </div>
      </section>

      <section class="card mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="row g-2 align-items-center" method="GET" id="filterForm">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <div class="col-12 col-md-8">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
                <input type="text" name="search" id="searchInput" class="form-control" placeholder="Search request, client or skill" value="<?= htmlspecialchars($searchTerm) ?>" autocomplete="off">
              </div>
            </div>
            <div class="col-12 col-md-4">
              <select name="sort" class="form-select" onchange="this.form.submit()">
                <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
                <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
              </select>
            </div>
            <div class="col-12 pt-1">
              <div class="status-filter">
                <button type="submit" name="status" value="" class="btn filter-btn fc-all <?= $statusFilter === '' ? 'active' : '' ?>">All</button>
                <button type="submit" name="status" value="New" class="btn filter-btn fc-new <?= $statusFilter === 'New' ? 'active' : '' ?>">New</button>
                <button type="submit" name="status" value="In Progress" class="btn filter-btn fc-progress <?= $statusFilter === 'In Progress' ? 'active' : '' ?>">In Progress</button>
                <button type="submit" name="status" value="Completed" class="btn filter-btn fc-completed <?= $statusFilter === 'Completed' ? 'active' : '' ?>">Completed</button>
                <button type="submit" name="status" value="Cancelled" class="btn filter-btn fc-cancelled <?= $statusFilter === 'Cancelled' ? 'active' : '' ?>">Cancelled</button>
              </div>
            </div>
          </form>
        </div>
      </section>

      <section class="card">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Request</th>
                <th scope="col" class="d-none d-md-table-cell">Client</th>
                <th scope="col" class="d-none d-lg-table-cell">Required Skill</th>
                <th scope="col" class="d-none d-lg-table-cell">Date</th>
                <th scope="col" class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($assignments)): ?>
                <tr>
                  <td colspan="5" class="text-center py-5" style="color:var(--ink-soft);">
                    <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                    No assignments found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($assignments as $row): ?>
                  <tr class="assignment-row"
                    data-bs-toggle="modal" data-bs-target="#viewAssignmentModal"
                    data-id="<?= (int) $row['request_id'] ?>"
                    data-title="<?= htmlspecialchars($row['request_title']) ?>"
                    data-company="<?= htmlspecialchars($row['company_name']) ?>"
                    data-contact="<?= htmlspecialchars($row['contact_person']) ?>"
                    data-email="<?= htmlspecialchars($row['email']) ?>"
                    data-number="<?= htmlspecialchars($row['contact_number']) ?>"
                    data-industry="<?= htmlspecialchars($row['industry'] ?? '') ?>"
                    data-skill="<?= htmlspecialchars($row['required_skill'] ?? '') ?>"
                    data-created="<?= htmlspecialchars(date('M d, Y', strtotime($row['created_at']))) ?>"
                    data-updated="<?= htmlspecialchars(date('M d, Y', strtotime($row['updated_at']))) ?>"
                    data-status="<?= htmlspecialchars($row['status']) ?>">
                    <td>
                      <div class="fw-semibold small"><?= htmlspecialchars($row['request_title']) ?></div>
                      <div class="d-md-none" style="font-size:.72rem; color:var(--ink-soft);"><?= htmlspecialchars($row['company_name']) ?></div>
                    </td>
                    <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($row['company_name']) ?></td>
                    <td class="small d-none d-lg-table-cell">
                      <?php if (!empty($row['required_skill'])): ?>
                        <span class="skill-tag-static"><?= htmlspecialchars($row['required_skill']) ?></span>
                      <?php else: ?>
                        <span style="color:var(--ink-soft);">&mdash;</span>
                      <?php endif; ?>
                    </td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars(date('M d, Y', strtotime($row['created_at']))) ?></td>
                    <td class="text-center">
                      <span class="status-stack <?= statusBadgeClass($row['status']) ?>">
                        <span><?= htmlspecialchars($row['status']) ?></span>
                        <i class="fa-solid <?= statusIcon($row['status']) ?>"></i>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3" style="border-top:1px solid var(--line);">
          <span class="small" style="color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredCount ?> total</span>
          <nav aria-label="Assignments pagination">
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPageUrl($page - 1, $searchTerm, $sortOrder, $statusFilter) ?>">Previous</a>
              </li>
              <?php foreach (pageWindow($page, $totalPages) as $p): ?>
                <?php if ($p === '...'): ?>
                  <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                <?php else: ?>
                  <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= buildPageUrl($p, $searchTerm, $sortOrder, $statusFilter) ?>"><?= $p ?></a>
                  </li>
                <?php endif; ?>
              <?php endforeach; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPageUrl($page + 1, $searchTerm, $sortOrder, $statusFilter) ?>">Next</a>
              </li>
            </ul>
          </nav>
        </div>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<div class="modal fade" id="viewAssignmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-wide">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold">Assignment Details</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Request Title</div>
              <div class="view-value" id="view_title"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Status</div>
              <div class="view-value"><span class="status-stack" id="view_status"></span></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Client</div>
              <div class="view-value" id="view_company"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Industry</div>
              <div class="view-value" id="view_industry"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Contact Person</div>
              <div class="view-value" id="view_contact"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Contact Number</div>
              <div class="view-value" id="view_number"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Email Address</div>
              <div class="view-value" id="view_email"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Required Skill</div>
              <div class="view-value" id="view_skill"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Date Created</div>
              <div class="view-value" id="view_created"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Last Updated</div>
              <div class="view-value" id="view_updated"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer d-none" id="statusFooter">
        <form method="POST" id="statusForm">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="request_id" id="status_request_id" value="">
          <input type="hidden" name="back_search" value="<?= htmlspecialchars($searchTerm) ?>">
          <input type="hidden" name="back_status" value="<?= htmlspecialchars($statusFilter) ?>">
          <input type="hidden" name="back_sort" value="<?= htmlspecialchars($sortOrder) ?>">
          <input type="hidden" name="back_page" value="<?= (int) $page ?>">
          <button type="submit" name="new_status" value="In Progress" class="btn btn-brand d-none" id="btnStart"><i class="fa-solid fa-play me-2"></i>Start Work</button>
          <button type="submit" name="new_status" value="Completed" class="btn btn-success-solid d-none" id="btnComplete"><i class="fa-solid fa-circle-check me-2"></i>Mark as Completed</button>
          <button type="submit" name="new_status" value="Cancelled" class="btn btn-danger-solid d-none" id="btnCancel" onclick="return confirm('Cancel this request?');"><i class="fa-solid fa-circle-xmark me-2"></i>Cancel Request</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
    <div class="modal-content">
      <div class="modal-body">
        <div class="alert-modal-icon" id="alertModalIcon"><i class="fa-solid"></i></div>
        <h2 class="h5 fw-bold mb-2" id="alertModalTitle"></h2>
        <p class="small mb-0" id="alertModalMessage" style="color:var(--ink-soft);"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-brand px-5" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('viewAssignmentModal').addEventListener('show.bs.modal', function (event) {
  const data = event.relatedTarget.dataset;

  document.getElementById('view_title').textContent = data.title;
  document.getElementById('view_company').textContent = data.company;
  document.getElementById('view_contact').textContent = data.contact;
  document.getElementById('view_email').textContent = data.email;
  document.getElementById('view_number').textContent = data.number;
  document.getElementById('view_industry').textContent = data.industry || '-';
  document.getElementById('view_skill').textContent = data.skill || 'Not specified / any skill';
  document.getElementById('view_created').textContent = data.created;
  document.getElementById('view_updated').textContent = data.updated;

  const statusMap = {
    'New': { cls: 'status-draft', icon: 'fa-circle-plus' },
    'In Progress': { cls: 'status-warn', icon: 'fa-hourglass-half' },
    'Completed': { cls: 'status-approved', icon: 'fa-circle-check' },
    'Cancelled': { cls: 'status-rejected', icon: 'fa-circle-xmark' }
  };
  const meta = statusMap[data.status] || { cls: 'status-draft', icon: 'fa-circle' };
  const statusEl = document.getElementById('view_status');
  statusEl.className = 'status-stack ' + meta.cls;
  statusEl.innerHTML = '';
  const statusText = document.createElement('span');
  statusText.textContent = data.status;
  const statusIconEl = document.createElement('i');
  statusIconEl.className = 'fa-solid ' + meta.icon;
  statusEl.appendChild(statusText);
  statusEl.appendChild(statusIconEl);

  const footer = document.getElementById('statusFooter');
  const btnStart = document.getElementById('btnStart');
  const btnComplete = document.getElementById('btnComplete');
  const btnCancel = document.getElementById('btnCancel');

  document.getElementById('status_request_id').value = data.id;

  btnStart.classList.toggle('d-none', data.status !== 'New');
  btnComplete.classList.toggle('d-none', data.status !== 'In Progress');
  btnCancel.classList.toggle('d-none', data.status !== 'In Progress');
  footer.classList.toggle('d-none', data.status !== 'New' && data.status !== 'In Progress');
});

const filterForm = document.getElementById('filterForm');
const searchInput = document.getElementById('searchInput');
let searchTimer = null;

searchInput.addEventListener('input', function () {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(function () {
    sessionStorage.setItem('refocusSearch', '1');
    filterForm.submit();
  }, 400);
});

if (sessionStorage.getItem('refocusSearch') === '1') {
  sessionStorage.removeItem('refocusSearch');
  searchInput.focus();
  const end = searchInput.value.length;
  searchInput.setSelectionRange(end, end);
}

function showAlertModal(type, message) {
  const config = {
    success: { title: 'Success', icon: 'fa-circle-check', cls: 'is-success' },
    warning: { title: 'Notice', icon: 'fa-triangle-exclamation', cls: 'is-warning' },
    error:   { title: 'Something Went Wrong', icon: 'fa-circle-xmark', cls: 'is-error' }
  }[type] || { title: 'Notice', icon: 'fa-circle-info', cls: 'is-warning' };

  document.getElementById('alertModalIcon').className = 'alert-modal-icon ' + config.cls;
  document.getElementById('alertModalIcon').innerHTML = '<i class="fa-solid ' + config.icon + '"></i>';
  document.getElementById('alertModalTitle').textContent = config.title;
  document.getElementById('alertModalMessage').textContent = message;
  new bootstrap.Modal(document.getElementById('alertModal')).show();
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  showAlertModal(<?= json_encode($alertType) ?>, <?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>