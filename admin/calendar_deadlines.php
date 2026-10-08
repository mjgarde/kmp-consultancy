<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$URL = [
    'requests'   => 'client_management.php?tab=requests',
    'quotations' => 'cpq_quotations.php',
    'contracts'  => 'sow_contracts.php',
];

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function rows(PDO $pdo, string $sql, array $p = []): array
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function scalar(PDO $pdo, string $sql, array $p = [])
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchColumn();
}

function dueInfo(int $diff): array
{
    if ($diff < 0) {
        return ['Overdue ' . abs($diff) . 'd', 'pl-terra', 'db-terra'];
    }
    if ($diff === 0) {
        return ['Due today', 'pl-terra', 'db-terra'];
    }
    if ($diff === 1) {
        return ['Tomorrow', 'pl-ochre', 'db-ochre'];
    }
    return ['In ' . $diff . ' days', 'pl-blue', $diff <= 3 ? 'db-ochre' : 'db-blue'];
}

function statusPill(string $s): string
{
    $map = [
        'New'         => 'pl-blue',
        'In Progress' => 'pl-ochre',
        'Completed'   => 'pl-sage',
        'Cancelled'   => 'pl-slate',
        'Draft'       => 'pl-slate',
        'Revert'      => 'pl-plum',
    ];
    return '<span class="pill ' . ($map[$s] ?? 'pl-slate') . '">' . h($s) . '</span>';
}

$typeMeta = [
    'expiry' => ['Quotation expiry', 'tn-ochre', 'fa-hourglass-half', '#A06B1F'],
    'start'  => ['Contract start', 'tn-blue', 'fa-play', '#3F6C8F'],
    'end'    => ['Contract end', 'tn-plum', 'fa-flag-checkered', '#7B4F7D'],
];

$today = new DateTime('today');
$todayStr = $today->format('Y-m-d');

$parsed = DateTime::createFromFormat('!Y-m', (string) ($_GET['month'] ?? ''));
$errs = DateTime::getLastErrors();
$validMonth = $parsed && (!$errs || ($errs['warning_count'] === 0 && $errs['error_count'] === 0));
$monthStart = $validMonth ? $parsed : (clone $today)->modify('first day of this month');

$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');
$isCurrentMonth = $monthStart->format('Y-m') === $today->format('Y-m');

$daysInMonth = (int) $monthStart->format('t');
$lead = (int) $monthStart->format('w');
$cells = (int) (ceil(($lead + $daysInMonth) / 7) * 7);
$gridStart = (clone $monthStart)->modify("-{$lead} days");
$gridEnd = (clone $gridStart)->modify('+' . ($cells - 1) . ' days');
$gs = $gridStart->format('Y-m-d');
$ge = $gridEnd->format('Y-m-d');

$monthRows = rows(
    $pdo,
    "SELECT 'expiry' AS type, q.quotation_number AS ref, q.valid_until AS d, c.company_name, sr.request_title AS title, q.status AS st, '' AS staff
     FROM quotations q
     INNER JOIN clients c ON c.client_id = q.client_id
     INNER JOIN service_requests sr ON sr.request_id = q.request_id
     WHERE q.status IN ('Draft','Revert') AND q.valid_until BETWEEN ? AND ?
     UNION ALL
     SELECT 'start', ct.contract_number, ct.start_date, c.company_name, sr.request_title, sr.status, CONCAT_WS(' ', u.firstname, u.lastname)
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     INNER JOIN service_requests sr ON sr.request_id = ct.request_id
     LEFT JOIN users u ON u.user_id = sr.assigned_to
     WHERE ct.status = 'Approved' AND ct.start_date BETWEEN ? AND ?
     UNION ALL
     SELECT 'end', ct.contract_number, ct.end_date, c.company_name, sr.request_title, sr.status, CONCAT_WS(' ', u.firstname, u.lastname)
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     INNER JOIN service_requests sr ON sr.request_id = ct.request_id
     LEFT JOIN users u ON u.user_id = sr.assigned_to
     WHERE ct.status = 'Approved' AND ct.end_date BETWEEN ? AND ?
     ORDER BY d ASC",
    [$gs, $ge, $gs, $ge, $gs, $ge]
);

