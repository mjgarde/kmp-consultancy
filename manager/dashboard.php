<?php

session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$dateStart = $_GET['date_start'] ?? '';
$dateEnd = $_GET['date_end'] ?? '';

$validDate = function (string $d): bool {
    if ($d === '') return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
};

$hasFilter = false;
$filterWhereSr = '';
$filterWhereQt = '';
$filterWhereCt = '';
$filterWhereCtAlias = '';
$filterParams = [];
$filterLabel = '';

if ($validDate($dateStart) && !$validDate($dateEnd)) {
    $dateEnd = $dateStart;
}

if ($validDate($dateStart) && $validDate($dateEnd)) {
    if ($dateStart > $dateEnd) {
        [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
    }
    $hasFilter = true;

    if ($dateStart === $dateEnd) {
        $filterWhereSr = " AND DATE(sr.created_at) = :fsingle ";
        $filterWhereQt = " AND DATE(q.created_at) = :fsingle ";
        $filterWhereCt = " AND DATE(created_at) = :fsingle ";
        $filterWhereCtAlias = " AND DATE(ct.created_at) = :fsingle ";
        $filterParams = [':fsingle' => $dateStart];
        $filterLabel = date('M d, Y', strtotime($dateStart));
    } else {
        $filterWhereSr = " AND DATE(sr.created_at) BETWEEN :fstart AND :fend ";
        $filterWhereQt = " AND DATE(q.created_at) BETWEEN :fstart AND :fend ";
        $filterWhereCt = " AND DATE(created_at) BETWEEN :fstart AND :fend ";
        $filterWhereCtAlias = " AND DATE(ct.created_at) BETWEEN :fstart AND :fend ";
        $filterParams = [':fstart' => $dateStart, ':fend' => $dateEnd];
        $filterLabel = date('M d, Y', strtotime($dateStart)) . ' – ' . date('M d, Y', strtotime($dateEnd));
    }
} else {
    $dateStart = $dateEnd = '';
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

$revertQuotationsStmt = $pdo->prepare("SELECT COUNT(*) FROM quotations q WHERE status = 'Revert' $filterWhereQt");
$revertQuotationsStmt->execute($filterParams);
$revertQuotations = (int) $revertQuotationsStmt->fetchColumn();

$draftContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE status = 'Draft' $filterWhereCt");
$draftContractsStmt->execute($filterParams);
$draftContracts = (int) $draftContractsStmt->fetchColumn();

$approvedContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE status = 'Approved' $filterWhereCt");
$approvedContractsStmt->execute($filterParams);
$approvedContracts = (int) $approvedContractsStmt->fetchColumn();

$revertContractsStmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE status = 'Revert' $filterWhereCt");
$revertContractsStmt->execute($filterParams);
$revertContracts = (int) $revertContractsStmt->fetchColumn();

$pendingApproval = $draftQuotations + $draftContracts;
$revertTotal = $revertQuotations + $revertContracts;

$awaitingAssignment = (int) $pdo->query(
    "SELECT COUNT(*) FROM service_requests sr
     INNER JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'"
)->fetchColumn();

$activeStaffCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff' AND status = 'Active'")->fetchColumn();

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

$pendingContractsStmt = $pdo->prepare(
    "SELECT ct.contract_number, ct.status, ct.total_amount, ct.end_date, c.company_name
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     WHERE ct.status = 'Draft' $filterWhereCtAlias
     ORDER BY ct.created_at DESC
     LIMIT 5"
);
$pendingContractsStmt->execute($filterParams);
$pendingContracts = $pendingContractsStmt->fetchAll();

$staffWorkloadStmt = $pdo->query(
    "SELECT u.user_id, u.firstname, u.lastname, u.status,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'In Progress') AS workload
     FROM users u
     WHERE u.role = 'staff' AND u.status = 'Active'
     ORDER BY workload DESC
     LIMIT 5"
);
$staffWorkload = $staffWorkloadStmt->fetchAll();

$requestSlices = [
    ['label' => 'New',         'value' => $newRequests,        'color' => '#0B67B8'],
    ['label' => 'In Progress', 'value' => $inProgressRequests, 'color' => '#12AEEF'],
    ['label' => 'Completed',   'value' => $completedRequests,  'color' => '#33B9CC'],
    ['label' => 'Cancelled',   'value' => $cancelledRequests,  'color' => '#A9B4C2'],
];
$requestTotal = array_sum(array_column($requestSlices, 'value'));

$donutRadius = 64;
$donutCirc = 2 * M_PI * $donutRadius;
$donutNonZero = count(array_filter(array_column($requestSlices, 'value')));
$donutSegments = [];
$donutAcc = 0.0;
foreach ($requestSlices as $slice) {
    if ($slice['value'] <= 0 || $requestTotal <= 0) continue;
    $segLen = ($slice['value'] / $requestTotal) * $donutCirc;
    $segGap = $donutNonZero > 1 ? 2.0 : 0.0;
    $segDash = max($segLen - $segGap, 0.5);
    $donutSegments[] = [
        'color' => $slice['color'],
        'dash' => round($segDash, 2),
        'rest' => round($donutCirc - $segDash, 2),
        'offset' => round(-$donutAcc, 2),
    ];
    $donutAcc += $segLen;
}

$approvalBars = [
    ['label' => 'Quotations Approved', 'value' => $approvedQuotations, 'color' => '#0B67B8'],
    ['label' => 'Contracts Approved',  'value' => $approvedContracts,  'color' => '#12AEEF'],
    ['label' => 'Pending Approval',    'value' => $pendingApproval,    'color' => '#33B9CC'],
    ['label' => 'Needs Revision',      'value' => $revertTotal,        'color' => '#A9B4C2'],
];
$approvalMax = max(array_column($approvalBars, 'value')) ?: 1;

$workloadMax = $staffWorkload ? (max(array_column($staffWorkload, 'workload')) ?: 1) : 1;

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
        'Revert' => 'status-progress',
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
<link rel="stylesheet" href="../assets/vendor/flatpickr/flatpickr.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --blue: #0B67B8;
  --blue-dark: #084F8F;
  --sky: #12AEEF;
  --teal: #33B9CC;
  --grey: #A9B4C2;

  --blue-soft: #E8F1FA;
  --blue-text: #0B4F8F;
  --surface: #F4F7FA;
  --chip: #EEF2F6;

  --success-soft: #E3F3EC;
  --success-text: #0F5F49;
  --success-border: #BFE3D3;

  --warn-soft: #FBF0DD;
  --warn-text: #8A5A15;
  --warn-border: #EFD8A8;

  --danger-soft: #FAECE8;
  --danger-text: #93382A;
  --danger-border: #EDC7BC;

  --ink: #1F2933;
  --ink-soft: #6B7785;
  --line: #E3E8EF;
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

.dashboard-title {
  color: var(--ink);
  letter-spacing: -0.01em;
  font-size: 1.15rem !important;
}
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar {
  border-bottom: 1px solid var(--line) !important;
  background-color: #fff;
  padding-top: .75rem;
  padding-bottom: .75rem;
}

.kpi-strip {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 1.15rem 0;
}
.kpi-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: .2rem .75rem;
  border-left: 1px solid var(--line);
  min-width: 0;
}
.kpi-item:first-child { border-left: none; }
.kpi-icon {
  position: relative;
  width: 78px;
  height: 46px;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  margin-bottom: .55rem;
}
.kpi-icon::before {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 15px;
  border-radius: 50%;
  background-color: var(--chip);
}
.kpi-icon i {
  position: relative;
  font-size: 1.85rem;
  color: var(--blue);
  line-height: 1;
  margin-top: .15rem;
}
.kpi-value {
  font-family: 'Lexend', sans-serif;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.1;
}
.kpi-label {
  font-size: .78rem;
  color: var(--ink-soft);
  margin-top: .2rem;
}

.panel {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 1rem 1.1rem 1.1rem;
  height: 100%;
}
.panel-title {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: .95rem;
  font-weight: 600;
  color: var(--ink);
  text-align: center;
  margin: 0 0 .15rem;
}
.panel-sub {
  font-size: .74rem;
  color: var(--ink-soft);
  text-align: center;
  margin: 0 0 .9rem;
}
.panel-head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  margin-bottom: .9rem;
}
.panel-head-row .panel-title { text-align: left; margin: 0; }
.panel-head-row .panel-sub { text-align: left; margin: .1rem 0 0; }

