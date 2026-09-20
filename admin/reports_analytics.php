<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$preset = $_GET['preset'] ?? '6m';
$today = new DateTime('today');

$presetMonths = match ($preset) {
    '3m' => 3,
    '12m' => 12,
    'ytd' => null,
    'custom' => null,
    default => 6,
};

if ($preset === 'ytd') {
    $defaultFrom = new DateTime($today->format('Y') . '-01-01');
    $defaultTo = clone $today;
} elseif ($presetMonths !== null) {
    $defaultFrom = (clone $today)->modify('first day of this month')->modify('-' . ($presetMonths - 1) . ' months');
    $defaultTo = clone $today;
} else {
    $defaultFrom = (clone $today)->modify('first day of this month')->modify('-5 months');
    $defaultTo = clone $today;
}

$fromInput = $_GET['from'] ?? $defaultFrom->format('Y-m-d');
$toInput = $_GET['to'] ?? $defaultTo->format('Y-m-d');

try {
    $fromDate = new DateTime($fromInput);
} catch (Exception $e) {
    $fromDate = $defaultFrom;
}
try {
    $toDate = new DateTime($toInput);
} catch (Exception $e) {
    $toDate = $defaultTo;
}
if ($fromDate > $toDate) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
}

$rangeFrom = $fromDate->format('Y-m-d 00:00:00');
$rangeTo = $toDate->format('Y-m-d 23:59:59');
$rangeLabel = $fromDate->format('M d, Y') . ' – ' . $toDate->format('M d, Y');

function formatDisplayDate(?string $date): ?string
{
    return $date ? date('M d, Y', strtotime($date)) : null;
}

function statusClass(string $status): string
{
    return match ($status) {
        'New', 'Draft' => 'status-new',
        'In Progress' => 'status-progress',
        'Completed', 'Approved', 'Active' => 'status-approved',
        'Cancelled', 'Rejected', 'Inactive' => 'status-rejected',
        default => 'status-new',
    };
}

$revenueStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(total_amount) AS total
     FROM contracts
     WHERE status = 'Approved' AND created_at BETWEEN :from AND :to
     GROUP BY ym
     ORDER BY ym ASC"
);
$revenueStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$revenueRows = $revenueStmt->fetchAll();

$quotationTrendStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(total_amount) AS total, COUNT(*) AS cnt
     FROM quotations
     WHERE created_at BETWEEN :from AND :to
     GROUP BY ym
     ORDER BY ym ASC"
);
$quotationTrendStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$quotationRows = $quotationTrendStmt->fetchAll();

$months = [];
$cursor = clone $fromDate;
$cursor->modify('first day of this month');
$endCursor = clone $toDate;
$endCursor->modify('first day of this month');
while ($cursor <= $endCursor) {
    $months[] = $cursor->format('Y-m');
    $cursor->modify('+1 month');
}
if (empty($months)) {
    $months[] = $fromDate->format('Y-m');
}

$revenueByMonth = array_fill_keys($months, 0.0);
foreach ($revenueRows as $row) {
    if (isset($revenueByMonth[$row['ym']])) {
        $revenueByMonth[$row['ym']] = (float) $row['total'];
    }
}

$quotationValueByMonth = array_fill_keys($months, 0.0);
$quotationCountByMonth = array_fill_keys($months, 0);
foreach ($quotationRows as $row) {
    if (isset($quotationValueByMonth[$row['ym']])) {
        $quotationValueByMonth[$row['ym']] = (float) $row['total'];
        $quotationCountByMonth[$row['ym']] = (int) $row['cnt'];
    }
}

$monthLabels = array_map(fn($ym) => (new DateTime($ym . '-01'))->format('M Y'), $months);

$requestStatusStmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS cnt FROM service_requests WHERE created_at BETWEEN :from AND :to GROUP BY status'
);
$requestStatusStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$requestStatusCounts = ['New' => 0, 'In Progress' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach ($requestStatusStmt->fetchAll() as $row) {
    if (isset($requestStatusCounts[$row['status']])) {
        $requestStatusCounts[$row['status']] = (int) $row['cnt'];
    }
}

$requestsByIndustryStmt = $pdo->prepare(
    "SELECT COALESCE(NULLIF(c.industry, ''), 'Unspecified') AS industry, COUNT(sr.request_id) AS cnt
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.created_at BETWEEN :from AND :to
     GROUP BY industry
     ORDER BY cnt DESC
     LIMIT 8"
);
$requestsByIndustryStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$requestsByIndustry = $requestsByIndustryStmt->fetchAll();

$quotationStatusStmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS cnt FROM quotations WHERE created_at BETWEEN :from AND :to GROUP BY status'
);
$quotationStatusStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$quotationStatusCounts = ['Draft' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach ($quotationStatusStmt->fetchAll() as $row) {
    if (isset($quotationStatusCounts[$row['status']])) {
        $quotationStatusCounts[$row['status']] = (int) $row['cnt'];
    }
}
$totalQuotationsInRange = array_sum($quotationStatusCounts);
$conversionRate = $totalQuotationsInRange > 0
    ? round(($quotationStatusCounts['Approved'] / $totalQuotationsInRange) * 100, 1)
    : 0.0;

$avgDealSizeStmt = $pdo->prepare(
    "SELECT COALESCE(AVG(total_amount), 0) FROM contracts WHERE status = 'Approved' AND created_at BETWEEN :from AND :to"
);
$avgDealSizeStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$avgDealSize = (float) $avgDealSizeStmt->fetchColumn();

$draftContractStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount), 0), COUNT(*) FROM contracts WHERE status = 'Draft' AND created_at BETWEEN :from AND :to"
);
$draftContractStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$draftContractRow = $draftContractStmt->fetch(PDO::FETCH_NUM);
$draftContractValue = (float) $draftContractRow[0];
$draftContractCount = (int) $draftContractRow[1];

$totalContractValueStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) FROM contracts WHERE status = 'Approved' AND created_at BETWEEN :from AND :to"
);
$totalContractValueStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$totalContractValue = (float) $totalContractValueStmt->fetchColumn();

$totalRequestsStmt = $pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE created_at BETWEEN :from AND :to');
$totalRequestsStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$totalRequests = (int) $totalRequestsStmt->fetchColumn();

$completedRequestsStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM service_requests WHERE status = 'Completed' AND created_at BETWEEN :from AND :to"
);
$completedRequestsStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$completedRequests = (int) $completedRequestsStmt->fetchColumn();

