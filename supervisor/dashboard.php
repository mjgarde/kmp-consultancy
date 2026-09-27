<?php

session_name('SUPERVISOR_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$filterMode  = $_GET['filter_mode'] ?? '';
$dateStart   = $_GET['date_start'] ?? '';
$dateEnd     = $_GET['date_end'] ?? '';
$dateSingle  = $_GET['date_single'] ?? '';

$validDate = function (string $d): bool {
    if ($d === '') return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
};

$hasFilter = false;
$filterWhereSr = '';
$filterWhereQt = '';
$filterWhereCt = '';
$filterParams  = [];
$filterLabel   = '';

if ($filterMode === 'range' && $validDate($dateStart) && $validDate($dateEnd)) {
    $hasFilter     = true;
    $filterWhereSr = " AND DATE(sr.created_at) BETWEEN :fstart AND :fend ";
    $filterWhereQt = " AND DATE(q.created_at) BETWEEN :fstart AND :fend ";
    $filterWhereCt = " AND DATE(created_at) BETWEEN :fstart AND :fend ";
    $filterParams  = [':fstart' => $dateStart, ':fend' => $dateEnd];
    $filterLabel   = 'Filtered: ' . date('M d, Y', strtotime($dateStart)) . ' – ' . date('M d, Y', strtotime($dateEnd));
} elseif ($filterMode === 'single' && $validDate($dateSingle)) {
    $hasFilter     = true;
    $filterWhereSr = " AND DATE(sr.created_at) = :fsingle ";
    $filterWhereQt = " AND DATE(q.created_at) = :fsingle ";
    $filterWhereCt = " AND DATE(created_at) = :fsingle ";
    $filterParams  = [':fsingle' => $dateSingle];
    $filterLabel   = 'Filtered: ' . date('M d, Y', strtotime($dateSingle));
}

$totalClients = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();

$newRequestsStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests sr WHERE status = 'New' $filterWhereSr");
$newRequestsStmt->execute($filterParams);
$newRequests = (int) $newRequestsStmt->fetchColumn();

$inProgressStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests sr WHERE status = 'In Progress' $filterWhereSr");
$inProgressStmt->execute($filterParams);
$inProgressRequests = (int) $inProgressStmt->fetchColumn();

$completedStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests sr WHERE status = 'Completed' $filterWhereSr");
$completedStmt->execute($filterParams);
$completedRequests = (int) $completedStmt->fetchColumn();

$cancelledStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests sr WHERE status = 'Cancelled' $filterWhereSr");
$cancelledStmt->execute($filterParams);
$cancelledRequests = (int) $cancelledStmt->fetchColumn();

$draftQuotationsStmt = $pdo->prepare("SELECT COUNT(*) FROM quotations q WHERE status = 'Draft' $filterWhereQt");
$draftQuotationsStmt->execute($filterParams);
$draftQuotations = (int) $draftQuotationsStmt->fetchColumn();

$approvedQuotationsStmt = $pdo->prepare("SELECT COUNT(*) FROM quotations q WHERE status = 'Approved' $filterWhereQt");
$approvedQuotationsStmt->execute($filterParams);
$approvedQuotations = (int) $approvedQuotationsStmt->fetchColumn();

$rejectedQuotationsStmt = $pdo->prepare("SELECT COUNT(*) FROM quotations q WHERE status = 'Rejected' $filterWhereQt");
$rejectedQuotationsStmt->execute($filterParams);
$rejectedQuotations = (int) $rejectedQuotationsStmt->fetchColumn();

$draftContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE status = 'Draft' $filterWhereCt");
$draftContractsStmt->execute($filterParams);
$draftContracts = (int) $draftContractsStmt->fetchColumn();

$approvedContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE status = 'Approved' $filterWhereCt");
$approvedContractsStmt->execute($filterParams);
$approvedContracts = (int) $approvedContractsStmt->fetchColumn();

$rejectedContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE status = 'Rejected' $filterWhereCt");
$rejectedContractsStmt->execute($filterParams);
$rejectedContracts = (int) $rejectedContractsStmt->fetchColumn();

$awaitingAssignment = (int) $pdo->query(
    "SELECT COUNT(*) FROM service_requests sr
     INNER JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'"
)->fetchColumn();

$activeStaffCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Staff' AND status = 'Active'")->fetchColumn();

$recentRequestsStmt = $pdo->prepare(
    "SELECT sr.request_id, sr.request_title, sr.status, sr.created_at, c.company_name
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE 1=1 $filterWhereSr
     ORDER BY sr.created_at DESC
     LIMIT 5"
);
$recentRequestsStmt->execute($filterParams);
$recentRequests = $recentRequestsStmt->fetchAll();

$recentQuotationsStmt = $pdo->prepare(
    "SELECT q.quotation_number, q.status, q.total_amount, q.created_at, c.company_name
     FROM quotations q
     INNER JOIN clients c ON c.client_id = q.client_id
     WHERE 1=1 $filterWhereQt
     ORDER BY q.created_at DESC
     LIMIT 5"
);
$recentQuotationsStmt->execute($filterParams);
$recentQuotations = $recentQuotationsStmt->fetchAll();

$staffWorkloadStmt = $pdo->query(
    "SELECT u.user_id, u.firstname, u.lastname, u.status,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'In Progress') AS workload
     FROM users u
     WHERE u.role = 'Staff'
     ORDER BY workload DESC
     LIMIT 5"
);
$staffWorkload = $staffWorkloadStmt->fetchAll();

function requestStatusClass(string $status): string
{
    return match ($status) {
        'New' => 'status-new',
        'In Progress' => 'status-progress',
        'Completed' => 'status-approved',
        'Cancelled' => 'status-rejected',
        default => 'status-new',
    };
}

function quotationStatusClass(string $status): string
{
    return match ($status) {
        'Draft' => 'status-new',
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        default => 'status-new',
    };
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
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

.card-header {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card) !important;
  border-radius: 12px 12px 0 0 !important;
  padding: 1rem 1.15rem;
}
.card-header h2 { color: var(--ink); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; margin-bottom: 0; }
.card-header.d-flex { flex-wrap: wrap; row-gap: .5rem; }

.metric-card {
  border-radius: 12px;
  border: 1px solid var(--line);
  background-color: var(--card);
  padding: 1.1rem 1.2rem;
  height: 100%;
  overflow: hidden;
}
.metric-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  flex-shrink: 0;
}
.metric-label { font-size: .72rem; color: var(--ink-soft); font-weight: 700; text-transform: uppercase; letter-spacing: .03em; word-break: break-word; }
.metric-value { font-size: 1.5rem; font-weight: 700; font-family: 'Lexend', sans-serif; color: var(--navy-deep); }

