<?php

session_name('STAFF_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$pdo = getConnection();
$staffId = (int) $_SESSION['user_id'];

$statusCountsStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM service_requests WHERE assigned_to = :staff_id GROUP BY status"
);
$statusCountsStmt->execute(['staff_id' => $staffId]);
$statusCounts = ['New' => 0, 'In Progress' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach ($statusCountsStmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['cnt'];
}
$totalAssigned = array_sum($statusCounts);

$staleStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM service_requests
     WHERE assigned_to = :staff_id AND status IN ('New','In Progress') AND updated_at < DATE_SUB(NOW(), INTERVAL 5 DAY)"
);
$staleStmt->execute(['staff_id' => $staffId]);
$staleCount = (int) $staleStmt->fetchColumn();

$awaitingStmt = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM quotations WHERE prepared_by = :q_staff AND status = 'Draft')
      + (SELECT COUNT(*) FROM contracts WHERE prepared_by = :c_staff AND status = 'Draft')"
);
$awaitingStmt->execute(['q_staff' => $staffId, 'c_staff' => $staffId]);
$awaitingCount = (int) $awaitingStmt->fetchColumn();

$revertStmt = $pdo->prepare(
    "SELECT 'Quotation' AS doc_type, q.quotation_number AS doc_number, c.company_name, q.updated_at, NULL AS note
     FROM quotations q
     INNER JOIN clients c ON c.client_id = q.client_id
     WHERE q.prepared_by = :q_staff AND q.status = 'Revert'
     UNION ALL
     SELECT 'Contract' AS doc_type, ct.contract_number AS doc_number, c.company_name, ct.updated_at,
            (SELECT cr.revision_note FROM contract_revisions cr
             WHERE cr.contract_id = ct.contract_id
             ORDER BY cr.created_at DESC, cr.revision_id DESC LIMIT 1) AS note
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     WHERE ct.prepared_by = :c_staff AND ct.status = 'Revert'
     ORDER BY updated_at DESC"
);
$revertStmt->execute(['q_staff' => $staffId, 'c_staff' => $staffId]);
$revertItems = $revertStmt->fetchAll();
$revertCount = count($revertItems);
$revertShown = array_slice($revertItems, 0, 5);

$deadlineStmt = $pdo->prepare(
    "SELECT 'Contract' AS doc_type, ct.contract_number AS doc_number, c.company_name, ct.end_date AS due_date, 'Ends' AS due_label
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     WHERE ct.prepared_by = :c_staff AND ct.status = 'Approved'
       AND ct.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     UNION ALL
     SELECT 'Quotation' AS doc_type, q.quotation_number AS doc_number, c.company_name, q.valid_until AS due_date, 'Expires' AS due_label
     FROM quotations q
     INNER JOIN clients c ON c.client_id = q.client_id
     WHERE q.prepared_by = :q_staff AND q.status = 'Draft'
       AND q.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY due_date ASC
     LIMIT 5"
);
$deadlineStmt->execute(['c_staff' => $staffId, 'q_staff' => $staffId]);
$deadlines = $deadlineStmt->fetchAll();

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

function dueText(string $date): string
{
    $days = (int) (new DateTime('today'))->diff(new DateTime($date))->format('%r%a');
    if ($days <= 0) {
        return 'Today';
    }
    if ($days === 1) {
        return 'Tomorrow';
    }
    return 'In ' . $days . ' days';
}

function shortNote(?string $note): string
{
    $note = trim((string) $note);
    if ($note === '') {
        return '';
    }
    return mb_strimwidth($note, 0, 90, '...');
}

$displayName = $_SESSION['fullname'] ?? $_SESSION['staff_fullname'] ?? 'Staff';

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
<link rel="stylesheet" href="../assets/css/staff/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
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
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Welcome back, <?= htmlspecialchars($displayName) ?></p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="summary-strip">
        <div class="summary-cell is-progress">
          <div class="n"><?= $statusCounts['In Progress'] ?></div>
          <div class="l">In progress</div>
        </div>
        <div class="summary-cell">
          <div class="n"><?= $awaitingCount ?></div>
          <div class="l">Awaiting approval</div>
        </div>
        <div class="summary-cell is-urgent">
          <div class="n"><?= $revertCount ?></div>
          <div class="l">Needs revision</div>
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
                <div class="legend-row">
                  <span class="legend-label">Total</span>
                  <span class="legend-value"><?= $totalAssigned ?></span>
                </div>
              </div>
            </div>
          </section>

          <section class="panel">
            <div class="panel-head">
              <h2>Needs revision</h2>
              <p>Quotations and contracts sent back to you.</p>
            </div>
            <div class="panel-body">
              <?php if (empty($revertShown)): ?>
                <div class="empty-state"><i class="fa-regular fa-circle-check"></i>Nothing to revise.</div>
              <?php else: ?>
                <?php foreach ($revertShown as $r): ?>
                  <div class="activity-item">
                    <div>
                      <div class="activity-flag">
                        <span class="activity-dot" style="background-color:var(--signal-urgent);"></span>
                        <?= htmlspecialchars($r['doc_type']) ?> <?= htmlspecialchars($r['doc_number']) ?>
                      </div>
                      <div class="activity-sub"><?= htmlspecialchars($r['company_name']) ?></div>
                      <?php if (shortNote($r['note']) !== ''): ?>
                        <div class="activity-sub"><?= htmlspecialchars(shortNote($r['note'])) ?></div>
                      <?php endif; ?>
                    </div>
                    <span class="activity-time"><?= (new DateTime($r['updated_at']))->format('M d, g:i A') ?></span>
                  </div>
                <?php endforeach; ?>
                <?php if ($revertCount > count($revertShown)): ?>
                  <div class="activity-sub pt-2">+<?= $revertCount - count($revertShown) ?> more</div>
                <?php endif; ?>
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
              <h2>Due this week</h2>
              <p>Contracts ending and quotations expiring in 7 days.</p>
            </div>
            <div class="panel-body">
              <?php if (empty($deadlines)): ?>
                <div class="empty-state"><i class="fa-regular fa-calendar"></i>No deadlines this week.</div>
              <?php else: ?>
                <?php foreach ($deadlines as $d): ?>
                  <div class="activity-item">
                    <div>
                      <div class="activity-flag">
                        <span class="activity-dot" style="background-color:var(--signal-progress);"></span>
                        <?= htmlspecialchars($d['doc_type']) ?> <?= htmlspecialchars($d['doc_number']) ?>
                      </div>
                      <div class="activity-sub"><?= htmlspecialchars($d['company_name']) ?></div>
                    </div>
                    <span class="activity-time"><?= htmlspecialchars($d['due_label']) ?> <?= htmlspecialchars(dueText($d['due_date'])) ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
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
Chart.defaults.font = { family: 'Inter', size: 11 };
Chart.defaults.color = '#5C6773';

new Chart(document.getElementById('statusDonut'), {
  type: 'doughnut',
  data: {
    labels: ['New', 'In Progress', 'Completed', 'Cancelled'],
    datasets: [{
      data: [<?= $statusCounts['New'] ?>, <?= $statusCounts['In Progress'] ?>, <?= $statusCounts['Completed'] ?>, <?= $statusCounts['Cancelled'] ?>],
      backgroundColor: ['#1F3D37', '#B8873A', '#3E7D63', '#B14A3A'],
      borderWidth: 0
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
      maxBarThickness: 42
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