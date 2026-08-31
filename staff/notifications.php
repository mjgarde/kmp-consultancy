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

$notificationsStmt = $pdo->prepare(
    "SELECT sr.request_id, sr.request_title, sr.required_skill, sr.status, sr.updated_at, c.company_name
     FROM service_requests sr
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.assigned_to = :staff_id
     ORDER BY sr.updated_at DESC
     LIMIT 30"
);
$notificationsStmt->execute(['staff_id' => $staffId]);
$notifications = $notificationsStmt->fetchAll();

$today = new DateTime('today');
$yesterday = (clone $today)->modify('-1 day');

function relativeDay(DateTime $date, DateTime $today, DateTime $yesterday): string
{
    $d = (clone $date)->setTime(0, 0, 0);

    if ($d == $today) {
        return 'Today';
    }

    if ($d == $yesterday) {
        return 'Yesterday';
    }

    return $d->format('F j, Y');
}

$grouped = [];
foreach ($notifications as $n) {
    $dt = new DateTime($n['updated_at']);
    $key = relativeDay($dt, $today, $yesterday);
    $grouped[$key][] = $n;
}

$newTodayCount = 0;
foreach ($notifications as $n) {
    $dt = new DateTime($n['updated_at']);
    if ($dt->format('Y-m-d') === $today->format('Y-m-d')) {
        $newTodayCount++;
    }
}

function notifIcon(string $status): array
{
    return match ($status) {
        'New' => ['icon' => 'fa-solid fa-user-plus', 'bg' => 'var(--navy-soft)', 'text' => 'var(--navy)', 'verb' => 'Assigned to you'],
        'In Progress' => ['icon' => 'fa-solid fa-spinner', 'bg' => 'var(--amber-soft)', 'text' => 'var(--amber-text)', 'verb' => 'In progress'],
        'Completed' => ['icon' => 'fa-solid fa-circle-check', 'bg' => 'var(--teal-soft)', 'text' => 'var(--teal-text)', 'verb' => 'Marked completed'],
        'Cancelled' => ['icon' => 'fa-solid fa-circle-xmark', 'bg' => 'var(--coral-soft)', 'text' => 'var(--coral-text)', 'verb' => 'Cancelled'],
        default => ['icon' => 'fa-solid fa-bell', 'bg' => 'var(--navy-soft)', 'text' => 'var(--navy)', 'verb' => 'Updated'],
    };
}

function statusPillClass(string $status): string
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
<title>Notifications</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #1F3245;
  --navy-deep: #16232F;
  --navy-soft: #E9EDF2;
  --teal: #2F8C7E;
  --teal-soft: #E1F1EE;
  --teal-text: #1F6459;
  --amber: #C6871F;
  --amber-soft: #FBEEDB;
  --amber-text: #8A5D14;
  --coral: #C15A44;
  --coral-soft: #F8E6E1;
  --coral-text: #8F3E2C;
  --ink: #1C242E;
  --ink-soft: #667180;
  --line: #E2E6EB;
  --canvas: #F3F5F7;
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

.card {
  border-radius: 12px;
  border: 1px solid var(--line);
  box-shadow: 0 1px 2px rgba(28, 36, 46, 0.04);
}

.card-header {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card) !important;
  border-radius: 12px 12px 0 0 !important;
  padding: 1.1rem 1.3rem;
}
.card-header h2 { color: var(--ink); letter-spacing: -0.01em; font-size: 1rem; }
.card-header p { color: var(--ink-soft) !important; }

.status-pill {
  font-size: .68rem;
  font-weight: 600;
  padding: .28rem .65rem;
  border-radius: 6px;
  white-space: nowrap;
  letter-spacing: .01em;
}
.status-new { background-color: var(--navy-soft); color: var(--navy); }
.status-progress { background-color: var(--amber-soft); color: var(--amber-text); }
.status-approved { background-color: var(--teal-soft); color: var(--teal-text); }
.status-rejected { background-color: var(--coral-soft); color: var(--coral-text); }

