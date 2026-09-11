<?php

session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['manager_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

$clientReportStmt = $pdo->query(
    "SELECT c.client_id, c.company_name,
            COUNT(sr.request_id) AS total_requests,
            SUM(CASE WHEN sr.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN sr.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
            AVG(CASE WHEN sr.status != 'New' THEN TIMESTAMPDIFF(HOUR, sr.created_at, sr.updated_at) END) AS avg_response_hours
     FROM clients c
     LEFT JOIN service_requests sr ON sr.client_id = c.client_id
     GROUP BY c.client_id, c.company_name
     ORDER BY total_requests DESC"
);
$clientReport = $clientReportStmt->fetchAll();

$overallAvgResponse = $pdo->query(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) FROM service_requests WHERE status != 'New'"
)->fetchColumn();
$overallAvgResponse = $overallAvgResponse !== null ? round((float) $overallAvgResponse, 1) : null;

$newRequests        = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'New'")->fetchColumn();
$inProgressRequests = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'In Progress'")->fetchColumn();
$completedRequests  = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Completed'")->fetchColumn();

$staffPerfStmt = $pdo->query(
    "SELECT u.user_id, u.firstname, u.lastname, u.status,
            COUNT(sr.request_id) AS total_assigned,
            SUM(CASE WHEN sr.status = 'In Progress' THEN 1 ELSE 0 END) AS active_tasks,
            SUM(CASE WHEN sr.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks
     FROM users u
     LEFT JOIN service_requests sr ON sr.assigned_to = u.user_id
     WHERE u.role = 'Staff'
     GROUP BY u.user_id, u.firstname, u.lastname, u.status
     ORDER BY total_assigned DESC"
);
$staffPerf = $staffPerfStmt->fetchAll();

$skillDemandStmt = $pdo->query(
    "SELECT required_skill, COUNT(*) AS demand
     FROM service_requests
     WHERE required_skill IS NOT NULL AND required_skill != ''
     GROUP BY required_skill
     ORDER BY demand DESC
     LIMIT 6"
);
$skillDemand = $skillDemandStmt->fetchAll();

$contractStatusStmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM contracts GROUP BY status");
$contractStatusCounts = ['Draft' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach ($contractStatusStmt->fetchAll() as $row) {
    if (isset($contractStatusCounts[$row['status']])) {
        $contractStatusCounts[$row['status']] = (int) $row['cnt'];
    }
}

$upcomingExpirationsStmt = $pdo->query(
    "SELECT ct.contract_number, ct.end_date, c.company_name
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     WHERE ct.status = 'Approved' AND ct.end_date IS NOT NULL AND ct.end_date >= CURDATE()
     ORDER BY ct.end_date ASC
     LIMIT 5"
);
$upcomingExpirations = $upcomingExpirationsStmt->fetchAll();

$avgApprovalHours = $pdo->query(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, approved_at)) FROM contracts WHERE approved_at IS NOT NULL"
)->fetchColumn();
$avgApprovalHours = $avgApprovalHours !== null ? round((float) $avgApprovalHours, 1) : null;

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

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.15rem;
}
.stat-card .stat-label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); }
.stat-card .stat-value { font-family: 'Lexend', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--navy-deep); margin-top: .15rem; }
.stat-card .stat-icon {
  width: 40px; height: 40px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; flex-shrink: 0;
  background-color: var(--navy-soft);
  border: 1px solid var(--line);
  color: var(--indigo-text);
}

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
}
.table td { border-bottom: 1px solid var(--line); color: var(--ink); vertical-align: middle; font-size: .82rem; }
.table-hover tbody tr:hover { background-color: var(--navy-soft); }

.status-pill {
  font-size: .7rem;
  font-weight: 700;
  padding: .32rem .7rem;
  border-radius: 999px;
  white-space: nowrap;
  letter-spacing: .01em;
  border: 1px solid transparent;
}
.status-draft { background-color: var(--navy-soft); color: var(--slate); border-color: var(--line); }
.status-approved { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }
.status-progress { background-color: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-border); }