$eventsByDate = [];
foreach ($monthRows as $r) {
    $done = ($r['type'] === 'start' && $r['st'] !== 'New')
        || ($r['type'] === 'end' && in_array($r['st'], ['Completed', 'Cancelled'], true));
    $eventsByDate[$r['d']][] = [
        'type'    => $r['type'],
        'ref'     => $r['ref'],
        'company' => $r['company_name'],
        'title'   => $r['title'],
        'status'  => $r['st'],
        'staff'   => trim((string) $r['staff']),
        'done'    => $done,
    ];
}

$openRows = rows(
    $pdo,
    "SELECT 'expiry' AS type, q.quotation_number AS ref, q.valid_until AS d, c.company_name
     FROM quotations q
     INNER JOIN clients c ON c.client_id = q.client_id
     WHERE q.status IN ('Draft','Revert') AND q.valid_until IS NOT NULL
     UNION ALL
     SELECT 'end', ct.contract_number, ct.end_date, c.company_name
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     INNER JOIN service_requests sr ON sr.request_id = ct.request_id
     WHERE ct.status = 'Approved' AND ct.end_date IS NOT NULL AND sr.status IN ('New','In Progress')
     UNION ALL
     SELECT 'start', ct.contract_number, ct.start_date, c.company_name
     FROM contracts ct
     INNER JOIN clients c ON c.client_id = ct.client_id
     INNER JOIN service_requests sr ON sr.request_id = ct.request_id
     WHERE ct.status = 'Approved' AND ct.start_date IS NOT NULL AND sr.status = 'New'
     ORDER BY d ASC"
);

$dueToday = 0;
$next7 = 0;
$overdue = 0;
$expiring7 = 0;
$upcoming = [];
foreach ($openRows as $r) {
    $diff = (int) $today->diff(new DateTime($r['d']))->format('%r%a');
    if ($diff < 0) {
        $overdue++;
    } elseif ($diff === 0) {
        $dueToday++;
    } elseif ($diff <= 7) {
        $next7++;
    }
    if ($r['type'] === 'expiry' && $diff >= 0 && $diff <= 7) {
        $expiring7++;
    }
    if (count($upcoming) < 6) {
        $upcoming[] = $r + ['diff' => $diff];
    }
}

$completedThisMonth = (int) scalar(
    $pdo,
    "SELECT COUNT(*) FROM service_requests WHERE status = 'Completed' AND updated_at BETWEEN ? AND ?",
    [
        (new DateTime('first day of this month'))->format('Y-m-d 00:00:00'),
        (new DateTime('last day of this month'))->format('Y-m-d 23:59:59'),
    ]
);

$awaitingAssignment = (int) scalar(
    $pdo,
    "SELECT COUNT(*) FROM service_requests sr
     INNER JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'"
);

$revertCount = (int) scalar(
    $pdo,
    "SELECT (SELECT COUNT(*) FROM quotations WHERE status='Revert') + (SELECT COUNT(*) FROM contracts WHERE status='Revert')"
);

$actions = [];
if ($overdue > 0) {
    $actions[] = [
        'tn-terra',
        'fa-triangle-exclamation',
        $overdue . ' overdue deadline' . ($overdue === 1 ? '' : 's'),
        'Contracts or quotations past their date.',
        $URL['contracts'],
    ];
}
if ($awaitingAssignment > 0) {
    $actions[] = [
        'tn-ochre',
        'fa-user-clock',
        $awaitingAssignment . ' awaiting assignment',
        'Approved contracts with no staff yet.',
        $URL['requests'],
    ];
}
if ($expiring7 > 0) {
    $actions[] = [
        'tn-blue',
        'fa-file-invoice-dollar',
        $expiring7 . ' quotation' . ($expiring7 === 1 ? '' : 's') . ' expiring in 7 days',
        'Follow up with the client.',
        $URL['quotations'],
    ];
}
if ($revertCount > 0) {
    $actions[] = [
        'tn-plum',
        'fa-rotate-left',
        $revertCount . ' sent back for revision',
        'Quotations or contracts to revise.',
        $URL['quotations'],
    ];
}

