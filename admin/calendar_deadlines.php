<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

$totalClients = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();

$newRequests = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'New'")->fetchColumn();
$inProgressRequests = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'In Progress'")->fetchColumn();
$completedRequestsTotal = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Completed'")->fetchColumn();
$cancelledRequests = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Cancelled'")->fetchColumn();
$totalRequests = $newRequests + $inProgressRequests + $completedRequestsTotal + $cancelledRequests;
$ongoingRequests = $newRequests + $inProgressRequests;

$today = new DateTime('today');
$next7 = (clone $today)->modify('+7 days');

$dueTodayStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM (
        SELECT valid_until AS d FROM quotations WHERE status IN ('Sent','Draft')
        UNION ALL
        SELECT end_date AS d FROM contracts WHERE status = 'Approved'
     ) t WHERE t.d = :today"
);
$dueTodayStmt->execute(['today' => $today->format('Y-m-d')]);
$dueTodayCount = (int) $dueTodayStmt->fetchColumn();

$overdueRequestsCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE status = 'New' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetchColumn();

$completedThisMonthStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM service_requests WHERE status = 'Completed' AND updated_at BETWEEN :start AND :end"
);
$completedThisMonthStmt->execute([
    'start' => (new DateTime('first day of this month'))->format('Y-m-d 00:00:00'),
    'end' => (new DateTime('last day of this month'))->format('Y-m-d 23:59:59'),
]);
$completedThisMonth = (int) $completedThisMonthStmt->fetchColumn();

$awaitingAssignment = (int) $pdo->query(
    "SELECT COUNT(*) FROM service_requests WHERE assigned_to IS NULL AND status = 'New'"
)->fetchColumn();

$next14 = (clone $today)->modify('+14 days');
$expiringQuotationsCount = (int) (function () use ($pdo, $today, $next14) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM quotations WHERE valid_until BETWEEN :today AND :next14 AND status IN ('Sent','Draft')"
    );
    $stmt->execute(['today' => $today->format('Y-m-d'), 'next14' => $next14->format('Y-m-d')]);
    return $stmt->fetchColumn();
})();

$pendingContractsCount = (int) $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'Pending Approval'")->fetchColumn();

function dueBadge(string $dateStr): array
{
    $today = new DateTime('today');
    $target = new DateTime($dateStr);
    $diff = (int) $today->diff($target)->format('%r%a');

    if ($diff < 0) {
        return ['label' => 'Overdue', 'bg' => 'var(--coral-soft)', 'text' => 'var(--coral-text)'];
    }
    if ($diff === 0) {
        return ['label' => 'Due Today', 'bg' => 'var(--coral-soft)', 'text' => 'var(--coral-text)'];
    }
    if ($diff === 1) {
        return ['label' => 'Due Tomorrow', 'bg' => 'var(--amber-soft)', 'text' => 'var(--amber-text)'];
    }
    return ['label' => "Due in {$diff} days", 'bg' => 'var(--navy-soft)', 'text' => 'var(--navy)'];
}

$upcomingStmt = $pdo->prepare(
    "SELECT 'Quotation Expiry' AS title, q.quotation_number AS ref, q.valid_until AS due_date, c.company_name
     FROM quotations q INNER JOIN clients c ON c.client_id = q.client_id
     WHERE q.valid_until >= :today AND q.status IN ('Sent','Draft')
     UNION ALL
     SELECT 'Engagement End' AS title, ct.contract_number AS ref, ct.end_date AS due_date, c.company_name
     FROM contracts ct INNER JOIN clients c ON c.client_id = ct.client_id
     WHERE ct.end_date >= :today2 AND ct.status = 'Approved'
     ORDER BY due_date ASC
     LIMIT 5"
);
$upcomingStmt->execute(['today' => $today->format('Y-m-d'), 'today2' => $today->format('Y-m-d')]);
$upcomingDeadlines = $upcomingStmt->fetchAll();

