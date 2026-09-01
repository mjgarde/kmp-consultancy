<?php

session_name('STAFF_SESSION');
session_start();

if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();
$staffId = (int) $_SESSION['staff_id'];

$statusCountsStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM service_requests WHERE assigned_to = :staff_id GROUP BY status"
);
$statusCountsStmt->execute(['staff_id' => $staffId]);
$statusCounts = ['New' => 0, 'In Progress' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach ($statusCountsStmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['cnt'];
}
$totalAssigned = array_sum($statusCounts);

$completedThisMonthStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM service_requests
     WHERE assigned_to = :staff_id AND status = 'Completed' AND updated_at BETWEEN :start AND :end"
);
$completedThisMonthStmt->execute([
    'staff_id' => $staffId,
    'start' => (new DateTime('first day of this month'))->format('Y-m-d 00:00:00'),
    'end' => (new DateTime('last day of this month'))->format('Y-m-d 23:59:59'),
]);
$completedThisMonth = (int) $completedThisMonthStmt->fetchColumn();

$staleStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM service_requests
     WHERE assigned_to = :staff_id AND status IN ('New','In Progress') AND updated_at < DATE_SUB(NOW(), INTERVAL 5 DAY)"
);
$staleStmt->execute(['staff_id' => $staffId]);
$staleCount = (int) $staleStmt->fetchColumn();

$trendMonths = [];
for ($i = 5; $i >= 0; $i--) {
    $trendMonths[date('Y-m', strtotime("-$i months"))] = 0;
}

$trendStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(updated_at, '%Y-%m') AS ym, COUNT(*) AS cnt
     FROM service_requests
     WHERE assigned_to = :staff_id AND status = 'Completed'
       AND updated_at >= :start
     GROUP BY ym"
);
$trendStmt->execute([
    'staff_id' => $staffId,
    'start' => (new DateTime('first day of this month'))->modify('-5 months')->format('Y-m-d 00:00:00'),
]);
foreach ($trendStmt->fetchAll() as $row) {
    if (isset($trendMonths[$row['ym']])) {
        $trendMonths[$row['ym']] = (int) $row['cnt'];
    }
}
$trendLabels = array_map(fn($m) => date('M', strtotime($m . '-01')), array_keys($trendMonths));
$trendData = array_values($trendMonths);

$recentStmt = $pdo->prepare(
    "SELECT sr.request_title, sr.status, sr.updated_at, c.company_name
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.assigned_to = :staff_id
     ORDER BY sr.updated_at DESC
     LIMIT 5"
);
$recentStmt->execute(['staff_id' => $staffId]);
$recentActivity = $recentStmt->fetchAll();

$topClientsStmt = $pdo->prepare(
    "SELECT c.company_name, COUNT(*) AS cnt
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.assigned_to = :staff_id
     GROUP BY c.client_id
     ORDER BY cnt DESC
     LIMIT 5"
);
$topClientsStmt->execute(['staff_id' => $staffId]);
$topClients = $topClientsStmt->fetchAll();
$topClientsMax = 1;
foreach ($topClients as $tc) {
    $topClientsMax = max($topClientsMax, (int) $tc['cnt']);
}

function dotColor(string $status): string
{
    return match ($status) {
        'New' => 'var(--signal-new)',
        'In Progress' => 'var(--signal-progress)',
        'Completed' => 'var(--signal-done)',
        'Cancelled' => 'var(--signal-urgent)',
        default => 'var(--signal-new)',
    };
}

