<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

$adminId = $_SESSION['user_id'];
$adminFullname = $_SESSION['fullname'] ?? 'Admin';

$roleFilter   = $_GET['role'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$searchTerm   = trim($_GET['search'] ?? '');
$dateFilter   = trim($_GET['date'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

/**
 * Repository events (documents / folders). Admin uploads live in the
 * `administrator` table, everyone else in `users`.
 */
function repoBlock(bool $admin, string $kind): string
{
    $actor = $admin
        ? "a.admin_id, a.fullname, '', 'Admin'"
        : "u.user_id, u.firstname, u.lastname, u.role";
    $join = $admin
        ? "INNER JOIN administrator a ON a.admin_id = kd.uploaded_by"
        : "INNER JOIN users u ON u.user_id = kd.uploaded_by";
    $where = $admin
        ? "kd.uploaded_by_role = 'admin'"
        : "kd.uploaded_by_role IN ('manager', 'supervisor', 'staff')";

    if ($kind === 'created') {
        $label = "IF(kd.item_type = 'folder', 'Created folder', 'Uploaded document')";
        $time  = 'kd.created_at';
    } else {
        $label = "IF(kd.item_type = 'folder', 'Updated folder', 'Updated document')";
        $time  = 'kd.updated_at';
        $where .= ' AND kd.updated_at != kd.created_at';
    }

    return "
    SELECT {$actor},
           'Repository', {$label},
           CONCAT(kd.title, IF(kd.category IS NULL, '', CONCAT(' (', kd.category, ')'))),
           {$time}, NULL,
           c.company_name, NULL, NULL,
           CONCAT('Type: ', UPPER(COALESCE(kd.file_type, kd.item_type)),
                  ' · Location: ', COALESCE(p.title, 'Root'),
                  IF(kd.file_size > 0, CONCAT(' · Size: ', ROUND(kd.file_size / 1024, 1), ' KB'), ''))
    FROM knowledge_documents kd
    {$join}
    LEFT JOIN knowledge_documents p ON p.document_id = kd.parent_id
    LEFT JOIN clients c ON c.company_name = IF(kd.item_type = 'folder', kd.title, p.title)
    WHERE {$where}";
}

$unionQuery = "
    SELECT u.user_id AS actor_id, u.firstname, u.lastname, u.role,
           'Assignment' AS action_type,
           CONCAT('Assigned service request to ', s.firstname, ' ', s.lastname) AS action_label,
           sr.request_title AS target_label, sr.updated_at AS occurred_at,
           CONCAT(s.firstname, ' ', s.lastname) AS related_staff,
           c.company_name AS client_name,
           sr.status AS record_status,
           CAST(NULL AS DECIMAL(12,2)) AS amount,
           CONCAT('Required skill: ', COALESCE(sr.required_skill, '—')) AS note
    FROM service_requests sr
    INNER JOIN users u ON u.user_id = sr.assigned_by
    INNER JOIN users s ON s.user_id = sr.assigned_to
    LEFT JOIN clients c ON c.client_id = sr.client_id
    WHERE sr.assigned_to IS NOT NULL

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Progress Update', CONCAT('Marked request as ', sr.status),
           sr.request_title, sr.updated_at, NULL,
           c.company_name, sr.status, NULL,
           CONCAT('Required skill: ', COALESCE(sr.required_skill, '—'))
    FROM service_requests sr
    INNER JOIN users u ON u.user_id = sr.assigned_to
    LEFT JOIN clients c ON c.client_id = sr.client_id
    WHERE sr.status IN ('In Progress', 'Completed', 'Cancelled')

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Quotation', 'Created quotation',
           q.quotation_number, q.created_at, NULL,
           c.company_name, q.status, q.total_amount, q.project_scope
    FROM quotations q
    INNER JOIN users u ON u.user_id = q.prepared_by
    LEFT JOIN clients c ON c.client_id = q.client_id

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Quotation', 'Reverted quotation',
           COALESCE(q.quotation_number, CONCAT('Quotation #', qr.quotation_id)), qr.created_at, NULL,
           c.company_name, q.status, q.total_amount, qr.revision_note
    FROM quotation_revisions qr
    INNER JOIN users u ON u.user_id = qr.revised_by
    LEFT JOIN quotations q ON q.quotation_id = qr.quotation_id
    LEFT JOIN clients c ON c.client_id = q.client_id

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Contract', 'Generated contract',
           ct.contract_number, ct.created_at, NULL,
           c.company_name, ct.status, ct.total_amount, ct.scope_summary
    FROM contracts ct
    INNER JOIN users u ON u.user_id = ct.prepared_by
    LEFT JOIN clients c ON c.client_id = ct.client_id

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Contract', 'Approved contract',
           ct.contract_number, ct.approved_at, NULL,
           c.company_name, ct.status, ct.total_amount, ct.scope_summary
    FROM contracts ct
    INNER JOIN users u ON u.user_id = ct.approved_by
    LEFT JOIN clients c ON c.client_id = ct.client_id
    WHERE ct.approved_at IS NOT NULL

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Revision', 'Recorded contract revision',
           ct.contract_number, cr.created_at, NULL,
           c.company_name, ct.status, ct.total_amount, cr.revision_note
    FROM contract_revisions cr
    INNER JOIN users u ON u.user_id = cr.revised_by
    LEFT JOIN contracts ct ON ct.contract_id = cr.contract_id
    LEFT JOIN clients c ON c.client_id = ct.client_id

    UNION ALL " . repoBlock(true, 'created') . "
    UNION ALL " . repoBlock(false, 'created') . "
    UNION ALL " . repoBlock(true, 'updated') . "
    UNION ALL " . repoBlock(false, 'updated') . "
";

/* ---- Filters (each condition carries its own params so they never get misaligned) ---- */
$baseConds = [];
if ($actionFilter !== '') {
    $baseConds[] = ['action_type = ?', [$actionFilter]];
}
if ($searchTerm !== '') {
    $like = '%' . $searchTerm . '%';
    $baseConds[] = [
        '(target_label LIKE ? OR client_name LIKE ? OR CONCAT(firstname, " ", lastname) LIKE ?)',
        [$like, $like, $like],
    ];
}
if ($dateFilter !== '') {
    $baseConds[] = ['DATE(occurred_at) = ?', [$dateFilter]];
}

$roleCond = in_array($roleFilter, ['Manager', 'Supervisor', 'Staff', 'Admin'], true)
    ? ['role = ?', [$roleFilter]]
    : null;

function buildWhere(array $conds): array
{
    if (empty($conds)) {
        return ['', []];
    }
    $sql = [];
    $params = [];
    foreach ($conds as [$c, $p]) {
        $sql[] = $c;
        foreach ($p as $v) {
            $params[] = $v;
        }
    }
    return ['WHERE ' . implode(' AND ', $sql), $params];
}

$allConds = $baseConds;
if ($roleCond) {
    $allConds[] = $roleCond;
}
[$whereClause, $params] = buildWhere($allConds);
[$roleCountWhere, $roleCountParams] = buildWhere($baseConds);

/* ---- CSV export (uses the same filters) ---- */
if (($_GET['export'] ?? '') === 'csv') {
    $exStmt = $pdo->prepare("SELECT * FROM ({$unionQuery}) AS activity_feed {$whereClause} ORDER BY occurred_at DESC");
    $exStmt->execute($params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Timestamp', 'Actor', 'Role', 'Event Type', 'Description', 'Record', 'Company / Client', 'Status', 'Amount', 'Assigned To', 'Details']);
    while ($r = $exStmt->fetch()) {
        fputcsv($out, [
            $r['occurred_at'],
            trim($r['firstname'] . ' ' . $r['lastname']),
            $r['role'],
            $r['action_type'],
            $r['action_label'],
            $r['target_label'],
            $r['client_name'],
            $r['record_status'],
            $r['amount'],
            $r['related_staff'],
            $r['note'],
        ]);
    }
    fclose($out);
    exit;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$unionQuery}) AS activity_feed {$whereClause}");
$countStmt->execute($params);
$totalLogs = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalLogs / $perPage));

$logsStmt = $pdo->prepare(
    "SELECT * FROM ({$unionQuery}) AS activity_feed
     {$whereClause}
     ORDER BY occurred_at DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$logsStmt->execute($params);
$logs = $logsStmt->fetchAll();

$roleCountsStmt = $pdo->prepare(
    "SELECT role, COUNT(*) AS cnt FROM ({$unionQuery}) AS activity_feed {$roleCountWhere} GROUP BY role"
);
$roleCountsStmt->execute($roleCountParams);
$roleCounts = ['Manager' => 0, 'Supervisor' => 0, 'Staff' => 0, 'Admin' => 0];
foreach ($roleCountsStmt->fetchAll() as $row) {
    $normalizedRole = strtolower(trim($row['role'] ?? ''));
    foreach (array_keys($roleCounts) as $knownRole) {
        if (strtolower($knownRole) === $normalizedRole) {
            $roleCounts[$knownRole] += (int) $row['cnt'];
            break;
        }
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function actionTypeClass(string $type): string
{
    return match ($type) {
        'Assignment' => 'cat-assignment',
        'Progress Update' => 'cat-progress',
        'Quotation' => 'cat-quotation',
        'Contract' => 'cat-contract',
        'Revision' => 'cat-revision',
        'Repository' => 'cat-repository',
        default => 'cat-repository',
    };
}

function actionTypeIcon(string $type): string
{
    return match ($type) {
        'Assignment' => 'fa-user-plus',
        'Progress Update' => 'fa-chart-line',
        'Quotation' => 'fa-file-invoice',
        'Contract' => 'fa-file-signature',
        'Revision' => 'fa-clock-rotate-left',
        'Repository' => 'fa-folder-open',
        default => 'fa-circle-dot',
    };
}

function roleClass(?string $role): string
{
    return 'role-' . strtolower(trim((string) $role));
}

function statusClass(?string $status): string
{
    $s = strtolower(trim((string) $status));
    return $s === '' ? '' : 'st-' . preg_replace('/[^a-z]+/', '-', $s);
}

function initialsFor(?string $first, ?string $last): string
{
    $first = trim((string) $first);
    $last = trim((string) $last);
    if ($last === '') {
        $parts = preg_split('/\s+/', $first) ?: [];
        $a = $parts[0] ?? '';
        $b = $parts[1] ?? '';
        return strtoupper(substr($a, 0, 1) . substr($b, 0, 1));
    }
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

function buildLogPageUrl(int $targetPage, string $role, string $action, string $search, string $date): string
{
    return '?' . http_build_query([
        'page' => $targetPage,
        'role' => $role,
        'action' => $action,
        'search' => $search,
        'date' => $date,
    ]);
}

function relativeTime(?string $datetime): string
{
    $ts = $datetime ? strtotime($datetime) : false;
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 2592000) {
        return floor($diff / 86400) . ' d ago';
    }
    return '';
}

function dayHeading(string $ymd): string
{
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $pretty = date('l, F j, Y', strtotime($ymd));
    if ($ymd === $today) {
        return 'Today · ' . $pretty;
    }
    if ($ymd === $yesterday) {
        return 'Yesterday · ' . $pretty;
    }
    return $pretty;
}

function pageWindow(int $current, int $total): array
{
    if ($total <= 7) {
        return range(1, $total);
    }
    $pages = [1];
    $start = max(2, $current - 1);
    $end = min($total - 1, $current + 1);
    if ($start > 2) {
        $pages[] = '...';
    }
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }
    if ($end < $total - 1) {
        $pages[] = '...';
    }
    $pages[] = $total;
    return $pages;
}

$actionTypes = ['Assignment', 'Progress Update', 'Quotation', 'Contract', 'Revision', 'Repository'];
$roleTypes = ['Manager', 'Supervisor', 'Staff', 'Admin'];
$hasActiveFilters = $searchTerm !== '' || $roleFilter !== '' || $actionFilter !== '' || $dateFilter !== '';
$roleBase = max(1, array_sum($roleCounts));
$rangeFrom = $totalLogs === 0 ? 0 : $offset + 1;
$rangeTo = min($offset + $perPage, $totalLogs);
$todayYmd = date('Y-m-d');
$yesterdayYmd = date('Y-m-d', strtotime('-1 day'));
$exportUrl = '?' . http_build_query([
    'role' => $roleFilter,
    'action' => $actionFilter,
    'search' => $searchTerm,
    'date' => $dateFilter,
    'export' => 'csv',
]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Audit Trail · Activity Logs</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg: #F4F6F9;
  --surface: #FFFFFF;
  --surface-2: #F9FAFC;
  --surface-3: #F1F4F8;
  --border: #E3E7EE;
  --border-strong: #CDD4DF;

  --text: #121926;
  --text-2: #4A5568;
  --text-3: #7A8699;

  --brand: #1F3A8A;
  --brand-hover: #1A3176;
  --brand-soft: #E8EDFB;
  --focus: rgba(31, 58, 138, .22);

  --c-assignment: #4F46E5;
  --c-assignment-bg: #EEF0FE;
  --c-progress: #0F8A6A;
  --c-progress-bg: #E4F5EF;
  --c-quotation: #0E7490;
  --c-quotation-bg: #E2F3F7;
  --c-contract: #BE3A34;
  --c-contract-bg: #FBEBEA;
  --c-revision: #B45309;
  --c-revision-bg: #FDF1E0;
  --c-repository: #475569;
  --c-repository-bg: #EDF0F4;

  --r-manager: #7C3AED;
  --r-supervisor: #0E7490;
  --r-staff: #0F8A6A;
  --r-admin: #1F3A8A;

  --mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  --sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

* { -webkit-tap-highlight-color: transparent; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--sans);
  font-feature-settings: 'cv11', 'ss01';
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

.dashboard-layout, .dashboard-content { background: var(--bg) !important; }
.dashboard-main { min-width: 0; max-width: 100%; }
.dashboard-topbar {
  background: var(--surface) !important;
  border-bottom: 1px solid var(--border) !important;
}

.page-title {
  font-family: var(--sans);
  font-size: 1.05rem;
  font-weight: 650;
  letter-spacing: -0.012em;
  color: var(--text);
  margin: 0;
}
.page-sub {
  font-size: .8rem;
  color: var(--text-3);
  margin: 0;
}

/* ---------- Metric boxes ---------- */
.metrics {
  display: grid;
  grid-template-columns: 1.35fr repeat(4, 1fr);
  gap: .75rem;
}
.metric {
  display: block;
  text-decoration: none;
  color: #fff;
  background: rgba(23, 49, 120, .85);
  border: 1px solid rgba(23, 49, 120, .85);
  border-radius: 10px;
  padding: .95rem 1.05rem;
  transition: background .15s ease, border-color .15s ease;
}
a.metric:hover,
.metric.is-active {
  background: rgb(23, 49, 120);
  border-color: rgb(23, 49, 120);
  color: #fff;
}
.metric-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
}
.metric-label {
  font-size: .78rem;
  font-weight: 600;
  color: rgba(255, 255, 255, .9);
}
.metric-pct {
  font-family: var(--mono);
  font-size: .7rem;
  color: rgba(255, 255, 255, .75);
}
.metric-value {
  font-size: 1.75rem;
  font-weight: 650;
  letter-spacing: -0.025em;
  line-height: 1.1;
  margin-top: .55rem;
  color: #fff;
  font-variant-numeric: tabular-nums;
}
.metric-total .metric-value { font-size: 2rem; }

.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
}

.filters { padding: .9rem 1rem; }
.filters .field-label {
  display: block;
  font-size: .72rem;
  font-weight: 600;
  color: var(--text-2);
  margin-bottom: .32rem;
}
.filters .form-control,
.filters .form-select {
  height: 36px;
  font-size: .85rem;
  color: var(--text);
  background-color: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: 7px;
  padding-top: 0;
  padding-bottom: 0;
}
.filters .form-control::placeholder { color: var(--text-3); }
.filters .form-control:focus,
.filters .form-select:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px var(--focus);
}
.filters .input-group-text {
  height: 36px;
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-right: none;
  color: var(--text-3);
  border-radius: 7px 0 0 7px;
  font-size: .8rem;
}
.filters .input-group .form-control { border-left: none; border-radius: 0 7px 7px 0; padding-left: 0; }

.quick-range {
  display: inline-flex;
  gap: .35rem;
  flex-wrap: wrap;
}
.quick-btn {
  display: inline-flex;
  align-items: center;
  height: 36px;
  padding: 0 .75rem;
  font-size: .8rem;
  font-weight: 600;
  color: var(--text-2);
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: 7px;
  text-decoration: none;
  transition: background .12s ease, border-color .12s ease;
}
.quick-btn:hover { background: var(--surface-3); color: var(--text); }
.quick-btn.is-on { background: var(--brand-soft); border-color: var(--brand); color: var(--brand); }

.active-filters {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: .4rem;
  margin-top: .8rem;
  padding-top: .8rem;
  border-top: 1px solid var(--border);
}
.active-filters .af-title {
  font-size: .74rem;
  font-weight: 600;
  color: var(--text-3);
  margin-right: .2rem;
}
.filter-pill {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .76rem;
  font-weight: 500;
  color: var(--brand);
  background: var(--brand-soft);
  border-radius: 999px;
  padding: .22rem .6rem;
}
.filter-pill b { font-weight: 600; color: var(--text-2); }
.btn-clear {
  margin-left: auto;
  font-size: .78rem;
  font-weight: 600;
  color: var(--c-contract);
  text-decoration: none;
  padding: .25rem .5rem;
  border-radius: 6px;
}
.btn-clear:hover { background: var(--c-contract-bg); color: var(--c-contract); }

.log-panel { overflow: hidden; }
.log-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  padding: .85rem 1.1rem;
  border-bottom: 1px solid var(--border);
}
.log-panel-title {
  font-size: .92rem;
  font-weight: 650;
  margin: 0;
  letter-spacing: -0.01em;
}
.log-panel-right { display: flex; align-items: center; gap: .85rem; flex-wrap: wrap; }
.log-panel-meta {
  font-size: .78rem;
  color: var(--text-3);
  font-variant-numeric: tabular-nums;
}
.log-panel-meta b { color: var(--text-2); font-weight: 600; }
.btn-export {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  height: 32px;
  padding: 0 .75rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--brand);
  background: var(--surface);
  border: 1px solid var(--border-strong);
  border-radius: 7px;
  text-decoration: none;
}
.btn-export:hover { background: var(--brand-soft); border-color: var(--brand); color: var(--brand); }