.chart-legend-item {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .78rem;
  color: var(--ink-soft);
  white-space: nowrap;
}
.chart-legend-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}
.chart-legend-row {
  display: flex;
  flex-wrap: wrap;
  gap: .9rem 1.4rem;
  justify-content: center;
}

.approved-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1.6rem 0;
  gap: .4rem;
}
.approved-stat .approved-value {
  font-family: 'Lexend', sans-serif;
  font-size: 2.4rem;
  font-weight: 700;
  color: var(--success-text);
  line-height: 1;
}
.approved-stat .approved-label {
  font-size: .78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--ink-soft);
}
.approved-icon {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  background-color: var(--success-soft);
  color: var(--success-text);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.15rem;
  margin-bottom: .2rem;
}
.approval-split {
  display: flex;
  align-items: center;
  justify-content: space-around;
  gap: 1rem;
  padding: .6rem 0;
}
.approval-split-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: .35rem;
  flex: 1;
}
.approval-split-item .approved-value {
  font-family: 'Lexend', sans-serif;
  font-size: 2rem;
  font-weight: 700;
  color: var(--success-text);
  line-height: 1;
}
.approval-split-item .approved-label {
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--ink-soft);
}
.approval-divider {
  width: 1px;
  align-self: stretch;
  background-color: var(--line);
}

body { letter-spacing: -0.005em; }

.card {
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04) !important;
  transition: box-shadow .15s ease;
}
.card:hover { box-shadow: 0 4px 14px rgba(15, 23, 42, .06) !important; }

.metric-card {
  box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}

.dashboard-title {
  font-size: 1.15rem !important;
}
.dashboard-topbar { padding-top: .75rem; padding-bottom: .75rem; }

.status-pill {
  font-size: .68rem;
  font-weight: 700;
  padding: .28rem .6rem;
  border-radius: 999px;
  white-space: nowrap;
  border: 1px solid transparent;
}
.status-new { background-color: var(--navy-soft); color: var(--slate); border-color: var(--line); }
.status-progress { background-color: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-border); }
.status-approved { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .68rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  background-color: var(--navy-soft) !important;
}
.table td { border-bottom: 1px solid var(--line); vertical-align: middle; font-size: .82rem; }
.table-hover tbody tr:hover { background-color: var(--navy-soft); }