$completionRate = $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100, 1) : 0.0;

$topClientsStmt = $pdo->prepare(
    "SELECT c.client_id, c.company_name, c.industry, c.contact_person, c.email, c.contact_number, c.address,
            COALESCE(SUM(ct.total_amount), 0) AS total_value, COUNT(ct.contract_id) AS contract_count
     FROM clients c
     LEFT JOIN contracts ct ON ct.client_id = c.client_id AND ct.status = 'Approved' AND ct.created_at BETWEEN :from AND :to
     GROUP BY c.client_id
     HAVING total_value > 0
     ORDER BY total_value DESC
     LIMIT 5"
);
$topClientsStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$topClients = $topClientsStmt->fetchAll();

$clientContracts = [];
if (!empty($topClients)) {
    $clientIds = array_column($topClients, 'client_id');
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
    $clientContractsStmt = $pdo->prepare(
        "SELECT ct.client_id, ct.contract_number, ct.total_amount, ct.start_date, ct.end_date, ct.created_at, sr.request_title
         FROM contracts ct
         INNER JOIN service_requests sr ON sr.request_id = ct.request_id
         WHERE ct.status = 'Approved' AND ct.created_at BETWEEN ? AND ? AND ct.client_id IN ($placeholders)
         ORDER BY ct.created_at DESC"
    );
    $clientContractsStmt->execute(array_merge([$rangeFrom, $rangeTo], $clientIds));
    foreach ($clientContractsStmt->fetchAll() as $row) {
        $clientContracts[$row['client_id']][] = [
            'number'  => $row['contract_number'],
            'request' => $row['request_title'],
            'start'   => formatDisplayDate($row['start_date']),
            'end'     => formatDisplayDate($row['end_date']),
            'date'    => formatDisplayDate($row['created_at']),
            'value'   => (float) $row['total_amount'],
        ];
    }
}

$staffPerformanceStmt = $pdo->prepare(
    "SELECT u.user_id, u.firstname, u.lastname, u.status, u.role,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'Completed' AND sr.updated_at BETWEEN :from1 AND :to1) AS completed_count,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'In Progress') AS active_count,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.created_at BETWEEN :from2 AND :to2) AS total_assigned
     FROM users u
     WHERE u.role IN ('Staff', 'Supervisor')
     ORDER BY completed_count DESC, active_count DESC"
);
$staffPerformanceStmt->execute([
    'from1' => $rangeFrom, 'to1' => $rangeTo,
    'from2' => $rangeFrom, 'to2' => $rangeTo,
]);
$staffPerformance = $staffPerformanceStmt->fetchAll();

$skillsByStaff = [];
foreach ($pdo->query('SELECT user_id, skill_name FROM staff_skills ORDER BY skill_id ASC')->fetchAll() as $row) {
    $skillsByStaff[$row['user_id']][] = $row['skill_name'];
}

$requestsByStaff = [];
if (!empty($staffPerformance)) {
    $staffIds = array_column($staffPerformance, 'user_id');
    $staffPlaceholders = implode(',', array_fill(0, count($staffIds), '?'));
    $staffRequestsStmt = $pdo->prepare(
        "SELECT sr.assigned_to, sr.request_title, sr.status, sr.created_at, sr.updated_at, c.company_name
         FROM service_requests sr
         INNER JOIN clients c ON c.client_id = sr.client_id
         WHERE sr.assigned_to IN ($staffPlaceholders)
           AND ((sr.created_at BETWEEN ? AND ?) OR (sr.updated_at BETWEEN ? AND ?))
         ORDER BY sr.updated_at DESC"
    );
    $staffRequestsStmt->execute(array_merge($staffIds, [$rangeFrom, $rangeTo, $rangeFrom, $rangeTo]));
    foreach ($staffRequestsStmt->fetchAll() as $row) {
        $requestsByStaff[$row['assigned_to']][] = [
            'title'   => $row['request_title'],
            'client'  => $row['company_name'],
            'status'  => $row['status'],
            'created' => formatDisplayDate($row['created_at']),
            'updated' => formatDisplayDate($row['updated_at']),
        ];
    }
}

$clientList = array_map(function ($client) use ($clientContracts) {
    return [
        'id'             => (int) $client['client_id'],
        'company'        => $client['company_name'],
        'industry'       => $client['industry'],
        'contact_person' => $client['contact_person'],
        'email'          => $client['email'],
        'contact_number' => $client['contact_number'],
        'address'        => $client['address'],
        'total_value'    => (float) $client['total_value'],
        'contract_count' => (int) $client['contract_count'],
        'contracts'      => $clientContracts[$client['client_id']] ?? [],
    ];
}, $topClients);

$staffList = array_map(function ($staff) use ($skillsByStaff, $requestsByStaff) {
    return [
        'id'        => (int) $staff['user_id'],
        'name'      => $staff['firstname'] . ' ' . $staff['lastname'],
        'status'    => $staff['status'],
        'role'      => $staff['role'],
        'completed' => (int) $staff['completed_count'],
        'active'    => (int) $staff['active_count'],
        'assigned'  => (int) $staff['total_assigned'],
        'skills'    => $skillsByStaff[$staff['user_id']] ?? [],
        'requests'  => $requestsByStaff[$staff['user_id']] ?? [],
    ];
}, $staffPerformance);

$reportData = [
    'period'       => $rangeLabel,
    'metrics'      => [
        'totalContractValue' => $totalContractValue,
        'avgDealSize'        => $avgDealSize,
        'conversionRate'     => $conversionRate,
        'approvedQuotations' => $quotationStatusCounts['Approved'],
        'totalQuotations'    => $totalQuotationsInRange,
        'completionRate'     => $completionRate,
        'completedRequests'  => $completedRequests,
        'totalRequests'      => $totalRequests,
        'draftValue'         => $draftContractValue,
        'draftCount'         => $draftContractCount,
    ],
    'monthly'      => array_map(function ($label, $ym) use ($revenueByMonth, $quotationValueByMonth, $quotationCountByMonth) {
        return [
            'label'      => $label,
            'contracts'  => $revenueByMonth[$ym],
            'quotations' => $quotationValueByMonth[$ym],
            'count'      => $quotationCountByMonth[$ym],
        ];
    }, $monthLabels, $months),
    'requestStatus'   => $requestStatusCounts,
    'quotationStatus' => $quotationStatusCounts,
    'industries'      => array_map(fn($row) => ['name' => $row['industry'], 'count' => (int) $row['cnt']], $requestsByIndustry),
    'clients'         => $clientList,
    'staff'           => $staffList,
];