$assignments = rows(
    $pdo,
    "SELECT u.firstname, u.lastname, sr.request_title, sr.status, c.company_name, ct.end_date
     FROM service_requests sr
     INNER JOIN users u ON u.user_id = sr.assigned_to
     INNER JOIN clients c ON c.client_id = sr.client_id
     LEFT JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     WHERE sr.status IN ('New','In Progress')
     ORDER BY ct.end_date IS NULL, ct.end_date ASC
     LIMIT 6"
);

$defaultSel = $isCurrentMonth ? $todayStr : $monthStart->format('Y-m-d');
$monthLabel = $monthStart->format('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Calendar &amp; Schedule</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --canvas: #FAF9F6;
  --card: #FFFEFC;
  --surface: #F6F4EF;
  --line: #E6E2DA;
  --ink: #2A2D2F;
  --ink-soft: #6E7275;
  --charcoal: #2B3134;
  --teal: #2F6F6A;
  --teal-soft: #E3EFEC;
  --teal-text: #245853;
  --blue: #3F6C8F;
  --blue-soft: #E2ECF3;
  --blue-text: #2E5272;
  --ochre: #A06B1F;
  --ochre-soft: #F6EBD6;
  --ochre-text: #7F5719;
  --terra: #B5523F;
  --terra-soft: #F8E9E5;
  --terra-text: #8C3D2E;
  --sage: #3E7D5A;
  --sage-soft: #E6F1EA;
  --sage-text: #2C5E42;
  --plum: #7B4F7D;
  --plum-soft: #F0E6F1;
  --plum-text: #5E3A60;
  --slate-soft: #EFEDE8;
  --slate-text: #55595C;
}

* {
  -webkit-tap-highlight-color: transparent;
}

body {
  background: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, sans-serif;
  overflow-x: hidden;
}

.dashboard-layout,
.dashboard-main,
.dashboard-content {
  background: var(--canvas) !important;
}

.dashboard-main {
  min-width: 0;
  max-width: 100%;
}

.dashboard-title,
h1,
h2,
h3,
.num {
  font-family: 'Lexend', 'Inter', sans-serif;
}

.dashboard-title {
  color: var(--charcoal);
  letter-spacing: -.01em;
}

.dashboard-subtitle {
  color: var(--ink-soft) !important;
}

.dashboard-topbar {
  border-bottom: 1px solid var(--line) !important;
  background: var(--card);
}

.card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
  height: 100%;
}

.card-header {
  background: var(--card) !important;
  border-bottom: 1px solid var(--line) !important;
  border-radius: 12px 12px 0 0 !important;
  padding: 1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: .75rem;
  flex-wrap: wrap;
}

.card-header h2 {
  color: var(--charcoal);
  letter-spacing: -.01em;
  font-size: .95rem;
  font-weight: 700;
  margin: 0;
}

.card-header p {
  color: var(--ink-soft);
  font-size: .76rem;
  margin: .15rem 0 0;
}

.card-body {
  padding: 1rem 1.25rem;
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
  background: transparent;
  color: var(--charcoal);
  border: 1px solid var(--line);
  border-radius: 8px;
  font-size: .82rem;
  font-weight: 700;
  padding: .42rem .85rem;
  text-decoration: none;
  transition: background .15s;
}

.btn-ghost:hover {
  background: var(--surface);
  color: var(--charcoal);
}

.btn-ghost.icon {
  width: 34px;
  padding: .42rem 0;
}

.kpi {
  border-radius: 12px;
  padding: 1.05rem 1.2rem;
  color: #fff;
  height: 100%;
}

.kpi-ochre {
  background: #A06B1F;
}

.kpi-blue {
  background: #3F6C8F;
}

.kpi-terra {
  background: #B5523F;
}

.kpi-teal {
  background: #2F6F6A;
}

.kpi-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-label {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  opacity: .92;
}

.kpi-icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  background: rgba(255, 255, 255, .2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .85rem;
}