.donut-wrap {
  position: relative;
  height: 175px;
  width: 100%;
  max-width: 220px;
  margin: 0 auto;
}
.donut-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  pointer-events: none;
  z-index: 1;
}
.donut-center .donut-total {
  font-family: 'Lexend', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1;
  color: var(--ink);
}
.donut-center .donut-caption {
  font-size: .68rem;
  color: var(--ink-soft);
  margin-top: .2rem;
}
.donut-print { display: none; }
.legend-list {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: .45rem 1.1rem;
  margin-top: .9rem;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .76rem;
  color: var(--ink-soft);
  white-space: nowrap;
}
.legend-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}
.legend-item strong { color: var(--ink); font-weight: 700; }

.chart-box { position: relative; height: 215px; }

.hbar-list { display: flex; flex-direction: column; gap: .95rem; padding-top: .35rem; }
.hbar-row {
  display: grid;
  grid-template-columns: 112px 1fr;
  align-items: center;
  gap: .75rem;
}
.hbar-label {
  font-size: .76rem;
  color: var(--ink);
  text-align: right;
  line-height: 1.2;
}
.hbar-track { display: flex; align-items: center; gap: .55rem; min-width: 0; }
.hbar-fill {
  height: 20px;
  border-radius: 2px;
  min-width: 3px;
}
.hbar-value { font-size: .76rem; font-weight: 700; color: var(--ink); }