.table-responsive { max-height: none; }
#logsTable {
  margin: 0;
  --bs-table-bg: transparent;
  border-collapse: separate;
  border-spacing: 0;
}
#logsTable thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--surface-2) !important;
  color: var(--text-3);
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .02em;
  padding: .65rem 1.1rem;
  border-bottom: 1px solid var(--border) !important;
  white-space: nowrap;
}
#logsTable td {
  padding: .78rem 1.1rem;
  font-size: .85rem;
  color: var(--text);
  vertical-align: middle;
  border-bottom: 1px solid var(--border);
  background: transparent;
}
#logsTable tbody tr:last-child td { border-bottom: none; }

tr.day-divider td {
  background: var(--surface-2) !important;
  padding: .45rem 1.1rem !important;
  font-size: .72rem;
  font-weight: 600;
  color: var(--text-2);
  letter-spacing: .01em;
  border-bottom: 1px solid var(--border);
  border-top: 1px solid var(--border);
}
tr.day-divider:first-child td { border-top: none; }
tr.day-divider td i { color: var(--text-3); margin-right: .45rem; }

tr.log-row { cursor: pointer; transition: background .1s ease; }
tr.log-row:hover td { background: #F6F8FD; }
tr.log-row:focus-visible { outline: 2px solid var(--brand); outline-offset: -2px; }
tr.log-row td:first-child { box-shadow: inset 3px 0 0 var(--row-accent, transparent); }
tr.log-row.cat-assignment { --row-accent: var(--c-assignment); }
tr.log-row.cat-progress { --row-accent: var(--c-progress); }
tr.log-row.cat-quotation { --row-accent: var(--c-quotation); }
tr.log-row.cat-contract { --row-accent: var(--c-contract); }
tr.log-row.cat-revision { --row-accent: var(--c-revision); }
tr.log-row.cat-repository { --row-accent: var(--c-repository); }

.actor { display: flex; align-items: center; gap: .65rem; min-width: 0; }
.avatar {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .75rem;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
  letter-spacing: .02em;
}
.avatar.role-manager { background: var(--r-manager); }
.avatar.role-supervisor { background: var(--r-supervisor); }
.avatar.role-staff { background: var(--r-staff); }
.avatar.role-admin { background: var(--r-admin); }
.avatar.role-unknown { background: var(--text-3); }
.actor-name { font-weight: 600; font-size: .86rem; line-height: 1.25; }
.actor-id { font-family: var(--mono); font-size: .68rem; color: var(--text-3); line-height: 1.3; }

.role-tag {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--text-2);
}
.role-tag::before {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--text-3);
}
.role-tag.role-manager::before { background: var(--r-manager); }
.role-tag.role-supervisor::before { background: var(--r-supervisor); }
.role-tag.role-staff::before { background: var(--r-staff); }
.role-tag.role-admin::before { background: var(--r-admin); }