function activityVerb(string $status): string
{
    return match ($status) {
        'New' => 'Assigned',
        'In Progress' => 'In progress',
        'Completed' => 'Completed',
        'Cancelled' => 'Cancelled',
        default => 'Updated',
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
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
<style>
:root {
  --ink: #17222C;
  --ink-soft: #5C6773;
  --ink-faint: #8A93A0;
  --surface: #FBFAF7;
  --panel: #FFFFFF;
  --line: #E6E2D6;
  --line-soft: #F0EDE3;
  --primary: #1F3D37;
  --primary-hover: #16302B;
  --primary-tint: #E7EEEB;
  --signal-new: #1F3D37;
  --signal-progress: #B8873A;
  --signal-progress-tint: #F7EFDF;
  --signal-done: #3E7D63;
  --signal-done-tint: #E7F1EC;
  --signal-urgent: #B14A3A;
  --signal-urgent-tint: #F7E7E2;
}

* { font-variant-numeric: tabular-nums; }

body {
  background-color: var(--surface);
  color: var(--ink);
  font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background-color: var(--panel) !important; }
.dashboard-title { color: var(--ink); font-weight: 600; letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }

.summary-strip {
  background-color: var(--panel);
  border: 1px solid var(--line);
  border-radius: 10px;
  display: flex;
  flex-wrap: wrap;
  margin-bottom: 1.25rem;
  overflow: hidden;
}
.summary-cell {
  flex: 1 1 0;
  min-width: 150px;
  padding: .95rem 1.15rem;
  border-right: 1px solid var(--line-soft);
}
.summary-cell:last-child { border-right: none; }
.summary-cell .n { font-size: 1.55rem; font-weight: 700; line-height: 1; color: var(--ink); }
.summary-cell .l { font-size: .75rem; color: var(--ink-soft); margin-top: .3rem; }
.summary-cell.is-new .n { color: var(--signal-new); }
.summary-cell.is-progress .n { color: var(--signal-progress); }
.summary-cell.is-done .n { color: var(--signal-done); }
.summary-cell.is-urgent .n { color: var(--signal-urgent); }

.panel {
  background-color: var(--panel);
  border: 1px solid var(--line);
  border-radius: 10px;
  margin-bottom: 1.1rem;
  overflow: hidden;
}
.panel-head {
  padding: 1rem 1.2rem;
  border-bottom: 1px solid var(--line);
}
.panel-head h2 { font-size: .95rem; font-weight: 600; color: var(--ink); margin: 0; }
.panel-head p { font-size: .76rem; color: var(--ink-faint); margin: .15rem 0 0; }
.panel-body { padding: 1.15rem 1.2rem; }

.legend-list { display: flex; flex-direction: column; gap: .6rem; }
.legend-row { display: flex; align-items: center; justify-content: space-between; font-size: .82rem; }
.legend-label { display: flex; align-items: center; gap: .5rem; color: var(--ink-soft); }
.legend-dot { width: 8px; height: 8px; border-radius: 999px; }
.legend-value { font-weight: 700; font-family: 'IBM Plex Sans', sans-serif; color: var(--ink); }

.activity-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .6rem;
  padding: .75rem 0;
  border-bottom: 1px solid var(--line-soft);
}
.activity-item:last-child { border-bottom: none; padding-bottom: 0; }
.activity-item:first-child { padding-top: 0; }
.activity-flag { display: flex; align-items: center; gap: .45rem; font-size: .82rem; font-weight: 600; color: var(--ink); }
.activity-dot { width: 7px; height: 7px; border-radius: 999px; flex-shrink: 0; }
.activity-sub { font-size: .76rem; color: var(--ink-soft); margin-top: .15rem; margin-left: 1.35rem; }
.activity-time { font-size: .7rem; color: var(--ink-faint); white-space: nowrap; }

.client-row { display: flex; align-items: center; gap: .8rem; padding: .55rem 0; }
.client-name { width: 40%; font-size: .8rem; color: var(--ink); flex-shrink: 0; }
.client-track { flex-grow: 1; height: 7px; background-color: var(--line-soft); border-radius: 999px; overflow: hidden; }
.client-fill { height: 100%; border-radius: 999px; background-color: var(--primary); }
.client-count { width: 26px; text-align: right; font-size: .78rem; font-weight: 600; color: var(--ink); flex-shrink: 0; }

.empty-state { color: var(--ink-faint); text-align: center; padding: 2rem 0; }
.empty-state i { font-size: 1.4rem; display: block; margin-bottom: .5rem; color: var(--line); }

@media (max-width: 767px) {
  .summary-strip { flex-wrap: wrap; }
  .summary-cell { flex: 1 1 50%; border-bottom: 1px solid var(--line-soft); }
  .summary-cell:nth-child(2n) { border-right: none; }
  .summary-cell:nth-last-child(-n+2) { border-bottom: none; }
}
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md mb-0">Dashboard</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Welcome back, <?= htmlspecialchars($_SESSION['staff_fullname'] ?? 'Staff') ?></p>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3 gap-md-4">
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="summary-strip">
        <div class="summary-cell">
          <div class="n"><?= $totalAssigned ?></div>
          <div class="l">Total assigned</div>
        </div>
        <div class="summary-cell is-progress">
          <div class="n"><?= $statusCounts['In Progress'] ?></div>
          <div class="l">In progress</div>
        </div>
        <div class="summary-cell is-done">
          <div class="n"><?= $completedThisMonth ?></div>
          <div class="l">Completed this month</div>
        </div>
        <div class="summary-cell is-urgent">
          <div class="n"><?= $staleCount ?></div>
          <div class="l">Needs follow-up</div>
        </div>
      </div>

      <div class="row g-3">

        <div class="col-lg-5">
          <section class="panel">
            <div class="panel-head">
              <h2>Your task mix</h2>
              <p>All service requests assigned to you.</p>
            </div>
            <div class="panel-body d-flex align-items-center gap-4 flex-wrap">
              <div style="width:140px; height:140px; flex-shrink:0;">
                <canvas id="statusDonut"></canvas>
              </div>
              <div class="legend-list flex-grow-1">
                <div class="legend-row">
                  <span class="legend-label"><span class="legend-dot" style="background-color:var(--signal-new);"></span>New</span>
                  <span class="legend-value"><?= $statusCounts['New'] ?></span>
                </div>
                <div class="legend-row">
                  <span class="legend-label"><span class="legend-dot" style="background-color:var(--signal-progress);"></span>In progress</span>
                  <span class="legend-value"><?= $statusCounts['In Progress'] ?></span>
                </div>
                <div class="legend-row">
                  <span class="legend-label"><span class="legend-dot" style="background-color:var(--signal-done);"></span>Completed</span>
                  <span class="legend-value"><?= $statusCounts['Completed'] ?></span>
                </div>
                <div class="legend-row">
                  <span class="legend-label"><span class="legend-dot" style="background-color:var(--signal-urgent);"></span>Cancelled</span>
                  <span class="legend-value"><?= $statusCounts['Cancelled'] ?></span>
                </div>
              </div>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head">
              <h2>Top clients</h2>
              <p>By number of requests handled.</p>
            </div>
            <div class="panel-body">
              <?php if (empty($topClients)): ?>
                <div class="empty-state"><i class="fa-regular fa-building"></i>No client data yet.</div>
              <?php else: ?>
                <?php foreach ($topClients as $tc): ?>
                  <?php $pct = round(((int) $tc['cnt'] / $topClientsMax) * 100); ?>
                  <div class="client-row">
                    <span class="client-name text-truncate"><?= htmlspecialchars($tc['company_name']) ?></span>
                    <span class="client-track"><span class="client-fill" style="width:<?= $pct ?>%;"></span></span>
                    <span class="client-count"><?= $tc['cnt'] ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-7">
          <section class="panel">
            <div class="panel-head">
              <h2>Completed over time</h2>
              <p>Tasks you closed out, last 6 months.</p>
            </div>
            <div class="panel-body">
              <div style="height:200px;">
                <canvas id="completedTrend"></canvas>
              </div>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head">
              <h2>Recent activity</h2>
              <p>Latest changes on your assignments.</p>
            </div>
            <div class="panel-body">
              <?php if (empty($recentActivity)): ?>
                <div class="empty-state"><i class="fa-regular fa-clock"></i>Nothing yet.</div>
              <?php else: ?>
                <?php foreach ($recentActivity as $a): ?>
                  <div class="activity-item">
                    <div>
                      <div class="activity-flag">
                        <span class="activity-dot" style="background-color:<?= dotColor($a['status']) ?>;"></span>
                        <?= htmlspecialchars(activityVerb($a['status'])) ?>: <?= htmlspecialchars($a['request_title']) ?>
                      </div>
                      <div class="activity-sub"><?= htmlspecialchars($a['company_name']) ?></div>
                    </div>
                    <span class="activity-time"><?= (new DateTime($a['updated_at']))->format('M d, g:i A') ?></span>
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
Chart.defaults.font = { family: 'IBM Plex Sans', size: 11 };
Chart.defaults.color = '#5C6773';

new Chart(document.getElementById('statusDonut'), {
  type: 'doughnut',
  data: {
    labels: ['New', 'In Progress', 'Completed', 'Cancelled'],
    datasets: [{
      data: [<?= $statusCounts['New'] ?>, <?= $statusCounts['In Progress'] ?>, <?= $statusCounts['Completed'] ?>, <?= $statusCounts['Cancelled'] ?>],
      backgroundColor: ['#1F3D37', '#B8873A', '#3E7D63', '#B14A3A'],
      borderWidth: 0,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '70%',
    plugins: { legend: { display: false } }
  }
});

new Chart(document.getElementById('completedTrend'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($trendLabels) ?>,
    datasets: [{
      data: <?= json_encode($trendData) ?>,
      backgroundColor: '#3E7D63',
      borderRadius: 5,
      maxBarThickness: 42,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#F0EDE3' }, ticks: { precision: 0 } },
      x: { grid: { display: false } }
    }
  }
});
</script>

</body>
</html>