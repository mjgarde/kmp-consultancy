<?php
$supervisorFullname = $_SESSION['fullname'] ?? 'Supervisor';
$supervisorEmail    = $_SESSION['email'] ?? '';
$currentPage         = basename($_SERVER['PHP_SELF']);

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
<script>
  try {
    if (localStorage.getItem('sidebarCollapsed') === '1') {
      document.documentElement.classList.add('sidebar-collapsed');
    }
  } catch (e) {}
</script>
<style>
  .dashboard-sidebar {
    width: 264px;
    min-width: 264px;
    max-width: 264px;
    flex-shrink: 0;
  }

  .dashboard-sidebar-brand img {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
  }

  .dashboard-sidebar-brand .brand-link {
    min-width: 0;
  }

  .dashboard-sidebar-brand .sidebar-text {
    white-space: nowrap;
  }

  .sidebar-toggle-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #dee2e6;
    background: #fff;
    color: #495057;
    border-radius: 8px;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 0;
    transition: background-color .15s ease, color .15s ease;
  }

  .sidebar-toggle-btn:hover {
    background: #f1f3f5;
    color: #0d6efd;
  }

  .sidebar-toggle-btn i {
    transition: transform .25s ease;
  }

  @media (min-width: 992px) {
    .dashboard-sidebar {
      position: sticky;
      top: 0;
      z-index: 1030;
      height: 100vh;
      height: 100dvh;
      align-self: flex-start;
      overflow: visible;
      transition: width .25s ease, min-width .25s ease, max-width .25s ease;
    }

    .dashboard-sidebar .dashboard-sidebar-scroll {
      overflow-y: auto;
      overflow-x: hidden;
      min-height: 0;
    }

    .dashboard-sidebar .dashboard-sidebar-brand,
    .dashboard-sidebar .dashboard-sidebar-footer {
      flex-shrink: 0;
    }

    html.sidebar-collapsed .dashboard-sidebar {
      width: 80px;
      min-width: 80px;
      max-width: 80px;
    }

    html.sidebar-collapsed .sidebar-text {
      display: none !important;
    }

    html.sidebar-collapsed .dashboard-sidebar-brand {
      flex-direction: column;
      justify-content: center !important;
      padding: 1rem .5rem !important;
      gap: .75rem !important;
    }

    html.sidebar-collapsed .dashboard-sidebar-brand img {
      width: 40px;
      height: 40px;
    }

    html.sidebar-collapsed .sidebar-toggle-btn i {
      transform: rotate(180deg);
    }

    html.sidebar-collapsed .dashboard-sidebar-scroll {
      padding-left: .75rem !important;
      padding-right: .75rem !important;
    }

    html.sidebar-collapsed .dashboard-nav-label {
      font-size: 0 !important;
      height: 1px;
      padding: 0 !important;
      margin: .5rem .5rem 1rem !important;
      background: #dee2e6;
    }

    html.sidebar-collapsed .dashboard-sidebar-menu {
      margin-bottom: 0 !important;
    }

    html.sidebar-collapsed .dashboard-sidebar-menu .nav-link {
      justify-content: center;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }

    html.sidebar-collapsed .dashboard-sidebar-menu .nav-link i {
      width: auto !important;
      font-size: 1.05rem;
    }

    html.sidebar-collapsed .dashboard-sidebar-footer {
      padding-left: .5rem !important;
      padding-right: .5rem !important;
    }

    html.sidebar-collapsed .dashboard-sidebar-footer .dropdown-toggle {
      justify-content: center;
    }

    html.sidebar-collapsed .dashboard-sidebar-footer .dropdown-toggle::after {
      display: none;
    }

    html.sidebar-collapsed .dashboard-sidebar-footer .dropdown-menu {
      width: auto;
      min-width: 200px;
    }
  }
</style>

<aside class="dashboard-sidebar offcanvas-lg offcanvas-start bg-white border-end d-flex flex-column" tabindex="-1" id="sidebarOffcanvas">

  <div class="dashboard-sidebar-brand d-flex align-items-center justify-content-between gap-2 border-bottom px-3 py-3">
    <a href="../index.php" class="brand-link d-flex align-items-center gap-2 text-decoration-none">
      <img src="../assets/img/system_img/logo.png" alt="KMP ConsultHub">
      <span class="sidebar-text fw-bold fs-6" style="color: #000000;">KMP ConsultHub</span>
    </a>
    <button type="button" id="sidebarToggle" class="sidebar-toggle-btn d-none d-lg-flex" aria-label="Toggle sidebar" title="Collapse / Expand">
      <i class="fa-solid fa-angles-left"></i>
    </button>
    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Close"></button>
  </div>

  <nav class="dashboard-sidebar-scroll flex-grow-1 py-3 px-3">

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em; font-size:.72rem;">
      Main
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="dashboard.php" title="Dashboard" class="nav-link <?= navActive('dashboard.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-table-columns" style="width:18px;"></i> <span class="sidebar-text">Dashboard</span>
        </a>
      </li>
    </ul>

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em; font-size:.72rem;">
      Operations
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="client_management.php" title="Client Management" class="nav-link <?= navActive('client_management.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-users" style="width:18px;"></i> <span class="sidebar-text">Client Management</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="resource_matching.php" title="Resource Matching" class="nav-link <?= navActive('resource_matching.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-diagram-project" style="width:18px;"></i> <span class="sidebar-text">Resource Matching</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="sow_contracts.php" title="SOW &amp; Contracts" class="nav-link <?= navActive('sow_contracts.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-file-signature" style="width:18px;"></i> <span class="sidebar-text">SOW &amp; Contracts</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="knowledge_repository.php" title="Repository" class="nav-link <?= navActive('knowledge_repository.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-book" style="width:18px;"></i> <span class="sidebar-text">Repository</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="cpq_quotations.php" title="CPQ &amp; Quotations" class="nav-link <?= navActive('cpq_quotations.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-file-invoice-dollar" style="width:18px;"></i> <span class="sidebar-text">CPQ &amp; Quotations</span>
        </a>
      </li>
    </ul>

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em; font-size:.72rem;">
      Insights
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column">
      <li class="nav-item mb-1">
        <a href="reports.php" title="Reports" class="nav-link <?= navActive('reports.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-chart-line" style="width:18px;"></i> <span class="sidebar-text">Reports</span>
        </a>
      </li>
    </ul>

  </nav>

  <div class="dashboard-sidebar-footer border-top p-3">
    <div class="dropdown dropup">
      <button type="button" class="btn btn-link p-0 w-100 text-start text-decoration-none dropdown-toggle d-flex align-items-center gap-3 text-dark" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($supervisorFullname) ?>">
        <span class="dashboard-user-icon d-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 flex-shrink-0" style="width:38px; height:38px;">
          <i class="fa-solid fa-user text-primary"></i>
        </span>
        <div class="sidebar-text overflow-hidden flex-grow-1">
          <div class="fw-semibold small text-truncate"><?= htmlspecialchars($supervisorFullname) ?></div>
          <div class="text-secondary text-truncate" style="font-size:.75rem;"><?= htmlspecialchars(maskEmail($supervisorEmail)) ?></div>
        </div>
      </button>
      <ul class="dropdown-menu w-100 shadow-sm">
        <li>
          <a href="../config/logout.php?role=supervisor" class="dropdown-item d-flex align-items-center gap-2 text-danger">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
          </a>
        </li>
      </ul>
    </div>
  </div>

</aside>

<script>
  document.getElementById('sidebarToggle').addEventListener('click', function () {
    var collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
    try {
      localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
    } catch (e) {}
  });
</script>