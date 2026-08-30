<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
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
    $defaultFrom = (clone $today)->modify('first day of this month')->modify("-" . ($presetMonths - 1) . " months");
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
foreach ($revenueRows as $r) {
    if (isset($revenueByMonth[$r['ym']])) {
        $revenueByMonth[$r['ym']] = (float) $r['total'];
    }
}

$quotationValueByMonth = array_fill_keys($months, 0.0);
$quotationCountByMonth = array_fill_keys($months, 0);
foreach ($quotationRows as $r) {
    if (isset($quotationValueByMonth[$r['ym']])) {
        $quotationValueByMonth[$r['ym']] = (float) $r['total'];
        $quotationCountByMonth[$r['ym']] = (int) $r['cnt'];
    }
}

$monthLabels = array_map(fn($ym) => (new DateTime($ym . '-01'))->format('M Y'), $months);

$requestStatusStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM service_requests WHERE created_at BETWEEN :from AND :to GROUP BY status"
);
$requestStatusStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$requestStatusCounts = ['New' => 0, 'In Progress' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach ($requestStatusStmt->fetchAll() as $row) {
    $requestStatusCounts[$row['status']] = (int) $row['cnt'];
}

$requestsByIndustryStmt = $pdo->prepare(
    "SELECT c.industry, COUNT(sr.request_id) AS cnt
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.created_at BETWEEN :from AND :to
     GROUP BY c.industry
     ORDER BY cnt DESC
     LIMIT 8"
);
$requestsByIndustryStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$requestsByIndustry = $requestsByIndustryStmt->fetchAll();

$quotationConversionStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM quotations WHERE created_at BETWEEN :from AND :to GROUP BY status"
);
$quotationConversionStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$quotationStatusCounts = ['Draft' => 0, 'Sent' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach ($quotationConversionStmt->fetchAll() as $row) {
    $quotationStatusCounts[$row['status']] = (int) $row['cnt'];
}
$totalQuotationsInRange = array_sum($quotationStatusCounts);
$conversionRate = $totalQuotationsInRange > 0
    ? round(($quotationStatusCounts['Approved'] / $totalQuotationsInRange) * 100, 1)
    : 0.0;

$avgDealSizeStmt = $pdo->prepare(
    "SELECT COALESCE(AVG(total_amount),0) FROM contracts WHERE status = 'Approved' AND created_at BETWEEN :from AND :to"
);
$avgDealSizeStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$avgDealSize = (float) $avgDealSizeStmt->fetchColumn();

$pendingContractValueStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount),0), COUNT(*) FROM contracts WHERE status = 'Pending Approval' AND created_at BETWEEN :from AND :to"
);
$pendingContractValueStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$pendingContractRow = $pendingContractValueStmt->fetch(PDO::FETCH_NUM);
$pendingContractValue = (float) $pendingContractRow[0];
$pendingContractCount = (int) $pendingContractRow[1];

$topClientsStmt = $pdo->prepare(
    "SELECT c.company_name, c.industry, COALESCE(SUM(ct.total_amount),0) AS total_value, COUNT(ct.contract_id) AS contract_count
     FROM clients c
     LEFT JOIN contracts ct ON ct.client_id = c.client_id AND ct.status = 'Approved' AND ct.created_at BETWEEN :from AND :to
     GROUP BY c.client_id
     HAVING total_value > 0
     ORDER BY total_value DESC
     LIMIT 5"
);
$topClientsStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$topClients = $topClientsStmt->fetchAll();

$staffPerformanceStmt = $pdo->prepare(
    "SELECT u.user_id, u.firstname, u.lastname, u.status,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'Completed' AND sr.updated_at BETWEEN :from1 AND :to1) AS completed_count,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'In Progress') AS active_count,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.created_at BETWEEN :from2 AND :to2) AS total_assigned,
            (SELECT GROUP_CONCAT(skill_name SEPARATOR ', ') FROM staff_skills ss WHERE ss.user_id = u.user_id) AS skills
     FROM users u
     WHERE u.role IN ('Staff', 'Supervisor')
     ORDER BY completed_count DESC, active_count DESC"
);
$staffPerformanceStmt->execute([
    'from1' => $rangeFrom, 'to1' => $rangeTo,
    'from2' => $rangeFrom, 'to2' => $rangeTo,
]);
$staffPerformance = $staffPerformanceStmt->fetchAll();

$totalContractValueStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(total_amount),0) FROM contracts WHERE status = 'Approved' AND created_at BETWEEN :from AND :to"
);
$totalContractValueStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$totalContractValueAllTime = (float) $totalContractValueStmt->fetchColumn();

