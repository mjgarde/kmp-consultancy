<?php

session_name('SUPERVISOR_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['supervisor_id']) || ($_SESSION['role'] ?? '') !== 'supervisor') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

$totalClients = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();

$newRequests        = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'New'")->fetchColumn();
$inProgressRequests = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'In Progress'")->fetchColumn();
$completedRequests  = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Completed'")->fetchColumn();
$cancelledRequests  = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Cancelled'")->fetchColumn();

$draftQuotations    = (int) $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'Draft'")->fetchColumn();
$approvedQuotations = (int) $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'Approved'")->fetchColumn();
$rejectedQuotations = (int) $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'Rejected'")->fetchColumn();

$draftContracts    = (int) $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'Draft'")->fetchColumn();
$approvedContracts = (int) $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'Approved'")->fetchColumn();
$rejectedContracts = (int) $pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'Rejected'")->fetchColumn();

$awaitingAssignment = (int) $pdo->query(
    "SELECT COUNT(*) FROM service_requests sr
     INNER JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'"
)->fetchColumn();

$activeStaffCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Staff' AND status = 'Active'")->fetchColumn();

$recentRequestsStmt = $pdo->query(
    "SELECT sr.request_id, sr.request_title, sr.status, sr.created_at, c.company_name
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     ORDER BY sr.created_at DESC
     LIMIT 5"
);
$recentRequests = $recentRequestsStmt->fetchAll();

$recentQuotationsStmt = $pdo->query(
    "SELECT q.quotation_number, q.status, q.total_amount, q.created_at, c.company_name
     FROM quotations q
     INNER JOIN clients c ON c.client_id = q.client_id
     ORDER BY q.created_at DESC
     LIMIT 5"
);
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
.card-header p { color: var(--ink-soft) !important; }

.metric-card {
  border-radius: 12px;
  border: 1px solid var(--line);
  background-color: var(--card);
  padding: 1.1rem 1.2rem;
  height: 100%;
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
.metric-label { font-size: .72rem; color: var(--ink-soft); font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.metric-value { font-size: 1.5rem; font-weight: 700; font-family: 'Lexend', sans-serif; color: var(--navy-deep); }

.chart-legend-item {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .78rem;
  color: var(--ink-soft);
}
.chart-legend-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  flex-shrink: 0;
}

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
</style>
</head>

<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/supervisor/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Dashboard</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Welcome back, <?= htmlspecialchars($_SESSION['supervisor_fullname'] ?? 'Supervisor') ?>.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

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
        <div class="col-lg-4">
          <section class="card h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Service Requests</h2>
              <p class="small mb-0">Status distribution.</p>
            </div>
            <div class="card-body">
              <div style="height:150px;">
                <canvas id="requestsChart"></canvas>
              </div>
              <div class="d-flex flex-wrap gap-3 justify-content-center mt-3">
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#1E293B;"></span>New</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#B7791F;"></span>In Progress</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#157A5F;"></span>Completed</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#B4432F;"></span>Cancelled</span>
              </div>
            </div>
          </section>
        </div>
        <div class="col-lg-4">
          <section class="card h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Quotations</h2>
              <p class="small mb-0">Status distribution.</p>
            </div>
            <div class="card-body">
              <div style="height:150px;">
                <canvas id="quotationsChart"></canvas>
              </div>
              <div class="d-flex flex-wrap gap-3 justify-content-center mt-3">
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#1E293B;"></span>Draft</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#157A5F;"></span>Approved</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#B4432F;"></span>Rejected</span>
              </div>
            </div>
          </section>
        </div>
        <div class="col-lg-4">
          <section class="card h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Contracts</h2>
              <p class="small mb-0">Status distribution.</p>
            </div>
            <div class="card-body">
              <div style="height:150px;">
                <canvas id="contractsChart"></canvas>
              </div>
              <div class="d-flex flex-wrap gap-3 justify-content-center mt-3">
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#1E293B;"></span>Draft</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#157A5F;"></span>Approved</span>
                <span class="chart-legend-item"><span class="chart-legend-dot" style="background-color:#B4432F;"></span>Rejected</span>
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
              <a href="client_management.php?tab=requests" class="btn btn-sm btn-view-all">View All</a>
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
buildDoughnut('quotationsChart', [<?= $draftQuotations ?>, <?= $approvedQuotations ?>, <?= $rejectedQuotations ?>], ['#1E293B', '#157A5F', '#B4432F']);
buildDoughnut('contractsChart', [<?= $draftContracts ?>, <?= $approvedContracts ?>, <?= $rejectedContracts ?>], ['#1E293B', '#157A5F', '#B4432F']);
</script>

</body>
</html>