$presetOptions = [
    '3m'  => 'Last 3 Months',
    '6m'  => 'Last 6 Months',
    '12m' => 'Last 12 Months',
    'ytd' => 'Year to Date',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Reports &amp; Analytics</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
<style>
:root {
  --canvas: #FAF9F6;
  --card: #FFFEFC;
  --surface: #F6F4EF;
  --line: #E6E2DA;

  --ink: #2A2D2F;
  --ink-soft: #6E7275;
  --slate: #55595C;
  --charcoal: #2B3134;

  --accent: #2F6F6A;
  --accent-hover: #245853;
  --accent-soft: #E3EFEC;
  --accent-text: #245853;

  --success-soft: #E6F1EA;
  --success-text: #2C5E42;
  --success-border: #C5DDCF;

  --warn-soft: #F8EEDB;
  --warn-text: #7F5719;
  --warn-border: #E9D5A6;

  --danger: #B5523F;
  --danger-soft: #F8E9E5;
  --danger-text: #8C3D2E;
  --danger-border: #E8C8BF;
}

* { -webkit-tap-highlight-color: transparent; }

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  overflow-x: hidden;
}

.dashboard-layout, .dashboard-main, .dashboard-content {
  background-color: var(--canvas) !important;
}

.dashboard-main { min-width: 0; max-width: 100%; }

.dashboard-title, h1, h2, h3 { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--charcoal); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background-color: var(--card); }

.card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
}

.card-header {
  background-color: var(--card) !important;
  border-bottom: 1px solid var(--line) !important;
  border-radius: 12px 12px 0 0 !important;
  padding: 1rem 1.25rem;
}
.card-header h2 { color: var(--charcoal); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; }

.form-control { border-color: var(--line); background-color: var(--card); font-size: .875rem; }
.form-control:hover { border-color: #CFCAC0; }
.form-control:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 .2rem rgba(47, 111, 106, .14);
  outline: none;
}

.filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; }
.preset-group {
  display: inline-flex;
  background-color: var(--surface);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .2rem;
  gap: .15rem;
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: none;
}
.preset-group::-webkit-scrollbar { display: none; }
.preset-btn {
  border-radius: 8px;
  padding: .4rem .8rem;
  font-size: .8rem;
  font-weight: 600;
  color: var(--slate);
  text-decoration: none;
  white-space: nowrap;
  transition: background-color .15s ease, color .15s ease;
}
.preset-btn:hover { color: var(--charcoal); }
.preset-btn.active {
  background-color: var(--card);
  color: var(--accent-text);
  box-shadow: 0 1px 2px rgba(42, 45, 47, .1);
}

.filter-dates { display: flex; align-items: center; gap: .5rem; flex: 1 1 320px; }
.filter-dates .form-control { flex: 1 1 0; min-width: 0; }
.filter-dates .to-label { font-size: .8rem; color: var(--ink-soft); }
.filter-actions { display: flex; gap: .5rem; }

.btn-apply {
  background-color: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: .85rem;
  padding: .45rem 1.1rem;
}
.btn-apply:hover { background-color: var(--accent-hover); color: #fff; }

.btn-reset {
  background-color: var(--surface);
  color: var(--charcoal);
  border: 1px solid var(--line);
  border-radius: 8px;
  font-weight: 600;
  font-size: .85rem;
  padding: .45rem 1rem;
}
.btn-reset:hover { background-color: #EAE7E0; color: var(--charcoal); }

.btn-print-report {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  background-color: transparent;
  color: var(--charcoal);
  border: 1px solid var(--line);
  border-radius: 8px;
  font-size: .85rem;
  font-weight: 700;
  padding: .45rem .95rem;
  transition: background-color .15s ease;
}
.btn-print-report:hover { background-color: var(--surface); color: var(--charcoal); }

.metric-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.15rem;
  height: 100%;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
}
.metric-label {
  font-size: .68rem;
  color: var(--ink-soft);
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  margin-bottom: .3rem;
}
.metric-value {
  font-family: 'Lexend', sans-serif;
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--charcoal);
  word-break: break-word;
}
.metric-sub { font-size: .72rem; color: var(--ink-soft); margin-top: .25rem; }

.section-label {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin-bottom: .25rem;
}

.status-pill {
  display: inline-block;
  font-size: .7rem;
  font-weight: 700;
  padding: .3rem .7rem;
  border-radius: 999px;
  white-space: nowrap;
  border: 1px solid transparent;
}
.status-new { background-color: var(--surface); color: var(--slate); border-color: var(--line); }
.status-progress { background-color: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-border); }
.status-approved { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }

.table thead th {
  background-color: var(--surface) !important;
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .7rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  white-space: nowrap;
}
.table td { border-bottom: 1px solid var(--line); vertical-align: middle; font-size: .85rem; color: var(--ink); }
.table-hover tbody tr:hover { background-color: var(--surface); }

