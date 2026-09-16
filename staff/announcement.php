<?php

session_name('STAFF_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$stmt = $pdo->query(
    "SELECT a.*, u.firstname, u.lastname
     FROM announcements a
     LEFT JOIN users u ON a.created_by = u.user_id
     WHERE a.audience IN ('All', 'Staff')
     ORDER BY a.created_at DESC"
);
$announcements = $stmt->fetchAll();

function audiencePaletteStaff(string $audience): array
{
    return match ($audience) {
        'Staff' => ['bg' => '#E3F3EC', 'fg' => '#0F5F49'],
        default => ['bg' => '#E9ECF6', 'fg' => '#2E3E70'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements | Staff</title>
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
  --ink: #1A2233;
  --ink-soft: #667085;
  --line: #E2E5EB;
  --canvas: #FFFFFF;
  --card: #FFFFFF;
}
body { background-color: var(--canvas); color: var(--ink); font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
.dashboard-layout, .dashboard-main, .dashboard-content { background-color: var(--canvas) !important; }
.dashboard-title, h1, h2, h3 { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--navy-deep); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background-color: #fff; }
.card { border-radius: 12px; border: 1px solid var(--line) !important; box-shadow: none !important; }
.announcement-item { border-bottom: 1px solid var(--line); padding: 1rem 1.15rem; }
.announcement-item:last-child { border-bottom: none; }
.announcement-title { font-family: 'Lexend', sans-serif; font-weight: 700; font-size: .95rem; color: var(--ink); }
.announcement-message { font-size: .85rem; color: var(--ink-soft); white-space: pre-wrap; }
.announcement-meta { font-size: .74rem; color: var(--ink-soft); }
.audience-pill { font-size: .68rem; font-weight: 700; padding: .2rem .6rem; border-radius: 999px; white-space: nowrap; }
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Announcements</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Updates and notices from management.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="card">
        <?php if (empty($announcements)): ?>
          <div class="text-center py-5" style="color:var(--ink-soft);">
            <i class="fa-regular fa-bell fs-2 d-block mb-2"></i>
            <p class="mb-0 small">No announcements yet.</p>
          </div>
        <?php else: ?>
          <?php foreach ($announcements as $a): ?>
            <?php $audColors = audiencePaletteStaff($a['audience']); ?>
            <div class="announcement-item">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <h2 class="announcement-title mb-0"><?= htmlspecialchars($a['title']) ?></h2>
                <span class="audience-pill" style="background-color:<?= $audColors['bg'] ?>; color:<?= $audColors['fg'] ?>;"><?= htmlspecialchars($a['audience']) ?></span>
              </div>
              <p class="announcement-message mb-2"><?= htmlspecialchars($a['message']) ?></p>
              <div class="announcement-meta">
                Posted by <?= htmlspecialchars(trim(($a['firstname'] ?? '') . ' ' . ($a['lastname'] ?? '')) ?: 'Management') ?>
                &middot; <?= date('M d, Y g:i A', strtotime($a['created_at'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>

</body>
</html>