.event-cat {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .72rem;
  font-weight: 600;
  padding: .2rem .55rem;
  border-radius: 5px;
  white-space: nowrap;
  line-height: 1.3;
}
.event-cat i { font-size: .68rem; }
.event-cat.cat-assignment { color: var(--c-assignment); background: var(--c-assignment-bg); }
.event-cat.cat-progress { color: var(--c-progress); background: var(--c-progress-bg); }
.event-cat.cat-quotation { color: var(--c-quotation); background: var(--c-quotation-bg); }
.event-cat.cat-contract { color: var(--c-contract); background: var(--c-contract-bg); }
.event-cat.cat-revision { color: var(--c-revision); background: var(--c-revision-bg); }
.event-cat.cat-repository { color: var(--c-repository); background: var(--c-repository-bg); }
.event-desc {
  display: block;
  margin-top: .3rem;
  font-size: .8rem;
  color: var(--text-2);
  line-height: 1.4;
}

.client-name {
  display: block;
  font-weight: 600;
  font-size: .82rem;
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.client-name i { color: var(--text-3); margin-right: .35rem; font-size: .72rem; }
.muted-dash { color: var(--text-3); }

.target-ref {
  font-family: var(--mono);
  font-size: .76rem;
  color: var(--text);
  background: var(--surface-3);
  border: 1px solid var(--border);
  border-radius: 5px;
  padding: .18rem .45rem;
  display: inline-block;
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}

/* status badges */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .72rem;
  font-weight: 600;
  padding: .18rem .55rem;
  border-radius: 999px;
  color: var(--text-2);
  background: var(--surface-3);
  white-space: nowrap;
}
.status-badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.status-badge.st-approved, .status-badge.st-completed { color: #0F8A6A; background: #E4F5EF; }
.status-badge.st-draft, .status-badge.st-new { color: #475569; background: #EDF0F4; }
.status-badge.st-rejected, .status-badge.st-cancelled { color: #BE3A34; background: #FBEBEA; }
.status-badge.st-revert { color: #B45309; background: #FDF1E0; }
.status-badge.st-in-progress { color: #4F46E5; background: #EEF0FE; }

.ts { white-space: nowrap; }
.ts-main { font-family: var(--mono); font-size: .78rem; color: var(--text); font-variant-numeric: tabular-nums; }
.ts-rel { display: block; font-size: .7rem; color: var(--text-3); margin-top: .1rem; }

.row-chevron { color: var(--border-strong); font-size: .7rem; text-align: right; width: 32px; }
tr.log-row:hover .row-chevron { color: var(--brand); }

.empty-state { padding: 3.5rem 1rem; text-align: center; }
.empty-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: var(--surface-3);
  color: var(--text-3);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  margin-bottom: .9rem;
}
.empty-title { font-size: .95rem; font-weight: 650; margin: 0 0 .25rem; }
.empty-text { font-size: .83rem; color: var(--text-3); margin: 0 0 1rem; }
.btn-empty {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .8rem;
  font-weight: 600;
  color: var(--brand);
  border: 1px solid var(--border-strong);
  background: var(--surface);
  border-radius: 7px;
  padding: .4rem .8rem;
  text-decoration: none;
}
.btn-empty:hover { background: var(--brand-soft); color: var(--brand); border-color: var(--brand); }

.log-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .75rem;
  padding: .8rem 1.1rem;
  border-top: 1px solid var(--border);
  background: var(--surface-2);
}
.log-foot .foot-info { font-size: .78rem; color: var(--text-3); font-variant-numeric: tabular-nums; }
.pager { display: flex; align-items: center; gap: .25rem; margin: 0; padding: 0; list-style: none; flex-wrap: wrap; }
.pager a, .pager span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 32px;
  padding: 0 .6rem;
  font-size: .8rem;
  font-weight: 600;
  color: var(--text-2);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 7px;
  text-decoration: none;
  font-variant-numeric: tabular-nums;
}
.pager a:hover { background: var(--surface-3); border-color: var(--border-strong); color: var(--text); }
.pager .is-current { background: var(--brand); border-color: var(--brand); color: #fff; }
.pager .is-disabled { color: var(--text-3); opacity: .5; pointer-events: none; }
.pager .is-gap { border-color: transparent; background: transparent; min-width: 20px; padding: 0; }

/* ---------- Detail modal (fullscreen, same look as CPQ view) ---------- */
#logDetailModal .modal-content {
  --accent: var(--brand);
  --accent-bg: var(--brand-soft);
  border-radius: 0;
  background-color: #FFFEFC;
}
#logDetailModal .modal-content.cat-assignment { --accent: var(--c-assignment); --accent-bg: var(--c-assignment-bg); }
#logDetailModal .modal-content.cat-progress { --accent: var(--c-progress); --accent-bg: var(--c-progress-bg); }
#logDetailModal .modal-content.cat-quotation { --accent: var(--c-quotation); --accent-bg: var(--c-quotation-bg); }
#logDetailModal .modal-content.cat-contract { --accent: var(--c-contract); --accent-bg: var(--c-contract-bg); }
#logDetailModal .modal-content.cat-revision { --accent: var(--c-revision); --accent-bg: var(--c-revision-bg); }
#logDetailModal .modal-content.cat-repository { --accent: var(--c-repository); --accent-bg: var(--c-repository-bg); }

#logDetailModal .modal-header {
  padding: .9rem 1.25rem;
  background-color: #FFFEFC;
  border-bottom: 1px solid #E6E2DA;
}
#logDetailModal .modal-title {
  display: flex;
  align-items: center;
  gap: .65rem;
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: #2B3134;
}
#logDetailModal .mh-icon {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .85rem;
  color: #fff;
  background: var(--accent);
  flex-shrink: 0;
}
#logDetailModal .modal-body {
  background-color: #F6F4EF;
  padding: 1.25rem;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