.kpi-value {
  font-size: 1.7rem;
  font-weight: 700;
  line-height: 1.1;
  margin-top: .8rem;
}

.kpi-sub {
  font-size: .72rem;
  opacity: .92;
  margin-top: .3rem;
}

.tn-ochre {
  background: var(--ochre-soft);
  color: var(--ochre-text);
}

.tn-blue {
  background: var(--blue-soft);
  color: var(--blue-text);
}

.tn-plum {
  background: var(--plum-soft);
  color: var(--plum-text);
}

.tn-terra {
  background: var(--terra-soft);
  color: var(--terra-text);
}

.tn-teal {
  background: var(--teal-soft);
  color: var(--teal-text);
}

.pill {
  display: inline-block;
  font-size: .68rem;
  font-weight: 700;
  padding: .25rem .65rem;
  border-radius: 999px;
  white-space: nowrap;
}

.pl-blue {
  background: var(--blue-soft);
  color: var(--blue-text);
}

.pl-ochre {
  background: var(--ochre-soft);
  color: var(--ochre-text);
}

.pl-sage {
  background: var(--sage-soft);
  color: var(--sage-text);
}

.pl-slate {
  background: var(--slate-soft);
  color: var(--slate-text);
}

.pl-terra {
  background: var(--terra-soft);
  color: var(--terra-text);
}

.pl-plum {
  background: var(--plum-soft);
  color: var(--plum-text);
}

.month-nav {
  display: flex;
  align-items: center;
  gap: .4rem;
}

.month-title {
  font-family: 'Lexend', sans-serif;
  font-weight: 700;
  font-size: 1rem;
  color: var(--charcoal);
  min-width: 140px;
}

.legend {
  display: flex;
  flex-wrap: wrap;
  gap: .3rem 1rem;
  padding: .7rem 1.25rem;
  border-bottom: 1px solid var(--line);
}

.legend span {
  font-size: .72rem;
  color: var(--ink-soft);
  display: inline-flex;
  align-items: center;
  gap: .4rem;
}

.legend i {
  width: 9px;
  height: 9px;
  border-radius: 3px;
  display: inline-block;
}

.cal-wrap {
  overflow: hidden;
  border-radius: 0 0 12px 12px;
}

.cal-head,
.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
}

.cal-head div {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--ink-soft);
  text-align: center;
  padding: .55rem 0;
  background: var(--surface);
}

.cal-head div:first-child {
  color: var(--terra-text);
}

.cal-head div:last-child {
  color: var(--blue-text);
}

.cal-cell {
  min-height: 106px;
  background: var(--card);
  border: 0;
  border-top: 1px solid var(--line);
  border-right: 1px solid var(--line);
  padding: .4rem;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 3px;
  cursor: pointer;
  transition: background .15s;
  min-width: 0;
  font-family: inherit;
}

.cal-cell:nth-child(7n) {
  border-right: 0;
}

.cal-cell:hover {
  background: var(--surface);
}

.cal-cell.out {
  background: var(--surface);
}

.cal-cell.out .cal-num {
  color: #A9ADB0;
}

.cal-cell.sel {
  background: var(--teal-soft);
}

.cal-num {
  font-size: .78rem;
  font-weight: 600;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  color: var(--ink);
}

.cal-cell:nth-child(7n+1) .cal-num {
  color: var(--terra);
}

.cal-cell:nth-child(7n) .cal-num {
  color: var(--blue);
}

.cal-cell.out:nth-child(7n+1) .cal-num,
.cal-cell.out:nth-child(7n) .cal-num {
  opacity: .5;
}

.cal-cell.today .cal-num {
  background: var(--teal);
  color: #fff !important;
  opacity: 1;
}