$recentActivityStmt = $pdo->query(
    "SELECT c.company_name, 'Service request' AS activity, CONCAT(sr.status, ': ', sr.request_title) AS detail, sr.updated_at AS ts
     FROM service_requests sr INNER JOIN clients c ON c.client_id = sr.client_id
     UNION ALL
     SELECT c.company_name, 'Quotation' AS activity, CONCAT(q.status, ': ', q.quotation_number) AS detail, q.updated_at AS ts
     FROM quotations q INNER JOIN clients c ON c.client_id = q.client_id
     UNION ALL
     SELECT c.company_name, 'Contract' AS activity, CONCAT(ct.status, ': ', ct.contract_number) AS detail, ct.updated_at AS ts
     FROM contracts ct INNER JOIN clients c ON c.client_id = ct.client_id
     ORDER BY ts DESC
     LIMIT 6"
);
$recentActivities = $recentActivityStmt->fetchAll();

$staffAssignmentsStmt = $pdo->query(
    "SELECT u.firstname, u.lastname, sr.request_title, sr.status, c.company_name, sr.updated_at
     FROM service_requests sr
     INNER JOIN users u ON u.user_id = sr.assigned_to
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.status IN ('New','In Progress')
     ORDER BY sr.updated_at DESC
     LIMIT 6"
);
$staffAssignments = $staffAssignmentsStmt->fetchAll();

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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calendar &amp; Schedule</title>
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
  --purple: #8B7FC7;
  --purple-soft: #EFEDFB;
  --purple-text: #5B4FA0;
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
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}
.metric-label { font-size: .75rem; color: var(--ink-soft); font-weight: 600; }
.metric-value { font-size: 1.65rem; font-weight: 700; font-family: 'Lexend', sans-serif; color: var(--ink); }
.metric-sub { font-size: .72rem; color: var(--ink-soft); }
.metric-sub.link { color: var(--teal-text); font-weight: 600; }
.metric-sub.warn { color: var(--amber-text); font-weight: 600; }
.metric-sub.danger { color: var(--coral-text); font-weight: 600; }

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

.legend-list { display: flex; flex-direction: column; gap: .65rem; }
.legend-row { display: flex; align-items: center; justify-content: space-between; font-size: .8rem; }
.legend-dot { width: 9px; height: 9px; border-radius: 999px; display: inline-block; margin-right: 8px; }
.legend-count { font-weight: 700; font-family: 'Lexend', sans-serif; }

.deadline-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .6rem;
  padding: .7rem 0;
  border-bottom: 1px solid var(--line);
}
.deadline-item:last-child { border-bottom: none; padding-bottom: 0; }
.deadline-date-box {
  width: 42px;
  border-radius: 9px;
  background-color: var(--coral-soft);
  color: var(--coral-text);
  text-align: center;
  padding: .3rem 0;
  flex-shrink: 0;
}
.deadline-date-box .mon { font-size: .6rem; font-weight: 700; text-transform: uppercase; display: block; }
.deadline-date-box .day { font-size: .95rem; font-weight: 700; font-family: 'Lexend', sans-serif; display: block; }

.alert-item {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: .7rem 0;
  border-bottom: 1px solid var(--line);
}
.alert-item:last-child { border-bottom: none; padding-bottom: 0; }
.alert-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.activity-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: .6rem;
  padding: .65rem 0;
  border-bottom: 1px solid var(--line);
}
.activity-row:last-child { border-bottom: none; padding-bottom: 0; }

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