#logDetailModal .modal-footer { display: none; }

.quote-shell { max-width: 1400px; margin: 0 auto; }
.quote-panel {
  background-color: #FFFEFC;
  border: 1px solid #E6E2DA;
  border-radius: 12px;
  padding: 1.1rem 1.25rem;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
}
.quote-panel-title {
  display: flex;
  align-items: center;
  gap: .55rem;
  font-size: .95rem;
  font-weight: 600;
  color: #2B3134;
  margin-bottom: 1rem;
}
.quote-panel-title .step-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background-color: var(--brand-soft);
  color: var(--brand);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .75rem;
  flex-shrink: 0;
}
.quote-sticky { position: sticky; top: 0; }

.view-section-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #6E7275;
  margin-bottom: .3rem;
}
.view-value {
  font-size: .95rem;
  font-weight: 600;
  color: var(--text);
  word-break: break-word;
}
.view-value.mono { font-family: var(--mono); font-size: .88rem; font-weight: 500; }
.view-value.money { font-family: var(--mono); color: var(--c-progress); }
.view-lead {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 500;
  line-height: 1.55;
  color: var(--text);
  word-break: break-word;
}
.view-notes-box {
  background-color: #F6F4EF;
  border: 1px solid #E6E2DA;
  border-radius: 10px;
  padding: .75rem .9rem;
  white-space: pre-line;
  word-break: break-word;
  min-height: 2.5rem;
  color: var(--text);
  font-size: .9rem;
  margin: 0;
}
.actor-card { display: flex; align-items: center; gap: 1rem; }
.actor-card .avatar { width: 56px; height: 56px; border-radius: 14px; font-size: 1.15rem; }
.actor-card-name { font-size: 1.1rem; font-weight: 700; line-height: 1.25; word-break: break-word; }
.hero-actor-role {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  margin-top: .2rem;
  font-size: .85rem;
  font-weight: 600;
  color: var(--text-2);
}
.hero-actor-role::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--text-3); }
.hero-actor-role.role-manager::before { background: var(--r-manager); }
.hero-actor-role.role-supervisor::before { background: var(--r-supervisor); }
.hero-actor-role.role-staff::before { background: var(--r-staff); }
.hero-actor-role.role-admin::before { background: var(--r-admin); }
#logDetailModal .event-cat { font-size: .82rem; padding: .3rem .7rem; }
#logDetailModal .status-badge { font-size: .8rem; padding: .28rem .7rem; }