.click-row { cursor: pointer; }
.click-row:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
.row-chevron { color: #B9B3A8; font-size: .75rem; width: 28px; text-align: right; }
.click-row:hover .row-chevron { color: var(--accent); }

.staff-list { display: flex; flex-direction: column; gap: .6rem; }
.staff-row {
  width: 100%;
  text-align: left;
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .8rem .95rem;
  display: flex;
  align-items: center;
  gap: .75rem;
  transition: border-color .15s ease, background-color .15s ease;
}
.staff-row:hover { border-color: var(--accent); background-color: #F4F9F8; }
.staff-avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background-color: var(--accent-soft);
  color: var(--accent-text);
  font-size: .78rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.staff-name { font-size: .875rem; font-weight: 600; color: var(--charcoal); }
.staff-meta { font-size: .74rem; color: var(--ink-soft); }
.staff-skills-line { font-size: .7rem; color: var(--ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #CFCAC0; }

.chart-wrap { position: relative; height: 280px; }
.chart-wrap.chart-md { height: 260px; }
.chart-wrap.chart-sm { height: 220px; }

.modal-content { background-color: var(--card); border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); padding: 1rem 1.25rem; }
.modal-title { font-family: 'Lexend', 'Inter', sans-serif; color: var(--charcoal); }

.detail-stat {
  background-color: var(--surface);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .75rem .9rem;
  height: 100%;
}
.detail-stat .value {
  font-family: 'Lexend', sans-serif;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--accent-text);
  word-break: break-word;
}
.detail-block-title {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: .85rem;
  font-weight: 600;
  color: var(--charcoal);
  margin: 1.25rem 0 .6rem;
}
.detail-value { font-size: .875rem; color: var(--ink); word-break: break-word; }

.skill-badge {
  display: inline-block;
  background-color: var(--surface);
  color: var(--slate);
  font-size: .7rem;
  font-weight: 600;
  padding: .28rem .65rem;
  border-radius: 999px;
  margin: 0 .35rem .35rem 0;
  border: 1px solid var(--line);
}

#printArea { display: none; }

#printArea {
  color: #111;
  font-family: 'Inter', Arial, Helvetica, sans-serif;
  font-size: 10pt;
  line-height: 1.5;
}
#printArea .p-letterhead {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #111;
}
#printArea .p-brand { display: flex; align-items: center; gap: 12px; }
#printArea .p-brand img { width: 60px; height: 60px; object-fit: contain; }
#printArea .p-company { font-size: 14pt; font-weight: 700; letter-spacing: .03em; line-height: 1.2; }
#printArea .p-tagline { font-size: 9pt; color: #555; font-style: italic; margin-top: 2px; }
#printArea .p-docmeta { text-align: right; font-size: 9pt; color: #333; line-height: 1.6; }
#printArea .p-docmeta strong { font-size: 11pt; color: #000; }
#printArea .p-title {
  text-align: center;
  font-size: 13pt;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  margin: 18px 0 4px;
}
#printArea .p-subtitle { text-align: center; font-size: 9.5pt; color: #444; margin-bottom: 14px; }
#printArea .p-section { margin-top: 14px; }
#printArea .p-h {
  font-size: 9.5pt;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  padding-bottom: 3px;
  border-bottom: 1px solid #888;
  margin-bottom: 7px;
  break-after: avoid;
  page-break-after: avoid;
}
#printArea table { width: 100%; border-collapse: collapse; }
#printArea .p-kv td { padding: 3px 0; vertical-align: top; }
#printArea .p-kv td.k { width: 26%; color: #444; font-weight: 600; padding-right: 10px; }
#printArea .p-kv td.v { width: 24%; padding-right: 14px; }
#printArea .p-items th {
  background: #EDEDED;
  border: 1px solid #999;
  padding: 5px 7px;
  font-size: 9pt;
  text-align: left;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
#printArea .p-items td { border: 1px solid #bbb; padding: 5px 7px; font-size: 9.5pt; vertical-align: top; }
#printArea .p-items .r { text-align: right; white-space: nowrap; }
#printArea .p-items .c { text-align: center; width: 6%; }
#printArea .p-items tr { break-inside: avoid; page-break-inside: avoid; }
#printArea .p-items tr.total td { font-weight: 700; background: #F5F5F5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
#printArea .p-chart { display: block; width: 100%; max-height: 72mm; object-fit: contain; margin-bottom: 8px; }
#printArea .p-note { font-size: 9pt; color: #555; margin-top: 4px; }
#printArea .p-block { break-inside: avoid; page-break-inside: avoid; }
#printArea .p-footer {
  margin-top: 26px;
  padding-top: 8px;
  border-top: 1px solid #aaa;
  text-align: center;
  font-size: 8.5pt;
  color: #555;
}

@media (max-width: 1199.98px) {
  .metric-card { padding: .9rem 1rem; }
  .metric-value { font-size: 1.2rem; }
}

@media (max-width: 991.98px) {
  .dashboard-title { font-size: 1rem !important; }
  .card-header { padding: .9rem 1rem; }
  .chart-wrap { height: 250px; }
}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .65rem !important; }
  .dashboard-topbar { padding-left: .65rem !important; padding-right: .65rem !important; }
  .dashboard-title { font-size: .92rem !important; }

  .card { border-radius: 10px; }
  .card-header { padding: .75rem .85rem; }
  .card-header h2 { font-size: .9rem; }
  .card-header p { font-size: .74rem; }
  .card .card-body { padding: .8rem; }

  .filter-form { gap: .6rem; }
  .preset-group { width: 100%; }
  .preset-btn { flex: 1 0 auto; text-align: center; font-size: .74rem; padding: .4rem .6rem; }
  .filter-dates { flex: 1 1 100%; }
  .filter-actions { flex: 1 1 100%; }
  .filter-actions .btn { flex: 1 1 0; }
  .form-control { font-size: .8rem; padding: .4rem .55rem; }

  .metric-card { padding: .7rem .8rem; border-radius: 10px; }
  .metric-label { font-size: .6rem; }
  .metric-value { font-size: 1rem; }
  .metric-sub { font-size: .66rem; }

  .chart-wrap, .chart-wrap.chart-md { height: 220px; }
  .chart-wrap.chart-sm { height: 200px; }

  .table td { font-size: .78rem; padding: .55rem .6rem; }
  .table thead th { font-size: .62rem; padding: .5rem .6rem; }

  .staff-row { padding: .7rem .75rem; }
  .staff-avatar { width: 34px; height: 34px; font-size: .72rem; }

  .modal-header { padding: .75rem .9rem; }
  .modal-title { font-size: .95rem; }
  .detail-stat { padding: .6rem .7rem; }
  .detail-stat .value { font-size: .95rem; }
}

@media (max-width: 575.98px) {
  .dashboard-content { padding: .5rem !important; }
  .btn-print-report .print-label { display: none; }
  .btn-print-report { padding: .45rem .7rem; }
}

