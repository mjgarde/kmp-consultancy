<?php

session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$filterMode = $_GET['filter_mode'] ?? '';
$dateStart  = $_GET['date_start'] ?? '';
$dateEnd    = $_GET['date_end'] ?? '';
$dateSingle = $_GET['date_single'] ?? '';

$validDate = function (string $d): bool {
    if ($d === '') return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
};

$hasFilter     = false;
$filterWhereSr = '';
$filterWhereCt = '';
$filterWhereQt = '';
$filterParams  = [];
$filterLabel   = '';

if ($filterMode === 'range' && $validDate($dateStart) && $validDate($dateEnd)) {
    if ($dateStart > $dateEnd) {
        [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
    }
    $hasFilter     = true;
    $filterWhereSr = " AND DATE(sr.created_at) BETWEEN :fstart AND :fend ";
    $filterWhereCt = " AND DATE(ct.created_at) BETWEEN :fstart AND :fend ";
    $filterWhereQt = " AND DATE(qt.created_at) BETWEEN :fstart AND :fend ";
    $filterParams  = [':fstart' => $dateStart, ':fend' => $dateEnd];
    $filterLabel   = date('M d, Y', strtotime($dateStart)) . ' – ' . date('M d, Y', strtotime($dateEnd));
} elseif ($filterMode === 'single' && $validDate($dateSingle)) {
    $hasFilter     = true;
    $filterWhereSr = " AND DATE(sr.created_at) = :fsingle ";
    $filterWhereCt = " AND DATE(ct.created_at) = :fsingle ";
    $filterWhereQt = " AND DATE(qt.created_at) = :fsingle ";
    $filterParams  = [':fsingle' => $dateSingle];
    $filterLabel   = date('M d, Y', strtotime($dateSingle));
}

$clientReportSql = "SELECT c.client_id, c.company_name,
            COUNT(sr.request_id) AS total_requests,
            SUM(CASE WHEN sr.status = 'New' THEN 1 ELSE 0 END) AS new_requests,
            SUM(CASE WHEN sr.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN sr.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN sr.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
            AVG(CASE WHEN sr.status != 'New' THEN TIMESTAMPDIFF(HOUR, sr.created_at, sr.updated_at) END) AS avg_response_hours
     FROM clients c
     LEFT JOIN service_requests sr ON sr.client_id = c.client_id $filterWhereSr
     GROUP BY c.client_id, c.company_name
     ORDER BY total_requests DESC, c.company_name ASC";
$clientReportStmt = $pdo->prepare($clientReportSql);
$clientReportStmt->execute($filterParams);
$clientReport = $clientReportStmt->fetchAll();

$totRequests = 0;
$totNew = 0;
$totProgress = 0;
$totCompleted = 0;
$totCancelled = 0;
foreach ($clientReport as $row) {
    $totRequests  += (int) $row['total_requests'];
    $totNew       += (int) $row['new_requests'];
    $totProgress  += (int) $row['in_progress'];
    $totCompleted += (int) $row['completed'];
    $totCancelled += (int) $row['cancelled'];
}

$overallAvgSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, sr.created_at, sr.updated_at)) FROM service_requests sr WHERE sr.status != 'New' $filterWhereSr";
$overallAvgStmt = $pdo->prepare($overallAvgSql);
$overallAvgStmt->execute($filterParams);
$overallAvgResponse = $overallAvgStmt->fetchColumn();
$overallAvgResponse = $overallAvgResponse !== null ? round((float) $overallAvgResponse, 1) : null;