@media (max-width: 991.98px) {
  .quote-sticky { position: static; }
  #logDetailModal .modal-body { padding: .9rem; }
}
@media (max-width: 767.98px) {
  #logDetailModal .modal-header { padding: .7rem .8rem; }
  #logDetailModal .modal-title { font-size: .95rem; }
  #logDetailModal .modal-body { padding: .65rem; }
  .quote-panel { padding: .8rem .85rem; border-radius: 10px; }
  .quote-panel-title { font-size: .84rem; margin-bottom: .7rem; }
  .view-lead { font-size: .95rem; }
}

.mobile-label { display: none; }

@media (max-width: 1399.98px) {
  .metrics { grid-template-columns: repeat(5, 1fr); }
  .metric-total .metric-value { font-size: 1.75rem; }
}

@media (max-width: 1199.98px) {
  #logsTable thead th, #logsTable td { padding-left: .9rem; padding-right: .9rem; }
  .metric { padding: .85rem .9rem; }
  .metric-value { font-size: 1.5rem; }
  .metric-total .metric-value { font-size: 1.5rem; }
}

@media (max-width: 991.98px) {
  .metrics { grid-template-columns: repeat(3, 1fr); }
  .metric-total { grid-column: span 3; }
  .page-title { font-size: 1rem; }

}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .7rem !important; }
  .dashboard-topbar { padding: .55rem .75rem !important; }

  .metrics { grid-template-columns: repeat(2, 1fr); gap: .5rem; }
  .metric-total { grid-column: span 2; }
  .metric { padding: .75rem .8rem; border-radius: 9px; }
  .metric-value { font-size: 1.3rem; margin-top: .4rem; }

  .filters { padding: .8rem; }
  .quick-range { width: 100%; }
  .quick-btn { flex: 1; justify-content: center; }

  .log-panel-head { padding: .75rem .85rem; }

  .table-responsive { overflow: visible; }
  #logsTable thead { display: none; }
  #logsTable, #logsTable tbody, #logsTable tr, #logsTable td { display: block; width: 100%; }
  tr.day-divider td { padding: .45rem .85rem !important; }
  tr.log-row {
    padding: .8rem .85rem .75rem 1rem;
    border-bottom: 1px solid var(--border);
    border-left: 3px solid var(--row-accent, transparent);
  }
  tr.log-row td:first-child { box-shadow: none; }
  #logsTable tbody tr.log-row td {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    padding: .2rem 0;
    border: none;
    font-size: .8rem;
  }
  tr.log-row:hover td { background: transparent; }
  .mobile-label {
    display: block;
    flex: 0 0 62px;
    font-size: .68rem;
    font-weight: 600;
    color: var(--text-3);
    padding-top: .2rem;
  }
  .cell-body { flex: 1 1 auto; min-width: 0; text-align: right; }
  .actor { justify-content: flex-end; text-align: left; }
  .actor-id { display: none; }
  .target-ref { max-width: 100%; }
  .client-name { max-width: 100%; }
  .row-chevron { display: none !important; }
  .ts-rel { display: inline; margin-left: .4rem; }

  .log-foot { padding: .75rem .85rem; justify-content: center; }
  .pager a, .pager span { min-width: 30px; height: 30px; font-size: .75rem; }

}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}
</style>
</head>

