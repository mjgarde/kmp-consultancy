<?php
require_once __DIR__ . '/../../config/database.php';

$staffFullname = $_SESSION['staff_fullname'] ?? 'Staff';
$staffEmail    = $_SESSION['staff_email'] ?? '';
$currentPage   = basename($_SERVER['PHP_SELF']);

$pdo = getConnection();
$staffId = $_SESSION['staff_id'] ?? 0;

$newCountStmt = $pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE assigned_to = ? AND status = ?');
$newCountStmt->execute([$staffId, 'New']);
$newAssignmentCount = (int) $newCountStmt->fetchColumn();

$progressCountStmt = $pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE assigned_to = ? AND status = ?');
$progressCountStmt->execute([$staffId, 'In Progress']);
$progressAssignmentCount = (int) $progressCountStmt->fetchColumn();

function maskEmail(string $email): string
{
    if ($email === '' || !str_contains($email, '@')) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $localLength = strlen($local);

    if ($localLength <= 2) {
        $maskedLocal = str_repeat('*', $localLength);
    } else {
        $visible     = substr($local, 0, $localLength - 2);
        $maskedLocal = $visible . str_repeat('*', 2);
    }

    return $maskedLocal . '@' . $domain;
}

function navActive(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'active fw-semibold' : 'text-dark';
}

function badgeCount(int $count): string
{
    return $count > 99 ? '99+' : (string) $count;
}
?>

<style>
.sidebar-badge-group {
  gap: 6px;
}

.sidebar-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 5px;
  border-radius: 50%;
  border: 1.4px solid currentColor;
  background: transparent;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1;
  user-select: none;
}

.sidebar-badge-new {
  color: #33495C;
}

.sidebar-badge-progress {
  color: #C6893A;
}
</style>

<aside class="dashboard-sidebar offcanvas-lg offcanvas-start bg-white border-end d-flex flex-column min-vh-100" style="width:264px; min-width:264px; max-width:264px; flex-shrink:0;" tabindex="-1" id="sidebarOffcanvas">

  <div class="dashboard-sidebar-brand d-flex align-items-center justify-content-between gap-2 border-bottom px-3 py-3">
    <div class="d-flex align-items-center gap-2">
      <img src="../assets/img/system_img/logo.png" alt="KMP ConsultHub" width="60" height="60">
      <span class="fw-bold fs-6" style="color: #000000;">KMP ConsultHub</span>
    </div>
    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Close"></button>
  </div>

  <nav class="dashboard-sidebar-scroll flex-grow-1 py-3 px-3">

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em; font-size:.72rem;">
      Main
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="dashboard.php" class="nav-link <?= navActive('dashboard.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-table-columns" style="width:18px;"></i> Dashboard
        </a>
      </li>
    </ul>

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em; font-size:.72rem;">
      Workspace
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="my_assignments.php" class="nav-link <?= navActive('my_assignments.php', $currentPage) ?> d-flex align-items-center flex-nowrap gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-list-check flex-shrink-0" style="width:18px;"></i>
          <span class="flex-grow-1 text-truncate" style="min-width:0;">My Assignments</span>

          <span class="d-flex align-items-center gap-1 flex-shrink-0 sidebar-badge-group">
            <?php if ($newAssignmentCount > 0): ?>
              <span class="sidebar-badge sidebar-badge-new" title="<?= $newAssignmentCount ?> new assignment<?= $newAssignmentCount === 1 ? '' : 's' ?>">
                <?= badgeCount($newAssignmentCount) ?>
              </span>
            <?php endif; ?>
            <?php if ($progressAssignmentCount > 0): ?>
              <span class="sidebar-badge sidebar-badge-progress" title="<?= $progressAssignmentCount ?> in progress">
                <?= badgeCount($progressAssignmentCount) ?>
              </span>
            <?php endif; ?>
          </span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="knowledge_repository.php" class="nav-link <?= navActive('knowledge_repository.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-book" style="width:18px;"></i> Repository
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="schedule_tasks.php" class="nav-link <?= navActive('schedule_tasks.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-calendar-days" style="width:18px;"></i> My Schedule / Tasks
        </a>
      </li>
    </ul>

  </nav>

  <div class="dashboard-sidebar-footer border-top p-3">
    <div class="dropdown">
      <button type="button" class="btn btn-link p-0 w-100 text-start text-decoration-none dropdown-toggle d-flex align-items-center gap-3 text-dark" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="dashboard-user-icon d-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 flex-shrink-0" style="width:38px; height:38px;">
          <i class="fa-solid fa-user text-primary"></i>
        </span>
        <div class="overflow-hidden flex-grow-1">
          <div class="fw-semibold small text-truncate"><?= htmlspecialchars($staffFullname) ?></div>
          <div class="text-secondary text-truncate" style="font-size:.75rem;"><?= htmlspecialchars(maskEmail($staffEmail)) ?></div>
        </div>
      </button>
      <ul class="dropdown-menu dropdown-menu-end w-100 shadow-sm">
        <li>
          <a href="../config/logout.php?role=staff" class="dropdown-item d-flex align-items-center gap-2 text-danger">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
          </a>
        </li>
      </ul>
    </div>
  </div>

</aside>