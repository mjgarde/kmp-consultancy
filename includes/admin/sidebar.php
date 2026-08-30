<?php
$adminFullname = $_SESSION['admin_fullname'] ?? 'Administrator';
$adminEmail    = $_SESSION['admin_email'] ?? '';
$currentPage   = basename($_SERVER['PHP_SELF']);

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
?>
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
      Operations
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="client_management.php" class="nav-link <?= navActive('client_management.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-users" style="width:18px;"></i> Client Management
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="resource_matching.php" class="nav-link <?= navActive('resource_matching.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-diagram-project" style="width:18px;"></i> Resource Matching
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="sow_contracts.php" class="nav-link <?= navActive('sow_contracts.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-file-signature" style="width:18px;"></i> SOW &amp; Contracts
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="knowledge_repository.php" class="nav-link <?= navActive('knowledge_repository.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-book" style="width:18px;"></i> Repository
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="cpq_quotations.php" class="nav-link <?= navActive('cpq_quotations.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-file-invoice-dollar" style="width:18px;"></i> CPQ &amp; Quotations
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="calendar_deadlines.php" class="nav-link <?= navActive('calendar_deadlines.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-calendar-days" style="width:18px;"></i> Calendar Deadlines
        </a>
      </li>
    </ul>

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em; font-size:.72rem;">
      Administration
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column">
      <li class="nav-item mb-1">
        <a href="user_management.php" class="nav-link <?= navActive('user_management.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-user-gear" style="width:18px;"></i> User Management
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="reports_analytics.php" class="nav-link <?= navActive('reports_analytics.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-chart-line" style="width:18px;"></i> Reports &amp; Analytics
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="system_settings.php" class="nav-link <?= navActive('system_settings.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-gear" style="width:18px;"></i> System Settings
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
          <div class="fw-semibold small text-truncate"><?= htmlspecialchars($adminFullname) ?></div>
          <div class="text-secondary text-truncate" style="font-size:.75rem;"><?= htmlspecialchars(maskEmail($adminEmail)) ?></div>
        </div>
      </button>
      <ul class="dropdown-menu dropdown-menu-end w-100 shadow-sm">
        <li>
          <a href="../config/logout.php?role=admin" class="dropdown-item d-flex align-items-center gap-2 text-danger">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
          </a>
        </li>
      </ul>
    </div>
  </div>

</aside>