.quick-link {
  display: flex;
  align-items: center;
  gap: .75rem;
  border-radius: 12px;
  border: 1px solid var(--line);
  padding: .85rem 1rem;
  text-decoration: none;
  color: var(--ink);
  transition: border-color .15s ease, background-color .15s ease;
}
.quick-link:hover { border-color: var(--teal); background-color: var(--teal-soft); color: var(--ink); }
.quick-link-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: .95rem;
}
.quick-link-title { font-weight: 600; font-size: .85rem; }
.quick-link-sub { font-size: .72rem; color: var(--ink-soft); }

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
  body { background-color: #fff !important; }
  .dashboard-layout { display: block !important; }
  .dashboard-layout > *:not(.dashboard-main) { display: none !important; }
  .dashboard-main { width: 100% !important; margin: 0 !important; }
  main.dashboard-content { padding: 0 24px 24px !important; }
  .card, .metric-card { border: 1px solid #D8DEE3 !important; box-shadow: none !important; break-inside: avoid; }
  a.quick-link { display: none !important; }
  section:has(.quick-link) { display: none !important; }
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Calendar &amp; Schedule</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Deadlines, engagement dates, and pending actions.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <a href="dashboard.php" class="btn btn-sm px-3" style="background-color:var(--navy-soft); color:var(--navy); font-weight:600;">
          <i class="fa-solid fa-gauge me-1"></i> Dashboard
        </a>
        <a href="reports_analytics.php" class="btn btn-sm px-3" style="background-color:var(--teal-soft); color:var(--teal-text); font-weight:600;">
          <i class="fa-solid fa-chart-line me-1"></i> Reports
        </a>
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
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--navy-soft);">
              <i class="fa-solid fa-building" style="color:var(--navy);"></i>
            </span>
            <div>
              <div class="metric-label">Total Clients</div>
              <div class="metric-value"><?= $totalClients ?></div>
              <div class="metric-sub">Active clients</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--teal-soft);">
              <i class="fa-solid fa-clipboard-list" style="color:var(--teal-text);"></i>
            </span>
            <div>
              <div class="metric-label">Ongoing Requests</div>
              <div class="metric-value"><?= $ongoingRequests ?></div>
              <div class="metric-sub">New &amp; in progress</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--amber-soft);">
              <i class="fa-solid fa-clock" style="color:var(--amber-text);"></i>
            </span>
            <div>
              <div class="metric-label">Due Today</div>
              <div class="metric-value"><?= $dueTodayCount ?></div>
              <div class="metric-sub warn">Quotations &amp; contracts</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--coral-soft);">
              <i class="fa-solid fa-triangle-exclamation" style="color:var(--coral-text);"></i>
            </span>
            <div>
              <div class="metric-label">Overdue Requests</div>
              <div class="metric-value"><?= $overdueRequestsCount ?></div>
              <div class="metric-sub danger">Requires attention</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--purple-soft);">
              <i class="fa-solid fa-circle-check" style="color:var(--purple-text);"></i>
            </span>
            <div>
              <div class="metric-label">Completed</div>
              <div class="metric-value"><?= $completedThisMonth ?></div>
              <div class="metric-sub">This month</div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">

        <div class="col-lg-5">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Requests Overview</h2>
              <p class="small mb-0">Breakdown of all service requests by status.</p>
            </div>
            <div class="card-body d-flex align-items-center gap-3 flex-wrap flex-md-nowrap">
              <div style="width:150px; height:150px; flex-shrink:0;">
                <canvas id="requestsDonut"></canvas>
              </div>
              <div class="legend-list flex-grow-1">
                <div class="legend-row">
                  <span><span class="legend-dot" style="background-color:var(--navy);"></span>Not Started</span>
                  <span class="legend-count"><?= $newRequests ?></span>
                </div>
                <div class="legend-row">
                  <span><span class="legend-dot" style="background-color:var(--teal);"></span>In Progress</span>
                  <span class="legend-count"><?= $inProgressRequests ?></span>
                </div>
                <div class="legend-row">
                  <span><span class="legend-dot" style="background-color:var(--purple);"></span>Completed</span>
                  <span class="legend-count"><?= $completedRequestsTotal ?></span>
                </div>
                <div class="legend-row">
                  <span><span class="legend-dot" style="background-color:var(--coral);"></span>Cancelled</span>
                  <span class="legend-count"><?= $cancelledRequests ?></span>
                </div>
              </div>
            </div>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Upcoming Deadlines</h2>
            </div>
            <div class="card-body">
              <?php if (empty($upcomingDeadlines)): ?>
                <div class="empty-state text-center py-4"><i class="fa-regular fa-calendar fs-4 d-block mb-2"></i><p class="small mb-0">Walang malapit na deadline.</p></div>
              <?php else: ?>
                <?php foreach ($upcomingDeadlines as $d): ?>
                  <?php $badge = dueBadge($d['due_date']); $dt = new DateTime($d['due_date']); ?>
                  <div class="deadline-item">
                    <div class="d-flex gap-2">
                      <div class="deadline-date-box">
                        <span class="mon"><?= $dt->format('M') ?></span>
                        <span class="day"><?= $dt->format('d') ?></span>
                      </div>
                      <div>
                        <div class="small fw-semibold"><?= htmlspecialchars($d['title']) ?> &mdash; <?= htmlspecialchars($d['ref']) ?></div>
                        <div class="small" style="color:var(--ink-soft);"><?= htmlspecialchars($d['company_name']) ?></div>
                      </div>
                    </div>
                    <span class="status-pill" style="background-color:<?= $badge['bg'] ?>; color:<?= $badge['text'] ?>;"><?= $badge['label'] ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-3">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Reminders &amp; Alerts</h2>
            </div>
            <div class="card-body">
              <div class="alert-item">
                <span class="alert-icon" style="background-color:var(--coral-soft);"><i class="fa-solid fa-bell" style="color:var(--coral-text);"></i></span>
                <div>
                  <div class="small fw-semibold"><?= $overdueRequestsCount ?> requests are overdue</div>
                  <div class="small" style="color:var(--ink-soft);">Please take immediate action.</div>
                </div>
              </div>
              <div class="alert-item">
                <span class="alert-icon" style="background-color:var(--navy-soft);"><i class="fa-solid fa-user-clock" style="color:var(--navy);"></i></span>
                <div>
                  <div class="small fw-semibold"><?= $awaitingAssignment ?> requests awaiting assignment</div>
                  <div class="small" style="color:var(--ink-soft);">Assign to available staff.</div>
                </div>
              </div>
              <div class="alert-item">
                <span class="alert-icon" style="background-color:var(--amber-soft);"><i class="fa-solid fa-file-invoice-dollar" style="color:var(--amber-text);"></i></span>
                <div>
                  <div class="small fw-semibold"><?= $expiringQuotationsCount ?> quotations expiring soon</div>
                  <div class="small" style="color:var(--ink-soft);">Follow up with clients.</div>
                </div>
              </div>
              <div class="alert-item">
                <span class="alert-icon" style="background-color:var(--teal-soft);"><i class="fa-solid fa-file-signature" style="color:var(--teal-text);"></i></span>
                <div>
                  <div class="small fw-semibold"><?= $pendingContractsCount ?> contracts pending approval</div>
                  <div class="small" style="color:var(--ink-soft);">Review &amp; approve.</div>
                </div>
              </div>
            </div>
          </section>
        </div>

      </div>

      <div class="row g-3">

        <div class="col-lg-6">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Recent Client Activities</h2>
            </div>
            <div class="card-body">
              <?php if (empty($recentActivities)): ?>
                <div class="empty-state text-center py-4"><p class="small mb-0">Walang recent activity.</p></div>
              <?php else: ?>
                <?php foreach ($recentActivities as $a): ?>
                  <div class="activity-row">
                    <div>
                      <div class="small fw-semibold"><?= htmlspecialchars($a['company_name']) ?></div>
                      <div class="small" style="color:var(--ink-soft);"><?= htmlspecialchars($a['activity']) ?>: <?= htmlspecialchars($a['detail']) ?></div>
                    </div>
                    <div class="small text-end" style="color:var(--ink-soft); white-space:nowrap;"><?= (new DateTime($a['ts']))->format('M d, g:i A') ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-6">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Active Staff Assignments</h2>
            </div>
            <div class="card-body">
              <?php if (empty($staffAssignments)): ?>
                <div class="empty-state text-center py-4"><p class="small mb-0">Walang active assignments.</p></div>
              <?php else: ?>
                <?php foreach ($staffAssignments as $s): ?>
                  <div class="activity-row">
                    <div>
                      <div class="small fw-semibold"><?= htmlspecialchars($s['request_title']) ?></div>
                      <div class="small" style="color:var(--ink-soft);"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?> &middot; <?= htmlspecialchars($s['company_name']) ?></div>
                    </div>
                    <span class="status-pill <?= requestStatusClass($s['status']) ?>"><?= htmlspecialchars($s['status']) ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </div>

      </div>

      <section class="card border-0 shadow-sm mt-3 no-print">
        <div class="card-header">
          <h2 class="h6 fw-bold mb-0">Quick Actions</h2>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
          <a href="client_management.php" class="quick-link flex-grow-1" style="min-width:200px;">
            <span class="quick-link-icon" style="background-color:var(--navy-soft);"><i class="fa-solid fa-building" style="color:var(--navy);"></i></span>
            <div>
              <div class="quick-link-title">Client Management</div>
              <div class="quick-link-sub">Clients &amp; service requests</div>
            </div>
          </a>
          <a href="cpq_builder.php" class="quick-link flex-grow-1" style="min-width:200px;">
            <span class="quick-link-icon" style="background-color:var(--teal-soft);"><i class="fa-solid fa-file-invoice-dollar" style="color:var(--teal-text);"></i></span>
            <div>
              <div class="quick-link-title">CPQ &amp; Scope Builder</div>
              <div class="quick-link-sub">Create client quotations</div>
            </div>
          </a>
          <a href="sow_contract.php" class="quick-link flex-grow-1" style="min-width:200px;">
            <span class="quick-link-icon" style="background-color:var(--amber-soft);"><i class="fa-solid fa-file-signature" style="color:var(--amber-text);"></i></span>
            <div>
              <div class="quick-link-title">SOW &amp; Contracts</div>
              <div class="quick-link-sub">Generate &amp; approve contracts</div>
            </div>
          </a>
          <a href="resource_matching.php" class="quick-link flex-grow-1" style="min-width:200px;">
            <span class="quick-link-icon" style="background-color:var(--coral-soft);"><i class="fa-solid fa-people-arrows" style="color:var(--coral-text);"></i></span>
            <div>
              <div class="quick-link-title">Resource Matching</div>
              <div class="quick-link-sub">Assign staff to contracts</div>
            </div>
          </a>
          <a href="dashboard.php" class="quick-link flex-grow-1" style="min-width:200px;">
            <span class="quick-link-icon" style="background-color:var(--purple-soft);"><i class="fa-solid fa-gauge" style="color:var(--purple-text);"></i></span>
            <div>
              <div class="quick-link-title">Dashboard</div>
              <div class="quick-link-sub">Overview &amp; quick metrics</div>
            </div>
          </a>
          <a href="reports_analytics.php" class="quick-link flex-grow-1" style="min-width:200px;">
            <span class="quick-link-icon" style="background-color:var(--navy-soft);"><i class="fa-solid fa-chart-line" style="color:var(--navy);"></i></span>
            <div>
              <div class="quick-link-title">Reports &amp; Analytics</div>
              <div class="quick-link-sub">Revenue, trends &amp; performance</div>
            </div>
          </a>
        </div>
      </section>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