@media print {
  @page { size: A4; margin: 15mm 14mm; }

  html, body { background: #fff !important; }
  body > *:not(#printArea) { display: none !important; }
  #printArea { display: block !important; }
}
</style>
</head>

<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1">

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3" style="min-width:0;">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div style="min-width:0;">
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Reports &amp; Analytics</h1>
          <p class="dashboard-subtitle small mb-0 text-truncate"><?= htmlspecialchars($rangeLabel) ?></p>
        </div>
      </div>
      <button type="button" class="btn-print-report" id="printReportBtn">
        <i class="fa-solid fa-print"></i><span class="print-label">Print Report</span>
      </button>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="card mb-3">
        <div class="card-body p-2 p-md-3">
          <form method="get" class="filter-form" id="filterForm">
            <div class="preset-group" role="group" aria-label="Quick date ranges">
              <?php foreach ($presetOptions as $key => $label): ?>
                <a href="?preset=<?= $key ?>" class="preset-btn <?= $preset === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="preset" value="custom">
            <div class="filter-dates">
              <input type="date" name="from" value="<?= htmlspecialchars($fromDate->format('Y-m-d')) ?>" class="form-control" aria-label="From date">
              <span class="to-label">to</span>
              <input type="date" name="to" value="<?= htmlspecialchars($toDate->format('Y-m-d')) ?>" class="form-control" aria-label="To date">
            </div>
            <div class="filter-actions">
              <button type="submit" class="btn btn-apply">Apply</button>
              <a href="reports_analytics.php" class="btn btn-reset">Reset</a>
            </div>
          </form>
        </div>
      </section>

      <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-4 col-xl">
          <div class="metric-card">
            <div class="metric-label">Total Contract Value</div>
            <div class="metric-value">&#8369;<?= number_format($totalContractValue, 2) ?></div>
            <div class="metric-sub">Approved contracts in period</div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="metric-card">
            <div class="metric-label">Avg. Deal Size</div>
            <div class="metric-value">&#8369;<?= number_format($avgDealSize, 2) ?></div>
            <div class="metric-sub">Per approved contract</div>
          </div>
        </div>
        <div class="col-6 col-md-4 col-xl">
          <div class="metric-card">
            <div class="metric-label">Quote-to-Win Rate</div>
            <div class="metric-value"><?= $conversionRate ?>%</div>
            <div class="metric-sub"><?= $quotationStatusCounts['Approved'] ?> of <?= $totalQuotationsInRange ?> quotations</div>
          </div>
        </div>
        <div class="col-6 col-md-6 col-xl">
          <div class="metric-card">
            <div class="metric-label">Request Completion</div>
            <div class="metric-value"><?= $completionRate ?>%</div>
            <div class="metric-sub"><?= $completedRequests ?> of <?= $totalRequests ?> requests</div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
          <div class="metric-card">
            <div class="metric-label">Draft Contract Value</div>
            <div class="metric-value">&#8369;<?= number_format($draftContractValue, 2) ?></div>
            <div class="metric-sub"><?= $draftContractCount ?> contract<?= $draftContractCount === 1 ? '' : 's' ?> awaiting review</div>
          </div>
        </div>
      </div>

      <div class="row g-3">

        <div class="col-lg-8">

          <section class="card mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Revenue Over Time</h2>
              <p class="small mb-0">Approved contract value versus quotation value issued per month.</p>
            </div>
            <div class="card-body">
              <div class="chart-wrap">
                <canvas id="revenueChart"></canvas>
              </div>
            </div>
          </section>

          <section class="card mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Service Requests by Industry</h2>
              <p class="small mb-0">Where client demand is concentrated across sectors.</p>
            </div>
            <div class="card-body">
              <?php if (empty($requestsByIndustry)): ?>
                <div class="empty-state text-center py-5">
                  <i class="fa-regular fa-chart-bar fs-4 d-block mb-2"></i>
                  <p class="small mb-0">No service requests in the selected date range.</p>
                </div>
              <?php else: ?>
                <div class="chart-wrap chart-md">
                  <canvas id="industryChart"></canvas>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="card mb-3 overflow-hidden">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Top Clients by Contract Value</h2>
              <p class="small mb-0">Highest-value client relationships in the selected period. Select a client to view details.</p>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Client</th>
                    <th scope="col" class="d-none d-md-table-cell">Industry</th>
                    <th scope="col">Contracts</th>
                    <th scope="col">Total Value</th>
                    <th scope="col"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clientList)): ?>
                    <tr>
                      <td colspan="5">
                        <div class="empty-state text-center py-4">
                          <i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i>
                          <p class="small mb-0">No approved contracts in the selected date range.</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($clientList as $client): ?>
                      <tr class="click-row" data-client-id="<?= $client['id'] ?>" tabindex="0" role="button" aria-label="View details for <?= htmlspecialchars($client['company']) ?>">
                        <td class="fw-semibold"><?= htmlspecialchars($client['company']) ?></td>
                        <td class="d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($client['industry'] ?: '—') ?></td>
                        <td><?= $client['contract_count'] ?></td>
                        <td class="fw-semibold" style="color:var(--success-text);">&#8369;<?= number_format($client['total_value'], 2) ?></td>
                        <td class="row-chevron"><i class="fa-solid fa-chevron-right"></i></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>

        </div>

        <div class="col-lg-4">

          <section class="card mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Request Status Breakdown</h2>
            </div>
            <div class="card-body">
              <?php if (array_sum($requestStatusCounts) === 0): ?>
                <div class="empty-state text-center py-4"><p class="small mb-0">No data in the selected range.</p></div>
              <?php else: ?>
                <div class="chart-wrap chart-sm">
                  <canvas id="requestStatusChart"></canvas>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="card mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Quotation Pipeline</h2>
              <p class="small mb-0">Draft, Approved, Rejected.</p>
            </div>
            <div class="card-body">
              <?php if ($totalQuotationsInRange === 0): ?>
                <div class="empty-state text-center py-4"><p class="small mb-0">No data in the selected range.</p></div>
              <?php else: ?>
                <div class="chart-wrap chart-sm">
                  <canvas id="quotationStatusChart"></canvas>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="card mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Staff Performance</h2>
              <p class="small mb-0">Completed vs. active service requests. Select a staff member to view details.</p>
            </div>
            <div class="card-body">
              <?php if (empty($staffList)): ?>
                <div class="empty-state text-center py-3"><p class="small mb-0">No staff accounts found.</p></div>
              <?php else: ?>
                <div class="staff-list">
                  <?php foreach ($staffList as $staff): ?>
                    <?php
                      $initials = strtoupper(implode('', array_map(
                          fn($part) => mb_substr($part, 0, 1),
                          array_slice(array_filter(explode(' ', $staff['name'])), 0, 2)
                      )));
                    ?>
                    <button type="button" class="staff-row" data-staff-id="<?= $staff['id'] ?>">
                      <span class="staff-avatar"><?= htmlspecialchars($initials) ?></span>
                      <span class="flex-grow-1" style="min-width:0;">
                        <span class="d-flex justify-content-between align-items-center gap-2">
                          <span class="staff-name text-truncate"><?= htmlspecialchars($staff['name']) ?></span>
                          <span class="status-pill <?= statusClass($staff['status']) ?>"><?= htmlspecialchars($staff['status']) ?></span>
                        </span>
                        <span class="staff-meta d-block"><?= $staff['completed'] ?> completed &middot; <?= $staff['active'] ?> active</span>
                        <?php if (!empty($staff['skills'])): ?>
                          <span class="staff-skills-line d-block"><?= htmlspecialchars(implode(', ', $staff['skills'])) ?></span>
                        <?php endif; ?>
                      </span>
                      <i class="fa-solid fa-chevron-right row-chevron"></i>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>

        </div>

      </div>

    </main>

  </div>