.status-pill {
  font-size: .68rem;
  font-weight: 700;
  padding: .28rem .6rem;
  border-radius: 999px;
  white-space: nowrap;
  border: 1px solid transparent;
}
.status-new { background-color: var(--blue-soft); color: var(--blue-text); border-color: #CFE1F3; }
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
  background-color: var(--surface) !important;
}
.table td { border-bottom: 1px solid var(--line); vertical-align: middle; font-size: .82rem; }
.table-hover tbody tr:hover { background-color: var(--surface); }
.table-wrap {
  border: 1px solid var(--line);
  border-radius: 10px;
  overflow: hidden;
}
.table-wrap .table tbody tr:last-child td { border-bottom: none; }
.amount { color: var(--blue-text); font-weight: 600; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C5CED8; }

.btn-view-all {
  background-color: var(--blue-soft);
  color: var(--blue-text);
  font-weight: 600;
  border-radius: 7px;
  font-size: .76rem;
  padding: .3rem .7rem;
}
.btn-view-all:hover { background-color: #D8E8F6; color: var(--blue-text); }

.date-filter {
  position: relative;
  display: flex;
  align-items: center;
  background-color: #fff;
  border: 1px solid var(--line);
  border-radius: 10px;
  height: 44px;
  padding: 0 .75rem;
  gap: .55rem;
  min-width: 270px;
  transition: border-color .15s, box-shadow .15s, background-color .15s;
}
.date-filter:hover { border-color: #C5CED8; }
.date-filter:focus-within { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(11, 103, 184, .15) !important; }
.date-filter > i.fa-calendar { color: var(--blue); font-size: .95rem; }
.date-filter input[type="text"] {
  border: none !important;
  outline: none !important;
  background: transparent !important;
  box-shadow: none !important;
  font-size: .85rem;
  font-weight: 600;
  color: var(--ink);
  width: 100%;
  cursor: pointer;
  padding: 0;
}
.date-filter input::placeholder { color: var(--ink-soft); font-weight: 500; }
.date-filter.is-active { border-color: var(--blue); background-color: var(--blue-soft); }
.date-clear {
  color: var(--ink-soft);
  width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: .75rem;
  text-decoration: none;
  flex-shrink: 0;
}
.date-clear:hover { background-color: #fff; color: #B4432F; }

.btn-print {
  display: inline-flex;
  align-items: center;
  gap: .6rem;
  height: 44px;
  padding: 0 1.4rem;
  background-color: var(--blue);
  color: #fff;
  border: 1px solid var(--blue);
  border-radius: 10px;
  font-family: 'Inter', sans-serif;
  font-size: .92rem;
  font-weight: 600;
  letter-spacing: .01em;
  white-space: nowrap;
  transition: background-color .15s, transform .05s;
}
.btn-print i { font-size: 1rem; color: #fff; }
.btn-print:hover { background-color: var(--blue-dark); border-color: var(--blue-dark); color: #fff; }
.btn-print:active { transform: translateY(1px); }

button, .btn, .btn:focus, .btn:active, .btn:focus-visible, .btn:active:focus,
input, input:focus, input:focus-visible,
a:focus, a:focus-visible {
  outline: none !important;
  box-shadow: none !important;
}
button::-moz-focus-inner {
  border: 0 !important;
}

.flatpickr-calendar {
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, .15);
  border: 1px solid var(--line);
  font-family: 'Inter', sans-serif;
}
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange { background: var(--blue); border-color: var(--blue); }
.flatpickr-day.inRange {
  background: var(--blue-soft);
  border-color: var(--blue-soft);
  box-shadow: -5px 0 0 var(--blue-soft), 5px 0 0 var(--blue-soft);
}
.flatpickr-day.today { border-color: var(--blue); }

.print-header { display: none; }

@media (max-width: 1199.98px) {
  .kpi-strip { grid-template-columns: repeat(3, 1fr); row-gap: 1.1rem; }
  .kpi-item { border-left: 1px solid var(--line); }
  .kpi-item:nth-child(3n+1) { border-left: none; }
}
@media (max-width: 767.98px) {
  .dashboard-topbar-actions { min-width: 0; flex: 1 1 auto; justify-content: flex-end; }
  #filterForm { min-width: 0; flex: 1 1 auto; justify-content: flex-end; }
  .date-filter {
    min-width: 0;
    width: 100%;
    max-width: 190px;
    height: 38px;
    padding: 0 .6rem;
    gap: .4rem;
    border-radius: 8px;
  }
  .date-filter > i.fa-calendar { font-size: .8rem; }
  .date-filter input[type="text"] { font-size: .74rem; text-overflow: ellipsis; }
  .date-clear { width: 18px; height: 18px; font-size: .65rem; }
  .btn-print {
    width: 38px;
    height: 38px;
    padding: 0;
    justify-content: center;
    gap: 0;
    border-radius: 8px;
  }
  .btn-print span { display: none; }
  .btn-print i { font-size: .85rem; }
}
@media (max-width: 575.98px) {
  .date-filter { max-width: 150px; height: 34px; }
  .btn-print { width: 34px; height: 34px; }

  .kpi-strip {
    grid-template-columns: repeat(3, 1fr);
    row-gap: .85rem;
    padding: .8rem 0;
  }
  .kpi-item { padding: .1rem .3rem; }
  .kpi-item:nth-child(n) { border-left: 1px solid var(--line); }
  .kpi-item:nth-child(3n+1) { border-left: none; }
  .kpi-icon { width: 46px; height: 28px; margin-bottom: .35rem; }
  .kpi-icon::before { height: 9px; }
  .kpi-icon i { font-size: 1.05rem; margin-top: .1rem; }
  .kpi-value { font-size: .95rem; }
  .kpi-label { font-size: .62rem; margin-top: .1rem; line-height: 1.2; }

  .hbar-row { grid-template-columns: 92px 1fr; }
}

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
    border-bottom: 2px solid #000000;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
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
    color: #000000 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    margin-bottom: 2px;
  }
  .print-header p {
    font-size: 11px;
    color: #000000 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    margin: 0;
  }
  body { background-color: #fff !important; }
  .dashboard-layout { display: block !important; }
  .dashboard-layout > *:not(.dashboard-main) { display: none !important; }
  .dashboard-main { width: 100% !important; margin: 0 !important; }
  main.dashboard-content { padding: 0 24px 24px !important; }
  .panel, .kpi-strip { break-inside: avoid; }
  .hbar-fill, .legend-dot {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .status-pill,
  .status-pill.status-new,
  .status-pill.status-progress,
  .status-pill.status-approved,
  .status-pill.status-rejected {
    background: none !important;
    background-color: transparent !important;
    border: 0 !important;
    border-color: transparent !important;
    box-shadow: none !important;
    outline: none !important;
    padding: 0 !important;
    border-radius: 0 !important;
    color: #000000 !important;
    font-weight: 600 !important;
    font-size: .82rem !important;
  }

  .donut-wrap { display: none !important; }
  .donut-print {
    display: block !important;
    width: 180px;
    height: 180px;
    margin: 0 auto;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .donut-print svg {
    display: block;
    width: 180px;
    height: 180px;
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Dashboard</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Welcome back, <?= htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['manager_fullname'] ?? 'Manager') ?>.</p>
        </div>
      </div>

      <div class="dashboard-topbar-actions d-flex align-items-center gap-2 gap-md-3">
        <form method="GET" id="filterForm" class="d-flex align-items-center gap-2 m-0">
          <div class="date-filter <?= $hasFilter ? 'is-active' : '' ?>">
            <i class="fa-regular fa-calendar"></i>
            <input type="text" id="dateRange" placeholder="Select date or range" autocomplete="off">
            <?php if ($hasFilter): ?>
              <a href="dashboard.php" class="date-clear" title="Clear filter" aria-label="Clear filter"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
          </div>
          <input type="hidden" name="date_start" id="date_start" value="<?= htmlspecialchars($dateStart) ?>">
          <input type="hidden" name="date_end" id="date_end" value="<?= htmlspecialchars($dateEnd) ?>">
        </form>

        <button type="button" class="btn-print" onclick="window.print()">
          <i class="fa-solid fa-print"></i><span>Print</span>
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

      <div class="kpi-strip mb-3">
        <div class="kpi-item">
          <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
          <div class="kpi-value"><?= $totalClients ?></div>
          <div class="kpi-label">Total Clients</div>
        </div>
        <div class="kpi-item">
          <div class="kpi-icon"><i class="fa-solid fa-clipboard-list"></i></div>
          <div class="kpi-value"><?= $newRequests ?></div>
          <div class="kpi-label">Pending Requests</div>
        </div>
        <div class="kpi-item">
          <div class="kpi-icon"><i class="fa-solid fa-user-check"></i></div>
          <div class="kpi-value"><?= $activeStaffCount ?></div>
          <div class="kpi-label">Active Staff</div>
        </div>
        <div class="kpi-item">
          <div class="kpi-icon"><i class="fa-solid fa-user-clock"></i></div>
          <div class="kpi-value"><?= $awaitingAssignment ?></div>
          <div class="kpi-label">Awaiting Assignment</div>
        </div>
        <div class="kpi-item">
          <div class="kpi-icon"><i class="fa-solid fa-hourglass-half"></i></div>
          <div class="kpi-value"><?= $pendingApproval ?></div>
          <div class="kpi-label">Pending Approval</div>
        </div>
        <div class="kpi-item">
          <div class="kpi-icon"><i class="fa-solid fa-rotate-left"></i></div>
          <div class="kpi-value"><?= $revertTotal ?></div>
          <div class="kpi-label">Needs Revision</div>
        </div>
      </div>

      <div class="row g-3 mb-3">

        <div class="col-lg-4">
          <section class="panel">
            <h2 class="panel-title">Service Requests</h2>
            <p class="panel-sub">Status distribution</p>

            <div class="donut-wrap">
              <canvas id="requestsChart"></canvas>
              <div class="donut-center">
                <span class="donut-total"><?= $requestTotal ?></span>
                <span class="donut-caption">Total</span>
              </div>
            </div>

            <div class="donut-print" aria-hidden="true">
              <svg viewBox="0 0 180 180" xmlns="http://www.w3.org/2000/svg">
                <?php if (empty($donutSegments)): ?>
                  <circle cx="90" cy="90" r="<?= $donutRadius ?>" fill="none" stroke="#E3E8EF" stroke-width="26"/>
                <?php else: ?>
                  <?php foreach ($donutSegments as $seg): ?>
                    <circle cx="90" cy="90" r="<?= $donutRadius ?>" fill="none" stroke="<?= $seg['color'] ?>" stroke-width="26" stroke-dasharray="<?= $seg['dash'] ?> <?= $seg['rest'] ?>" stroke-dashoffset="<?= $seg['offset'] ?>" transform="rotate(-90 90 90)"/>
                  <?php endforeach; ?>
                <?php endif; ?>
                <text x="90" y="91" text-anchor="middle" font-family="Lexend, Inter, Arial, sans-serif" font-size="26" font-weight="700" fill="#1F2933"><?= $requestTotal ?></text>
                <text x="90" y="107" text-anchor="middle" font-family="Inter, Arial, sans-serif" font-size="10" fill="#6B7785">Total</text>
              </svg>
            </div>

            <div class="legend-list">
              <?php foreach ($requestSlices as $slice): ?>
                <?php $slicePct = $requestTotal > 0 ? round(($slice['value'] / $requestTotal) * 100) : 0; ?>
                <span class="legend-item">
                  <span class="legend-dot" style="background-color:<?= $slice['color'] ?>;"></span>
                  <?= htmlspecialchars($slice['label']) ?> <strong><?= $slice['value'] ?></strong> (<?= $slicePct ?>%)
                </span>
              <?php endforeach; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="panel">
            <h2 class="panel-title">Quotations &amp; Contracts</h2>
            <p class="panel-sub">Approved, pending and revert</p>
            <div class="chart-box">
              <canvas id="approvalsChart"></canvas>
            </div>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="panel">
            <h2 class="panel-title">Staff Workload</h2>
            <p class="panel-sub"><?= $activeStaffCount ?> active staff &middot; tasks in progress</p>
            <?php if (empty($staffWorkload)): ?>
              <div class="empty-state text-center py-4"><p class="small mb-0">No staff accounts found.</p></div>
            <?php else: ?>
              <div class="hbar-list">
                <?php foreach ($staffWorkload as $s): ?>
                  <?php $barPct = round(($s['workload'] / $workloadMax) * 100); ?>
                  <div class="hbar-row">
                    <div class="hbar-label"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></div>
                    <div class="hbar-track">
                      <div class="hbar-fill" style="width:<?= max($barPct * 0.72, 1) ?>%; background-color:var(--blue);"></div>
                      <span class="hbar-value"><?= $s['workload'] ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        </div>

      </div>

      <div class="row g-3 mb-3">

        <div class="col-lg-6">
          <section class="panel">
            <div class="panel-head-row">
              <div>
                <h2 class="panel-title">Recent Service Requests</h2>
                <p class="panel-sub">Latest requests across all clients</p>
              </div>
              <a href="client_management.php?tab=requests" class="btn btn-sm btn-view-all no-print">View All</a>
            </div>
            <div class="table-wrap">
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
            </div>
          </section>
        </div>

        <div class="col-lg-6">
          <section class="panel">
            <div class="panel-head-row">
              <div>
                <h2 class="panel-title">Recent Quotations</h2>
                <p class="panel-sub">Latest quotations issued</p>
              </div>
            </div>
            <div class="table-wrap">
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
                          <td class="amount">&#8369;<?= number_format((float) $q['total_amount'], 2) ?></td>
                          <td><span class="status-pill <?= quotationStatusClass($q['status']) ?>"><?= htmlspecialchars($q['status']) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>

      </div>

      <div class="row g-3">

        <div class="col-lg-6">
          <section class="panel">
            <div class="panel-head-row">
              <div>
                <h2 class="panel-title">Contracts Awaiting Approval</h2>
                <p class="panel-sub">Draft contracts that need a decision</p>
              </div>
            </div>
            <div class="table-wrap">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead>
                    <tr>
                      <th scope="col">Contract #</th>
                      <th scope="col" class="d-none d-md-table-cell">Client</th>
                      <th scope="col">Total</th>
                      <th scope="col">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($pendingContracts)): ?>
                      <tr><td colspan="4"><div class="empty-state text-center py-4"><i class="fa-regular fa-circle-check fs-4 d-block mb-2"></i><p class="small mb-0">No contracts awaiting approval.</p></div></td></tr>
                    <?php else: ?>
                      <?php foreach ($pendingContracts as $ct): ?>
                        <tr>
                          <td class="fw-semibold"><?= htmlspecialchars($ct['contract_number']) ?></td>
                          <td class="d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($ct['company_name']) ?></td>
                          <td class="amount">&#8369;<?= number_format((float) $ct['total_amount'], 2) ?></td>
                          <td><span class="status-pill <?= quotationStatusClass($ct['status']) ?>"><?= htmlspecialchars($ct['status']) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </div>

        <div class="col-lg-6">
          <section class="panel">
            <h2 class="panel-title">Approvals Overview</h2>
            <p class="panel-sub">Quotations and contracts at a glance</p>
            <div class="hbar-list">
              <?php foreach ($approvalBars as $bar): ?>
                <?php $barPct = round(($bar['value'] / $approvalMax) * 100); ?>
                <div class="hbar-row">
                  <div class="hbar-label"><?= htmlspecialchars($bar['label']) ?></div>
                  <div class="hbar-track">
                    <div class="hbar-fill" style="width:<?= max($barPct * 0.72, 1) ?>%; background-color:<?= $bar['color'] ?>;"></div>
                    <span class="hbar-value"><?= $bar['value'] ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        </div>

      </div>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
<script src="../assets/vendor/flatpickr/flatpickr.min.js"></script>
<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#6B7785';

const requestValues = <?= json_encode(array_column($requestSlices, 'value')) ?>;
const requestColors = <?= json_encode(array_column($requestSlices, 'color')) ?>;
const requestLabels = <?= json_encode(array_column($requestSlices, 'label')) ?>;
const requestHasData = requestValues.some(function (v) { return v > 0; });

const requestsCanvas = document.getElementById('requestsChart');
if (requestsCanvas) {
  new Chart(requestsCanvas, {
    type: 'doughnut',
    data: {
      labels: requestLabels,
      datasets: [{
        data: requestHasData ? requestValues : [1],
        backgroundColor: requestHasData ? requestColors : ['#E3E8EF'],
        borderColor: '#FFFFFF',
        borderWidth: 3,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '64%',
      layout: { padding: 0 },
      plugins: {
        legend: { display: false },
        tooltip: { enabled: requestHasData }
      }
    }
  });
}

const approvalsCanvas = document.getElementById('approvalsChart');
if (approvalsCanvas) {
  new Chart(approvalsCanvas, {
    type: 'bar',
    data: {
      labels: ['Approved', 'Pending', 'Revert'],
      datasets: [
        {
          label: 'Quotations',
          data: [<?= $approvedQuotations ?>, <?= $draftQuotations ?>, <?= $revertQuotations ?>],
          backgroundColor: '#0B67B8',
          borderRadius: 3,
          barPercentage: 0.7,
          categoryPercentage: 0.7
        },
        {
          label: 'Contracts',
          data: [<?= $approvedContracts ?>, <?= $draftContracts ?>, <?= $revertContracts ?>],
          backgroundColor: '#33B9CC',
          borderRadius: 3,
          barPercentage: 0.7,
          categoryPercentage: 0.7
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: {
            usePointStyle: true,
            pointStyle: 'rect',
            boxWidth: 10,
            boxHeight: 10,
            padding: 16,
            font: { weight: '600' }
          }
        }
      },
      scales: {
        x: { grid: { display: false }, border: { color: '#E3E8EF' } },
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: '#EEF2F6' },
          border: { display: false }
        }
      }
    }
  });
}
</script>
<script>
(function () {
  if (typeof flatpickr === 'undefined') return;

  const form = document.getElementById('filterForm');
  const startEl = document.getElementById('date_start');
  const endEl = document.getElementById('date_end');
  const curStart = startEl.value;
  const curEnd = endEl.value;

  const preset = [];
  if (curStart) preset.push(curStart);
  if (curEnd && curEnd !== curStart) preset.push(curEnd);

  flatpickr('#dateRange', {
    mode: 'range',
    showMonths: window.innerWidth < 768 ? 1 : 2,
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'M j, Y',
    rangeSeparator: '  –  ',
    maxDate: 'today',
    defaultDate: preset,
    disableMobile: true,
    onClose: function (selected, str, inst) {
      if (!selected.length) return;
      const s = inst.formatDate(selected[0], 'Y-m-d');
      const e = inst.formatDate(selected[selected.length - 1], 'Y-m-d');
      if (s === curStart && e === (curEnd || curStart)) return;
      startEl.value = s;
      endEl.value = e;
      form.submit();
    }
  });
})();
</script>
<script>
window.addEventListener('beforeprint', function () {
  document.querySelectorAll('canvas').forEach(function (canvas) {
    var chart = Chart.getChart(canvas);
    if (chart) chart.resize();
  });
});
</script>

</body>
</html>