$staffPerfSql = "SELECT u.user_id, u.firstname, u.lastname, u.status,
            COUNT(sr.request_id) AS total_assigned,
            SUM(CASE WHEN sr.status = 'In Progress' THEN 1 ELSE 0 END) AS active_tasks,
            SUM(CASE WHEN sr.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks
     FROM users u
     LEFT JOIN service_requests sr ON sr.assigned_to = u.user_id $filterWhereSr
     WHERE u.role = 'staff'
     GROUP BY u.user_id, u.firstname, u.lastname, u.status
     ORDER BY total_assigned DESC, u.firstname ASC";
$staffPerfStmt = $pdo->prepare($staffPerfSql);
$staffPerfStmt->execute($filterParams);
$staffPerf = $staffPerfStmt->fetchAll();

$skillDemandSql = "SELECT sr.required_skill, COUNT(*) AS demand
     FROM service_requests sr
     WHERE sr.required_skill IS NOT NULL AND sr.required_skill != '' $filterWhereSr
     GROUP BY sr.required_skill
     ORDER BY demand DESC
     LIMIT 6";
$skillDemandStmt = $pdo->prepare($skillDemandSql);
$skillDemandStmt->execute($filterParams);
$skillDemand = $skillDemandStmt->fetchAll();

$contractStatusSql = "SELECT ct.status, COUNT(*) AS cnt, COALESCE(SUM(ct.total_amount), 0) AS amt
     FROM contracts ct WHERE 1=1 $filterWhereCt GROUP BY ct.status";
$contractStatusStmt = $pdo->prepare($contractStatusSql);
$contractStatusStmt->execute($filterParams);
$contractStatusCounts = ['Draft' => 0, 'Approved' => 0, 'Rejected' => 0, 'Revert' => 0];
$approvedValue = 0.0;
foreach ($contractStatusStmt->fetchAll() as $row) {
    if (isset($contractStatusCounts[$row['status']])) {
        $contractStatusCounts[$row['status']] = (int) $row['cnt'];
    }
    if ($row['status'] === 'Approved') {
        $approvedValue = (float) $row['amt'];
    }
}
$contractTotal = array_sum($contractStatusCounts);

$quotationStatusSql = "SELECT qt.status, COUNT(*) AS cnt, COALESCE(SUM(qt.total_amount), 0) AS amt
     FROM quotations qt WHERE 1=1 $filterWhereQt GROUP BY qt.status";
$quotationStatusStmt = $pdo->prepare($quotationStatusSql);
$quotationStatusStmt->execute($filterParams);
$quotationStatus = [
    'Draft'    => ['cnt' => 0, 'amt' => 0.0],
    'Approved' => ['cnt' => 0, 'amt' => 0.0],
    'Rejected' => ['cnt' => 0, 'amt' => 0.0],
    'Revert'   => ['cnt' => 0, 'amt' => 0.0],
];
foreach ($quotationStatusStmt->fetchAll() as $row) {
    if (isset($quotationStatus[$row['status']])) {
        $quotationStatus[$row['status']] = ['cnt' => (int) $row['cnt'], 'amt' => (float) $row['amt']];
    }
}
$quotationTotalCnt = array_sum(array_column($quotationStatus, 'cnt'));
$quotationTotalAmt = array_sum(array_column($quotationStatus, 'amt'));

$upcomingExpirationsStmt = $pdo->query(
    "SELECT ct.contract_number, ct.end_date, c.company_name, DATEDIFF(ct.end_date, CURDATE()) AS days_left
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     WHERE ct.status = 'Approved' AND ct.end_date IS NOT NULL AND ct.end_date >= CURDATE()
     ORDER BY ct.end_date ASC
     LIMIT 5"
);
$upcomingExpirations = $upcomingExpirationsStmt->fetchAll();

$avgApprovalSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, ct.created_at, ct.approved_at)) FROM contracts ct WHERE ct.approved_at IS NOT NULL $filterWhereCt";
$avgApprovalStmt = $pdo->prepare($avgApprovalSql);
$avgApprovalStmt->execute($filterParams);
$avgApprovalHours = $avgApprovalStmt->fetchColumn();
$avgApprovalHours = $avgApprovalHours !== null ? round((float) $avgApprovalHours, 1) : null;

$statusColors = [
    'Draft'    => '#64748B',
    'Approved' => '#157A5F',
    'Rejected' => '#B4432F',
    'Revert'   => '#B7791F',
];
$statusClass = [
    'Draft'    => 'status-draft',
    'Approved' => 'status-approved',
    'Rejected' => 'status-rejected',
    'Revert'   => 'status-progress',
];

$money = function (float $n): string {
    return '₱' . number_format($n, 2);
};

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports</title>
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