</div>

<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <div style="min-width:0;">
          <h2 class="modal-title h5 fw-bold mb-0" id="client_modal_title"></h2>
          <div class="small" id="client_modal_industry" style="color:var(--ink-soft);"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 g-md-3">
          <div class="col-4">
            <div class="detail-stat">
              <div class="section-label">Total Value</div>
              <div class="value" id="client_total_value"></div>
            </div>
          </div>
          <div class="col-4">
            <div class="detail-stat">
              <div class="section-label">Contracts</div>
              <div class="value" id="client_contract_count"></div>
            </div>
          </div>
          <div class="col-4">
            <div class="detail-stat">
              <div class="section-label">Avg. Value</div>
              <div class="value" id="client_average_value"></div>
            </div>
          </div>
        </div>

        <div class="detail-block-title">Client Information</div>
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="section-label">Contact Person</div>
            <div class="detail-value" id="client_contact_person"></div>
          </div>
          <div class="col-sm-6">
            <div class="section-label">Contact Number</div>
            <div class="detail-value" id="client_contact_number"></div>
          </div>
          <div class="col-sm-6">
            <div class="section-label">Email</div>
            <div class="detail-value" id="client_email"></div>
          </div>
          <div class="col-sm-6">
            <div class="section-label">Address</div>
            <div class="detail-value" id="client_address"></div>
          </div>
        </div>

        <div class="detail-block-title">Approved Contracts in Period</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Contract #</th>
                <th>Request</th>
                <th>Duration</th>
                <th class="text-end">Value</th>
              </tr>
            </thead>
            <tbody id="client_contracts_body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header">
        <div style="min-width:0;">
          <h2 class="modal-title h5 fw-bold mb-0" id="staff_modal_name"></h2>
          <div class="small" id="staff_modal_role" style="color:var(--ink-soft);"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 g-md-3">
          <div class="col-4">
            <div class="detail-stat">
              <div class="section-label">Completed</div>
              <div class="value" id="staff_completed"></div>
            </div>
          </div>
          <div class="col-4">
            <div class="detail-stat">
              <div class="section-label">Active</div>
              <div class="value" id="staff_active"></div>
            </div>
          </div>
          <div class="col-4">
            <div class="detail-stat">
              <div class="section-label">Assigned</div>
              <div class="value" id="staff_assigned"></div>
            </div>
          </div>
        </div>

        <div class="detail-block-title">Account Status</div>
        <span class="status-pill" id="staff_status_pill"></span>

        <div class="detail-block-title">Skills</div>
        <div id="staff_skills"></div>

        <div class="detail-block-title">Assigned Service Requests in Period</div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Request</th>
                <th>Client</th>
                <th>Received</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="staff_requests_body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="printArea" aria-hidden="true"></div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
const reportData = <?= json_encode($reportData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const EMPTY_VALUE = '\u2014';
const statusClassMap = {
  'New': 'status-new',
  'Draft': 'status-new',
  'In Progress': 'status-progress',
  'Completed': 'status-approved',
  'Approved': 'status-approved',
  'Active': 'status-approved',
  'Cancelled': 'status-rejected',
  'Rejected': 'status-rejected',
  'Inactive': 'status-rejected'
};

const clientMap = {};
reportData.clients.forEach(function (client) { clientMap[client.id] = client; });

const staffMap = {};
reportData.staff.forEach(function (member) { staffMap[member.id] = member; });

function escapeHtml(value) {
  const element = document.createElement('div');
  element.textContent = (value === null || value === undefined) ? '' : String(value);
  return element.innerHTML;
}

function hasText(value) {
  return value !== null && value !== undefined && String(value).trim() !== '';
}

function peso(amount) {
  return '\u20B1' + Number(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function percentOf(part, whole) {
  return whole > 0 ? ((part / whole) * 100).toFixed(1) + '%' : '0.0%';
}

function setText(elementId, value, fallback) {
  document.getElementById(elementId).textContent = hasText(value) ? value : (fallback !== undefined ? fallback : EMPTY_VALUE);
}

Chart.defaults.font = { family: 'Inter', size: 11 };
Chart.defaults.color = '#6E7275';

const chartColors = {
  accent: '#2F6F6A',
  accentSoft: 'rgba(47, 111, 106, 0.12)',
  sage: '#3E7D5A',
  sageSoft: 'rgba(62, 125, 90, 0.10)',
  amber: '#B07A2A',
  terracotta: '#B5523F',
  slate: '#7A7E81',
  grid: '#E6E2DA'
};

const legendOptions = { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, padding: 12 } };
const charts = {};

charts.revenue = new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: reportData.monthly.map(function (m) { return m.label; }),
    datasets: [
      {
        label: 'Approved Contract Value',
        data: reportData.monthly.map(function (m) { return m.contracts; }),
        borderColor: chartColors.accent,
        backgroundColor: chartColors.accentSoft,
        fill: true,
        tension: 0.35,
        pointRadius: 3
      },
      {
        label: 'Quotation Value Issued',
        data: reportData.monthly.map(function (m) { return m.quotations; }),
        borderColor: chartColors.amber,
        backgroundColor: 'rgba(176, 122, 42, 0.06)',
        fill: true,
        tension: 0.35,
        pointRadius: 3
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10 } } },
    scales: {
      y: { beginAtZero: true, grid: { color: chartColors.grid }, ticks: { callback: function (v) { return '\u20B1' + v.toLocaleString(); } } },
      x: { grid: { display: false } }
    }
  }
});

if (document.getElementById('industryChart')) {
  charts.industry = new Chart(document.getElementById('industryChart'), {
    type: 'bar',
    data: {
      labels: reportData.industries.map(function (row) { return row.name; }),
      datasets: [{
        label: 'Service Requests',
        data: reportData.industries.map(function (row) { return row.count; }),
        backgroundColor: chartColors.accent,
        borderRadius: 6,
        maxBarThickness: 34
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: chartColors.grid }, ticks: { precision: 0 } },
        x: { grid: { display: false } }
      }
    }
  });
}