<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="page-title">Activity Logs</h1>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="metrics mb-3" aria-label="Event summary">
        <a class="metric metric-total <?= $roleFilter === '' ? 'is-active' : '' ?>" href="<?= e(buildLogPageUrl(1, '', $actionFilter, $searchTerm, $dateFilter)) ?>">
          <div class="metric-head">
            <span class="metric-label">All events</span>
            <span class="metric-pct"><?= $hasActiveFilters ? 'filtered' : 'all time' ?></span>
          </div>
          <div class="metric-value"><?= number_format($totalLogs) ?></div>
        </a>
        <?php foreach ($roleTypes as $rt): ?>
          <?php $pct = round(($roleCounts[$rt] / $roleBase) * 100); ?>
          <a class="metric <?= $roleFilter === $rt ? 'is-active' : '' ?>" href="<?= e(buildLogPageUrl(1, $rt, $actionFilter, $searchTerm, $dateFilter)) ?>">
            <div class="metric-head">
              <span class="metric-label"><?= e($rt) ?></span>
              <span class="metric-pct"><?= $pct ?>%</span>
            </div>
            <div class="metric-value"><?= number_format($roleCounts[$rt]) ?></div>
          </a>
        <?php endforeach; ?>
      </section>

      <form method="GET" id="filterForm" class="panel filters mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-lg-4">
            <label class="field-label" for="searchInput">Search</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
              <input type="text" name="search" id="searchInput" class="form-control" placeholder="User, company, or record reference" value="<?= e($searchTerm) ?>" autocomplete="off">
            </div>
          </div>
          <div class="col-6 col-lg-2">
            <label class="field-label" for="roleSelect">Role</label>
            <select name="role" id="roleSelect" class="form-select" onchange="this.form.submit()">
              <option value="">All roles</option>
              <?php foreach ($roleTypes as $rt): ?>
                <option value="<?= e($rt) ?>" <?= $roleFilter === $rt ? 'selected' : '' ?>><?= e($rt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-lg-2">
            <label class="field-label" for="actionSelect">Event type</label>
            <select name="action" id="actionSelect" class="form-select" onchange="this.form.submit()">
              <option value="">All events</option>
              <?php foreach ($actionTypes as $at): ?>
                <option value="<?= e($at) ?>" <?= $actionFilter === $at ? 'selected' : '' ?>><?= e($at) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6 col-lg-2">
            <label class="field-label" for="dateInput">Date</label>
            <input type="date" name="date" id="dateInput" class="form-control" value="<?= e($dateFilter) ?>" onchange="this.form.submit()">
          </div>
          <div class="col-12 col-md-6 col-lg-2">
            <div class="quick-range">
              <a class="quick-btn <?= $dateFilter === $todayYmd ? 'is-on' : '' ?>" href="<?= e(buildLogPageUrl(1, $roleFilter, $actionFilter, $searchTerm, $todayYmd)) ?>">Today</a>
              <a class="quick-btn <?= $dateFilter === $yesterdayYmd ? 'is-on' : '' ?>" href="<?= e(buildLogPageUrl(1, $roleFilter, $actionFilter, $searchTerm, $yesterdayYmd)) ?>">Yesterday</a>
            </div>
          </div>
        </div>

        <?php if ($hasActiveFilters): ?>
          <div class="active-filters">
            <span class="af-title">Active filters</span>
            <?php if ($searchTerm !== ''): ?>
              <span class="filter-pill"><b>Search</b> <?= e($searchTerm) ?></span>
            <?php endif; ?>
            <?php if ($roleFilter !== ''): ?>
              <span class="filter-pill"><b>Role</b> <?= e($roleFilter) ?></span>
            <?php endif; ?>
            <?php if ($actionFilter !== ''): ?>
              <span class="filter-pill"><b>Event</b> <?= e($actionFilter) ?></span>
            <?php endif; ?>
            <?php if ($dateFilter !== ''): ?>
              <span class="filter-pill"><b>Date</b> <?= e($dateFilter) ?></span>
            <?php endif; ?>
            <a href="activity_logs.php" class="btn-clear"><i class="fa-solid fa-xmark me-1"></i>Clear all</a>
          </div>
        <?php endif; ?>
      </form>

      <section class="panel log-panel">
        <div class="log-panel-head">
          <h2 class="log-panel-title">Event log</h2>
          <div class="log-panel-right">
            <span class="log-panel-meta">
              Showing <b><?= number_format($rangeFrom) ?>–<?= number_format($rangeTo) ?></b> of <b><?= number_format($totalLogs) ?></b> events
            </span>
            <a class="btn-export" href="<?= e($exportUrl) ?>"><i class="fa-solid fa-file-csv"></i>Export CSV</a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle mb-0" id="logsTable">
            <thead>
              <tr>
                <th scope="col">Actor</th>
                <th scope="col" class="d-none d-md-table-cell">Role</th>
                <th scope="col">Event</th>
                <th scope="col" class="d-none d-xl-table-cell">Company</th>
                <th scope="col" class="d-none d-lg-table-cell">Record</th>
                <th scope="col">Timestamp</th>
                <th scope="col" class="d-none d-md-table-cell"></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr>
                  <td colspan="7">
                    <div class="empty-state">
                      <div class="empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                      <h3 class="empty-title">No events match these filters</h3>
                      <p class="empty-text">Try a different date, role, or event type, or clear the filters to see all activity.</p>
                      <?php if ($hasActiveFilters): ?>
                        <a href="activity_logs.php" class="btn-empty"><i class="fa-solid fa-rotate-left"></i>Reset filters</a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php $lastDay = null; ?>
                <?php foreach ($logs as $log): ?>
                  <?php
                    $ts = $log['occurred_at'] ? strtotime($log['occurred_at']) : false;
                    $dayKey = $ts !== false ? date('Y-m-d', $ts) : 'unknown';
                    $fullName = trim($log['firstname'] . ' ' . $log['lastname']);
                    $initials = initialsFor($log['firstname'], $log['lastname']);
                    $rClass = roleClass($log['role']);
                    $avatarClass = in_array($rClass, ['role-manager', 'role-supervisor', 'role-staff', 'role-admin'], true) ? $rClass : 'role-unknown';
                    $catClass = actionTypeClass($log['action_type']);
                    $targetText = $log['target_label'] ?? '—';
                    $clientName = trim((string) ($log['client_name'] ?? ''));
                    $recStatus = trim((string) ($log['record_status'] ?? ''));
                    $amountText = ($log['amount'] !== null && $log['amount'] !== '') ? '₱' . number_format((float) $log['amount'], 2) : '';
                    $noteText = trim((string) ($log['note'] ?? ''));
                    $fullStamp = $ts !== false ? date('F d, Y g:i:s A', $ts) : '—';
                    $rel = relativeTime($log['occurred_at']);
                    $summary = $fullName . ' ' . lcfirst((string) $log['action_label'])
                        . ($targetText !== '—' && $targetText !== '' ? ' “' . $targetText . '”' : '')
                        . ($clientName !== '' ? ' for ' . $clientName : '')
                        . '.';
                  ?>
                  <?php if ($dayKey !== $lastDay): ?>
                    <?php $lastDay = $dayKey; ?>
                    <tr class="day-divider">
                      <td colspan="7"><i class="fa-regular fa-calendar"></i><?= $dayKey === 'unknown' ? 'Undated' : e(dayHeading($dayKey)) ?></td>
                    </tr>
                  <?php endif; ?>
                  <tr class="log-row <?= e($catClass) ?>" tabindex="0" role="button" aria-label="View details for <?= e($log['action_type']) ?> by <?= e($fullName) ?>"
                    data-name="<?= e($fullName) ?>"
                    data-initials="<?= e($initials) ?>"
                    data-role="<?= e($log['role']) ?>"
                    data-role-class="<?= e($avatarClass) ?>"
                    data-actor-id="<?= e((string) $log['actor_id']) ?>"
                    data-action-type="<?= e($log['action_type']) ?>"
                    data-action-label="<?= e($log['action_label']) ?>"
                    data-cat-class="<?= e($catClass) ?>"
                    data-cat-icon="<?= e(actionTypeIcon($log['action_type'])) ?>"
                    data-target="<?= e($targetText) ?>"
                    data-related="<?= e($log['related_staff'] ?? '') ?>"
                    data-client="<?= e($clientName) ?>"
                    data-status="<?= e($recStatus) ?>"
                    data-status-class="<?= e(statusClass($recStatus)) ?>"
                    data-amount="<?= e($amountText) ?>"
                    data-note="<?= e($noteText) ?>"
                    data-summary="<?= e($summary) ?>"
                    data-occurred="<?= e($fullStamp) ?>"
                    data-relative="<?= e($rel) ?>">
                    <td>
                      <span class="mobile-label">Actor</span>
                      <span class="cell-body">
                        <span class="actor">
                          <span class="avatar <?= e($avatarClass) ?>"><?= e($initials) ?></span>
                          <span class="min-w-0">
                            <span class="actor-name d-block"><?= e($fullName) ?></span>
                            <span class="actor-id d-block">ID <?= e((string) $log['actor_id']) ?></span>
                          </span>
                        </span>
                      </span>
                    </td>
                    <td class="d-none d-md-table-cell">
                      <span class="mobile-label">Role</span>
                      <span class="cell-body"><span class="role-tag <?= e($avatarClass) ?>"><?= e($log['role']) ?></span></span>
                    </td>
                    <td>
                      <span class="mobile-label">Event</span>
                      <span class="cell-body">
                        <span class="event-cat <?= e($catClass) ?>"><i class="fa-solid <?= e(actionTypeIcon($log['action_type'])) ?>"></i><?= e($log['action_type']) ?></span>
                        <span class="event-desc"><?= e($log['action_label']) ?></span>
                      </span>
                    </td>
                    <td class="d-none d-xl-table-cell">
                      <span class="mobile-label">Company</span>
                      <span class="cell-body">
                        <?php if ($clientName !== ''): ?>
                          <span class="client-name" title="<?= e($clientName) ?>"><i class="fa-regular fa-building"></i><?= e($clientName) ?></span>
                          <?php if ($amountText !== ''): ?><span class="ts-rel"><?= e($amountText) ?></span><?php endif; ?>
                        <?php else: ?>
                          <span class="muted-dash">—</span>
                        <?php endif; ?>
                      </span>
                    </td>
                    <td class="d-none d-lg-table-cell">
                      <span class="mobile-label">Record</span>
                      <span class="cell-body">
                        <span class="target-ref" title="<?= e($targetText) ?>"><?= e($targetText) ?></span>
                        <?php if ($recStatus !== ''): ?><span class="d-block mt-1"><span class="status-badge <?= e(statusClass($recStatus)) ?>"><?= e($recStatus) ?></span></span><?php endif; ?>
                      </span>
                    </td>
                    <td class="ts">
                      <span class="mobile-label">When</span>
                      <span class="cell-body">
                        <span class="ts-main"><?= $ts !== false ? e(date('M d, Y · g:i A', $ts)) : '—' ?></span>
                        <?php if ($rel !== ''): ?><span class="ts-rel"><?= e($rel) ?></span><?php endif; ?>
                      </span>
                    </td>
                    <td class="row-chevron d-none d-md-table-cell"><i class="fa-solid fa-chevron-right"></i></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="log-foot">
          <span class="foot-info">Page <?= $page ?> of <?= $totalPages ?></span>
          <nav aria-label="Activity log pagination">
            <ul class="pager">
              <li>
                <?php if ($page <= 1): ?>
                  <span class="is-disabled"><i class="fa-solid fa-chevron-left"></i></span>
                <?php else: ?>
                  <a href="<?= e(buildLogPageUrl($page - 1, $roleFilter, $actionFilter, $searchTerm, $dateFilter)) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
              </li>
              <?php foreach (pageWindow($page, $totalPages) as $p): ?>
                <li>
                  <?php if ($p === '...'): ?>
                    <span class="is-gap">…</span>
                  <?php elseif ($p === $page): ?>
                    <span class="is-current" aria-current="page"><?= $p ?></span>
                  <?php else: ?>
                    <a href="<?= e(buildLogPageUrl($p, $roleFilter, $actionFilter, $searchTerm, $dateFilter)) ?>"><?= $p ?></a>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
              <li>
                <?php if ($page >= $totalPages): ?>
                  <span class="is-disabled"><i class="fa-solid fa-chevron-right"></i></span>
                <?php else: ?>
                  <a href="<?= e(buildLogPageUrl($page + 1, $roleFilter, $actionFilter, $searchTerm, $dateFilter)) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
              </li>
            </ul>
          </nav>
        </div>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<div class="modal fade detail-modal" id="logDetailModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content" id="detail_content">
      <div class="modal-header d-flex align-items-center justify-content-between">
        <h2 class="modal-title" id="modalTitle"><span class="mh-icon"><i id="detail_icon" class="fa-solid fa-circle-dot"></i></span><span id="modalTitleText">Event details</span></h2>
        <button type="button" class="btn-close m-0 ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="quote-shell">
          <div class="row g-2 g-md-3">

            <div class="col-lg-8">
              <div class="d-flex flex-column gap-2 gap-md-3">

                <div class="quote-panel">
                  <div class="quote-panel-title"><span class="step-icon"><i class="fa-solid fa-bolt"></i></span>What happened</div>
                  <p class="view-lead" id="detail_summary"></p>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title"><span class="step-icon"><i class="fa-solid fa-circle-info"></i></span>Overview</div>
                  <div class="row g-2 g-md-3">
                    <div class="col-6">
                      <div class="view-section-label">Event type</div>
                      <span class="event-cat" id="detail_cat"></span>
                    </div>
                    <div class="col-6" id="detail_status_wrap">
                      <div class="view-section-label">Status</div>
                      <span class="status-badge" id="detail_status"></span>
                    </div>
                    <div class="col-12" id="detail_client_wrap">
                      <div class="view-section-label">Company / Client</div>
                      <div class="view-value" id="detail_client"></div>
                    </div>
                    <div class="col-6" id="detail_related_wrap">
                      <div class="view-section-label">Assigned to</div>
                      <div class="view-value" id="detail_related"></div>
                    </div>
                    <div class="col-6" id="detail_amount_wrap">
                      <div class="view-section-label">Amount</div>
                      <div class="view-value money" id="detail_amount"></div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel" id="detail_note_wrap">
                  <div class="quote-panel-title"><span class="step-icon"><i class="fa-solid fa-align-left"></i></span>Details</div>
                  <p class="view-notes-box" id="detail_note"></p>
                </div>

              </div>
            </div>

            <div class="col-lg-4">
              <div class="quote-sticky d-flex flex-column gap-2 gap-md-3">

                <div class="quote-panel">
                  <div class="quote-panel-title"><span class="step-icon"><i class="fa-solid fa-user"></i></span>Performed by</div>
                  <div class="actor-card">
                    <span class="avatar" id="detail_avatar"></span>
                    <div class="min-w-0">
                      <div class="actor-card-name" id="detail_name"></div>
                      <div class="hero-actor-role" id="detail_role"></div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title"><span class="step-icon"><i class="fa-solid fa-hashtag"></i></span>Record &amp; Time</div>
                  <div class="mb-3">
                    <div class="view-section-label">Record</div>
                    <div class="view-value mono" id="detail_target"></div>
                  </div>
                  <div>
                    <div class="view-section-label">Timestamp</div>
                    <div class="view-value mono" id="detail_occurred"></div>
                    <div class="small mt-1" style="color:var(--text-3);" id="detail_relative"></div>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="modal-footer"></div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
const modalEl = document.getElementById('logDetailModal');
const detailModal = bootstrap.Modal.getOrCreateInstance(modalEl);
const catClasses = ['cat-assignment', 'cat-progress', 'cat-quotation', 'cat-contract', 'cat-revision', 'cat-repository'];

function setTile(wrapId, valueId, value) {
  const wrap = document.getElementById(wrapId);
  if (value) {
    wrap.classList.remove('d-none');
    document.getElementById(valueId).textContent = value;
  } else {
    wrap.classList.add('d-none');
  }
}

function openDetail(row) {
  const d = row.dataset;

  const content = document.getElementById('detail_content');
  catClasses.forEach(function (c) { content.classList.remove(c); });
  content.classList.add(d.catClass);

  document.getElementById('detail_icon').className = 'fa-solid ' + d.catIcon;
  document.getElementById('modalTitleText').textContent = d.actionType;

  const avatar = document.getElementById('detail_avatar');
  avatar.textContent = d.initials;
  avatar.className = 'avatar ' + d.roleClass;

  document.getElementById('detail_name').textContent = d.name;
  const role = document.getElementById('detail_role');
  role.textContent = d.role;
  role.className = 'hero-actor-role ' + d.roleClass;

  document.getElementById('detail_summary').textContent = d.summary || d.actionLabel;

  const cat = document.getElementById('detail_cat');
  cat.className = 'event-cat ' + d.catClass;
  cat.innerHTML = '';
  const icon = document.createElement('i');
  icon.className = 'fa-solid ' + d.catIcon;
  cat.appendChild(icon);
  cat.appendChild(document.createTextNode(d.actionType));

  const statusWrap = document.getElementById('detail_status_wrap');
  if (d.status) {
    statusWrap.classList.remove('d-none');
    const st = document.getElementById('detail_status');
    st.textContent = d.status;
    st.className = 'status-badge ' + (d.statusClass || '');
  } else {
    statusWrap.classList.add('d-none');
  }

  setTile('detail_client_wrap', 'detail_client', d.client);
  setTile('detail_amount_wrap', 'detail_amount', d.amount);
  setTile('detail_related_wrap', 'detail_related', d.related);
  setTile('detail_note_wrap', 'detail_note', d.note);

  document.getElementById('detail_target').textContent = d.target;
  document.getElementById('detail_occurred').textContent = d.occurred;
  document.getElementById('detail_relative').textContent = d.relative || '';

  detailModal.show();
}

document.querySelectorAll('.log-row').forEach(function (row) {
  row.addEventListener('click', function () { openDetail(row); });
  row.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' || ev.key === ' ') {
      ev.preventDefault();
      openDetail(row);
    }
  });
});

let searchDebounce;
const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('input', function () {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(function () {
    document.getElementById('filterForm').submit();
  }, 500);
});

if (searchInput.value) {
  const len = searchInput.value.length;
  if (document.activeElement === document.body && new URLSearchParams(location.search).has('search')) {
    searchInput.focus();
    searchInput.setSelectionRange(len, len);
  }
}

document.addEventListener('keydown', function (ev) {
  if (ev.key === '/' && !/INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName)) {
    ev.preventDefault();
    searchInput.focus();
  }
});
</script>

</body>
</html>