.quick-link {
  display: flex;
  align-items: center;
  gap: .75rem;
  border-radius: 10px;
  border: 1px solid var(--line);
  padding: .85rem 1rem;
  text-decoration: none;
  color: var(--ink);
  transition: border-color .15s ease, background-color .15s ease;
}
.quick-link:hover { border-color: var(--indigo); background-color: var(--indigo-soft); color: var(--ink); }
.quick-link-icon {
  width: 38px; height: 38px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: .95rem;
}
.quick-link-title { font-weight: 600; font-size: .85rem; }
.quick-link-sub { font-size: .72rem; color: var(--ink-soft); }

.workload-bar-track {
  background-color: var(--navy-soft);
  border-radius: 999px;
  height: 6px;
  overflow: hidden;
  width: 100%;
}
.workload-bar-fill { background-color: var(--indigo); height: 100%; border-radius: 999px; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.btn-view-all {
  background-color: var(--navy-soft);
  color: var(--navy);
  font-weight: 600;
  border-radius: 7px;
  font-size: .78rem;
}
.btn-view-all:hover { background-color: #E4E8F0; color: var(--navy); }

.btn-ghost {
  background-color: var(--navy-soft);
  color: var(--navy);
  border: 1px solid var(--line);
  border-radius: 7px;
  font-weight: 600;
  font-size: .8rem;
}
.btn-ghost:hover { background-color: #E4E8F0; color: var(--navy); }

.btn-print-text {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  background-color: transparent;
  color: var(--navy);
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: .85rem;
  padding: .4rem .7rem;
  transition: background-color .15s ease;
}
.btn-print-text:hover,
.btn-print-text:focus,
.btn-print-text:active,
.btn-print-text:focus-visible {
  background-color: var(--navy-soft);
  color: var(--navy);
  outline: none !important;
  box-shadow: none !important;
}
.btn-print-text:active { background-color: #E4E8F0; }
.btn-print-text i { font-size: .95rem; }

button, .btn, .btn:focus, .btn:active, .btn:focus-visible, .btn:active:focus,
input, input:focus, input:focus-visible,
a:focus, a:focus-visible {
  outline: none !important;
  box-shadow: none !important;
}
button::-moz-focus-inner {
  border: 0 !important;
}

.filter-bar {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: .85rem 1.15rem;
  display: flex;
  align-items: center;
  gap: .9rem;
  flex-wrap: wrap;
  row-gap: .7rem;
}
.filter-bar .filter-mode-toggle {
  display: flex;
  border: 1px solid var(--line);
  border-radius: 8px;
  overflow: hidden;
  flex-shrink: 0;
}
.filter-bar .filter-mode-toggle label {
  margin: 0;
  padding: .45rem .8rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--ink-soft);
  cursor: pointer;
  background-color: var(--navy-soft);
}
.filter-bar .filter-mode-toggle input { display: none; }
.filter-bar .filter-mode-toggle input:checked + label {
  background-color: var(--indigo);
  color: #fff;
}
.filter-bar .filter-fields {
  display: flex;
  align-items: center;
  gap: .6rem;
  flex-wrap: wrap;
  row-gap: .5rem;
}
.filter-bar label.field-label {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--ink-soft);
  margin: 0 0 0 .2rem;
}
.filter-bar input[type="date"] {
  border: 1px solid var(--line);
  border-radius: 7px;
  padding: .4rem .6rem;
  font-size: .82rem;
  color: var(--ink);
}
.filter-bar .filter-actions {
  display: flex;
  gap: .5rem;
  margin-left: auto;
  flex-shrink: 0;
}
.filter-active-badge {
  font-size: .72rem;
  font-weight: 600;
  color: var(--indigo-text);
  background-color: var(--indigo-soft);
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: .3rem .7rem;
  display: flex;
  align-items: center;
  gap: .35rem;
  white-space: nowrap;
}

.print-header { display: none; }

@media print {
  .no-print { display: none !important; }
  .print-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    text-align: center;
    margin-bottom: 24px;
    padding-bottom: 14px;
    border-bottom: 2px solid var(--navy);
  }
  .print-header img {
    height: 48px;
    width: 48px;
    object-fit: contain;
  }
  .print-header h1 {
    font-family: 'Lexend', sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 2px;
  }
  .print-header p {
    font-size: 11px;
    color: var(--ink-soft);
    margin: 0;
  }
  body { background-color: #fff !important; }
  .dashboard-layout { display: block !important; }
  .dashboard-layout > *:not(.dashboard-main) { display: none !important; }
  .dashboard-main { width: 100% !important; margin: 0 !important; }
  main.dashboard-content { padding: 0 24px 24px !important; }
  .card, .metric-card { border: 1px solid #D8DEE3 !important; box-shadow: none !important; break-inside: avoid; }
}
</style>
</head>

<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/supervisor/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4 no-print">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Dashboard</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Welcome back, <?= htmlspecialchars($_SESSION['manager_fullname'] ?? 'Supervisor') ?>.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <button type="button" class="btn-print-text" onclick="window.print()">
          <i class="fa-solid fa-print"></i> <span>Print</span>
        </button>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="print-header">
        <img src="../assets/img/system_img/logo.png" alt="Company Logo">
        <div>
          <h1>KMP Business Consultancy Services</h1>
          <p>Dashboard Summary &middot; Generated <?= date('M d, Y g:i A') ?></p>
        </div>
      </div>

      <form method="GET" class="filter-bar mb-3 no-print" id="filterForm">
        <div class="filter-mode-toggle">
          <input type="radio" name="filter_mode" id="mode-range" value="range" <?= $filterMode !== 'single' ? 'checked' : '' ?>>
          <label for="mode-range"><i class="fa-regular fa-calendar-days me-1"></i>Date Range</label>
          <input type="radio" name="filter_mode" id="mode-single" value="single" <?= $filterMode === 'single' ? 'checked' : '' ?>>
          <label for="mode-single"><i class="fa-solid fa-calendar-day me-1"></i>Specific Date</label>
        </div>

        <div class="filter-fields" id="range-fields" style="<?= $filterMode === 'single' ? 'display:none;' : 'display:flex;' ?>">
          <label class="field-label" for="date_start">From</label>
          <input type="date" name="date_start" id="date_start" value="<?= htmlspecialchars($dateStart) ?>" max="<?= date('Y-m-d') ?>">
          <label class="field-label" for="date_end">To</label>
          <input type="date" name="date_end" id="date_end" value="<?= htmlspecialchars($dateEnd) ?>" max="<?= date('Y-m-d') ?>">
        </div>

        <div class="filter-fields" id="single-fields" style="<?= $filterMode === 'single' ? 'display:flex;' : 'display:none;' ?>">
          <label class="field-label" for="date_single">Date</label>
          <input type="date" name="date_single" id="date_single" value="<?= htmlspecialchars($dateSingle) ?>" max="<?= date('Y-m-d') ?>">
        </div>

        <?php if ($hasFilter): ?>
          <span class="filter-active-badge"><i class="fa-solid fa-filter"></i><?= htmlspecialchars($filterLabel) ?></span>
        <?php endif; ?>

        <div class="filter-actions">
          <button type="submit" class="btn btn-ghost btn-sm px-3"><i class="fa-solid fa-magnifying-glass me-1"></i>Apply</button>
          <?php if ($hasFilter): ?>
            <a href="dashboard.php" class="btn btn-ghost btn-sm px-3"><i class="fa-solid fa-xmark me-1"></i>Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--indigo-soft);">
              <i class="fa-solid fa-building" style="color:var(--indigo-text);"></i>
            </span>
            <div>
              <div class="metric-label">Total Clients</div>
              <div class="metric-value"><?= $totalClients ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--warn-soft);">
              <i class="fa-solid fa-clipboard-list" style="color:var(--warn-text);"></i>
            </span>
            <div>
              <div class="metric-label">Pending Requests</div>
              <div class="metric-value"><?= $newRequests ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--success-soft);">
              <i class="fa-solid fa-user-check" style="color:var(--success-text);"></i>
            </span>
            <div>
              <div class="metric-label">Active Staff</div>
              <div class="metric-value"><?= $activeStaffCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--danger-soft);">
              <i class="fa-solid fa-user-clock" style="color:var(--danger-text);"></i>
            </span>
            <div>
              <div class="metric-label">Awaiting Assignment</div>
              <div class="metric-value"><?= $awaitingAssignment ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-8">
          <section class="card h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Service Requests</h2>
              <p class="small mb-0">Status distribution.</p>
            </div>
            <div class="card-body">
              <div class="row align-items-center g-3">
                <div class="col-md-6">
                  <div style="height:150px;">
                    <canvas id="requestsChart"></canvas>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="chart-legend-row" style="flex-direction:column; align-items:flex-start; gap:.7rem;">
                    <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#1E293B;"></span>New</span>
                    <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#B7791F;"></span>In Progress</span>
                    <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#157A5F;"></span>Completed</span>
                    <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#B4432F;"></span>Cancelled</span>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
        <div class="col-lg-4">
          <section class="card h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Approvals Overview</h2>
              <p class="small mb-0">Approved totals across records.</p>
            </div>
            <div class="card-body">
              <div class="approval-split">
                <div class="approval-split-item">
                  <span class="approved-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                  <div class="approved-value"><?= $approvedQuotations ?></div>
                  <div class="approved-label">Quotations Approved</div>
                </div>
                <div class="approval-divider"></div>
                <div class="approval-split-item">
                  <span class="approved-icon"><i class="fa-solid fa-file-signature"></i></span>
                  <div class="approved-value"><?= $approvedContracts ?></div>
                  <div class="approved-label">Contracts Approved</div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>

      <div class="row g-3">

        <div class="col-lg-8">

          <section class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <div>
                <h2 class="h6 fw-bold mb-0">Recent Service Requests</h2>
                <p class="small mb-0">Latest requests recorded across all clients.</p>
              </div>
              <a href="client_management.php?tab=requests" class="btn btn-sm btn-view-all no-print">View All</a>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Request</th>
                    <th scope="col" class="d-none d-md-table-cell">Client</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentRequests)): ?>
                    <tr><td colspan="3"><div class="empty-state text-center py-4"><i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i><p class="small mb-0">No service requests yet.</p></div></td></tr>
                  <?php else: ?>
                    <?php foreach ($recentRequests as $r): ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($r['request_title']) ?></td>
                        <td class="d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($r['company_name']) ?></td>
                        <td><span class="status-pill <?= requestStatusClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>

          <section class="card">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Recent Quotations</h2>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Quotation #</th>
                    <th scope="col" class="d-none d-md-table-cell">Client</th>
                    <th scope="col">Total</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentQuotations)): ?>
                    <tr><td colspan="4"><div class="empty-state text-center py-4"><i class="fa-regular fa-file-lines fs-4 d-block mb-2"></i><p class="small mb-0">No quotations yet.</p></div></td></tr>
                  <?php else: ?>
                    <?php foreach ($recentQuotations as $q): ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($q['quotation_number']) ?></td>
                        <td class="d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($q['company_name']) ?></td>
                        <td style="color:var(--indigo-text);">&#8369;<?= number_format((float) $q['total_amount'], 2) ?></td>
                        <td><span class="status-pill <?= quotationStatusClass($q['status']) ?>"><?= htmlspecialchars($q['status']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>

        </div>

        <div class="col-lg-4">

          <section class="card">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Staff Workload</h2>
              <p class="small mb-0"><?= $activeStaffCount ?> active staff members</p>
            </div>
            <div class="card-body d-flex flex-column gap-3">
              <?php if (empty($staffWorkload)): ?>
                <div class="empty-state text-center py-3"><p class="small mb-0">No staff accounts found.</p></div>
              <?php else: ?>
                <?php
                  $maxWorkload = max(array_column($staffWorkload, 'workload')) ?: 1;
                  foreach ($staffWorkload as $s):
                    $pct = $maxWorkload > 0 ? round(($s['workload'] / $maxWorkload) * 100) : 0;
                ?>
                  <div>
                    <div class="d-flex justify-content-between mb-1">
                      <span class="small fw-semibold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></span>
                      <span class="small" style="color:var(--ink-soft);"><?= $s['workload'] ?> task<?= $s['workload'] == 1 ? '' : 's' ?></span>
                    </div>
                    <div class="workload-bar-track">
                      <div class="workload-bar-fill" style="width:<?= $pct ?>%;"></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>

        </div>

      </div>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#667085';

function buildDoughnut(canvasId, values, colors) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  new Chart(canvas, {
    type: 'doughnut',
    data: {
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderWidth: 0,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: { legend: { display: false } },
    }
  });
}

buildDoughnut('requestsChart', [<?= $newRequests ?>, <?= $inProgressRequests ?>, <?= $completedRequests ?>, <?= $cancelledRequests ?>], ['#1E293B', '#B7791F', '#157A5F', '#B4432F']);
</script>
<script>
const rangeFields = document.getElementById('range-fields');
const singleFields = document.getElementById('single-fields');
const modeRange = document.getElementById('mode-range');
const modeSingle = document.getElementById('mode-single');

function syncFilterFields() {
  if (modeSingle && modeSingle.checked) {
    rangeFields.style.setProperty('display', 'none');
    singleFields.style.setProperty('display', 'flex');
  } else {
    rangeFields.style.setProperty('display', 'flex');
    singleFields.style.setProperty('display', 'none');
  }
}

if (modeRange && modeSingle && rangeFields && singleFields) {
  modeRange.addEventListener('change', syncFilterFields);
  modeSingle.addEventListener('change', syncFilterFields);
  modeRange.addEventListener('click', syncFilterFields);
  modeSingle.addEventListener('click', syncFilterFields);
  syncFilterFields();
}
</script>

</body>
</html>