if (document.getElementById('requestStatusChart')) {
  charts.requestStatus = new Chart(document.getElementById('requestStatusChart'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(reportData.requestStatus),
      datasets: [{
        data: Object.values(reportData.requestStatus),
        backgroundColor: [chartColors.slate, chartColors.amber, chartColors.sage, chartColors.terracotta],
        borderWidth: 0
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: legendOptions } }
  });
}

if (document.getElementById('quotationStatusChart')) {
  charts.quotationStatus = new Chart(document.getElementById('quotationStatusChart'), {
    type: 'doughnut',
    data: {
      labels: Object.keys(reportData.quotationStatus),
      datasets: [{
        data: Object.values(reportData.quotationStatus),
        backgroundColor: [chartColors.slate, chartColors.sage, chartColors.terracotta],
        borderWidth: 0
      }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: legendOptions } }
  });
}

function statusPillHtml(status) {
  return '<span class="status-pill ' + (statusClassMap[status] || 'status-new') + '">' + escapeHtml(status) + '</span>';
}

function openClientModal(clientId) {
  const client = clientMap[clientId];
  if (!client) return;

  setText('client_modal_title', client.company);
  setText('client_modal_industry', client.industry, 'Industry not specified');
  document.getElementById('client_total_value').textContent = peso(client.total_value);
  document.getElementById('client_contract_count').textContent = client.contract_count;
  document.getElementById('client_average_value').textContent = peso(client.contract_count > 0 ? client.total_value / client.contract_count : 0);
  setText('client_contact_person', client.contact_person);
  setText('client_contact_number', client.contact_number);
  setText('client_email', client.email);
  setText('client_address', client.address);

  const body = document.getElementById('client_contracts_body');
  body.innerHTML = '';

  if (client.contracts.length === 0) {
    body.innerHTML = '<tr><td colspan="4" class="text-center" style="color:var(--ink-soft);">No contracts recorded.</td></tr>';
  } else {
    client.contracts.forEach(function (contract) {
      const row = document.createElement('tr');
      row.innerHTML =
        '<td class="fw-semibold text-nowrap">' + escapeHtml(contract.number) + '</td>' +
        '<td>' + escapeHtml(contract.request) + '</td>' +
        '<td class="text-nowrap" style="color:var(--ink-soft);">' + escapeHtml(contract.start || EMPTY_VALUE) + ' \u2013 ' + escapeHtml(contract.end || EMPTY_VALUE) + '</td>' +
        '<td class="text-end fw-semibold text-nowrap">' + escapeHtml(peso(contract.value)) + '</td>';
      body.appendChild(row);
    });
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('clientModal')).show();
}

function openStaffModal(staffId) {
  const member = staffMap[staffId];
  if (!member) return;

  setText('staff_modal_name', member.name);
  setText('staff_modal_role', member.role);
  document.getElementById('staff_completed').textContent = member.completed;
  document.getElementById('staff_active').textContent = member.active;
  document.getElementById('staff_assigned').textContent = member.assigned;

  const statusPill = document.getElementById('staff_status_pill');
  statusPill.textContent = member.status;
  statusPill.className = 'status-pill ' + (statusClassMap[member.status] || 'status-new');

  const skills = document.getElementById('staff_skills');
  skills.innerHTML = member.skills.length > 0
    ? member.skills.map(function (skill) { return '<span class="skill-badge">' + escapeHtml(skill) + '</span>'; }).join('')
    : '<span class="small" style="color:var(--ink-soft);">No skills on record.</span>';

  const body = document.getElementById('staff_requests_body');
  body.innerHTML = '';

  if (member.requests.length === 0) {
    body.innerHTML = '<tr><td colspan="4" class="text-center" style="color:var(--ink-soft);">No assigned requests in this period.</td></tr>';
  } else {
    member.requests.forEach(function (request) {
      const row = document.createElement('tr');
      row.innerHTML =
        '<td class="fw-semibold">' + escapeHtml(request.title) + '</td>' +
        '<td>' + escapeHtml(request.client) + '</td>' +
        '<td class="text-nowrap" style="color:var(--ink-soft);">' + escapeHtml(request.created || EMPTY_VALUE) + '</td>' +
        '<td>' + statusPillHtml(request.status) + '</td>';
      body.appendChild(row);
    });
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('staffModal')).show();
}

document.querySelectorAll('[data-client-id]').forEach(function (element) {
  element.addEventListener('click', function () { openClientModal(element.dataset.clientId); });
  element.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openClientModal(element.dataset.clientId);
    }
  });
});

document.querySelectorAll('[data-staff-id]').forEach(function (element) {
  element.addEventListener('click', function () { openStaffModal(element.dataset.staffId); });
});

function chartImageHtml(chart, altText) {
  if (!chart) return '';
  return '<img class="p-chart" src="' + chart.toBase64Image('image/png', 1) + '" alt="' + escapeHtml(altText) + '">';
}

function buildShareTable(headers, rows, totalRow) {
  let html = '<table class="p-items"><thead><tr>';
  headers.forEach(function (header, index) {
    html += '<th' + (index > 0 ? ' style="text-align:right;"' : '') + '>' + escapeHtml(header) + '</th>';
  });
  html += '</tr></thead><tbody>';
  rows.forEach(function (cells) {
    html += '<tr>' + cells.map(function (cell, index) {
      return '<td' + (index > 0 ? ' class="r"' : '') + '>' + cell + '</td>';
    }).join('') + '</tr>';
  });
  if (totalRow) {
    html += '<tr class="total">' + totalRow.map(function (cell, index) {
      return '<td' + (index > 0 ? ' class="r"' : '') + '>' + cell + '</td>';
    }).join('') + '</tr>';
  }
  return html + '</tbody></table>';
}

