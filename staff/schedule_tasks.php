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

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['New', 'In Progress', 'Completed', 'Cancelled'];

$countsStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM service_requests WHERE assigned_to = :staff_id GROUP BY status"
);
$countsStmt->execute(['staff_id' => $staffId]);
$statusCounts = ['New' => 0, 'In Progress' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach ($countsStmt->fetchAll() as $row) {
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

$query = "SELECT sr.request_id, sr.request_title, sr.request_details, sr.required_skill, sr.status, sr.created_at, sr.updated_at, c.company_name
          FROM service_requests sr
          INNER JOIN clients c ON c.client_id = sr.client_id
          WHERE sr.assigned_to = :staff_id";
$params = ['staff_id' => $staffId];

if (in_array($statusFilter, $allowedStatuses, true)) {
    $query .= " AND sr.status = :status";
    $params['status'] = $statusFilter;
}

$query .= " ORDER BY FIELD(sr.status, 'New', 'In Progress', 'Completed', 'Cancelled'), sr.updated_at DESC";

$taskStmt = $pdo->prepare($query);
$taskStmt->execute($params);
$tasks = $taskStmt->fetchAll();

$staleTasksStmt = $pdo->prepare(
    "SELECT sr.request_id, sr.request_title, sr.status, sr.updated_at, c.company_name
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.assigned_to = :staff_id AND sr.status IN ('New','In Progress') AND sr.updated_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
     ORDER BY sr.updated_at ASC
     LIMIT 5"
);
$staleTasksStmt->execute(['staff_id' => $staffId]);
$staleTasks = $staleTasksStmt->fetchAll();

function taskStatusClass(string $status): string
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
<title>My Schedule / Tasks</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
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

.filter-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
}
.filter-tab {
  padding: .4rem .9rem;
  border-radius: 999px;
  border: 1px solid var(--line);
  font-size: .78rem;
  font-weight: 600;
  color: var(--ink-soft);
  text-decoration: none;
  background-color: var(--card);
}
.filter-tab.active { background-color: var(--navy); color: #fff; border-color: var(--navy); }
.filter-tab:hover { border-color: var(--teal); color: var(--teal-text); }
.filter-tab.active:hover { color: #fff; }

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

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.task-title { font-weight: 600; font-size: .85rem; }
.task-meta { font-size: .72rem; color: var(--ink-soft); }
</style>
</head>
<body class="bg-light">

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">My Schedule / Tasks</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Everything assigned to you, in one place.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <button type="button" class="btn btn-link text-secondary p-0">
          <i class="fa-regular fa-bell fs-5"></i>
        </button>
        <div class="dropdown">
          <button type="button" class="btn btn-link p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="dashboard-user-icon d-flex align-items-center justify-content-center rounded-circle bg-secondary bg-opacity-10 flex-shrink-0" style="width:36px; height:36px;">
              <i class="fa-solid fa-user text-secondary"></i>
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
              <a href="../config/logout.php?role=staff" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--navy-soft);">
              <i class="fa-solid fa-list-check" style="color:var(--navy);"></i>
            </span>
            <div>
              <div class="metric-label">Total Assigned</div>
              <div class="metric-value"><?= $totalAssigned ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--amber-soft);">
              <i class="fa-solid fa-spinner" style="color:var(--amber-text);"></i>
            </span>
            <div>
              <div class="metric-label">In Progress</div>
              <div class="metric-value"><?= $statusCounts['In Progress'] ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--teal-soft);">
              <i class="fa-solid fa-circle-check" style="color:var(--teal-text);"></i>
            </span>
            <div>
              <div class="metric-label">Completed This Month</div>
              <div class="metric-value"><?= $completedThisMonth ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--coral-soft);">
              <i class="fa-solid fa-triangle-exclamation" style="color:var(--coral-text);"></i>
            </span>
            <div>
              <div class="metric-label">Needs Follow-up (5d+)</div>
              <div class="metric-value"><?= $staleCount ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">

        <div class="col-lg-8">
          <section class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <h2 class="h6 fw-bold mb-0">My Tasks</h2>
                <p class="small mb-0">All service requests currently assigned to you.</p>
              </div>
              <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?= $statusFilter === 'all' ? 'active' : '' ?>">All (<?= $totalAssigned ?>)</a>
                <a href="?status=New" class="filter-tab <?= $statusFilter === 'New' ? 'active' : '' ?>">New (<?= $statusCounts['New'] ?>)</a>
                <a href="?status=In Progress" class="filter-tab <?= $statusFilter === 'In Progress' ? 'active' : '' ?>">In Progress (<?= $statusCounts['In Progress'] ?>)</a>
                <a href="?status=Completed" class="filter-tab <?= $statusFilter === 'Completed' ? 'active' : '' ?>">Completed (<?= $statusCounts['Completed'] ?>)</a>
                <a href="?status=Cancelled" class="filter-tab <?= $statusFilter === 'Cancelled' ? 'active' : '' ?>">Cancelled (<?= $statusCounts['Cancelled'] ?>)</a>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Task</th>
                    <th scope="col" class="d-none d-md-table-cell">Client</th>
                    <th scope="col" class="d-none d-lg-table-cell">Last Updated</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($tasks)): ?>
                    <tr><td colspan="4"><div class="empty-state text-center py-5"><i class="fa-regular fa-folder-open fs-4 d-block mb-2"></i><p class="small mb-0">No tasks match this filter.</p></div></td></tr>
                  <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                      <tr>
                        <td>
                          <div class="task-title"><?= htmlspecialchars($t['request_title']) ?></div>
                          <?php if (!empty($t['required_skill'])): ?>
                            <div class="task-meta"><?= htmlspecialchars($t['required_skill']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($t['company_name']) ?></td>
                        <td class="d-none d-lg-table-cell task-meta"><?= (new DateTime($t['updated_at']))->format('M d, Y g:i A') ?></td>
                        <td><span class="status-pill <?= taskStatusClass($t['status']) ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="card border-0 shadow-sm">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Needs Follow-up</h2>
              <p class="small mb-0">Open tasks untouched for 5+ days.</p>
            </div>
            <div class="card-body">
              <?php if (empty($staleTasks)): ?>
                <div class="empty-state text-center py-4"><i class="fa-regular fa-circle-check fs-4 d-block mb-2"></i><p class="small mb-0">All your open tasks are up to date.</p></div>
              <?php else: ?>
                <?php foreach ($staleTasks as $s): ?>
                  <div class="alert-item">
                    <span class="alert-icon" style="background-color:var(--coral-soft);"><i class="fa-solid fa-clock" style="color:var(--coral-text);"></i></span>
                    <div>
                      <div class="small fw-semibold"><?= htmlspecialchars($s['request_title']) ?></div>
                      <div class="small" style="color:var(--ink-soft);"><?= htmlspecialchars($s['company_name']) ?> &middot; since <?= (new DateTime($s['updated_at']))->format('M d, Y') ?></div>
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

</body>
</html>