Chart.defaults.font = { family: 'Inter', size: 11 };
Chart.defaults.color = '#6B7684';

new Chart(document.getElementById('requestsDonut'), {
  type: 'doughnut',
  data: {
    labels: ['Not Started', 'In Progress', 'Completed', 'Cancelled'],
    datasets: [{
      data: [<?= $newRequests ?>, <?= $inProgressRequests ?>, <?= $completedRequestsTotal ?>, <?= $cancelledRequests ?>],
      backgroundColor: ['#33495C', '#4CA79A', '#8B7FC7', '#DB7A66'],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: {
      legend: { display: false },
      tooltip: { enabled: true }
    }
  },
  plugins: [{
    id: 'centerText',
    afterDraw(chart) {
      const { ctx, chartArea } = chart;
      const total = <?= $totalRequests ?>;
      const cx = (chartArea.left + chartArea.right) / 2;
      const cy = (chartArea.top + chartArea.bottom) / 2;
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = '700 20px Lexend, sans-serif';
      ctx.fillStyle = '#2B3540';
      ctx.fillText(total, cx, cy - 8);
      ctx.font = '600 10px Inter, sans-serif';
      ctx.fillStyle = '#6B7684';
      ctx.fillText('Total', cx, cy + 12);
      ctx.restore();
    }
  }]
});
</script>

</body>
</html>