function buildPrintHtml() {
  const e = escapeHtml;
  const m = reportData.metrics;
  const printedOn = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });

  const monthlyRows = reportData.monthly.map(function (row) {
    return [e(row.label), e(peso(row.contracts)), e(peso(row.quotations)), e(row.count)];
  });
  const monthlyTotals = [
    'Total',
    e(peso(reportData.monthly.reduce(function (sum, row) { return sum + row.contracts; }, 0))),
    e(peso(reportData.monthly.reduce(function (sum, row) { return sum + row.quotations; }, 0))),
    e(reportData.monthly.reduce(function (sum, row) { return sum + row.count; }, 0))
  ];

  const requestTotal = Object.values(reportData.requestStatus).reduce(function (sum, count) { return sum + count; }, 0);
  const requestRows = Object.keys(reportData.requestStatus).map(function (status) {
    const count = reportData.requestStatus[status];
    return [e(status), e(count), e(percentOf(count, requestTotal))];
  });

  const quotationTotal = Object.values(reportData.quotationStatus).reduce(function (sum, count) { return sum + count; }, 0);
  const quotationRows = Object.keys(reportData.quotationStatus).map(function (status) {
    const count = reportData.quotationStatus[status];
    return [e(status), e(count), e(percentOf(count, quotationTotal))];
  });

  const industryTotal = reportData.industries.reduce(function (sum, row) { return sum + row.count; }, 0);
  const industryRows = reportData.industries.map(function (row) {
    return [e(row.name), e(row.count), e(percentOf(row.count, industryTotal))];
  });

  const clientRows = reportData.clients.map(function (client, index) {
    return [e(index + 1) + '. ' + e(client.company), e(client.industry || EMPTY_VALUE), e(client.contract_count), e(peso(client.total_value))];
  });

  const staffRows = reportData.staff.map(function (member) {
    return [
      e(member.name),
      e(member.status),
      e(member.completed),
      e(member.active),
      e(member.assigned),
      e(member.skills.length > 0 ? member.skills.join(', ') : EMPTY_VALUE)
    ];
  });

  let staffTable = '<table class="p-items"><thead><tr>' +
    '<th>Staff Member</th><th>Status</th><th style="text-align:right;">Completed</th>' +
    '<th style="text-align:right;">Active</th><th style="text-align:right;">Assigned</th><th>Skills</th></tr></thead><tbody>';
  if (staffRows.length === 0) {
    staffTable += '<tr><td colspan="6" style="text-align:center;">No staff accounts found.</td></tr>';
  } else {
    staffRows.forEach(function (cells) {
      staffTable += '<tr><td>' + cells[0] + '</td><td>' + cells[1] + '</td><td class="r">' + cells[2] +
        '</td><td class="r">' + cells[3] + '</td><td class="r">' + cells[4] + '</td><td>' + cells[5] + '</td></tr>';
    });
  }
  staffTable += '</tbody></table>';

  return '' +
    '<div class="p-letterhead">' +
      '<div class="p-brand">' +
        '<img src="../assets/img/system_img/logo.png" alt="KMP Integrated Enterprise, Inc.">' +
        '<div>' +
          '<div class="p-company">KMP INTEGRATED ENTERPRISE, INC.</div>' +
          '<div class="p-tagline">Shaping Smarter Solutions.</div>' +
        '</div>' +
      '</div>' +
      '<div class="p-docmeta">' +
        '<div><strong>Reports and Analytics</strong></div>' +
        '<div>Period: ' + e(reportData.period) + '</div>' +
        '<div>Date Printed: ' + e(printedOn) + '</div>' +
      '</div>' +
    '</div>' +

    '<div class="p-title">Business Performance Report</div>' +
    '<div class="p-subtitle">' + e(reportData.period) + '</div>' +

    '<div class="p-section p-block">' +
      '<div class="p-h">1. Key Performance Indicators</div>' +
      '<table class="p-kv">' +
        '<tr><td class="k">Total Contract Value</td><td class="v"><strong>' + e(peso(m.totalContractValue)) + '</strong></td>' +
            '<td class="k">Average Deal Size</td><td class="v">' + e(peso(m.avgDealSize)) + '</td></tr>' +
        '<tr><td class="k">Quote-to-Win Rate</td><td class="v">' + e(m.conversionRate) + '% (' + e(m.approvedQuotations) + ' of ' + e(m.totalQuotations) + ' quotations)</td>' +
            '<td class="k">Request Completion Rate</td><td class="v">' + e(m.completionRate) + '% (' + e(m.completedRequests) + ' of ' + e(m.totalRequests) + ' requests)</td></tr>' +
        '<tr><td class="k">Draft Contract Value</td><td class="v">' + e(peso(m.draftValue)) + '</td>' +
            '<td class="k">Draft Contracts</td><td class="v">' + e(m.draftCount) + ' awaiting review</td></tr>' +
      '</table>' +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">2. Revenue Over Time</div>' +
      chartImageHtml(charts.revenue, 'Revenue over time') +
      buildShareTable(['Month', 'Approved Contract Value', 'Quotation Value Issued', 'Quotations Issued'], monthlyRows, monthlyTotals) +
    '</div>' +

    '<div class="p-section p-block">' +
      '<div class="p-h">3. Request Status Breakdown</div>' +
      buildShareTable(['Status', 'Requests', 'Share'], requestRows, ['Total', e(requestTotal), e(requestTotal > 0 ? '100.0%' : '0.0%')]) +
    '</div>' +

    '<div class="p-section p-block">' +
      '<div class="p-h">4. Quotation Pipeline</div>' +
      buildShareTable(['Status', 'Quotations', 'Share'], quotationRows, ['Total', e(quotationTotal), e(quotationTotal > 0 ? '100.0%' : '0.0%')]) +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">5. Service Requests by Industry</div>' +
      (industryRows.length > 0
        ? chartImageHtml(charts.industry, 'Service requests by industry') + buildShareTable(['Industry', 'Requests', 'Share'], industryRows, null)
        : '<div class="p-note">No service requests in the selected period.</div>') +
    '</div>' +

    '<div class="p-section p-block">' +
      '<div class="p-h">6. Top Clients by Contract Value</div>' +
      (clientRows.length > 0
        ? buildShareTable(['Client', 'Industry', 'Contracts', 'Total Value'], clientRows, null)
        : '<div class="p-note">No approved contracts in the selected period.</div>') +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">7. Staff Performance</div>' +
      staffTable +
    '</div>';
}

document.getElementById('printReportBtn').addEventListener('click', function () {
  const printArea = document.getElementById('printArea');
  printArea.innerHTML = buildPrintHtml();

  const previousTitle = document.title;
  document.title = 'Reports and Analytics - ' + reportData.period;

  const images = Array.from(printArea.querySelectorAll('img'));
  const waitForImages = Promise.all(images.map(function (image) {
    return image.complete ? Promise.resolve() : new Promise(function (resolve) {
      image.onload = image.onerror = resolve;
    });
  }));

  waitForImages.then(function () {
    window.print();
    document.title = previousTitle;
  });
});
</script>

</body>
</html>