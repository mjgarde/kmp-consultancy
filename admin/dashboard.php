<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();
$adminFullname = $_SESSION['fullname'] ?? 'Admin';

$URL = [
    'requests'   => 'client_management.php?tab=requests',
    'clients'    => 'client_management.php',
    'quotations' => 'quotations.php',
    'contracts'  => 'contracts.php',
    'staff'      => 'staff_management.php',
];

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function peso($n): string { return '&#8369;' . number_format((float) $n, 2); }
function pesoShort($n): string {
    $n = (float) $n;
    if ($n >= 1000000) return '&#8369;' . round($n / 1000000, 1) . 'M';
    if ($n >= 1000) return '&#8369;' . round($n / 1000, 1) . 'K';
    return '&#8369;' . round($n);
}
function run(PDO $pdo, string $sql, array $p = []): PDOStatement {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st;
}
function scalar(PDO $pdo, string $sql, array $p = []) { return run($pdo, $sql, $p)->fetchColumn(); }
function rows(PDO $pdo, string $sql, array $p = []): array { return run($pdo, $sql, $p)->fetchAll(PDO::FETCH_ASSOC); }
function initials(string $name): string {
    $parts = array_values(array_filter(preg_split('/\s+/', trim($name))));
    if (!$parts) return '?';
    $a = strtoupper(substr($parts[0], 0, 1));
    $b = count($parts) > 1 ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $a . $b;
}
function timeAgo(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' hr ago';
    if ($d < 604800) return floor($d / 86400) . ' day' . (floor($d / 86400) > 1 ? 's' : '') . ' ago';
    return date('M d, Y', strtotime($dt));
}
function delta($cur, $prev, bool $has): string {
    if (!$has) return '';
    if ($prev == 0) return $cur > 0 ? '<span class="delta">New</span>' : '';
    $pct = round((($cur - $prev) / $prev) * 100);
    if ($pct === 0) return '<span class="delta">0%</span>';
    return '<span class="delta">' . ($pct > 0 ? '&#9650; ' : '&#9660; ') . abs($pct) . '%</span>';
}
function statusPill(string $s): string {
    $map = [
        'New' => 'pl-blue', 'In Progress' => 'pl-ochre', 'Completed' => 'pl-sage', 'Cancelled' => 'pl-slate',
        'Draft' => 'pl-slate', 'Approved' => 'pl-sage', 'Rejected' => 'pl-terra', 'Revert' => 'pl-plum',
    ];
    return '<span class="pill ' . ($map[$s] ?? 'pl-slate') . '">' . h($s) . '</span>';
}

$period = $_GET['period'] ?? 'month';
if (!in_array($period, ['month', 'quarter', 'year', 'all', 'custom'], true)) $period = 'month';
$today = new DateTime('today');
$from = clone $today;
$to = clone $today;

switch ($period) {
    case 'quarter':
        $m = (int) $today->format('n');
        $from->setDate((int) $today->format('Y'), $m - (($m - 1) % 3), 1);
        break;
    case 'year':
        $from->setDate((int) $today->format('Y'), 1, 1);
        break;
    case 'all':
        $from->setDate(2000, 1, 1);
        break;
    case 'custom':
        $f = DateTime::createFromFormat('Y-m-d', $_GET['from'] ?? '');
        $t = DateTime::createFromFormat('Y-m-d', $_GET['to'] ?? '');
        if ($f && $t) {
            if ($f > $t) { [$f, $t] = [$t, $f]; }
            $from = $f;
            $to = $t;
        } else {
            $period = 'month';
            $from->setDate((int) $today->format('Y'), (int) $today->format('n'), 1);
        }
        break;
    default:
        $from->setDate((int) $today->format('Y'), (int) $today->format('n'), 1);
}

$R = [$from->format('Y-m-d') . ' 00:00:00', $to->format('Y-m-d') . ' 23:59:59'];
$hasPrev = $period !== 'all';
$P = $R;
if ($hasPrev) {
    $days = (int) $from->diff($to)->days + 1;
    $prevEnd = (clone $from)->modify('-1 day');
    $prevStart = (clone $prevEnd)->modify('-' . ($days - 1) . ' days');
    $P = [$prevStart->format('Y-m-d') . ' 00:00:00', $prevEnd->format('Y-m-d') . ' 23:59:59'];
}
$periodLabel = $period === 'all' ? 'All time' : $from->format('M d, Y') . ' – ' . $to->format('M d, Y');

$contractValueSql = "SELECT COALESCE(SUM(total_amount),0) FROM contracts WHERE status='Approved' AND COALESCE(approved_at, created_at) BETWEEN ? AND ?";