$totalRequestsStmt = $pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE created_at BETWEEN :from AND :to');
$totalRequestsStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$totalRequestsAllTime = (int) $totalRequestsStmt->fetchColumn();

$completedRequestsStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE status = 'Completed' AND created_at BETWEEN :from AND :to");
$completedRequestsStmt->execute(['from' => $rangeFrom, 'to' => $rangeTo]);
$completedRequestsAllTime = (int) $completedRequestsStmt->fetchColumn();

$completionRate = $totalRequestsAllTime > 0 ? round(($completedRequestsAllTime / $totalRequestsAllTime) * 100, 1) : 0.0;

function statusClass(string $status): string
{
    return match ($status) {
        'New', 'Draft' => 'status-new',
        'In Progress', 'Sent', 'Pending Approval' => 'status-progress',
        'Completed', 'Approved' => 'status-approved',
        'Cancelled', 'Rejected' => 'status-rejected',
        default => 'status-new',
    };
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports &amp; Analytics</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
<style>
:root {
  --navy: #33495C;
  --navy-soft: #EEF2F5;
  --teal: #4CA79A;
  --teal-soft: #E7F5F2;
  --teal-text: #2E6E63;
  --amber: #E0A44E;
  --amber-soft: #FBF1E1;
  --amber-text: #93662A;
  --coral: #DB7A66;
  --coral-soft: #FBECE8;
  --coral-text: #A2452F;
  --ink: #2B3540;
  --ink-soft: #6B7684;
  --line: #E7EAEE;
  --canvas: #F6F8F9;
  --card: #FFFFFF;
}

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-title, h1, h2, h3 {
  font-family: 'Lexend', 'Inter', sans-serif;
}

.dashboard-title { color: var(--ink); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; }

.card { border-radius: 14px; border: 1px solid var(--line); }

.card-header {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card) !important;
  border-radius: 14px 14px 0 0 !important;
  padding: 1rem 1.15rem;
}
.card-header h2 { color: var(--ink); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; }

.metric-card {
  border-radius: 14px;
  border: 1px solid var(--line);
  background-color: var(--card);
  padding: 1.1rem 1.2rem;
  height: 100%;
}
.metric-icon {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  flex-shrink: 0;
}
.metric-label { font-size: .75rem; color: var(--ink-soft); font-weight: 600; }
.metric-value { font-size: 1.5rem; font-weight: 700; font-family: 'Lexend', sans-serif; color: var(--ink); }
.metric-sub { font-size: .72rem; color: var(--ink-soft); }
.metric-trend { font-size: .72rem; font-weight: 600; }
.metric-trend.up { color: var(--teal-text); }
.metric-trend.down { color: var(--coral-text); }

.status-pill {
  font-size: .68rem;
  font-weight: 600;
  padding: .28rem .6rem;
  border-radius: 999px;
  white-space: nowrap;
}
.status-new { background-color: var(--navy-soft); color: var(--navy); }
.status-progress { background-color: var(--amber-soft); color: var(--amber-text); }
.status-approved { background-color: var(--teal-soft); color: var(--teal-text); }
.status-rejected { background-color: var(--coral-soft); color: var(--coral-text); }

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 600;
  font-size: .68rem;
  letter-spacing: .04em;
  text-transform: uppercase;
  background-color: var(--canvas) !important;
}
.table td { border-bottom: 1px solid var(--line); vertical-align: middle; font-size: .82rem; }
.table-hover tbody tr:hover { background-color: var(--navy-soft); }