.bar-track { background-color: var(--navy-soft); border-radius: 999px; height: 8px; overflow: hidden; width: 100%; }
.bar-fill { height: 100%; border-radius: 999px; background-color: var(--indigo); }

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
  .card, .stat-card { border: 1px solid #D8DEE3 !important; box-shadow: none !important; break-inside: avoid; }
  .tab-pane { display: block !important; opacity: 1 !important; }
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
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Client service and staff performance insights.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <button type="button" class="btn btn-outline-print btn-sm px-3" onclick="window.print()">
          <i class="fa-solid fa-print me-1"></i> Print
        </button>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="print-header">
        <h1>KMP Business Consultancy Services</h1>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-4">
          <div class="stat-card d-flex align-items-center gap-3">
            <span class="stat-icon"><i class="fa-solid fa-clock"></i></span>
            <div>
              <div class="stat-label">Avg. Response Time</div>
              <div class="stat-value" style="font-size:1.2rem;"><?= $overallAvgResponse !== null ? $overallAvgResponse . ' hrs' : '&mdash;' ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-4">
          <div class="stat-card d-flex align-items-center gap-3">
            <span class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></span>
            <div>
              <div class="stat-label">In Progress Requests</div>
              <div class="stat-value" style="font-size:1.2rem;"><?= $inProgressRequests ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-4">
          <div class="stat-card d-flex align-items-center gap-3">
            <span class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></span>
            <div>
              <div class="stat-label">Avg. Contract Approval</div>
              <div class="stat-value" style="font-size:1.2rem;"><?= $avgApprovalHours !== null ? $avgApprovalHours . ' hrs' : '&mdash;' ?></div>
            </div>
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
          <section class="card">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Service Requests by Client</h2>
              <p class="small mb-0">Volume, completion, and cancellation per client.</p>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Client</th>
                    <th scope="col">Total Requests</th>
                    <th scope="col">Completed</th>
                    <th scope="col">Cancelled</th>
                    <th scope="col" class="d-none d-md-table-cell">Avg. Response</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($clientReport)): ?>
                    <tr><td colspan="5"><div class="empty-state text-center py-4"><i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i><p class="small mb-0">No client data yet.</p></div></td></tr>
                  <?php else: ?>
                    <?php foreach ($clientReport as $c): ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($c['company_name']) ?></td>
                        <td><?= (int) $c['total_requests'] ?></td>
                        <td style="color:var(--success-text);"><?= (int) $c['completed'] ?></td>
                        <td style="color:var(--danger-text);"><?= (int) $c['cancelled'] ?></td>
                        <td class="d-none d-md-table-cell" style="color:var(--ink-soft);">
                          <?= $c['avg_response_hours'] !== null ? round((float) $c['avg_response_hours'], 1) . ' hrs' : '&mdash;' ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
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
                        <th scope="col">Total Assigned</th>
                        <th scope="col">Active</th>
                        <th scope="col">Completed</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($staffPerf)): ?>
                        <tr><td colspan="5"><div class="empty-state text-center py-4"><p class="small mb-0">No staff accounts found.</p></div></td></tr>
                      <?php else: ?>
                        <?php foreach ($staffPerf as $s): ?>
                          <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></td>
                            <td><span class="status-pill <?= $s['status'] === 'Active' ? 'status-approved' : 'status-rejected' ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                            <td><?= (int) $s['total_assigned'] ?></td>
                            <td style="color:var(--warn-text);"><?= (int) $s['active_tasks'] ?></td>
                            <td style="color:var(--success-text);"><?= (int) $s['completed_tasks'] ?></td>
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
                        <div class="bar-track"><div class="bar-fill" style="width:<?= round(($sk['demand']/$maxDemand)*100) ?>%;"></div></div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </section>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="tab-contracts">
          <div class="row g-3">
            <div class="col-lg-5">
              <section class="card h-100">
                <div class="card-header">
                  <h2 class="h6 fw-bold mb-0">Contracts by Status</h2>
                </div>
                <div class="card-body">
                  <canvas id="contractStatusChart" height="220"></canvas>
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
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($upcomingExpirations)): ?>
                        <tr><td colspan="3"><div class="empty-state text-center py-4"><p class="small mb-0">No upcoming expirations.</p></div></td></tr>
                      <?php else: ?>
                        <?php foreach ($upcomingExpirations as $ue): ?>
                          <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($ue['contract_number']) ?></td>
                            <td style="color:var(--ink-soft);"><?= htmlspecialchars($ue['company_name']) ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($ue['end_date']))) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </section>
            </div>
          </div>
        </div>

      </div>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
const contractStatusCanvas = document.getElementById('contractStatusChart');
if (contractStatusCanvas) {
  new Chart(contractStatusCanvas, {
    type: 'doughnut',
    data: {
      labels: ['Draft', 'Approved', 'Rejected'],
      datasets: [{
        data: [
          <?= $contractStatusCounts['Draft'] ?>,
          <?= $contractStatusCounts['Approved'] ?>,
          <?= $contractStatusCounts['Rejected'] ?>
        ],
        backgroundColor: ['#64748B', '#157A5F', '#B4432F'],
        borderWidth: 0,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
    }
  });
}
</script>

</body>
</html>