.card { border-radius: 12px; border: 1px solid var(--line); box-shadow: none; }

.card-header {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card) !important;
  border-radius: 12px 12px 0 0 !important;
  padding: 1rem 1.15rem;
}

.card-header h2 { color: var(--ink); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; }

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .85rem;
}
@media (min-width: 768px) { .summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (min-width: 1400px) { .summary-grid.cols-6 { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
@media (min-width: 992px) { .summary-grid.cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); } }

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: .9rem 1rem;
  display: flex;
  align-items: center;
  gap: .8rem;
}
.stat-card .stat-label { font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); line-height: 1.2; }
.stat-card .stat-value { font-family: 'Lexend', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--navy-deep); margin-top: .15rem; }
.stat-card .stat-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: .95rem; flex-shrink: 0;
  background-color: var(--navy-soft);
  border: 1px solid var(--line);
  color: var(--indigo-text);
}
.stat-card.tone-success .stat-icon { background-color: var(--success-soft); border-color: var(--success-border); color: var(--success-text); }
.stat-card.tone-warn .stat-icon { background-color: var(--warn-soft); border-color: var(--warn-border); color: var(--warn-text); }
.stat-card.tone-danger .stat-icon { background-color: var(--danger-soft); border-color: var(--danger-border); color: var(--danger-text); }

.status-tabs {
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--line);
  padding: 0 1.15rem;
  background-color: var(--card);
  border-radius: 12px 12px 0 0;
}

.status-tab {
  border: none;
  background: none;
  padding: .8rem .3rem;
  font-size: .82rem;
  font-weight: 600;
  color: var(--ink-soft);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  display: flex;
  align-items: center;
  gap: .4rem;
}
.status-tab:hover { color: var(--navy-deep); }
.status-tab.active { color: var(--indigo-text); border-bottom-color: var(--indigo); }

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .7rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  background-color: var(--navy-soft) !important;
  white-space: nowrap;
}
.table td { border-bottom: 1px solid var(--line); color: var(--ink); vertical-align: middle; font-size: .82rem; }
.table-hover tbody tr:hover { background-color: var(--navy-soft); }
.table tfoot td {
  background-color: var(--navy-soft);
  font-weight: 700;
  border-top: 1px solid var(--line);
  border-bottom: none;
}
.num { text-align: right; font-variant-numeric: tabular-nums; }

.status-pill {
  font-size: .7rem;
  font-weight: 700;
  padding: .32rem .7rem;
  border-radius: 999px;
  white-space: nowrap;
  letter-spacing: .01em;
  border: 1px solid transparent;
  display: inline-block;
}
.status-draft { background-color: var(--navy-soft); color: var(--slate); border-color: var(--line); }
.status-approved { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }
.status-progress { background-color: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-border); }

.bar-track { background-color: var(--navy-soft); border-radius: 999px; height: 8px; overflow: hidden; width: 100%; }
.bar-fill { height: 100%; border-radius: 999px; background-color: var(--indigo); }

.rate-cell { display: flex; align-items: center; gap: .5rem; min-width: 110px; }
.rate-cell .bar-track { flex: 1; }
.rate-cell span { font-size: .75rem; font-weight: 600; color: var(--ink-soft); width: 34px; text-align: right; }

.status-list { display: flex; flex-direction: column; gap: .8rem; }
.status-list-item .label-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: .3rem; font-size: .8rem; }
.status-list-item .label-row .name { display: flex; align-items: center; gap: .45rem; font-weight: 600; }
.status-list-item .dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.status-list-item .count { color: var(--ink-soft); font-weight: 600; }