.workload-bar-track {
  background-color: var(--navy-soft);
  border-radius: 999px;
  height: 6px;
  overflow: hidden;
  width: 100%;
}
.workload-bar-fill { background-color: var(--teal); height: 100%; border-radius: 999px; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.btn-print {
  background-color: var(--navy);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-weight: 600;
}
.btn-print:hover { background-color: #263A4A; color: #fff; }

.range-toggle {
  display: flex;
  border: 1px solid var(--line);
  border-radius: 9px;
  overflow: hidden;
}
.range-toggle a {
  padding: .4rem .85rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--ink-soft);
  text-decoration: none;
  background-color: var(--card);
}
.range-toggle a.active { background-color: var(--navy); color: #fff; }
.range-toggle a:not(:last-child) { border-right: 1px solid var(--line); }

.chart-wrap { position: relative; }

.legend-dot {
  width: 9px; height: 9px; border-radius: 999px; display: inline-block; margin-right: 6px;
}

.print-header { display: none; }

@media print {
  .no-print { display: none !important; }
  .print-header {
    display: block;
    text-align: center;
    margin-bottom: 24px;
    padding-bottom: 14px;
    border-bottom: 2px solid var(--navy);
  }
  .print-header h1 {
    font-family: 'Lexend', sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 2px;
  }
  .print-header p {
    font-size: 12px;
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

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4 no-print">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Reports &amp; Analytics</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Showing <?= htmlspecialchars($fromDate->format('M d, Y')) ?> &ndash; <?= htmlspecialchars($toDate->format('M d, Y')) ?></p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <form method="get" class="d-flex align-items-center gap-2 flex-wrap" id="filterForm">
          <div class="range-toggle">
            <a href="?preset=3m" class="<?= $preset === '3m' ? 'active' : '' ?>">3M</a>
            <a href="?preset=6m" class="<?= $preset === '6m' ? 'active' : '' ?>">6M</a>
            <a href="?preset=12m" class="<?= $preset === '12m' ? 'active' : '' ?>">12M</a>
            <a href="?preset=ytd" class="<?= $preset === 'ytd' ? 'active' : '' ?>">YTD</a>
          </div>
          <input type="hidden" name="preset" value="custom">
          <input type="date" name="from" value="<?= htmlspecialchars($fromDate->format('Y-m-d')) ?>" class="form-control form-control-sm" style="width:150px;">
          <span class="small" style="color:var(--ink-soft);">to</span>
          <input type="date" name="to" value="<?= htmlspecialchars($toDate->format('Y-m-d')) ?>" class="form-control form-control-sm" style="width:150px;">
          <button type="submit" class="btn btn-sm px-3" style="background-color:var(--navy); color:#fff; font-weight:600;">Apply</button>
        </form>
        <button type="button" class="btn btn-print btn-sm px-3" onclick="window.print()">
          <i class="fa-solid fa-print me-1"></i> Print
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

      <div class="print-header">
        <h1>KMP Business Consultancy Services</h1>
        <p>Reports &amp; Analytics</p>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-label mb-1">Total Contract Value</div>
            <div class="metric-value" style="font-size:1.25rem;">&#8369;<?= number_format($totalContractValueAllTime, 2) ?></div>
            <div class="metric-sub mt-1">All approved contracts</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-label mb-1">Avg. Deal Size</div>
            <div class="metric-value" style="font-size:1.25rem;">&#8369;<?= number_format($avgDealSize, 2) ?></div>
            <div class="metric-sub mt-1">Per approved contract</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-label mb-1">Quote-to-Win Rate</div>
            <div class="metric-value"><?= $conversionRate ?>%</div>
            <div class="metric-sub mt-1"><?= $quotationStatusCounts['Approved'] ?> of <?= $totalQuotationsInRange ?> quotations</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-label mb-1">Request Completion Rate</div>
            <div class="metric-value"><?= $completionRate ?>%</div>
            <div class="metric-sub mt-1"><?= $completedRequestsAllTime ?> of <?= $totalRequestsAllTime ?> requests</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card">
            <div class="metric-label mb-1">Pending Approval Value</div>
            <div class="metric-value" style="font-size:1.25rem;">&#8369;<?= number_format($pendingContractValue, 2) ?></div>
            <div class="metric-sub mt-1"><?= $pendingContractCount ?> contract<?= $pendingContractCount === 1 ? '' : 's' ?> awaiting approval</div>
          </div>
        </div>
      </div>

      <div class="row g-3">

        <div class="col-lg-8">

          <section class="card border-0 shadow-sm mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Revenue Over Time</h2>
              <p class="small mb-0">Approved contract value versus quotation value issued per month.</p>
            </div>
            <div class="card-body">
              <div class="chart-wrap" style="height:280px;">
                <canvas id="revenueChart"></canvas>
              </div>
            </div>
          </section>

          <section class="card border-0 shadow-sm mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Service Requests by Industry</h2>
              <p class="small mb-0">Where client demand is concentrated across sectors.</p>
            </div>
            <div class="card-body">
              <?php if (empty($requestsByIndustry)): ?>
                <div class="empty-state text-center py-5"><i class="fa-regular fa-chart-bar fs-4 d-block mb-2"></i><p class="small mb-0">No service requests in the selected date range.</p></div>
              <?php else: ?>
                <div class="chart-wrap" style="height:260px;">
                  <canvas id="industryChart"></canvas>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="card border-0 shadow-sm">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Top Clients by Contract Value</h2>
              <p class="small mb-0">Highest-value client relationships to date.</p>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Client</th>
                    <th scope="col" class="d-none d-md-table-cell">Industry</th>
                    <th scope="col">Contracts</th>
                    <th scope="col">Total Value</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($topClients)): ?>
                    <tr><td colspan="4"><div class="empty-state text-center py-4"><i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i><p class="small mb-0">No approved contracts in the selected date range.</p></div></td></tr>
                  <?php else: ?>
                    <?php foreach ($topClients as $c): ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($c['company_name']) ?></td>
                        <td class="d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($c['industry'] ?? '—') ?></td>
                        <td><?= (int) $c['contract_count'] ?></td>
                        <td class="fw-semibold" style="color:var(--teal-text);">&#8369;<?= number_format((float) $c['total_value'], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>

        </div>

        <div class="col-lg-4">

          <section class="card border-0 shadow-sm mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Request Status Breakdown</h2>
            </div>
            <div class="card-body">
              <?php if (array_sum($requestStatusCounts) === 0): ?>
                <div class="empty-state text-center py-4"><p class="small mb-0">No data in the selected range.</p></div>
              <?php else: ?>
                <div class="chart-wrap" style="height:220px;">
                  <canvas id="requestStatusChart"></canvas>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="card border-0 shadow-sm mb-3">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Quotation Pipeline</h2>
            </div>
            <div class="card-body">
              <?php if ($totalQuotationsInRange === 0): ?>
                <div class="empty-state text-center py-4"><p class="small mb-0">No data in the selected range.</p></div>
              <?php else: ?>
                <div class="chart-wrap" style="height:220px;">
                  <canvas id="quotationStatusChart"></canvas>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="card border-0 shadow-sm">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Staff Performance</h2>
              <p class="small mb-0">Completed vs. active service requests.</p>
            </div>
            <div class="card-body d-flex flex-column gap-3">
              <?php if (empty($staffPerformance)): ?>
                <div class="empty-state text-center py-3"><p class="small mb-0">No staff accounts found.</p></div>
              <?php else: ?>
                <?php foreach ($staffPerformance as $s): ?>
                  <div class="pb-2" style="border-bottom:1px solid var(--line);">
                    <div class="d-flex justify-content-between mb-1">
                      <span class="small fw-semibold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></span>
                      <span class="status-pill <?= $s['status'] === 'Active' ? 'status-approved' : 'status-rejected' ?>"><?= htmlspecialchars($s['status']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                      <span class="small" style="color:var(--ink-soft);">
                        <?= (int) $s['completed_count'] ?> completed &middot; <?= (int) $s['active_count'] ?> active
                      </span>
                    </div>
                    <?php if (!empty($s['skills'])): ?>
                      <div class="small" style="color:var(--ink-soft); font-size:.7rem;"><?= htmlspecialchars($s['skills']) ?></div>
                    <?php endif; ?>
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
<script>
const chartFont = { family: 'Inter', size: 11 };
Chart.defaults.font = chartFont;
Chart.defaults.color = '#6B7684';

const monthLabels = <?= json_encode(array_values($monthLabels)) ?>;
const contractRevenue = <?= json_encode(array_values($revenueByMonth)) ?>;
const quotationValue = <?= json_encode(array_values($quotationValueByMonth)) ?>;

new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: monthLabels,
    datasets: [
      {
        label: 'Approved Contract Value',
        data: contractRevenue,
        borderColor: '#4CA79A',
        backgroundColor: 'rgba(76,167,154,0.12)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
      },
      {
        label: 'Quotation Value Issued',
        data: quotationValue,
        borderColor: '#33495C',
        backgroundColor: 'rgba(51,73,92,0.06)',
        fill: true,
        tension: 0.35,
        pointRadius: 3,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10 } } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#E7EAEE' }, ticks: { callback: (v) => '\u20B1' + v.toLocaleString() } },
      x: { grid: { display: false } }
    }
  }
});