$totalClients = (int) scalar($pdo, 'SELECT COUNT(*) FROM clients');
$newClients = (int) scalar($pdo, 'SELECT COUNT(*) FROM clients WHERE created_at BETWEEN ? AND ?', $R);
$newClientsPrev = (int) scalar($pdo, 'SELECT COUNT(*) FROM clients WHERE created_at BETWEEN ? AND ?', $P);
$reqCount = (int) scalar($pdo, 'SELECT COUNT(*) FROM service_requests WHERE created_at BETWEEN ? AND ?', $R);
$reqCountPrev = (int) scalar($pdo, 'SELECT COUNT(*) FROM service_requests WHERE created_at BETWEEN ? AND ?', $P);
$contractValue = (float) scalar($pdo, $contractValueSql, $R);
$contractValuePrev = (float) scalar($pdo, $contractValueSql, $P);
$quotationValue = (float) scalar($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM quotations WHERE status='Approved' AND created_at BETWEEN ? AND ?", $R);
$draftValue = (float) scalar($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM quotations WHERE status='Draft'");
$awaitingAssignment = (int) scalar($pdo,
    "SELECT COUNT(*) FROM service_requests sr
     INNER JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'");
$activeStaffCount = (int) scalar($pdo, "SELECT COUNT(*) FROM users WHERE role='staff' AND status='Active'");
$revertCount = (int) scalar($pdo, "SELECT (SELECT COUNT(*) FROM quotations WHERE status='Revert') + (SELECT COUNT(*) FROM contracts WHERE status='Revert')");

function countBy(PDO $pdo, string $table, array $statuses, array $R): array {
    $out = [];
    foreach ($statuses as $s) {
        $out[$s] = (int) scalar($pdo, "SELECT COUNT(*) FROM `$table` WHERE status = ? AND created_at BETWEEN ? AND ?", [$s, $R[0], $R[1]]);
    }
    return $out;
}
$reqS = countBy($pdo, 'service_requests', ['New', 'In Progress', 'Completed', 'Cancelled'], $R);
$quoS = countBy($pdo, 'quotations', ['Draft', 'Approved', 'Rejected', 'Revert'], $R);
$conS = countBy($pdo, 'contracts', ['Draft', 'Approved', 'Rejected', 'Revert'], $R);

$cReq = ['#3F6C8F', '#B07A2A', '#3E7D5A', '#8B8F92'];
$cDoc = ['#8B8F92', '#3E7D5A', '#B5523F', '#7B4F7D'];

$funnel = [
    ['Requests', $reqCount, '#3F6C8F'],
    ['Quotations', array_sum($quoS), '#7B4F7D'],
    ['Approved Quotes', $quoS['Approved'], '#2F6F6A'],
    ['Approved Contracts', $conS['Approved'], '#3E7D5A'],
    ['Completed', $reqS['Completed'], '#B07A2A'],
];

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTime('first day of this month');
    $d->modify("-$i month");
    $months[$d->format('Y-m')] = ['label' => $d->format('M'), 'q' => 0.0, 'c' => 0.0];
}
$firstMonth = array_key_first($months) . '-01 00:00:00';
foreach (rows($pdo, "SELECT DATE_FORMAT(COALESCE(approved_at, created_at), '%Y-%m') ym, SUM(total_amount) v
                     FROM contracts WHERE status='Approved' AND COALESCE(approved_at, created_at) >= ? GROUP BY ym", [$firstMonth]) as $r) {
    if (isset($months[$r['ym']])) $months[$r['ym']]['c'] = (float) $r['v'];
}
foreach (rows($pdo, "SELECT DATE_FORMAT(created_at, '%Y-%m') ym, SUM(total_amount) v
                     FROM quotations WHERE status='Approved' AND created_at >= ? GROUP BY ym", [$firstMonth]) as $r) {
    if (isset($months[$r['ym']])) $months[$r['ym']]['q'] = (float) $r['v'];
}

$attention = [];

$awaitRows = rows($pdo,
    "SELECT ct.contract_number, ct.start_date, sr.request_title, sr.required_skill, c.company_name
     FROM service_requests sr
     INNER JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'
     ORDER BY ct.start_date IS NULL, ct.start_date ASC LIMIT 4");
foreach ($awaitRows as $r) {
    $match = '';
    if (!empty($r['required_skill'])) {
        $mm = rows($pdo,
            "SELECT DISTINCT u.firstname, u.lastname FROM staff_skills s
             INNER JOIN users u ON u.user_id = s.user_id
             WHERE u.role='staff' AND u.status='Active' AND s.skill_name = ? LIMIT 3", [$r['required_skill']]);
        $match = $mm ? 'Suggested: ' . implode(', ', array_map(fn($x) => $x['firstname'] . ' ' . $x['lastname'], $mm)) : 'No staff with matching skill';
    }
    $soon = $r['start_date'] && strtotime($r['start_date']) <= strtotime('+7 days');
    $attention[] = [
        'sev' => $soon ? 'high' : 'med', 'icon' => 'fa-user-clock', 'tag' => 'Assign staff',
        'title' => $r['request_title'] . ' - ' . $r['company_name'],
        'sub' => $r['contract_number'] . ($r['start_date'] ? ' | Starts ' . date('M d', strtotime($r['start_date'])) : '') . ($match ? ' | ' . $match : ''),
        'url' => $URL['requests'],
    ];
}

$revertRows = rows($pdo,
    "SELECT 'Quotation' kind, q.quotation_number ref, c.company_name, q.updated_at dt
       FROM quotations q INNER JOIN clients c ON c.client_id = q.client_id WHERE q.status = 'Revert'
     UNION ALL
     SELECT 'Contract', ct.contract_number, c.company_name, ct.updated_at
       FROM contracts ct INNER JOIN clients c ON c.client_id = ct.client_id WHERE ct.status = 'Revert'
     ORDER BY dt DESC LIMIT 3");
foreach ($revertRows as $r) {
    $attention[] = [
        'sev' => 'med', 'icon' => 'fa-rotate-left', 'tag' => 'For revision',
        'title' => $r['kind'] . ' ' . $r['ref'] . ' - ' . $r['company_name'],
        'sub' => 'Sent back for revision ' . timeAgo($r['dt']),
        'url' => $r['kind'] === 'Quotation' ? $URL['quotations'] : $URL['contracts'],
    ];
}

$expRows = rows($pdo,
    "SELECT q.quotation_number, q.valid_until, c.company_name
     FROM quotations q INNER JOIN clients c ON c.client_id = q.client_id
     WHERE q.status = 'Draft' AND q.valid_until IS NOT NULL AND q.valid_until <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
     ORDER BY q.valid_until ASC LIMIT 3");
foreach ($expRows as $r) {
    $expired = strtotime($r['valid_until']) < strtotime('today');
    $attention[] = [
        'sev' => $expired ? 'high' : 'low', 'icon' => 'fa-hourglass-half', 'tag' => $expired ? 'Expired' : 'Expiring soon',
        'title' => 'Quotation ' . $r['quotation_number'] . ' - ' . $r['company_name'],
        'sub' => ($expired ? 'Expired ' : 'Valid until ') . date('M d, Y', strtotime($r['valid_until'])),
        'url' => $URL['quotations'],
    ];
}

$noQuoRows = rows($pdo,
    "SELECT sr.request_title, c.company_name, sr.created_at
     FROM service_requests sr INNER JOIN clients c ON c.client_id = sr.client_id
     WHERE sr.status = 'New' AND NOT EXISTS (SELECT 1 FROM quotations q WHERE q.request_id = sr.request_id)
     ORDER BY sr.created_at ASC LIMIT 3");
foreach ($noQuoRows as $r) {
    $attention[] = [
        'sev' => 'low', 'icon' => 'fa-file-circle-plus', 'tag' => 'Needs quotation',
        'title' => $r['request_title'] . ' - ' . $r['company_name'],
        'sub' => 'No quotation yet | Received ' . timeAgo($r['created_at']),
        'url' => $URL['quotations'],
    ];
}
$sevOrder = ['high' => 0, 'med' => 1, 'low' => 2];
usort($attention, fn($a, $b) => $sevOrder[$a['sev']] <=> $sevOrder[$b['sev']]);
$attention = array_slice($attention, 0, 7);

$staffWorkload = rows($pdo,
    "SELECT u.user_id, u.firstname, u.lastname,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'In Progress') AS workload
     FROM users u WHERE u.role = 'staff' AND u.status = 'Active'
     ORDER BY workload DESC, u.firstname ASC LIMIT 6");

$topClients = rows($pdo,
    "SELECT c.company_name, c.industry, COALESCE(SUM(ct.total_amount),0) v, COUNT(ct.contract_id) n
     FROM clients c LEFT JOIN contracts ct ON ct.client_id = c.client_id AND ct.status = 'Approved'
     GROUP BY c.client_id, c.company_name, c.industry
     ORDER BY v DESC, c.company_name ASC LIMIT 5");
$topMax = max(1, (float) ($topClients[0]['v'] ?? 0));

$activity = rows($pdo,
    "(SELECT 'request' k, sr.request_title t, c.company_name co, sr.status st, sr.created_at dt
        FROM service_requests sr INNER JOIN clients c ON c.client_id = sr.client_id)
     UNION ALL
     (SELECT 'quotation', q.quotation_number, c.company_name, q.status, q.created_at
        FROM quotations q INNER JOIN clients c ON c.client_id = q.client_id)
     UNION ALL
     (SELECT 'contract', ct.contract_number, c.company_name, ct.status, ct.created_at
        FROM contracts ct INNER JOIN clients c ON c.client_id = ct.client_id)
     ORDER BY dt DESC LIMIT 7");
$actMeta = [
    'request'   => ['fa-clipboard-list', 'tn-blue'],
    'quotation' => ['fa-file-invoice-dollar', 'tn-plum'],
    'contract'  => ['fa-file-signature', 'tn-teal'],
];
$avatarColors = ['#2F6F6A', '#3F6C8F', '#A06B1F', '#B5523F', '#7B4F7D', '#3E7D5A'];

$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$presetOptions = ['month' => 'This Month', 'quarter' => 'This Quarter', 'year' => 'This Year', 'all' => 'All Time'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Dashboard</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<script src="../assets/vendor/chartjs/chart.umd.js"></script>
<style>
:root {
  --canvas: #FAF9F6;
  --card: #FFFEFC;
  --surface: #F6F4EF;
  --line: #E6E2DA;
  --ink: #2A2D2F;
  --ink-soft: #6E7275;
  --charcoal: #2B3134;

  --teal: #2F6F6A;   --teal-soft: #E3EFEC;   --teal-text: #245853;
  --blue: #3F6C8F;   --blue-soft: #E2ECF3;   --blue-text: #2E5272;
  --ochre: #A06B1F;  --ochre-soft: #F6EBD6;  --ochre-text: #7F5719;
  --terra: #B5523F;  --terra-soft: #F8E9E5;  --terra-text: #8C3D2E;
  --sage: #3E7D5A;   --sage-soft: #E6F1EA;   --sage-text: #2C5E42;
  --plum: #7B4F7D;   --plum-soft: #F0E6F1;   --plum-text: #5E3A60;
  --slate: #7A7E81;  --slate-soft: #EFEDE8;  --slate-text: #55595C;
}

* { -webkit-tap-highlight-color: transparent; }
body { background: var(--canvas); color: var(--ink); font-family: 'Inter', -apple-system, sans-serif; overflow-x: hidden; }
.dashboard-layout, .dashboard-main, .dashboard-content { background: var(--canvas) !important; }
.dashboard-main { min-width: 0; max-width: 100%; }
.dashboard-title, h1, h2, h3, .num { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--charcoal); letter-spacing: -.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background: var(--card); }

.card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 1px 2px rgba(42,45,47,.04); height: 100%; }
.card-header { background: var(--card) !important; border-bottom: 1px solid var(--line) !important; border-radius: 12px 12px 0 0 !important; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
.card-header h2 { color: var(--charcoal); letter-spacing: -.01em; font-size: .95rem; font-weight: 700; margin: 0; }
.card-header p { color: var(--ink-soft); font-size: .76rem; margin: .15rem 0 0; }
.card-body { padding: 1rem 1.25rem; }

.form-control { border-color: var(--line); background: var(--card); font-size: .85rem; }
.form-control:focus { border-color: var(--teal); box-shadow: 0 0 0 .2rem rgba(47,111,106,.14); }
.filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; }
.preset-group { display: inline-flex; background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: .2rem; gap: .15rem; max-width: 100%; overflow-x: auto; scrollbar-width: none; }
.preset-group::-webkit-scrollbar { display: none; }
.preset-btn { border-radius: 8px; padding: .4rem .8rem; font-size: .8rem; font-weight: 600; color: var(--slate-text); text-decoration: none; white-space: nowrap; transition: .15s; }
.preset-btn:hover { color: var(--charcoal); }
.preset-btn.active { background: var(--card); color: var(--teal-text); box-shadow: 0 1px 2px rgba(42,45,47,.1); }
.filter-dates { display: flex; align-items: center; gap: .5rem; flex: 1 1 320px; }
.filter-dates .form-control { flex: 1 1 0; min-width: 0; }
.to-label { font-size: .8rem; color: var(--ink-soft); }
.btn-apply { background: var(--teal); color: #fff; border: 0; border-radius: 8px; font-weight: 600; font-size: .85rem; padding: .45rem 1.1rem; }
.btn-apply:hover { background: #245853; color: #fff; }
.btn-ghost { display: inline-flex; align-items: center; gap: .5rem; background: transparent; color: var(--charcoal); border: 1px solid var(--line); border-radius: 8px; font-size: .85rem; font-weight: 700; padding: .45rem .95rem; text-decoration: none; transition: background .15s; }
.btn-ghost:hover { background: var(--surface); color: var(--charcoal); }

.kpi { display: block; border-radius: 12px; padding: 1.1rem 1.2rem; color: #fff; text-decoration: none; height: 100%; transition: transform .15s, filter .15s; }
.kpi:hover { color: #fff; transform: translateY(-2px); filter: brightness(1.06); }
.kpi-teal { background: #2F6F6A; }
.kpi-blue { background: #3F6C8F; }
.kpi-ochre { background: #A06B1F; }
.kpi-terra { background: #B5523F; }
.kpi-top { display: flex; justify-content: space-between; align-items: center; }
.kpi-label { font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; opacity: .92; }
.kpi-icon { width: 34px; height: 34px; border-radius: 9px; background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; font-size: .85rem; }
.kpi-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; margin-top: .8rem; word-break: break-word; }
.kpi-sub { font-size: .72rem; opacity: .92; margin-top: .3rem; display: flex; gap: .45rem; align-items: center; flex-wrap: wrap; }
.delta { background: rgba(255,255,255,.22); font-size: .66rem; font-weight: 700; padding: .12rem .5rem; border-radius: 999px; }

.mini { border-radius: 12px; padding: .8rem 1rem; display: flex; align-items: center; gap: .8rem; height: 100%; }
.mini .ic { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.7); font-size: .82rem; }
.mini .lbl { font-size: .64rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; opacity: .85; }
.mini .val { font-size: 1.02rem; font-weight: 700; font-family: 'Lexend', sans-serif; }
.mn-blue { background: var(--blue-soft); color: var(--blue-text); }
.mn-teal { background: var(--teal-soft); color: var(--teal-text); }
.mn-ochre { background: var(--ochre-soft); color: var(--ochre-text); }
.mn-terra { background: var(--terra-soft); color: var(--terra-text); }

.tn-blue { background: var(--blue-soft); color: var(--blue-text); }
.tn-teal { background: var(--teal-soft); color: var(--teal-text); }
.tn-plum { background: var(--plum-soft); color: var(--plum-text); }
.tn-ochre { background: var(--ochre-soft); color: var(--ochre-text); }
.tn-terra { background: var(--terra-soft); color: var(--terra-text); }

.att-list { display: flex; flex-direction: column; gap: .25rem; }
.att { display: flex; align-items: center; gap: .85rem; padding: .65rem .7rem; border-radius: 10px; text-decoration: none; color: var(--ink); transition: background .15s; }
.att:hover { background: var(--surface); color: var(--ink); }
.att .ic { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .85rem; }
.att-body { flex: 1; min-width: 0; }
.att-title { font-size: .83rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.att-sub { font-size: .73rem; color: var(--ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: .1rem; }
.tag { font-size: .66rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; white-space: nowrap; }
.att.high .ic, .att.high .tag { background: var(--terra-soft); color: var(--terra-text); }
.att.med .ic, .att.med .tag { background: var(--ochre-soft); color: var(--ochre-text); }
.att.low .ic, .att.low .tag { background: var(--blue-soft); color: var(--blue-text); }
.count-badge { background: var(--terra); color: #fff; font-size: .7rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; white-space: nowrap; }

.canvas-box { position: relative; width: 100%; }
.pipe-title { font-size: .78rem; font-weight: 700; color: var(--charcoal); text-align: center; margin-bottom: .3rem; }

.pill { display: inline-block; font-size: .68rem; font-weight: 700; padding: .25rem .65rem; border-radius: 999px; white-space: nowrap; }
.pl-blue { background: var(--blue-soft); color: var(--blue-text); }
.pl-ochre { background: var(--ochre-soft); color: var(--ochre-text); }
.pl-sage { background: var(--sage-soft); color: var(--sage-text); }
.pl-slate { background: var(--slate-soft); color: var(--slate-text); }
.pl-terra { background: var(--terra-soft); color: var(--terra-text); }
.pl-plum { background: var(--plum-soft); color: var(--plum-text); }

.staff-row { display: flex; align-items: center; gap: .8rem; margin-bottom: .95rem; }
.staff-row:last-child { margin-bottom: 0; }
.avatar { width: 36px; height: 36px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; flex-shrink: 0; }
.staff-meta { flex: 1; min-width: 0; }
.staff-name { font-size: .82rem; font-weight: 600; color: var(--charcoal); display: flex; justify-content: space-between; gap: .5rem; }
.staff-name span { font-weight: 500; color: var(--ink-soft); font-size: .72rem; white-space: nowrap; }
.bar-track { background: var(--slate-soft); border-radius: 999px; height: 7px; overflow: hidden; margin-top: .35rem; }
.bar-fill { height: 100%; border-radius: 999px; }

.row-item { display: flex; align-items: center; gap: .8rem; padding: .6rem 0; }
.row-item + .row-item { border-top: 1px solid var(--line); }
.row-item:first-child { padding-top: 0; }
.row-item .ic { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .8rem; }
.row-main { flex: 1; min-width: 0; }
.row-title { font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--charcoal); }
.row-sub { font-size: .72rem; color: var(--ink-soft); }
.rank { width: 28px; height: 28px; border-radius: 8px; font-size: .74rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--slate-soft); color: var(--slate-text); }
.rank.r1 { background: var(--ochre); color: #fff; }
.rank.r2 { background: var(--blue); color: #fff; }
.rank.r3 { background: var(--plum); color: #fff; }
.empty { text-align: center; padding: 1.6rem 0; color: var(--ink-soft); font-size: .8rem; }
.empty i { font-size: 1.5rem; display: block; margin-bottom: .5rem; color: #CFCAC0; }
.link-more { font-size: .74rem; font-weight: 600; color: var(--teal-text); text-decoration: none; background: var(--teal-soft); padding: .3rem .75rem; border-radius: 999px; white-space: nowrap; }
.link-more:hover { background: #D5E8E4; color: var(--teal-text); }

.print-header { display: none; }

@media (max-width: 767.98px) {
  .dashboard-content { padding: .65rem !important; }
  .card-header { padding: .8rem .9rem; }
  .card-body { padding: .85rem .9rem; }
  .preset-group { width: 100%; }
  .preset-btn { flex: 1 0 auto; text-align: center; font-size: .74rem; padding: .4rem .6rem; }
  .filter-dates { flex: 1 1 100%; }
  .kpi { padding: .85rem .95rem; }
  .kpi-value { font-size: 1.3rem; }
}

@media print {
  @page { size: A4; margin: 14mm; }
  .no-print { display: none !important; }
  .print-header { display: block; margin-bottom: 16px; color: #111; font-family: 'Inter', Arial, Helvetica, sans-serif; }
  .print-header .p-letterhead { display: flex; align-items: center; gap: 12px; padding-bottom: 12px; border-bottom: 2px solid #111; }
  .print-header .p-letterhead img { width: 60px; height: 60px; object-fit: contain; }
  .print-header .p-company { font-size: 14pt; font-weight: 700; letter-spacing: .03em; line-height: 1.2; }
  .print-header .p-tagline { font-size: 9pt; color: #555; font-style: italic; margin-top: 2px; }
  .print-header .p-title { text-align: center; font-size: 13pt; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; margin: 18px 0 4px; }
  .print-header .p-subtitle { text-align: center; font-size: 9.5pt; color: #444; }
  body, .dashboard-main, .dashboard-content { background: #fff !important; }
  .dashboard-layout { display: block !important; }
  .dashboard-layout > *:not(.dashboard-main) { display: none !important; }
  .dashboard-main { width: 100% !important; margin: 0 !important; }
  .card, .kpi, .mini { break-inside: avoid; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>

<body>
<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1">

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4 no-print">
      <div class="d-flex align-items-center gap-3" style="min-width:0;">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div style="min-width:0;">
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Dashboard</h1>
          <p class="dashboard-subtitle small mb-0 text-truncate d-none d-sm-block"><?= h($greet) ?>, <?= h($adminFullname) ?>.</p>
        </div>
      </div>
      <button type="button" class="btn-ghost" onclick="window.print()"><i class="fa-solid fa-print"></i><span class="d-none d-sm-inline">Print</span></button>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="print-header">
        <div class="p-letterhead">
          <img src="../assets/img/system_img/logo.png" alt="KMP Integrated Enterprise, Inc.">
          <div>
            <div class="p-company">KMP INTEGRATED ENTERPRISE, INC.</div>
            <div class="p-tagline">Shaping Smarter Solutions.</div>
          </div>
        </div>
        <div class="p-title">Dashboard Summary</div>
        <div class="p-subtitle"><?= h($periodLabel) ?></div>
      </div>

      <section class="card mb-3 no-print" style="height:auto;">
        <div class="card-body p-2 p-md-3">
          <form method="get" class="filter-form">
            <div class="preset-group" role="group" aria-label="Quick date ranges">
              <?php foreach ($presetOptions as $key => $label): ?>
                <a href="?period=<?= $key ?>" class="preset-btn <?= $period === $key ? 'active' : '' ?>"><?= h($label) ?></a>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="period" value="custom">
            <div class="filter-dates">
              <input type="date" name="from" value="<?= h($from->format('Y-m-d')) ?>" class="form-control" aria-label="From date">
              <span class="to-label">to</span>
              <input type="date" name="to" value="<?= h($to->format('Y-m-d')) ?>" class="form-control" aria-label="To date">
            </div>
            <button type="submit" class="btn btn-apply">Apply</button>
          </form>
        </div>
      </section>

      <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-xl-3">
          <a href="<?= h($URL['requests']) ?>" class="kpi kpi-teal">
            <div class="kpi-top"><span class="kpi-label">Service Requests</span><span class="kpi-icon"><i class="fa-solid fa-clipboard-list"></i></span></div>
            <div class="kpi-value num"><?= $reqCount ?></div>
            <div class="kpi-sub"><?= delta($reqCount, $reqCountPrev, $hasPrev) ?><span><?= $hasPrev ? 'vs previous period' : 'all time' ?></span></div>
          </a>
        </div>
        <div class="col-6 col-xl-3">
          <a href="<?= h($URL['contracts']) ?>" class="kpi kpi-blue">
            <div class="kpi-top"><span class="kpi-label">Approved Contracts</span><span class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></span></div>
            <div class="kpi-value num" style="font-size:1.35rem;"><?= peso($contractValue) ?></div>
            <div class="kpi-sub"><?= delta($contractValue, $contractValuePrev, $hasPrev) ?><span><?= $conS['Approved'] ?> contract<?= $conS['Approved'] === 1 ? '' : 's' ?></span></div>
          </a>
        </div>
        <div class="col-6 col-xl-3">
          <a href="<?= h($URL['requests']) ?>" class="kpi kpi-ochre">
            <div class="kpi-top"><span class="kpi-label">Awaiting Assignment</span><span class="kpi-icon"><i class="fa-solid fa-user-clock"></i></span></div>
            <div class="kpi-value num"><?= $awaitingAssignment ?></div>
            <div class="kpi-sub"><span>Approved contracts with no staff</span></div>
          </a>
        </div>
        <div class="col-6 col-xl-3">
          <a href="<?= h($URL['clients']) ?>" class="kpi kpi-terra">
            <div class="kpi-top"><span class="kpi-label">Total Clients</span><span class="kpi-icon"><i class="fa-solid fa-building"></i></span></div>
            <div class="kpi-value num"><?= $totalClients ?></div>
            <div class="kpi-sub"><?= delta($newClients, $newClientsPrev, $hasPrev) ?><span><?= $newClients ?> new in period</span></div>
          </a>
        </div>
      </div>

      <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-lg-3"><div class="mini mn-blue"><span class="ic"><i class="fa-solid fa-file-invoice-dollar"></i></span><div><div class="lbl">Approved Quotes</div><div class="val"><?= pesoShort($quotationValue) ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="mini mn-teal"><span class="ic"><i class="fa-solid fa-hourglass-half"></i></span><div><div class="lbl">Draft Pipeline</div><div class="val"><?= pesoShort($draftValue) ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="mini mn-ochre"><span class="ic"><i class="fa-solid fa-users"></i></span><div><div class="lbl">Active Staff</div><div class="val"><?= $activeStaffCount ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="mini mn-terra"><span class="ic"><i class="fa-solid fa-rotate-left"></i></span><div><div class="lbl">For Revision</div><div class="val"><?= $revertCount ?></div></div></div></div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-7">
          <section class="card">
            <div class="card-header">
              <div><h2>Needs Attention</h2><p>Items that are waiting on you right now.</p></div>
              <?php if ($attention): ?><span class="count-badge"><?= count($attention) ?> open</span><?php endif; ?>
            </div>
            <div class="card-body">
              <?php if (!$attention): ?>
                <div class="empty"><i class="fa-regular fa-circle-check"></i>You're all caught up.</div>
              <?php else: ?>
                <div class="att-list">
                  <?php foreach ($attention as $a): ?>
                    <a href="<?= h($a['url']) ?>" class="att <?= h($a['sev']) ?>">
                      <span class="ic"><i class="fa-solid <?= h($a['icon']) ?>"></i></span>
                      <span class="att-body">
                        <div class="att-title"><?= h($a['title']) ?></div>
                        <div class="att-sub"><?= h($a['sub']) ?></div>
                      </span>
                      <span class="tag"><?= h($a['tag']) ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>
        </div>
        <div class="col-xl-5">
          <section class="card">
            <div class="card-header"><div><h2>Approved Value</h2><p>Last 6 months, quotations vs contracts.</p></div></div>
            <div class="card-body"><div class="canvas-box" style="height:290px;"><canvas id="chartRevenue"></canvas></div></div>
          </section>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-xl-7">
          <section class="card">
            <div class="card-header"><div><h2>Pipeline Overview</h2><p>Created within the selected period.</p></div></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4"><div class="pipe-title">Requests (<?= array_sum($reqS) ?>)</div><div class="canvas-box" style="height:230px;"><canvas id="chartReq"></canvas></div></div>
                <div class="col-md-4"><div class="pipe-title">Quotations (<?= array_sum($quoS) ?>)</div><div class="canvas-box" style="height:230px;"><canvas id="chartQuo"></canvas></div></div>
                <div class="col-md-4"><div class="pipe-title">Contracts (<?= array_sum($conS) ?>)</div><div class="canvas-box" style="height:230px;"><canvas id="chartCon"></canvas></div></div>
              </div>
            </div>
          </section>
        </div>
        <div class="col-xl-5">
          <section class="card">
            <div class="card-header"><div><h2>Conversion Funnel</h2><p>Where the work piles up.</p></div></div>
            <div class="card-body"><div class="canvas-box" style="height:270px;"><canvas id="chartFunnel"></canvas></div></div>
          </section>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-4">
          <section class="card">
            <div class="card-header">
              <div><h2>Staff Workload</h2><p><?= $activeStaffCount ?> active staff</p></div>
              <a href="<?= h($URL['staff']) ?>" class="link-more no-print">Manage</a>
            </div>
            <div class="card-body">
              <?php if (!$staffWorkload): ?>
                <div class="empty"><i class="fa-regular fa-user"></i>No active staff.</div>
              <?php else:
                $maxW = max(3, (int) max(array_column($staffWorkload, 'workload')));
                foreach ($staffWorkload as $i => $s):
                  $w = (int) $s['workload'];
                  $full = $s['firstname'] . ' ' . $s['lastname'];
                  $lp = $w === 0 ? ['Available', 'pl-sage'] : ($w <= 2 ? ['Moderate', 'pl-ochre'] : ['Busy', 'pl-terra']);
                  $col = $avatarColors[$i % count($avatarColors)];
              ?>
                <div class="staff-row">
                  <div class="avatar" style="background:<?= $col ?>;"><?= h(initials($full)) ?></div>
                  <div class="staff-meta">
                    <div class="staff-name"><?= h($full) ?> <span><?= $w ?> task<?= $w === 1 ? '' : 's' ?></span></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= round(($w / $maxW) * 100) ?>%;background:<?= $col ?>;"></div></div>
                  </div>
                  <span class="pill <?= $lp[1] ?>"><?= $lp[0] ?></span>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="card">
            <div class="card-header"><div><h2>Top Clients</h2><p>By approved contract value</p></div></div>
            <div class="card-body">
              <?php if (!$topClients): ?>
                <div class="empty"><i class="fa-regular fa-building"></i>No clients yet.</div>
              <?php else: foreach ($topClients as $i => $c): ?>
                <div class="row-item">
                  <span class="rank <?= $i < 3 ? 'r' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
                  <div class="row-main">
                    <div class="row-title"><?= h($c['company_name']) ?></div>
                    <div class="bar-track"><div class="bar-fill" style="width:<?= round(((float) $c['v'] / $topMax) * 100) ?>%;background:#2F6F6A;"></div></div>
                    <div class="row-sub mt-1"><?= h($c['industry'] ?: 'No industry') ?> &middot; <?= (int) $c['n'] ?> contract<?= (int) $c['n'] === 1 ? '' : 's' ?></div>
                  </div>
                  <div class="fw-bold" style="font-size:.78rem;color:var(--charcoal);"><?= pesoShort($c['v']) ?></div>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-4">
          <section class="card">
            <div class="card-header"><div><h2>Recent Activity</h2><p>Requests, quotations, and contracts</p></div></div>
            <div class="card-body">
              <?php if (!$activity): ?>
                <div class="empty"><i class="fa-regular fa-folder-open"></i>No activity yet.</div>
              <?php else: foreach ($activity as $a): $m = $actMeta[$a['k']]; ?>
                <div class="row-item">
                  <span class="ic <?= $m[1] ?>"><i class="fa-solid <?= $m[0] ?>"></i></span>
                  <div class="row-main">
                    <div class="row-title"><?= h($a['t']) ?></div>
                    <div class="row-sub"><?= h($a['co']) ?> &middot; <?= h(timeAgo($a['dt'])) ?></div>
                  </div>
                  <?= statusPill($a['st']) ?>
                </div>
              <?php endforeach; endif; ?>
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
  if (typeof Chart === 'undefined') return;

  Chart.defaults.font = { family: 'Inter', size: 11 };
  Chart.defaults.color = '#6E7275';

  var sign = '\u20B1';
  var grid = '#E6E2DA';
  var fmt = function (v) { return sign + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var short = function (v) {
    if (v >= 1000000) return sign + (v / 1000000) + 'M';
    if (v >= 1000) return sign + (v / 1000) + 'K';
    return sign + v;
  };

  new Chart(document.getElementById('chartRevenue'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column(array_values($months), 'label')) ?>,
      datasets: [
        { label: 'Quotations', data: <?= json_encode(array_column(array_values($months), 'q')) ?>, backgroundColor: '#3F6C8F', borderRadius: 6, maxBarThickness: 22 },
        { label: 'Contracts', data: <?= json_encode(array_column(array_values($months), 'c')) ?>, backgroundColor: '#2F6F6A', borderRadius: 6, maxBarThickness: 22 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, padding: 14 } },
        tooltip: { callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + fmt(c.parsed.y); } } }
      },
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { callback: short } }
      }
    }
  });

  function donut(id, labels, data, colors) {
    var total = data.reduce(function (a, b) { return a + b; }, 0);
    var empty = total === 0;
    new Chart(document.getElementById(id), {
      type: 'doughnut',
      data: {
        labels: empty ? ['No data'] : labels,
        datasets: [{ data: empty ? [1] : data, backgroundColor: empty ? ['#E6E2DA'] : colors, borderColor: '#FFFEFC', borderWidth: 3 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '66%',
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, padding: 10, font: { size: 10 } } },
          tooltip: { enabled: !empty }
        }
      }
    });
  }

  donut('chartReq', <?= json_encode(array_keys($reqS)) ?>, <?= json_encode(array_values($reqS)) ?>, <?= json_encode($cReq) ?>);
  donut('chartQuo', <?= json_encode(array_keys($quoS)) ?>, <?= json_encode(array_values($quoS)) ?>, <?= json_encode($cDoc) ?>);
  donut('chartCon', <?= json_encode(array_keys($conS)) ?>, <?= json_encode(array_values($conS)) ?>, <?= json_encode($cDoc) ?>);

  new Chart(document.getElementById('chartFunnel'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($funnel, 0)) ?>,
      datasets: [{ data: <?= json_encode(array_column($funnel, 1)) ?>, backgroundColor: <?= json_encode(array_column($funnel, 2)) ?>, borderRadius: 6, maxBarThickness: 26 }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0 } },
        y: { grid: { display: false } }
      }
    }
  });
})();
</script>
</body>
</html>