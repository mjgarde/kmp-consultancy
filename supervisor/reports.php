<?php

session_name('SUPERVISOR_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$dateStart = $_GET['date_start'] ?? '';
$dateEnd   = $_GET['date_end'] ?? '';

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
        $filterWhereCt = " AND DATE(ct.created_at) = :fsingle ";
        $filterWhereQt = " AND DATE(qt.created_at) = :fsingle ";
        $filterParams  = [':fsingle' => $dateStart];
        $filterLabel   = date('M d, Y', strtotime($dateStart));
    } else {
        $filterWhereSr = " AND DATE(sr.created_at) BETWEEN :fstart AND :fend ";
        $filterWhereCt = " AND DATE(ct.created_at) BETWEEN :fstart AND :fend ";
        $filterWhereQt = " AND DATE(qt.created_at) BETWEEN :fstart AND :fend ";
        $filterParams  = [':fstart' => $dateStart, ':fend' => $dateEnd];
        $filterLabel   = date('M d, Y', strtotime($dateStart)) . ' – ' . date('M d, Y', strtotime($dateEnd));
    }
} else {
    $dateStart = $dateEnd = '';
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
$clientsServed = count(array_filter($clientReport, fn($c) => (int) $c['total_requests'] > 0));

$overallAvgSql = "SELECT AVG(TIMESTAMPDIFF(HOUR, sr.created_at, sr.updated_at)) FROM service_requests sr WHERE sr.status != 'New' $filterWhereSr";
$overallAvgStmt = $pdo->prepare($overallAvgSql);
$overallAvgStmt->execute($filterParams);
$overallAvgResponse = $overallAvgStmt->fetchColumn();
$overallAvgResponse = $overallAvgResponse !== null ? round((float) $overallAvgResponse, 1) : null;

$weeklySql = "SELECT YEARWEEK(sr.created_at, 1) AS yw,
            MIN(DATE(sr.created_at)) AS week_start,
            COUNT(*) AS total,
            SUM(CASE WHEN sr.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
            AVG(CASE WHEN sr.status != 'New' THEN TIMESTAMPDIFF(HOUR, sr.created_at, sr.updated_at) END) AS avg_resp
     FROM service_requests sr
     WHERE 1=1 $filterWhereSr
     GROUP BY YEARWEEK(sr.created_at, 1)
     ORDER BY yw ASC";
$weeklyStmt = $pdo->prepare($weeklySql);
$weeklyStmt->execute($filterParams);
$weekly = array_slice($weeklyStmt->fetchAll(), -12);
$weeklyLabels    = array_map(fn($w) => date('M d', strtotime($w['week_start'])), $weekly);
$weeklyTotals    = array_map(fn($w) => (int) $w['total'], $weekly);
$weeklyCompleted = array_map(fn($w) => (int) $w['completed'], $weekly);
$weeklyResponse  = array_map(fn($w) => $w['avg_resp'] !== null ? round((float) $w['avg_resp'], 1) : null, $weekly);

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

$topClients = array_slice(array_values(array_filter($clientReport, fn($c) => (int) $c['total_requests'] > 0)), 0, 3);
$topSkills  = array_slice($skillDemand, 0, 3);
$staffByDone = array_values(array_filter($staffPerf, fn($s) => (int) $s['total_assigned'] > 0));
usort($staffByDone, fn($a, $b) => (int) $b['completed_tasks'] <=> (int) $a['completed_tasks']);
$topStaff = array_slice($staffByDone, 0, 3);

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

$moneyShort = function (float $n): string {
    if ($n >= 1000000) return '₱' . round($n / 1000000, 2) . 'M';
    if ($n >= 1000) return '₱' . round($n / 1000, 1) . 'K';
    return '₱' . number_format($n, 0);
};

$chartData = [
    'statusLabels' => ['New', 'In Progress', 'Completed', 'Cancelled'],
    'statusValues' => [$totNew, $totProgress, $totCompleted, $totCancelled],
    'weekLabels'   => array_values($weeklyLabels),
    'weekTotals'   => array_values($weeklyTotals),
    'weekDone'     => array_values($weeklyCompleted),
    'staffLabels'  => array_map(fn($x) => $x['firstname'] . ' ' . $x['lastname'], $staffPerf),
    'staffAssigned' => array_map(fn($x) => (int) $x['total_assigned'], $staffPerf),
    'staffDone'    => array_map(fn($x) => (int) $x['completed_tasks'], $staffPerf),
    'quoteLabels'  => array_keys($quotationStatus),
    'quoteCounts'  => array_values(array_map(fn($q) => $q['cnt'], $quotationStatus)),
    'contractValues' => array_values($contractStatusCounts),
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/vendor/flatpickr/flatpickr.min.css">
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

  --blue: #1F4FBF;
  --blue-soft: #E8EEFC;
  --sky: #0EA5E9;
  --sky-soft: #E0F2FE;
  --teal: #0E9AA7;
  --teal-soft: #DDF3F5;

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
  --canvas: #F6F8FC;
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

.card { border-radius: 14px; border: 1px solid var(--line); box-shadow: none; background-color: var(--card); }

.card-header {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card) !important;
  border-radius: 14px 14px 0 0 !important;
  padding: 1rem 1.15rem;
}

.card-header h2 { color: var(--ink); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; }

.kpi-strip {
  display: grid !important;
  grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
  gap: .85rem !important;
}
@media (min-width: 1200px) {
  .kpi-strip {
    grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
  }
}

.kpi {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 96px;
  padding: 1.1rem .5rem;
  text-align: center;
  border-radius: 14px;
  color: #fff;
  background-color: #2E5272;
}
.kpi.k-amber { background-color: #A06B1F; }
.kpi.k-sky   { background-color: #3F6C8F; }
.kpi.k-green { background-color: #3E7D5A; }
.kpi.k-red   { background-color: #B5523F; }
.kpi.k-navy  { background-color: #7B4F7D; }

.kpi-value {
  font-family: 'Lexend', sans-serif;
  font-size: clamp(1.15rem, 4.5vw, 1.75rem);
  font-weight: 700;
  color: #fff;
  line-height: 1.15;
}
.kpi-label {
  font-size: clamp(.7rem, 2vw, .85rem);
  color: rgba(255, 255, 255, .92);
  margin-top: .25rem;
}

.chart-card { height: 100%; }
.chart-card .card-body { padding: 1rem 1.15rem 1.15rem; }
.chart-title { font-family: 'Lexend', sans-serif; font-size: .9rem; font-weight: 600; color: var(--navy-deep); text-align: center; margin: 0 0 .75rem; }
.chart-box { position: relative; height: 220px; }
.chart-empty { height: 220px; display: flex; align-items: center; justify-content: center; color: var(--ink-soft); font-size: .8rem; }

.rank-card { padding: 1rem 1.15rem 1.1rem; }
.rank-list { display: flex; flex-direction: column; gap: .7rem; }
.rank-row { display: grid; grid-template-columns: 38% 1fr auto; align-items: center; gap: .6rem; font-size: .78rem; }
.rank-row .rank-name { color: var(--slate); text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rank-row .rank-track { height: 14px; background-color: var(--navy-soft); border-radius: 3px; overflow: hidden; }
.rank-row .rank-fill { height: 100%; border-radius: 3px; }
.rank-row .rank-val { font-weight: 700; color: var(--navy-deep); min-width: 28px; }
.rank-empty { color: var(--ink-soft); font-size: .8rem; text-align: center; padding: 1rem 0; }

.section-heading { font-family: 'Lexend', sans-serif; font-size: 1rem; font-weight: 600; color: var(--navy-deep); margin: 1.5rem 0 .75rem; }

.summary-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .85rem;
}
@media (min-width: 768px) { .summary-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (min-width: 992px) { .summary-grid.cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); } }

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
  background-color: var(--card);
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
  color: #000 !important;
}
.status-draft { background-color: var(--navy-soft); border-color: var(--line); }
.status-approved { background-color: var(--success-soft); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); border-color: var(--danger-border); }
.status-progress { background-color: var(--warn-soft); border-color: var(--warn-border); }

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

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

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
.date-filter:hover { border-color: #C5CBD8; }
.date-filter:focus-within { border-color: var(--indigo); box-shadow: 0 0 0 3px rgba(59, 78, 138, .15); }
.date-filter > i.fa-calendar { color: var(--indigo); font-size: .95rem; }
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
.date-filter.is-active { border-color: var(--indigo); background-color: var(--indigo-soft); }
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
.date-clear:hover { background-color: #fff; color: var(--danger); }

.btn-print {
  display: inline-flex;
  align-items: center;
  gap: .6rem;
  height: 44px;
  padding: 0 1.4rem;
  background-color: #1F4FBF;
  color: #fff;
  border: 1px solid #1F4FBF;
  border-radius: 10px;
  font-family: 'Inter', sans-serif;
  font-size: .92rem;
  font-weight: 600;
  letter-spacing: .01em;
  white-space: nowrap;
  transition: background-color .15s, transform .05s;
}
.btn-print i { font-size: 1rem; color: #fff; }
.btn-print:hover { background-color: #1A44A3; border-color: #1A44A3; color: #fff; }
.btn-print:active { transform: translateY(1px); }

.flatpickr-calendar {
  border-radius: 14px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, .15);
  border: 1px solid var(--line);
  font-family: 'Inter', sans-serif;
}
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange { background: var(--indigo); border-color: var(--indigo); }
.flatpickr-day.inRange {
  background: var(--indigo-soft);
  border-color: var(--indigo-soft);
  box-shadow: -5px 0 0 var(--indigo-soft), 5px 0 0 var(--indigo-soft);
}
.flatpickr-day.today { border-color: var(--indigo); }

@media (max-width: 767px) {
  .date-filter { min-width: 0; width: 100%; }
  .btn-print span { display: none; }
  .btn-print { padding: 0 1rem; }
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
  body { background-color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .dashboard-layout { display: block !important; }
  .dashboard-layout > *:not(.dashboard-main) { display: none !important; }
  .dashboard-main { width: 100% !important; margin: 0 !important; }
  main.dashboard-content { padding: 0 24px 24px !important; }
  .card, .stat-card { border: 1px solid #D8DEE3 !important; box-shadow: none !important; break-inside: avoid; }
  .kpi { break-inside: avoid; }
  .tab-pane { display: block !important; opacity: 1 !important; }

  .status-pill {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    border-radius: 0 !important;
    color: #000 !important;
    font-weight: 700 !important;
    font-size: .82rem !important;
  }
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Reports</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Client requests, staff workload, and contract status overview.</p>
        </div>
      </div>

      <div class="dashboard-topbar-actions d-flex align-items-center gap-2 gap-md-3">
        <form method="GET" id="filterForm" class="d-flex align-items-center gap-2 m-0">
          <div class="date-filter <?= $hasFilter ? 'is-active' : '' ?>">
            <i class="fa-regular fa-calendar"></i>
            <input type="text" id="dateRange" placeholder="Select date or range" autocomplete="off">
            <?php if ($hasFilter): ?>
              <a href="reports.php" class="date-clear" title="Clear filter" aria-label="Clear filter"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
          </div>
          <input type="hidden" name="date_start" id="date_start" value="<?= htmlspecialchars($dateStart) ?>">
          <input type="hidden" name="date_end" id="date_end" value="<?= htmlspecialchars($dateEnd) ?>">
        </form>

        <button type="button" class="btn-print" onclick="window.print()">
          <i class="fa-solid fa-print"></i><span>Print Report</span>
        </button>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="print-header">
        <img src="../assets/img/system_img/logo.png" alt="Company Logo">
        <div>
          <h1>KMP Business Consultancy Services</h1>
          <p>Reports generated <?= date('M d, Y g:i A') ?></p>
        </div>
      </div>

      <section class="kpi-strip mb-3">
        <div class="kpi">
          <div class="kpi-value"><?= number_format($totRequests) ?></div>
          <div class="kpi-label">Total Requests</div>
        </div>
        <div class="kpi k-amber">
          <div class="kpi-value"><?= number_format($totNew) ?></div>
          <div class="kpi-label">New / Pending</div>
        </div>
        <div class="kpi k-sky">
          <div class="kpi-value"><?= number_format($totProgress) ?></div>
          <div class="kpi-label">In Progress</div>
        </div>
        <div class="kpi k-green">
          <div class="kpi-value"><?= number_format($totCompleted) ?></div>
          <div class="kpi-label">Completed</div>
        </div>
        <div class="kpi k-red">
          <div class="kpi-value"><?= number_format($totCancelled) ?></div>
          <div class="kpi-label">Cancelled</div>
        </div>
        <div class="kpi k-navy">
          <div class="kpi-value"><?= number_format($clientsServed) ?></div>
          <div class="kpi-label">Clients Served</div>
        </div>
      </section>

      <div class="row g-3 mb-3">

        <div class="col-xl-8">
          <div class="row g-3">

            <div class="col-md-6">
              <section class="card chart-card">
                <div class="card-body">
                  <h2 class="chart-title">Requests by Status</h2>
                  <?php if ($totRequests > 0): ?>
                    <div class="chart-box"><canvas id="statusChart"></canvas></div>
                  <?php else: ?>
                    <div class="chart-empty">No request data yet.</div>
                  <?php endif; ?>
                </div>
              </section>
            </div>

            <div class="col-md-6">
              <section class="card chart-card">
                <div class="card-body">
                  <h2 class="chart-title">Requests by Week</h2>
                  <?php if (!empty($weekly)): ?>
                    <div class="chart-box"><canvas id="weeklyChart"></canvas></div>
                  <?php else: ?>
                    <div class="chart-empty">No weekly data yet.</div>
                  <?php endif; ?>
                </div>
              </section>
            </div>

            <div class="col-md-6">
              <section class="card chart-card">
                <div class="card-body">
                  <h2 class="chart-title">Staff Workload</h2>
                  <?php if (array_sum(array_map(fn($x) => (int) $x['total_assigned'], $staffPerf)) > 0): ?>
                    <div class="chart-box"><canvas id="staffChart"></canvas></div>
                  <?php else: ?>
                    <div class="chart-empty">No assignments yet.</div>
                  <?php endif; ?>
                </div>
              </section>
            </div>

            <div class="col-md-6">
              <section class="card chart-card">
                <div class="card-body">
                  <h2 class="chart-title">Quotations and Contracts by Status</h2>
                  <?php if ($quotationTotalCnt + $contractTotal > 0): ?>
                    <div class="chart-box"><canvas id="docsChart"></canvas></div>
                  <?php else: ?>
                    <div class="chart-empty">No quotation or contract data yet.</div>
                  <?php endif; ?>
                </div>
              </section>
            </div>

          </div>
        </div>

        <div class="col-xl-4">
          <div class="d-flex flex-column gap-3 h-100">

            <section class="card rank-card flex-fill">
              <h2 class="chart-title">Top 3 Clients by Requests</h2>
              <?php if (empty($topClients)): ?>
                <div class="rank-empty">No data yet.</div>
              <?php else: ?>
                <?php $maxC = max(array_map(fn($c) => (int) $c['total_requests'], $topClients)) ?: 1; ?>
                <div class="rank-list">
                  <?php foreach ($topClients as $c): ?>
                    <div class="rank-row">
                      <span class="rank-name" title="<?= htmlspecialchars($c['company_name']) ?>"><?= htmlspecialchars($c['company_name']) ?></span>
                      <div class="rank-track"><div class="rank-fill" style="width:<?= round(((int) $c['total_requests'] / $maxC) * 100) ?>%; background-color:#2E5272;"></div></div>
                      <span class="rank-val"><?= (int) $c['total_requests'] ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="card rank-card flex-fill">
              <h2 class="chart-title">Top 3 Skills by Demand</h2>
              <?php if (empty($topSkills)): ?>
                <div class="rank-empty">No data yet.</div>
              <?php else: ?>
                <?php $maxS = max(array_map(fn($s) => (int) $s['demand'], $topSkills)) ?: 1; ?>
                <div class="rank-list">
                  <?php foreach ($topSkills as $sk): ?>
                    <div class="rank-row">
                      <span class="rank-name" title="<?= htmlspecialchars($sk['required_skill']) ?>"><?= htmlspecialchars($sk['required_skill']) ?></span>
                      <div class="rank-track"><div class="rank-fill" style="width:<?= round(((int) $sk['demand'] / $maxS) * 100) ?>%; background-color:#3F6C8F;"></div></div>
                      <span class="rank-val"><?= (int) $sk['demand'] ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <section class="card rank-card flex-fill">
              <h2 class="chart-title">Top 3 Staff by Completed Tasks</h2>
              <?php if (empty($topStaff)): ?>
                <div class="rank-empty">No assignments yet.</div>
              <?php else: ?>
                <?php $maxT = max(array_map(fn($s) => (int) $s['completed_tasks'], $topStaff)) ?: 1; ?>
                <div class="rank-list">
                  <?php foreach ($topStaff as $s): ?>
                    <?php $sName = $s['firstname'] . ' ' . $s['lastname']; ?>
                    <div class="rank-row">
                      <span class="rank-name" title="<?= htmlspecialchars($sName) ?>"><?= htmlspecialchars($sName) ?></span>
                      <div class="rank-track"><div class="rank-fill" style="width:<?= round(((int) $s['completed_tasks'] / $maxT) * 100) ?>%; background-color:#2F6F6A;"></div></div>
                      <span class="rank-val"><?= (int) $s['completed_tasks'] ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

          </div>
        </div>

      </div>

      <h2 class="section-heading no-print">Detailed Reports</h2>

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
          <section class="card">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Service Requests by Client</h2>
              <p class="small mb-0">Number of requests and their status per client.</p>
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
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clientReport)): ?>
                    <tr><td colspan="6"><div class="empty-state text-center py-4"><i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i><p class="small mb-0">No client data yet.</p></div></td></tr>
                  <?php else: ?>
                    <?php foreach ($clientReport as $c): ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($c['company_name']) ?></td>
                        <td class="num"><?= (int) $c['total_requests'] ?></td>
                        <td class="num"><?= (int) $c['new_requests'] ?></td>
                        <td class="num"><?= (int) $c['in_progress'] ?></td>
                        <td class="num"><?= (int) $c['completed'] ?></td>
                        <td class="num"><?= (int) $c['cancelled'] ?></td>
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
                    </tr>
                  </tfoot>
                <?php endif; ?>
              </table>
            </div>
          </section>
        </div>

        <div class="tab-pane fade" id="tab-staff">
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
                            <td class="num"><?= (int) $s['active_tasks'] ?></td>
                            <td class="num"><?= (int) $s['completed_tasks'] ?></td>
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
          <div class="summary-grid cols-4 mb-3">
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
                  <p class="small mb-0">Approved contracts nearing their end date.</p>
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
              <p class="small mb-0">Number of quotations per status.</p>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Status</th>
                    <th scope="col" class="num">Quotations</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($quotationStatus as $label => $q): ?>
                    <tr>
                      <td><span class="status-pill <?= $statusClass[$label] ?>"><?= $label ?></span></td>
                      <td class="num"><?= $q['cnt'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td>Total</td>
                    <td class="num"><?= $quotationTotalCnt ?></td>
                  </tr>
                </tfoot>
              </table>
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
const D = <?= json_encode($chartData) ?>;

if (typeof Chart !== 'undefined') {
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 11;
  Chart.defaults.color = '#667085';
  Chart.defaults.animation = false;

  const gridColor = '#EEF1F6';

  const statusCanvas = document.getElementById('statusChart');
  if (statusCanvas) {
    new Chart(statusCanvas, {
      type: 'doughnut',
      data: {
        labels: D.statusLabels,
        datasets: [{
          data: D.statusValues,
          backgroundColor: ['#A06B1F', '#3F6C8F', '#3E7D5A', '#B5523F'],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: { legend: { position: 'right', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } }
      }
    });
  }

  const weeklyCanvas = document.getElementById('weeklyChart');
  if (weeklyCanvas) {
    new Chart(weeklyCanvas, {
      type: 'line',
      data: {
        labels: D.weekLabels,
        datasets: [
          { label: 'Requests', data: D.weekTotals, borderColor: '#3F6C8F', backgroundColor: 'rgba(63, 108, 143, .18)', fill: true, tension: .3, pointRadius: 3, borderWidth: 2 },
          { label: 'Completed', data: D.weekDone, borderColor: '#3E7D5A', backgroundColor: 'rgba(62, 125, 90, .14)', fill: true, tension: .3, pointRadius: 3, borderWidth: 2 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
          x: { grid: { display: false } }
        },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } }
      }
    });
  }

  const staffCanvas = document.getElementById('staffChart');
  if (staffCanvas) {
    new Chart(staffCanvas, {
      type: 'bar',
      data: {
        labels: D.staffLabels,
        datasets: [
          { label: 'Assigned', data: D.staffAssigned, backgroundColor: '#3F6C8F', borderRadius: 4, maxBarThickness: 36 },
          { label: 'Completed', data: D.staffDone, backgroundColor: '#3E7D5A', borderRadius: 4, maxBarThickness: 36 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
          x: { grid: { display: false } }
        },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } }
      }
    });
  }

  const docsCanvas = document.getElementById('docsChart');
  if (docsCanvas) {
    new Chart(docsCanvas, {
      type: 'bar',
      data: {
        labels: D.quoteLabels,
        datasets: [
          { label: 'Quotations', data: D.quoteCounts, backgroundColor: '#3F6C8F', borderRadius: 4, maxBarThickness: 36 },
          { label: 'Contracts', data: D.contractValues, backgroundColor: '#7B4F7D', borderRadius: 4, maxBarThickness: 36 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
          x: { grid: { display: false } }
        },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } }
      }
    });
  }

  const contractStatusCanvas = document.getElementById('contractStatusChart');
  if (contractStatusCanvas) {
    new Chart(contractStatusCanvas, {
      type: 'doughnut',
      data: {
        labels: ['Draft', 'Approved', 'Rejected', 'Revert'],
        datasets: [{
          data: D.contractValues,
          backgroundColor: ['#8B8F92', '#3E7D5A', '#B5523F', '#7B4F7D'],
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
}
</script>
<script>
(function () {
  if (typeof flatpickr === 'undefined') return;

  const form    = document.getElementById('filterForm');
  const startEl = document.getElementById('date_start');
  const endEl   = document.getElementById('date_end');
  const curStart = startEl.value;
  const curEnd   = endEl.value;

  const preset = [];
  if (curStart) preset.push(curStart);
  if (curEnd && curEnd !== curStart) preset.push(curEnd);

  flatpickr('#dateRange', {
    mode: 'range',
    showMonths: 2,
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
      endEl.value   = e;
      form.submit();
    }
  });
})();
</script>

</body>
</html>