const industryLabels = <?= json_encode(array_column($requestsByIndustry, 'industry')) ?>;
const industryCounts = <?= json_encode(array_map('intval', array_column($requestsByIndustry, 'cnt'))) ?>;

if (document.getElementById('industryChart')) {
new Chart(document.getElementById('industryChart'), {
  type: 'bar',
  data: {
    labels: industryLabels,
    datasets: [{
      label: 'Service Requests',
      data: industryCounts,
      backgroundColor: '#4CA79A',
      borderRadius: 6,
      maxBarThickness: 34,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#E7EAEE' }, ticks: { precision: 0 } },
      x: { grid: { display: false } }
    }
  }
});
}

if (document.getElementById('requestStatusChart')) {
new Chart(document.getElementById('requestStatusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($requestStatusCounts)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($requestStatusCounts)) ?>,
      backgroundColor: ['#33495C', '#E0A44E', '#4CA79A', '#DB7A66'],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, padding: 12 } } }
  }
});
}

if (document.getElementById('quotationStatusChart')) {
new Chart(document.getElementById('quotationStatusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($quotationStatusCounts)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($quotationStatusCounts)) ?>,
      backgroundColor: ['#33495C', '#E0A44E', '#4CA79A', '#DB7A66'],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, padding: 12 } } }
  }
});
}
</script>

</body>
</html>