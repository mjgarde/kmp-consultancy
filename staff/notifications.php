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
  <link rel="stylesheet" href="../assets/css/staff/notifications.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
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