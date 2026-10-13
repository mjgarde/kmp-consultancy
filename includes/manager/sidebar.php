<?php
$managerFullname = $_SESSION['fullname'] ?? 'Manager';
$managerEmail    = $_SESSION['email'] ?? '';
$currentPage   = basename($_SERVER['PHP_SELF']);

const SIDEBAR_STATUS_COLORS = [
    'Draft'  => '#CA8A04',
    'Revert' => '#3B4E8A',
];

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
    return $page === $currentPage ? 'active' : 'text-dark';
}

function sidebarStatusCounts(string $table): array
{
    $counts = array_fill_keys(array_keys(SIDEBAR_STATUS_COLORS), 0);

    if (!in_array($table, ['quotations', 'contracts'], true)) {
        return $counts;
    }

    try {
        $db = $GLOBALS['pdo'] ?? null;
        if (!$db && function_exists('getConnection')) {
            $db = getConnection();
        }
        if ($db) {
            $rows = $db->query("SELECT status, COUNT(*) AS cnt FROM {$table} WHERE status IN ('Draft', 'Revert') GROUP BY status")->fetchAll();
            foreach ($rows as $row) {
                if (isset($counts[$row['status']])) {
                    $counts[$row['status']] = (int) $row['cnt'];
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('sidebar counts: ' . $ex->getMessage());
    }

    return $counts;
}

function sidebarCountsHtml(array $counts): string
{
    $html = '';
    foreach ($counts as $status => $count) {
        if ($count <= 0) {
            continue;
        }
        $color = SIDEBAR_STATUS_COLORS[$status];
        $html .= '<span class="nav-count" style="--c:' . $color . ';" title="' . htmlspecialchars($status) . ': ' . $count . '">' . $count . '</span>';
    }
    return $html === '' ? '' : '<span class="nav-counts sidebar-text">' . $html . '</span>';
}

function sidebarCountsN(array $counts): int
{
    return count(array_filter($counts, fn($c) => $c > 0));
}

function sidebarNotifyDot(array $counts): string
{
    foreach ($counts as $status => $count) {
        if ($count > 0) {
            return '<span class="nav-notify" style="--c:' . SIDEBAR_STATUS_COLORS[$status] . ';"></span>';
        }
    }
    return '';
}

$contractCounts  = sidebarStatusCounts('contracts');
$quotationCounts = sidebarStatusCounts('quotations');
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
    overflow-x: hidden;
  }

  .dashboard-sidebar-brand {
    min-height: 68px;
  }

  .dashboard-sidebar-brand img {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
  }

  .dashboard-sidebar-brand .brand-link {
    min-width: 0;
    flex: 1 1 auto;
    overflow: hidden;
  }

  .dashboard-sidebar-brand .sidebar-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: .95rem !important;
  }

  .dashboard-sidebar-scroll {
    overflow-x: hidden;
    overflow-y: auto;
    min-height: 0;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
  }

  .dashboard-sidebar-scroll::-webkit-scrollbar {
    width: 4px;
    height: 0;
  }

  .dashboard-sidebar-scroll::-webkit-scrollbar-thumb {
    background: #ced4da;
    border-radius: 4px;
  }

  .dashboard-nav-label {
    font-size: .68rem !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .dashboard-sidebar-menu .nav-link {
    position: relative;
    font-size: .88rem;
    font-weight: 500;
    line-height: 1.3;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    border: 0;
  }

  .dashboard-sidebar-menu .nav-link .sidebar-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
  }

  .dashboard-sidebar-menu .nav-link i {
    flex-shrink: 0;
    text-align: center;
  }

  .dashboard-sidebar-menu .nav-link:hover:not(.active) {
    background: #f1f3f5;
  }

  .dashboard-sidebar-menu .nav-link.active {
    background: rgba(13, 110, 253, .1);
    color: #0d6efd;
    font-weight: 500;
  }

  .nav-title {
    max-width: calc(100% - (var(--n, 0) * 22px + 8px));
  }

  .nav-counts {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    gap: .35rem;
    pointer-events: none;
  }

  .nav-count {
    display: inline-flex;
    align-items: center;
    gap: .2rem;
    font-size: .72rem;
    font-weight: 700;
    line-height: 1;
    color: var(--c);
  }

  .nav-count::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--c);
    flex-shrink: 0;
  }

  .nav-notify {
    display: none;
    position: absolute;
    top: 7px;
    right: 12px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background-color: var(--c);
    box-shadow: 0 0 0 2px #fff;
  }

  .dashboard-sidebar-footer .sidebar-text {
    font-size: .85rem;
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

  .sidebar-tip {
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    padding: .45rem .8rem;
    background: #1E293B;
    color: #fff;
    font-size: .82rem;
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: .005em;
    white-space: nowrap;
    border-radius: 8px;
    box-shadow: 0 8px 22px rgba(15, 23, 42, .28);
    pointer-events: none;
    opacity: 0;
    visibility: hidden;
    transform: translate(-6px, -50%);
    transition: opacity .14s ease, transform .14s ease, visibility .14s ease;
  }

  .sidebar-tip::before {
    content: '';
    position: absolute;
    left: -5px;
    top: 50%;
    width: 10px;
    height: 10px;
    background: #1E293B;
    border-radius: 2px;
    transform: translateY(-50%) rotate(45deg);
  }

  .sidebar-tip.is-visible {
    opacity: 1;
    visibility: visible;
    transform: translate(0, -50%);
  }

  @media (max-width: 991.98px) {
    .dashboard-sidebar {
      height: 100vh;
      height: 100dvh;
      width: 250px;
      min-width: 0;
      max-width: 85vw;
    }

    .dashboard-sidebar-brand {
      min-height: 60px;
      padding-top: .6rem !important;
      padding-bottom: .6rem !important;
    }

    .dashboard-sidebar-brand img {
      width: 38px;
      height: 38px;
    }

    .dashboard-sidebar-brand .sidebar-text {
      font-size: .88rem !important;
    }

    .dashboard-sidebar-scroll {
      flex: 1 1 0;
      padding-top: .5rem !important;
      padding-bottom: .5rem !important;
    }

    .dashboard-nav-label {
      font-size: .64rem !important;
      margin-bottom: .25rem !important;
    }

    .dashboard-sidebar-menu {
      margin-bottom: .75rem !important;
    }

    .dashboard-sidebar-menu .nav-item {
      margin-bottom: 2px !important;
    }

    .dashboard-sidebar-menu .nav-link {
      font-size: .82rem;
      padding-top: .5rem !important;
      padding-bottom: .5rem !important;
      gap: .65rem !important;
    }

    .dashboard-sidebar-menu .nav-link i {
      font-size: .9rem;
    }

    .nav-count {
      font-size: .7rem;
    }

    .dashboard-sidebar-footer {
      padding: .6rem .75rem !important;
    }

    .dashboard-sidebar-footer .sidebar-text {
      font-size: .8rem;
    }
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

    html.sidebar-collapsed .nav-notify {
      display: block;
    }

    html.sidebar-collapsed .dashboard-sidebar-brand {
      flex-direction: column;
      justify-content: center !important;
      padding: 1rem .5rem !important;
      gap: .75rem !important;
    }

    html.sidebar-collapsed .dashboard-sidebar-brand .brand-link {
      flex: 0 0 auto;
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

  @media (prefers-reduced-motion: reduce) {
    .sidebar-tip { transition: none; }
  }
</style>

<aside class="dashboard-sidebar offcanvas-lg offcanvas-start bg-white border-end d-flex flex-column" tabindex="-1" id="sidebarOffcanvas">

  <div class="dashboard-sidebar-brand d-flex align-items-center justify-content-between gap-2 border-bottom px-3 py-3">
    <a href="../index.php" class="brand-link d-flex align-items-center gap-2 text-decoration-none" data-tip="KMP ConsultHub" aria-label="KMP ConsultHub">
      <img src="../assets/img/system_img/logo.png" alt="KMP ConsultHub">
      <span class="sidebar-text fw-bold" style="color: #000000;">KMP ConsultHub</span>
    </a>
    <button type="button" id="sidebarToggle" class="sidebar-toggle-btn d-none d-lg-flex" aria-label="Collapse sidebar" data-tip="Collapse sidebar">
      <i class="fa-solid fa-angles-left"></i>
    </button>
    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Close"></button>
  </div>

  <nav class="dashboard-sidebar-scroll flex-grow-1 py-3 px-3">

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em;">
      Main
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="dashboard.php" data-tip="Dashboard" aria-label="Dashboard" class="nav-link <?= navActive('dashboard.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-table-columns" style="width:18px;"></i> <span class="sidebar-text">Dashboard</span>
        </a>
      </li>
    </ul>

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em;">
      Operations
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="client_management.php" data-tip="Client Management" aria-label="Client Management" class="nav-link <?= navActive('client_management.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-users" style="width:18px;"></i> <span class="sidebar-text">Client Management</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="resource_matching.php" data-tip="Resource Matching" aria-label="Resource Matching" class="nav-link <?= navActive('resource_matching.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-diagram-project" style="width:18px;"></i> <span class="sidebar-text">Resource Matching</span>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="sow_contracts.php" data-tip="SOW &amp; Contracts" aria-label="SOW &amp; Contracts" class="nav-link <?= navActive('sow_contracts.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2" style="--n:<?= sidebarCountsN($contractCounts) ?>;">
          <i class="fa-solid fa-file-signature" style="width:18px;"></i>
          <span class="sidebar-text nav-title">SOW &amp; Contracts</span>
          <?= sidebarCountsHtml($contractCounts) ?>
          <?= sidebarNotifyDot($contractCounts) ?>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="cpq_quotations.php" data-tip="CPQ &amp; Scope" aria-label="CPQ &amp; Scope" class="nav-link <?= navActive('cpq_quotations.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2" style="--n:<?= sidebarCountsN($quotationCounts) ?>;">
          <i class="fa-solid fa-file-invoice-dollar" style="width:18px;"></i>
          <span class="sidebar-text nav-title">CPQ &amp; Scope</span>
          <?= sidebarCountsHtml($quotationCounts) ?>
          <?= sidebarNotifyDot($quotationCounts) ?>
        </a>
      </li>
      <li class="nav-item mb-1">
        <a href="calendar_deadlines.php" data-tip="Calendar Deadlines" aria-label="Calendar Deadlines" class="nav-link <?= navActive('calendar_deadlines.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-calendar-days" style="width:18px;"></i> <span class="sidebar-text">Calendar Deadlines</span>
        </a>
      </li>
    </ul>

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em;">
      Documents
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column mb-4">
      <li class="nav-item mb-1">
        <a href="knowledge_repository.php" data-tip="Repository" aria-label="Repository" class="nav-link <?= navActive('knowledge_repository.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-book" style="width:18px;"></i> <span class="sidebar-text">Repository</span>
        </a>
      </li>
    </ul>

    <div class="dashboard-nav-label text-uppercase text-secondary small fw-semibold px-3 mb-2" style="letter-spacing:.06em;">
      Insights
    </div>
    <ul class="dashboard-sidebar-menu nav flex-column">
      <li class="nav-item mb-1">
        <a href="reports.php" data-tip="Reports" aria-label="Reports" class="nav-link <?= navActive('reports.php', $currentPage) ?> d-flex align-items-center gap-3 rounded-3 px-3 py-2">
          <i class="fa-solid fa-chart-line" style="width:18px;"></i> <span class="sidebar-text">Reports</span>
        </a>
      </li>
    </ul>

  </nav>

  <div class="dashboard-sidebar-footer border-top p-3">
    <div class="dropdown dropup">
      <button type="button" class="btn btn-link p-0 w-100 text-start text-decoration-none dropdown-toggle d-flex align-items-center gap-3 text-dark" data-bs-toggle="dropdown" aria-expanded="false" data-tip="<?= htmlspecialchars($managerFullname) ?>" aria-label="<?= htmlspecialchars($managerFullname) ?>">
        <span class="dashboard-user-icon d-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 flex-shrink-0" style="width:36px; height:36px;">
          <i class="fa-solid fa-user text-primary"></i>
        </span>
        <div class="sidebar-text overflow-hidden flex-grow-1">
          <div class="fw-semibold small text-truncate"><?= htmlspecialchars($managerFullname) ?></div>
          <div class="text-secondary text-truncate" style="font-size:.72rem;"><?= htmlspecialchars(maskEmail($managerEmail)) ?></div>
        </div>
      </button>
      <ul class="dropdown-menu w-100 shadow-sm">
        <li>
          <a href="../config/logout.php?role=manager" class="dropdown-item d-flex align-items-center gap-2 text-danger">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
          </a>
        </li>
      </ul>
    </div>
  </div>

</aside>

<div class="sidebar-tip" id="sidebarTip" role="tooltip"></div>

<script>
  (function () {
    var root = document.documentElement;
    var tip = document.getElementById('sidebarTip');
    var toggle = document.getElementById('sidebarToggle');
    var desktop = window.matchMedia('(min-width: 992px)');

    function isCollapsed() {
      return root.classList.contains('sidebar-collapsed') && desktop.matches;
    }

    function hideTip() {
      tip.classList.remove('is-visible');
    }

    function showTip(el) {
      var text = el.getAttribute('data-tip');
      if (!text) return;
      var isToggle = el === toggle;
      if (!isToggle && !isCollapsed()) return;
      if (!desktop.matches) return;
      tip.textContent = text;
      var rect = el.getBoundingClientRect();
      tip.style.left = (rect.right + 12) + 'px';
      tip.style.top = (rect.top + rect.height / 2) + 'px';
      tip.classList.add('is-visible');
    }

    function syncToggleLabel() {
      var label = root.classList.contains('sidebar-collapsed') ? 'Expand sidebar' : 'Collapse sidebar';
      toggle.setAttribute('data-tip', label);
      toggle.setAttribute('aria-label', label);
    }

    document.querySelectorAll('#sidebarOffcanvas [data-tip]').forEach(function (el) {
      el.addEventListener('mouseenter', function () { showTip(el); });
      el.addEventListener('focus', function () { showTip(el); });
      el.addEventListener('mouseleave', hideTip);
      el.addEventListener('blur', hideTip);
      el.addEventListener('click', hideTip);
    });

    document.querySelector('.dashboard-sidebar-scroll').addEventListener('scroll', hideTip);
    window.addEventListener('resize', hideTip);

    syncToggleLabel();

    toggle.addEventListener('click', function () {
      var collapsed = root.classList.toggle('sidebar-collapsed');
      try {
        localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
      } catch (e) {}
      syncToggleLabel();
      hideTip();
      setTimeout(function () {
        if (toggle.matches(':hover')) showTip(toggle);
      }, 280);
    });
  })();
</script>