.badge-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  padding: 0 .4rem;
  border-radius: 999px;
  background-color: var(--navy);
  color: #fff;
  font-size: .72rem;
  font-weight: 700;
}

.day-divider {
  font-size: .7rem;
  font-weight: 700;
  color: var(--ink-soft);
  text-transform: uppercase;
  letter-spacing: .06em;
  padding: 1.1rem 0 .6rem;
}
.day-divider:first-child { padding-top: 0; }

.notif-item {
  display: flex;
  gap: .9rem;
  padding: .95rem 1rem;
  border-radius: 10px;
  border: 1px solid var(--line);
  margin-bottom: .6rem;
  background-color: var(--card);
  transition: border-color .15s ease;
}
.notif-item:hover { border-color: #C9D1DA; }
.notif-item.unread {
  background-color: #FAFBFC;
  border-left: 3px solid var(--navy);
  padding-left: calc(1rem - 2px);
}
.notif-icon {
  width: 38px;
  height: 38px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: .9rem;
}
.notif-title { font-weight: 600; font-size: .87rem; color: var(--ink); }
.notif-sub { font-size: .76rem; color: var(--ink-soft); margin-top: .1rem; }
.notif-time { font-size: .68rem; color: var(--ink-soft); white-space: nowrap; font-weight: 600; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C9D1DA; }
</style>
</head>
<body class="bg-light">

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center px-3 px-md-4">
      <button type="button" class="btn btn-link text-dark p-0 d-lg-none me-3" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
        <i class="fa-solid fa-bars fs-5"></i>
      </button>
      <div>
        <h1 class="dashboard-title h6 h5-md fw-bold mb-0 d-flex align-items-center gap-2">
          Notifications
          <?php if ($newTodayCount > 0): ?>
            <span class="badge-count"><?= $newTodayCount ?></span>
          <?php endif; ?>
        </h1>
        <p class="dashboard-subtitle small mb-0 d-none d-sm-block"><?= $newTodayCount ?> new today</p>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="card border-0">
        <div class="card-header">
          <h2 class="fw-bold mb-0">Recent Activity</h2>
          <p class="small mb-0">Service requests assigned to you, most recent first.</p>
        </div>
        <div class="card-body p-3 p-md-4">
          <?php if (empty($grouped)): ?>
            <div class="empty-state text-center py-5">
              <i class="fa-regular fa-bell fs-3 d-block mb-2"></i>
              <p class="small mb-0">No notifications yet. New assignments will show up here.</p>
            </div>
          <?php else: ?>
            <?php foreach ($grouped as $dayLabel => $items): ?>
              <div class="day-divider"><?= htmlspecialchars($dayLabel) ?></div>
              <?php foreach ($items as $n): ?>
                <?php
                  $meta = notifIcon($n['status']);
                  $dt = new DateTime($n['updated_at']);
                  $isUnread = $dt->format('Y-m-d') === $today->format('Y-m-d');
                ?>
                <div class="notif-item <?= $isUnread ? 'unread' : '' ?>">
                  <span class="notif-icon" style="background-color:<?= $meta['bg'] ?>;">
                    <i class="<?= $meta['icon'] ?>" style="color:<?= $meta['text'] ?>;"></i>
                  </span>
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                      <div class="notif-title"><?= htmlspecialchars($meta['verb']) ?>: <?= htmlspecialchars($n['request_title']) ?></div>
                      <span class="notif-time"><?= $dt->format('g:i A') ?></span>
                    </div>
                    <div class="notif-sub">
                      <?= htmlspecialchars($n['company_name']) ?>
                      <?php if (!empty($n['required_skill'])): ?>
                        &middot; <?= htmlspecialchars($n['required_skill']) ?>
                      <?php endif; ?>
                    </div>
                    <span class="status-pill mt-2 d-inline-block <?= statusPillClass($n['status']) ?>"><?= htmlspecialchars($n['status']) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>

</body>
</html>