.chip {
  display: block;
  font-size: .66rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chip.done {
  opacity: .5;
  text-decoration: line-through;
}

.more {
  font-size: .64rem;
  color: var(--ink-soft);
  font-weight: 600;
}

.dots {
  display: none;
  gap: 3px;
  flex-wrap: wrap;
}

.dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.row-item {
  display: flex;
  align-items: center;
  gap: .8rem;
  padding: .65rem 0;
}

.row-item + .row-item {
  border-top: 1px solid var(--line);
}

.row-item:first-child {
  padding-top: 0;
}

.row-item:last-child {
  padding-bottom: 0;
}

.row-item .ic {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: .85rem;
}

.row-main {
  flex: 1;
  min-width: 0;
}

.row-title {
  font-size: .82rem;
  font-weight: 600;
  color: var(--charcoal);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.row-sub {
  font-size: .72rem;
  color: var(--ink-soft);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.date-box {
  width: 44px;
  border: 1px solid var(--line);
  border-radius: 8px;
  text-align: center;
  flex-shrink: 0;
  overflow: hidden;
  background: var(--card);
}

.date-box .mon {
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #fff;
  display: block;
  line-height: 1.6;
}

.date-box .day {
  font-size: 1rem;
  font-weight: 700;
  font-family: 'Lexend', sans-serif;
  color: var(--charcoal);
  display: block;
  line-height: 1.5;
}

.db-terra .mon {
  background: var(--terra);
}

.db-ochre .mon {
  background: var(--ochre);
}

.db-blue .mon {
  background: var(--blue);
}

.action-row {
  text-decoration: none;
  color: var(--ink);
  border-radius: 10px;
}

.action-row:hover {
  color: var(--ink);
}

.empty {
  text-align: center;
  padding: 1.6rem 0;
  color: var(--ink-soft);
  font-size: .8rem;
}

.empty i {
  font-size: 1.5rem;
  display: block;
  margin-bottom: .5rem;
  color: #CFCAC0;
}

@media (max-width: 767.98px) {
  .dashboard-content {
    padding: .65rem !important;
  }

  .card-header {
    padding: .8rem .9rem;
  }

  .card-body {
    padding: .85rem .9rem;
  }

  .cal-cell {
    min-height: 58px;
    align-items: center;
    padding: .3rem .1rem;
  }

  .chips {
    display: none;
  }

  .dots {
    display: flex;
    justify-content: center;
  }

  .legend {
    padding: .6rem .9rem;
  }

  .month-title {
    min-width: 0;
    font-size: .92rem;
  }

  .kpi {
    padding: .85rem .95rem;
  }

  .kpi-value {
    font-size: 1.35rem;
  }
}
</style>
</head>

<body>
<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1">

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3" style="min-width:0;">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div style="min-width:0;">
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Calendar &amp; Schedule</h1>
          <p class="dashboard-subtitle small mb-0 text-truncate d-none d-sm-block">Quotation expiries, contract start and end dates, and pending actions.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-xl-3">
          <div class="kpi kpi-ochre">
            <div class="kpi-top">
              <span class="kpi-label">Due Today</span>
              <span class="kpi-icon"><i class="fa-solid fa-clock"></i></span>
            </div>
            <div class="kpi-value num"><?= $dueToday ?></div>
            <div class="kpi-sub">Quotations &amp; contracts</div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="kpi kpi-blue">
            <div class="kpi-top">
              <span class="kpi-label">Next 7 Days</span>
              <span class="kpi-icon"><i class="fa-solid fa-calendar-week"></i></span>
            </div>
            <div class="kpi-value num"><?= $next7 ?></div>
            <div class="kpi-sub">Upcoming deadlines</div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="kpi kpi-terra">
            <div class="kpi-top">
              <span class="kpi-label">Overdue</span>
              <span class="kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            </div>
            <div class="kpi-value num"><?= $overdue ?></div>
            <div class="kpi-sub">Needs attention</div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="kpi kpi-teal">
            <div class="kpi-top">
              <span class="kpi-label">Completed</span>
              <span class="kpi-icon"><i class="fa-solid fa-circle-check"></i></span>
            </div>
            <div class="kpi-value num"><?= $completedThisMonth ?></div>
            <div class="kpi-sub">Service requests this month</div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-3">

        <div class="col-xl-8">
          <section class="card">
            <div class="card-header">
              <div class="month-nav">
                <a href="?month=<?= h($prevMonth) ?>" class="btn-ghost icon" aria-label="Previous month">
                  <i class="fa-solid fa-chevron-left"></i>
                </a>
                <span class="month-title text-center"><?= h($monthLabel) ?></span>
                <a href="?month=<?= h($nextMonth) ?>" class="btn-ghost icon" aria-label="Next month">
                  <i class="fa-solid fa-chevron-right"></i>
                </a>
              </div>
              <a href="?" class="btn-ghost">Today</a>
            </div>
            <div class="legend">
              <?php foreach ($typeMeta as $m): ?>
                <span><i style="background:<?= $m[3] ?>;"></i><?= h($m[0]) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="cal-wrap">
              <div class="cal-head">
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dn): ?>
                  <div><?= $dn ?></div>
                <?php endforeach; ?>
              </div>
              <div class="cal-grid">
                <?php
                $cur = clone $gridStart;
                for ($i = 0; $i < $cells; $i++):
                    $ds = $cur->format('Y-m-d');
                    $ev = $eventsByDate[$ds] ?? [];
                    $cls = 'cal-cell';
                    if ($cur->format('Y-m') !== $monthStart->format('Y-m')) {
                        $cls .= ' out';
                    }
                    if ($ds === $todayStr) {
                        $cls .= ' today';
                    }
                    if ($ds === $defaultSel) {
                        $cls .= ' sel';
                    }
                ?>
                  <button type="button" class="<?= $cls ?>" data-date="<?= $ds ?>">
                    <span class="cal-num"><?= $cur->format('j') ?></span>
                    <?php if ($ev): ?>
                      <span class="chips">
                        <?php foreach (array_slice($ev, 0, 2) as $e): ?>
                          <span class="chip <?= $typeMeta[$e['type']][1] ?> <?= $e['done'] ? 'done' : '' ?>"><?= h($e['ref']) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($ev) > 2): ?>
                          <span class="more">+<?= count($ev) - 2 ?> more</span>
                        <?php endif; ?>
                      </span>
                      <span class="dots">
                        <?php foreach (array_slice($ev, 0, 4) as $e): ?>
                          <span class="dot" style="background:<?= $typeMeta[$e['type']][3] ?>;"></span>
                        <?php endforeach; ?>
                      </span>
                    <?php endif; ?>
                  </button>
                <?php
                    $cur->modify('+1 day');
                endfor;
                ?>
              </div>
            </div>
          </section>
        </div>

        <div class="col-xl-4">
          <div class="d-flex flex-column gap-3 h-100">

            <section class="card" style="height:auto;">
              <div class="card-header">
                <div>
                  <h2 id="dayTitle">Selected Day</h2>
                  <p id="daySub" class="mb-0"></p>
                </div>
              </div>
              <div class="card-body" id="dayBody"></div>
            </section>

            <section class="card" style="height:auto;">
              <div class="card-header">
                <div>
                  <h2>Upcoming Deadlines</h2>
                  <p>Open items, overdue first.</p>
                </div>
              </div>
              <div class="card-body">
                <?php if (!$upcoming): ?>
                  <div class="empty"><i class="fa-regular fa-calendar-check"></i>No open deadlines.</div>
                <?php else: ?>
                  <?php foreach ($upcoming as $u):
                      $info = dueInfo($u['diff']);
                      $dt = new DateTime($u['d']);
                      $meta = $typeMeta[$u['type']];
                  ?>
                    <div class="row-item">
                      <div class="date-box <?= $info[2] ?>">
                        <span class="mon"><?= $dt->format('M') ?></span>
                        <span class="day"><?= $dt->format('d') ?></span>
                      </div>
                      <div class="row-main">
                        <div class="row-title"><?= h($u['ref']) ?> &middot; <?= h($meta[0]) ?></div>
                        <div class="row-sub"><?= h($u['company_name']) ?></div>
                      </div>
                      <span class="pill <?= $info[1] ?>"><?= h($info[0]) ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </section>

          </div>
        </div>

      </div>

      <div class="row g-3">

        <div class="col-lg-6">
          <section class="card">
            <div class="card-header">
              <div>
                <h2>Needs Action</h2>
                <p>Things that are waiting on you.</p>
              </div>
            </div>
            <div class="card-body">
              <?php if (!$actions): ?>
                <div class="empty"><i class="fa-regular fa-circle-check"></i>Nothing needs action right now.</div>
              <?php else: ?>
                <?php foreach ($actions as $a): ?>
                  <a href="<?= h($a[4]) ?>" class="row-item action-row">
                    <span class="ic <?= $a[0] ?>"><i class="fa-solid <?= $a[1] ?>"></i></span>
                    <div class="row-main">
                      <div class="row-title"><?= h($a[2]) ?></div>
                      <div class="row-sub"><?= h($a[3]) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color:#B9B3A8;font-size:.72rem;"></i>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-6">
          <section class="card">
            <div class="card-header">
              <div>
                <h2>Active Staff Assignments</h2>
                <p>With the contract end date.</p>
              </div>
            </div>
            <div class="card-body">
              <?php if (!$assignments): ?>
                <div class="empty"><i class="fa-regular fa-user"></i>No active assignments.</div>
              <?php else: ?>
                <?php foreach ($assignments as $s): ?>
                  <div class="row-item">
                    <div class="row-main">
                      <div class="row-title"><?= h($s['request_title']) ?></div>
                      <div class="row-sub">
                        <?= h($s['firstname'] . ' ' . $s['lastname']) ?> &middot; <?= h($s['company_name']) ?><?= $s['end_date'] ? ' &middot; Ends ' . h(date('M d, Y', strtotime($s['end_date']))) : '' ?>
                      </div>
                    </div>
                    <?= statusPill($s['status']) ?>
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
(function () {
  var EVENTS = <?= json_encode($eventsByDate, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var TYPES = <?= json_encode(array_map(fn($m) => ['label' => $m[0], 'cls' => $m[1], 'icon' => $m[2]], $typeMeta)) ?>;
  var PILLS = {
    'New': 'pl-blue',
    'In Progress': 'pl-ochre',
    'Completed': 'pl-sage',
    'Cancelled': 'pl-slate',
    'Draft': 'pl-slate',
    'Revert': 'pl-plum'
  };
  var DEFAULT_DATE = <?= json_encode($defaultSel) ?>;

  var titleEl = document.getElementById('dayTitle');
  var subEl = document.getElementById('daySub');
  var bodyEl = document.getElementById('dayBody');
  var cells = document.querySelectorAll('.cal-cell');

  function esc(v) {
    var d = document.createElement('div');
    d.textContent = v == null ? '' : String(v);
    return d.innerHTML;
  }

  function showDay(date) {
    cells.forEach(function (c) {
      c.classList.toggle('sel', c.dataset.date === date);
    });

    var d = new Date(date + 'T00:00:00');
    titleEl.textContent = d.toLocaleDateString('en-US', {
      weekday: 'long',
      month: 'long',
      day: '2-digit',
      year: 'numeric'
    });

    var list = EVENTS[date] || [];
    subEl.textContent = list.length + (list.length === 1 ? ' event' : ' events');

    if (list.length === 0) {
      bodyEl.innerHTML = '<div class="empty"><i class="fa-regular fa-calendar"></i>No events on this day.</div>';
      return;
    }

    bodyEl.innerHTML = list.map(function (e) {
      var t = TYPES[e.type];
      var extra = [t.label, e.title];
      if (e.staff) {
        extra.push(e.staff);
      }
      return '<div class="row-item">' +
        '<span class="ic ' + t.cls + '"><i class="fa-solid ' + t.icon + '"></i></span>' +
        '<div class="row-main">' +
          '<div class="row-title">' + esc(e.ref) + ' &middot; ' + esc(e.company) + '</div>' +
          '<div class="row-sub">' + esc(extra.join(' | ')) + '</div>' +
        '</div>' +
        '<span class="pill ' + (PILLS[e.status] || 'pl-slate') + '">' + esc(e.done ? 'Done' : e.status) + '</span>' +
      '</div>';
    }).join('');
  }

  cells.forEach(function (c) {
    c.addEventListener('click', function () {
      showDay(c.dataset.date);
    });
  });

  showDay(DEFAULT_DATE);
})();
</script>
</body>
</html>