.btn-ghost {
  background-color: var(--navy-soft);
  color: var(--navy);
  border: 1px solid var(--line);
  border-radius: 7px;
  font-weight: 600;
  font-size: .8rem;
}
.btn-ghost:hover { background-color: #E4E8F0; color: var(--navy); }

.btn-outline-print {
  background-color: #fff;
  color: var(--indigo-text);
  border: 1px solid var(--indigo);
  border-radius: 8px;
  font-weight: 600;
}
.btn-outline-print:hover { background-color: var(--indigo-soft); color: var(--indigo-text); }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.filter-bar {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: .85rem 1.15rem;
  display: flex;
  align-items: center;
  gap: .9rem;
  flex-wrap: wrap;
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
}
.filter-hint {
  font-size: .74rem;
  color: var(--ink-soft);
  margin: .5rem 0 .75rem 0;
}
.note-small { font-size: .72rem; color: var(--ink-soft); }

.print-only { display: none; }

@page {
  size: A4 portrait;
  margin: 14mm 12mm 16mm 12mm;
  @bottom-right {
    content: "Page " counter(page) " of " counter(pages);
    font-family: 'Inter', sans-serif;
    font-size: 9px;
    color: #667085;
  }
}

@media print {
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  html, body { background: #fff !important; font-size: 11px; }
  .no-print { display: none !important; }
  .print-only { display: block; }

  .dashboard-layout { display: block !important; }
  .dashboard-layout > *:not(.dashboard-main) { display: none !important; }
  .offcanvas, .offcanvas-backdrop, .modal-backdrop { display: none !important; }
  .dashboard-main { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
  main.dashboard-content { padding: 0 !important; }

  .print-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-bottom: 12px;
    margin-bottom: 6px;
    border-bottom: 3px solid var(--navy);
  }
  .print-header img { height: 54px; width: 54px; object-fit: contain; }
  .print-header .ph-text { flex: 1; }
  .print-header h1 {
    font-family: 'Lexend', sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--navy);
    margin: 0 0 2px 0;
  }
  .print-header p { font-size: 10px; color: var(--ink-soft); margin: 0; }
  .print-header .ph-badge {
    text-align: right;
    font-size: 9.5px;
    color: var(--ink-soft);
    line-height: 1.5;
  }
  .print-header .ph-badge strong { color: var(--navy); font-size: 12px; font-family: 'Lexend', sans-serif; display: block; }

  .print-meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 10px;
    padding: 7px 10px;
    margin-bottom: 14px;
    background-color: var(--navy-soft);
    border: 1px solid var(--line);
    border-radius: 6px;
  }
  .print-meta span b { color: var(--navy); }

  .summary-grid, .summary-grid.cols-5, .summary-grid.cols-6 {
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 8px;
    margin-bottom: 14px;
  }
  .stat-card { padding: 7px 9px; gap: 8px; border-radius: 6px; break-inside: avoid; }
  .stat-card .stat-icon { width: 26px; height: 26px; font-size: .7rem; border-radius: 6px; }
  .stat-card .stat-label { font-size: 7.5px; }
  .stat-card .stat-value { font-size: 13px; }

  .tab-content > .tab-pane {
    display: block !important;
    opacity: 1 !important;
    margin-bottom: 18px;
  }

  .print-section-title {
    display: flex !important;
    align-items: center;
    gap: 8px;
    font-family: 'Lexend', sans-serif;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--navy);
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 6px 10px;
    margin: 0 0 10px 0;
    background-color: var(--indigo-soft);
    border-left: 4px solid var(--indigo);
    break-after: avoid;
  }

  .row { display: block !important; margin: 0 !important; }
  .row > [class*="col-"] { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin-bottom: 10px; }

  .card { border: 1px solid #CBD2DC !important; border-radius: 6px; box-shadow: none !important; break-inside: avoid; height: auto !important; }
  .card-header { padding: 7px 10px !important; background-color: #fff !important; border-radius: 6px 6px 0 0 !important; }
  .card-header h2 { font-size: 11px !important; }
  .card-header p { font-size: 9px; }
  .card-body { padding: 10px !important; }

  .table-responsive { overflow: visible !important; }
  .table { font-size: 10px; width: 100%; }
  .table thead { display: table-header-group; }
  .table thead th { font-size: 8.5px; padding: 5px 8px; background-color: var(--navy-soft) !important; }
  .table td { font-size: 10px; padding: 5px 8px; }
  .table tr { break-inside: avoid; }
  .table tfoot td { font-size: 10px; padding: 5px 8px; }
  .d-none.d-md-table-cell { display: table-cell !important; }

  .status-pill { font-size: 8.5px; padding: 2px 8px; }
  .bar-track { height: 6px; }

  .signature-block {
    display: flex !important;
    justify-content: space-between;
    gap: 40px;
    margin-top: 34px;
    break-inside: avoid;
  }
  .signature-block .sig {
    flex: 1;
    text-align: center;
    font-size: 10px;
    color: var(--ink-soft);
  }
  .signature-block .sig .line {
    border-top: 1px solid var(--navy);
    margin-top: 34px;
    padding-top: 4px;
    color: var(--navy);
    font-weight: 600;
  }

  .print-footer {
    display: flex !important;
    justify-content: space-between;
    margin-top: 16px;
    padding-top: 6px;
    border-top: 1px solid var(--line);
    font-size: 9px;
    color: var(--ink-soft);
  }
}
</style>
</head>

<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/manager/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4 no-print">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Reports</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Client service, staff performance, and contract insights.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <button type="button" class="btn btn-outline-print btn-sm px-3" onclick="window.print()">
          <i class="fa-solid fa-print me-1"></i> Print
        </button>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="print-header print-only">
        <img src="../assets/img/system_img/logo.png" alt="Company Logo">
        <div class="ph-text">
          <h1>KMP Business Consultancy Services</h1>
          <p>Management Reports Summary</p>
        </div>
        <div class="ph-badge">
          <strong>REPORTS</strong>
          <?= date('F d, Y') ?><br><?= date('g:i A') ?>
        </div>
      </div>

      <div class="print-meta print-only">
        <span><b>Coverage:</b> <?= $hasFilter ? htmlspecialchars($filterLabel) : 'All records' ?></span>
        <span><b>Total Requests:</b> <?= $totRequests ?></span>
        <span><b>Total Contracts:</b> <?= $contractTotal ?></span>
      </div>

      <form method="GET" class="filter-bar no-print" id="filterForm">
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
          <span class="filter-active-badge"><i class="fa-solid fa-filter"></i>Filtered: <?= htmlspecialchars($filterLabel) ?></span>
        <?php endif; ?>

        <div class="filter-actions">
          <button type="submit" class="btn btn-ghost btn-sm px-3"><i class="fa-solid fa-magnifying-glass me-1"></i>Apply</button>
          <?php if ($hasFilter): ?>
            <a href="reports.php" class="btn btn-ghost btn-sm px-3"><i class="fa-solid fa-xmark me-1"></i>Clear</a>
          <?php endif; ?>
        </div>
      </form>
      <p class="filter-hint no-print">
        <i class="fa-regular fa-circle-question me-1"></i>
        <?= $filterMode === 'single' ? 'Showing records for one exact day only.' : 'Showing records between two dates. Switch to "Specific Date" for a single day only.' ?>
      </p>

      <?php if ($filterMode === 'range' && ($dateStart || $dateEnd) && !$hasFilter): ?>
        <div class="alert alert-warning py-2 px-3 mb-3 no-print" style="font-size:.8rem;">
          <i class="fa-solid fa-triangle-exclamation me-1"></i>
          Please select both a "From" and "To" date to apply the range filter.
        </div>
      <?php elseif ($filterMode === 'single' && $dateSingle && !$hasFilter): ?>
        <div class="alert alert-warning py-2 px-3 mb-3 no-print" style="font-size:.8rem;">
          <i class="fa-solid fa-triangle-exclamation me-1"></i>
          That date looks invalid. Please pick a valid date.
        </div>
      <?php endif; ?>

      <div class="summary-grid cols-6 mb-3">
        <div class="stat-card">
          <span class="stat-icon"><i class="fa-solid fa-layer-group"></i></span>
          <div>
            <div class="stat-label">Total Requests</div>
            <div class="stat-value"><?= $totRequests ?></div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon"><i class="fa-solid fa-inbox"></i></span>
          <div>
            <div class="stat-label">New</div>
            <div class="stat-value"><?= $totNew ?></div>
          </div>
        </div>
        <div class="stat-card tone-warn">
          <span class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></span>
          <div>
            <div class="stat-label">In Progress</div>
            <div class="stat-value"><?= $totProgress ?></div>
          </div>
        </div>
        <div class="stat-card tone-success">
          <span class="stat-icon"><i class="fa-solid fa-circle-check"></i></span>
          <div>
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= $totCompleted ?></div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon"><i class="fa-solid fa-clock"></i></span>
          <div>
            <div class="stat-label">Avg. Response</div>
            <div class="stat-value"><?= $overallAvgResponse !== null ? $overallAvgResponse . ' hrs' : '&mdash;' ?></div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></span>
          <div>
            <div class="stat-label">Avg. Contract Approval</div>
            <div class="stat-value"><?= $avgApprovalHours !== null ? $avgApprovalHours . ' hrs' : '&mdash;' ?></div>
          </div>
        </div>
      </div>

      <nav class="status-tabs mb-3 no-print" style="border-radius:12px; border:1px solid var(--line);">
        <ul class="nav gap-2 p-2" id="reportTabs" role="tablist" style="border-bottom:none;">
          <li class="nav-item">
            <button class="status-tab active" data-bs-toggle="tab" data-bs-target="#tab-client" type="button">
              <i class="fa-solid fa-building me-1"></i> Client Service
            </button>
          </li>
          <li class="nav-item">
            <button class="status-tab" data-bs-toggle="tab" data-bs-target="#tab-staff" type="button">
              <i class="fa-solid fa-users me-1"></i> Staff Performance
            </button>
          </li>
          <li class="nav-item">
            <button class="status-tab" data-bs-toggle="tab" data-bs-target="#tab-contracts" type="button">
              <i class="fa-solid fa-file-signature me-1"></i> Contracts
            </button>
          </li>
        </ul>
      </nav>

      <div class="tab-content">

        <div class="tab-pane fade show active" id="tab-client">
          <h2 class="print-section-title print-only">1. Client Service</h2>
          <section class="card">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Service Requests by Client</h2>
              <p class="small mb-0">Volume, status, and response time per client.</p>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Client</th>
                    <th scope="col" class="num">Total</th>
                    <th scope="col" class="num">New</th>
                    <th scope="col" class="num">In Progress</th>
                    <th scope="col" class="num">Completed</th>
                    <th scope="col" class="num">Cancelled</th>
                    <th scope="col" class="num d-none d-md-table-cell">Avg. Response</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clientReport)): ?>
                    <tr><td colspan="7"><div class="empty-state text-center py-4"><i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i><p class="small mb-0">No client data yet.</p></div></td></tr>
                  <?php else: ?>
                    <?php foreach ($clientReport as $c): ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($c['company_name']) ?></td>
                        <td class="num"><?= (int) $c['total_requests'] ?></td>
                        <td class="num"><?= (int) $c['new_requests'] ?></td>
                        <td class="num" style="color:var(--warn-text);"><?= (int) $c['in_progress'] ?></td>
                        <td class="num" style="color:var(--success-text);"><?= (int) $c['completed'] ?></td>
                        <td class="num" style="color:var(--danger-text);"><?= (int) $c['cancelled'] ?></td>
                        <td class="num d-none d-md-table-cell" style="color:var(--ink-soft);">
                          <?= $c['avg_response_hours'] !== null ? round((float) $c['avg_response_hours'], 1) . ' hrs' : '&mdash;' ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
                <?php if (!empty($clientReport)): ?>
                  <tfoot>
                    <tr>
                      <td>Total</td>
                      <td class="num"><?= $totRequests ?></td>
                      <td class="num"><?= $totNew ?></td>
                      <td class="num"><?= $totProgress ?></td>
                      <td class="num"><?= $totCompleted ?></td>
                      <td class="num"><?= $totCancelled ?></td>
                      <td class="num d-none d-md-table-cell"><?= $overallAvgResponse !== null ? $overallAvgResponse . ' hrs' : '&mdash;' ?></td>
                    </tr>
                  </tfoot>
                <?php endif; ?>
              </table>
            </div>
          </section>
        </div>

        <div class="tab-pane fade" id="tab-staff">
          <h2 class="print-section-title print-only">2. Staff Performance</h2>
          <div class="row g-3">
            <div class="col-lg-8">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="h6 fw-bold mb-0">Staff Performance</h2>
                  <p class="small mb-0">Assigned, active, and completed tasks per staff member.</p>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th scope="col">Staff</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Assigned</th>
                        <th scope="col" class="num">Active</th>
                        <th scope="col" class="num">Completed</th>
                        <th scope="col">Completion Rate</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($staffPerf)): ?>
                        <tr><td colspan="6"><div class="empty-state text-center py-4"><p class="small mb-0">No staff accounts found.</p></div></td></tr>
                      <?php else: ?>
                        <?php foreach ($staffPerf as $s): ?>
                          <?php
                            $assigned = (int) $s['total_assigned'];
                            $rate = $assigned > 0 ? round(((int) $s['completed_tasks'] / $assigned) * 100) : 0;
                          ?>
                          <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></td>
                            <td><span class="status-pill <?= $s['status'] === 'Active' ? 'status-approved' : 'status-rejected' ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                            <td class="num"><?= $assigned ?></td>
                            <td class="num" style="color:var(--warn-text);"><?= (int) $s['active_tasks'] ?></td>
                            <td class="num" style="color:var(--success-text);"><?= (int) $s['completed_tasks'] ?></td>
                            <td>
                              <div class="rate-cell">
                                <div class="bar-track"><div class="bar-fill" style="width:<?= $rate ?>%;"></div></div>
                                <span><?= $rate ?>%</span>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </section>
            </div>
            <div class="col-lg-4">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="h6 fw-bold mb-0">Most Requested Skills</h2>
                  <p class="small mb-0">Top 6 skills by request count.</p>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                  <?php if (empty($skillDemand)): ?>
                    <div class="empty-state text-center py-3"><p class="small mb-0">No skill data yet.</p></div>
                  <?php else: ?>
                    <?php $maxDemand = max(array_column($skillDemand, 'demand')) ?: 1; ?>
                    <?php foreach ($skillDemand as $sk): ?>
                      <div>
                        <div class="d-flex justify-content-between mb-1">
                          <span class="small fw-semibold"><?= htmlspecialchars($sk['required_skill']) ?></span>
                          <span class="small" style="color:var(--ink-soft);"><?= (int) $sk['demand'] ?></span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= round(($sk['demand'] / $maxDemand) * 100) ?>%;"></div></div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </section>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="tab-contracts">
          <h2 class="print-section-title print-only">3. Contracts and Quotations</h2>

          <div class="summary-grid cols-5 mb-3">
            <div class="stat-card">
              <span class="stat-icon"><i class="fa-solid fa-file-pen"></i></span>
              <div>
                <div class="stat-label">Draft</div>
                <div class="stat-value"><?= $contractStatusCounts['Draft'] ?></div>
              </div>
            </div>
            <div class="stat-card tone-success">
              <span class="stat-icon"><i class="fa-solid fa-file-circle-check"></i></span>
              <div>
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?= $contractStatusCounts['Approved'] ?></div>
              </div>
            </div>
            <div class="stat-card tone-danger">
              <span class="stat-icon"><i class="fa-solid fa-file-circle-xmark"></i></span>
              <div>
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?= $contractStatusCounts['Rejected'] ?></div>
              </div>
            </div>
            <div class="stat-card tone-warn">
              <span class="stat-icon"><i class="fa-solid fa-rotate-left"></i></span>
              <div>
                <div class="stat-label">Revert</div>
                <div class="stat-value"><?= $contractStatusCounts['Revert'] ?></div>
              </div>
            </div>
            <div class="stat-card">
              <span class="stat-icon"><i class="fa-solid fa-peso-sign"></i></span>
              <div>
                <div class="stat-label">Approved Value</div>
                <div class="stat-value" style="font-size:1rem;"><?= $money($approvedValue) ?></div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-lg-5">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="h6 fw-bold mb-0">Contracts by Status</h2>
                  <p class="small mb-0"><?= $contractTotal ?> total contract<?= $contractTotal === 1 ? '' : 's' ?>.</p>
                </div>
                <div class="card-body">
                  <?php if ($contractTotal > 0): ?>
                    <div class="no-print mb-3" style="max-width:240px; margin-left:auto; margin-right:auto;">
                      <canvas id="contractStatusChart"></canvas>
                    </div>
                  <?php endif; ?>
                  <div class="status-list">
                    <?php foreach ($contractStatusCounts as $label => $cnt): ?>
                      <div class="status-list-item">
                        <div class="label-row">
                          <span class="name"><span class="dot" style="background-color:<?= $statusColors[$label] ?>;"></span><?= $label ?></span>
                          <span class="count"><?= $cnt ?></span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width:<?= $contractTotal > 0 ? round(($cnt / $contractTotal) * 100) : 0 ?>%; background-color:<?= $statusColors[$label] ?>;"></div></div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </section>
            </div>
            <div class="col-lg-7">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="h6 fw-bold mb-0">Upcoming Contract Expirations</h2>
                  <p class="small mb-0">Approved contracts nearing their end date. Not affected by the date filter.</p>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th scope="col">Contract #</th>
                        <th scope="col">Client</th>
                        <th scope="col">End Date</th>
                        <th scope="col">Days Left</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($upcomingExpirations)): ?>
                        <tr><td colspan="4"><div class="empty-state text-center py-4"><p class="small mb-0">No upcoming expirations.</p></div></td></tr>
                      <?php else: ?>
                        <?php foreach ($upcomingExpirations as $ue): ?>
                          <?php $daysLeft = (int) $ue['days_left']; ?>
                          <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($ue['contract_number']) ?></td>
                            <td style="color:var(--ink-soft);"><?= htmlspecialchars($ue['company_name']) ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($ue['end_date']))) ?></td>
                            <td>
                              <span class="status-pill <?= $daysLeft <= 7 ? 'status-rejected' : ($daysLeft <= 30 ? 'status-progress' : 'status-approved') ?>">
                                <?= $daysLeft === 0 ? 'Today' : $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') ?>
                              </span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </section>
            </div>
          </div>

          <section class="card">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Quotations by Status</h2>
              <p class="small mb-0">Count and total amount per quotation status.</p>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Status</th>
                    <th scope="col" class="num">Quotations</th>
                    <th scope="col" class="num">Total Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($quotationStatus as $label => $q): ?>
                    <tr>
                      <td><span class="status-pill <?= $statusClass[$label] ?>"><?= $label ?></span></td>
                      <td class="num"><?= $q['cnt'] ?></td>
                      <td class="num"><?= $money($q['amt']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td>Total</td>
                    <td class="num"><?= $quotationTotalCnt ?></td>
                    <td class="num"><?= $money($quotationTotalAmt) ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </section>
        </div>

      </div>

      <div class="signature-block print-only" style="display:none;">
        <div class="sig"><div class="line">Prepared by</div></div>
        <div class="sig"><div class="line">Reviewed by</div></div>
        <div class="sig"><div class="line">Approved by</div></div>
      </div>

      <div class="print-footer print-only" style="display:none;">
        <span>KMP Business Consultancy Services &bull; Reports</span>
        <span>Coverage: <?= $hasFilter ? htmlspecialchars($filterLabel) : 'All records' ?></span>
      </div>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
<script>
const contractStatusCanvas = document.getElementById('contractStatusChart');
if (contractStatusCanvas && typeof Chart !== 'undefined') {
  new Chart(contractStatusCanvas, {
    type: 'doughnut',
    data: {
      labels: ['Draft', 'Approved', 'Rejected', 'Revert'],
      datasets: [{
        data: [
          <?= $contractStatusCounts['Draft'] ?>,
          <?= $contractStatusCounts['Approved'] ?>,
          <?= $contractStatusCounts['Rejected'] ?>,
          <?= $contractStatusCounts['Revert'] ?>
        ],
        backgroundColor: ['#64748B', '#157A5F', '#B4432F', '#B7791F'],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true,
      cutout: '62%',
      plugins: { legend: { display: false } }
    }
  });
}
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