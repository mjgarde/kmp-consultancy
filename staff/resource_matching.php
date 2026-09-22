<?php

if (session_status() === PHP_SESSION_NONE) {
    session_name('STAFF_SESSION');
    session_start();
}
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $requestId = $_POST['request_id'] ?? null;
        $userId    = $_POST['user_id'] ?? null;

        if ($requestId && $userId) {
            $stmt = $pdo->prepare(
                "UPDATE service_requests SET assigned_to = ?, assigned_by = ? WHERE request_id = ?"
            );
            $stmt->execute([$userId, $_SESSION['user_id'], $requestId]);

            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Staff assigned successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Unable to assign staff. Please try again.';
        }

        header('Location: resource_matching.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

function formatDisplayDate(?string $date): ?string
{
    return $date ? date('M d, Y', strtotime($date)) : null;
}

function formatQuantity($quantity): string
{
    return rtrim(rtrim(number_format((float) $quantity, 2), '0'), '.');
}

function buildRequestPayload(array $row, array $itemsByQuotation): array
{
    $items = array_map(function ($item) {
        return [
            'description' => $item['description'],
            'quantity'    => formatQuantity($item['quantity']),
            'unit_price'  => number_format((float) $item['unit_price'], 2),
            'line_total'  => number_format((float) $item['line_total'], 2),
        ];
    }, $itemsByQuotation[$row['quotation_id']] ?? []);

    $approvedBy = trim(($row['approved_firstname'] ?? '') . ' ' . ($row['approved_lastname'] ?? ''));

    return [
        'request_id'       => (int) $row['request_id'],
        'request_title'    => $row['request_title'],
        'request_details'  => $row['request_details'],
        'required_skill'   => $row['required_skill'],
        'received_at'      => formatDisplayDate($row['created_at']),
        'company'          => $row['company_name'],
        'contact_person'   => $row['contact_person'],
        'email'            => $row['email'],
        'contact_number'   => $row['contact_number'],
        'address'          => $row['address'],
        'industry'         => $row['industry'],
        'contract_number'  => $row['contract_number'],
        'contract_total'   => number_format((float) $row['total_amount'], 2),
        'start_date'       => formatDisplayDate($row['start_date']),
        'end_date'         => formatDisplayDate($row['end_date']),
        'scope_summary'    => $row['scope_summary'],
        'terms_conditions' => $row['terms_conditions'],
        'approved_by'      => $approvedBy !== '' ? $approvedBy : null,
        'approved_at'      => formatDisplayDate($row['approved_at']),
        'quotation_number' => $row['quotation_number'],
        'subtotal'         => number_format((float) $row['subtotal'], 2),
        'tax_rate'         => formatQuantity($row['tax_rate']),
        'tax_amount'       => number_format((float) $row['tax_amount'], 2),
        'valid_until'      => formatDisplayDate($row['quotation_valid_until']),
        'quotation_notes'  => $row['quotation_notes'],
        'items'            => $items,
    ];
}

$pendingStmt = $pdo->query(
    "SELECT sr.request_id, sr.request_title, sr.request_details, sr.required_skill, sr.status, sr.created_at,
            c.company_name, c.contact_person, c.email, c.contact_number, c.address, c.industry,
            ct.contract_number, ct.quotation_id, ct.total_amount, ct.scope_summary, ct.terms_conditions,
            ct.start_date, ct.end_date, ct.approved_at,
            q.quotation_number, q.subtotal, q.tax_rate, q.tax_amount,
            q.valid_until AS quotation_valid_until, q.notes AS quotation_notes,
            ab.firstname AS approved_firstname, ab.lastname AS approved_lastname
     FROM service_requests sr
     INNER JOIN clients c ON sr.client_id = c.client_id
     INNER JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
     INNER JOIN quotations q ON ct.quotation_id = q.quotation_id
     LEFT JOIN users ab ON ct.approved_by = ab.user_id
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'
     ORDER BY sr.created_at ASC"
);
$pendingRequests = $pendingStmt->fetchAll();

$itemsStmt = $pdo->query('SELECT * FROM quotation_items ORDER BY quotation_id ASC, sort_order ASC');
$itemsByQuotation = [];
foreach ($itemsStmt->fetchAll() as $item) {
    $itemsByQuotation[$item['quotation_id']][] = $item;
}

$staffStmt = $pdo->query(
    "SELECT u.user_id, u.firstname, u.lastname, u.status,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status IN ('New', 'In Progress')) AS workload
     FROM users u
     WHERE u.role = 'Staff'
     ORDER BY u.firstname ASC"
);
$staffList = $staffStmt->fetchAll();

$skillsByStaffStmt = $pdo->query('SELECT user_id, skill_name FROM staff_skills ORDER BY skill_id ASC');
$skillsByStaff = [];
foreach ($skillsByStaffStmt->fetchAll() as $row) {
    $skillsByStaff[$row['user_id']][] = $row['skill_name'];
}

$assignedStmt = $pdo->query(
    "SELECT sr.request_id, sr.request_title, sr.status, sr.updated_at,
            c.company_name,
            u.firstname AS staff_firstname, u.lastname AS staff_lastname,
            ab.firstname AS assigned_by_firstname, ab.lastname AS assigned_by_lastname
     FROM service_requests sr
     INNER JOIN clients c ON sr.client_id = c.client_id
     LEFT JOIN users u ON sr.assigned_to = u.user_id
     LEFT JOIN users ab ON sr.assigned_by = ab.user_id
     WHERE sr.assigned_to IS NOT NULL
     ORDER BY sr.updated_at DESC
     LIMIT 10"
);
$assignedRequests = $assignedStmt->fetchAll();

$pendingCount     = count($pendingRequests);
$totalStaffCount  = count($staffList);
$activeStaffCount = count(array_filter($staffList, fn($staff) => $staff['status'] === 'Active'));
$availableStaffCount = count(array_filter($staffList, fn($staff) => (int) $staff['workload'] === 0));
$onAssignmentCount   = count(array_filter($staffList, fn($staff) => (int) $staff['workload'] > 0));
$inProgressCount  = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'In Progress'")->fetchColumn();
$totalAssigned    = (int) $pdo->query('SELECT COUNT(*) FROM service_requests WHERE assigned_to IS NOT NULL')->fetchColumn();

$staffPayload = array_map(function ($staff) use ($skillsByStaff) {
    return [
        'user_id'  => (int) $staff['user_id'],
        'name'     => $staff['firstname'] . ' ' . $staff['lastname'],
        'status'   => $staff['status'],
        'workload' => (int) $staff['workload'],
        'skills'   => $skillsByStaff[$staff['user_id']] ?? [],
    ];
}, $staffList);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resource Matching</title>
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
  --slate: #55595C;
  --charcoal: #2B3134;

  --accent: #2F6F6A;
  --accent-hover: #245853;
  --accent-soft: #E3EFEC;
  --accent-text: #245853;

  --success-soft: #E6F1EA;
  --success-text: #2C5E42;
  --success-border: #C5DDCF;

  --warn-soft: #F8EEDB;
  --warn-text: #7F5719;
  --warn-border: #E9D5A6;

  --danger-soft: #F8E9E5;
  --danger-text: #8C3D2E;
  --danger-border: #E8C8BF;
}

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-layout, .dashboard-main, .dashboard-content {
  background-color: var(--canvas) !important;
}

.dashboard-title, h1, h2, h3 {
  font-family: 'Lexend', 'Inter', sans-serif;
}

.dashboard-title { color: var(--charcoal); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background-color: var(--card); }

.card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
}

.card-header {
  background-color: var(--card) !important;
  border-bottom: 1px solid var(--line) !important;
  border-radius: 12px 12px 0 0 !important;
  padding: 1rem 1.25rem;
}
.card-header h2 { color: var(--charcoal); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; }

.form-control, .form-select { border-color: var(--line); background-color: var(--card); }
.form-control:hover, .form-select:hover { border-color: #CFCAC0; }
.form-control:focus, .form-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 .2rem rgba(47, 111, 106, .14);
  outline: none;
}

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.25rem;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  min-height: 84px;
}
.stat-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--ink-soft);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}
.stat-value {
  font-family: 'Lexend', sans-serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--charcoal);
  margin-top: .15rem;
}

.section-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin-bottom: .3rem;
}

.request-list { max-height: 68vh; overflow-y: auto; }

.request-card {
  cursor: pointer;
  border: 1px solid var(--line);
  border-radius: 10px;
  background-color: var(--card);
  padding: .9rem 1rem;
  margin-bottom: .6rem;
  transition: border-color .15s ease, background-color .15s ease;
}
.request-card:hover { border-color: var(--accent); }
.request-card.active { border-color: var(--accent); background-color: var(--accent-soft); }
.request-card .request-title { font-size: .875rem; font-weight: 600; color: var(--charcoal); }
.request-card .request-company { font-size: .78rem; color: var(--ink-soft); }
.request-card .request-contract { font-size: .78rem; color: var(--accent-text); font-weight: 600; margin-top: .55rem; }
.request-card .request-received { font-size: .72rem; color: var(--ink-soft); }

.badge-new {
  background-color: var(--warn-soft);
  color: var(--warn-text);
  font-size: .68rem;
  font-weight: 700;
  padding: .3rem .65rem;
  border-radius: 999px;
  border: 1px solid var(--warn-border);
  white-space: nowrap;
}

.skill-badge {
  display: inline-block;
  background-color: var(--surface);
  color: var(--slate);
  font-size: .7rem;
  font-weight: 600;
  padding: .28rem .65rem;
  border-radius: 999px;
  margin: 0 .35rem .35rem 0;
  border: 1px solid var(--line);
}
.skill-badge.matched {
  background-color: var(--success-soft);
  color: var(--success-text);
  border-color: var(--success-border);
}

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #CFCAC0; }

.request-summary {
  background-color: var(--surface);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.1rem 1.25rem;
}
.summary-title {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--charcoal);
  margin: .15rem 0 .1rem;
}
.summary-value {
  font-family: 'Lexend', sans-serif;
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--accent-text);
}
.summary-meta { font-size: .875rem; color: var(--ink); word-break: break-word; }

.btn-see-more {
  background: none;
  border: none;
  padding: 0;
  margin-top: .9rem;
  font-size: .82rem;
  font-weight: 600;
  color: var(--accent-text);
  display: inline-flex;
  align-items: center;
  gap: .4rem;
}
.btn-see-more:hover { color: var(--accent-hover); text-decoration: underline; }
.btn-see-more i { font-size: .7rem; }

.details-section { padding: 1rem 1.25rem; border-bottom: 1px solid var(--line); }
.details-section:last-child { border-bottom: none; }
.details-heading {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: .85rem;
  font-weight: 600;
  color: var(--charcoal);
  margin-bottom: .75rem;
  display: flex;
  align-items: center;
  gap: .55rem;
}
.details-heading .step-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background-color: var(--accent-soft);
  color: var(--accent-text);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .75rem;
  flex-shrink: 0;
}
.details-value { font-size: .875rem; color: var(--ink); word-break: break-word; }

.clamp-text {
  white-space: pre-line;
  word-break: break-word;
  font-size: .875rem;
  color: var(--ink);
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 4;
  overflow: hidden;
}
.clamp-text.is-expanded { display: block; -webkit-line-clamp: unset; }
.clamp-toggle {
  background: none;
  border: none;
  padding: 0;
  margin-top: .35rem;
  font-size: .78rem;
  font-weight: 600;
  color: var(--accent-text);
}
.clamp-toggle:hover { color: var(--accent-hover); text-decoration: underline; }

.items-table th {
  background-color: var(--surface) !important;
  color: var(--ink-soft);
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--line) !important;
}
.items-table td { font-size: .82rem; border-bottom: 1px solid var(--line); }
.totals-line { display: flex; justify-content: space-between; font-size: .85rem; color: var(--slate); }
.totals-line.grand {
  margin-top: .45rem;
  padding-top: .45rem;
  border-top: 1px solid var(--line);
  font-weight: 700;
  font-size: 1rem;
  color: var(--accent-text);
}

.staff-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 1.25rem 0 .75rem;
}
.staff-heading h3 { font-size: .95rem; font-weight: 600; color: var(--charcoal); margin: 0; }

.staff-match-row {
  display: flex;
  gap: .9rem;
  align-items: flex-start;
  border: 1px solid var(--line);
  border-radius: 10px;
  background-color: var(--card);
  padding: .9rem 1rem;
  transition: border-color .15s ease;
}
.staff-match-row:hover { border-color: var(--accent); }
.staff-match-row.is-best { border-color: var(--accent); background-color: #F4F9F8; }

.staff-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: var(--accent-soft);
  color: var(--accent-text);
  font-size: .8rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.staff-name { font-size: .9rem; font-weight: 600; color: var(--charcoal); }
.staff-meta { font-size: .75rem; color: var(--ink-soft); }

.match-pill {
  font-size: .68rem;
  font-weight: 700;
  padding: .26rem .6rem;
  border-radius: 999px;
  border: 1px solid transparent;
  white-space: nowrap;
}
.match-best { background-color: var(--accent); color: #fff; }
.match-high { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.match-none { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }
.match-neutral { background-color: var(--surface); color: var(--slate); border-color: var(--line); }

.btn-assign {
  background-color: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: .82rem;
  padding: .45rem 1.1rem;
}
.btn-assign:hover:not(:disabled) { background-color: var(--accent-hover); color: #fff; }
.btn-assign:disabled { background-color: var(--surface); color: var(--ink-soft); }

.table thead th {
  background-color: var(--surface) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .7rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  border-bottom: 1px solid var(--line) !important;
}
.table td { border-bottom: 1px solid var(--line); color: var(--ink); vertical-align: middle; }
.table-hover tbody tr:hover { background-color: var(--surface); }

.status-pill {
  font-size: .7rem;
  font-weight: 700;
  padding: .3rem .7rem;
  border-radius: 999px;
  border: 1px solid transparent;
  white-space: nowrap;
}
.status-in-progress { background-color: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-border); }
.status-completed { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-default { background-color: var(--surface); color: var(--slate); border-color: var(--line); }

.modal-content { background-color: var(--card); border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }
.confirm-summary { background-color: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: .85rem 1rem; }
.confirm-summary .row-line { display: flex; justify-content: space-between; gap: 1rem; font-size: .85rem; padding: .2rem 0; }
.confirm-summary .row-line span:first-child { color: var(--ink-soft); }
.confirm-summary .row-line span:last-child { font-weight: 600; text-align: right; }

.btn-confirm {
  background-color: var(--accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
}
.btn-confirm:hover { background-color: var(--accent-hover); color: #fff; }
.btn-cancel {
  background-color: var(--surface);
  color: var(--charcoal);
  border: 1px solid var(--line);
  border-radius: 8px;
  font-weight: 600;
}
.btn-cancel:hover { background-color: #EAE7E0; color: var(--charcoal); }

#requestDetailsModal .modal-content { border-radius: 0; }
#requestDetailsModal .modal-header {
  padding: .9rem 1.25rem;
  background-color: var(--card);
  border-bottom: 1px solid var(--line);
}
#requestDetailsModal .modal-header-col { flex: 1 1 0; min-width: 0; }
#requestDetailsModal .modal-eyebrow {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
#requestDetailsModal .modal-main-title {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: .95rem;
  font-weight: 700;
  color: var(--charcoal);
  margin: 0;
}
#requestDetailsModal .modal-body {
  background-color: var(--surface);
  padding: 1.25rem;
}
#requestDetailsModal .quote-shell { max-width: 1400px; margin: 0 auto; }
#requestDetailsModal .quote-panel {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.1rem 1.25rem;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
}
#requestDetailsModal .quote-sticky { position: sticky; top: 0; }
@media (max-width: 991.98px) {
  #requestDetailsModal .quote-sticky { position: static; }
}
@media (max-width: 767.98px) {
  #requestDetailsModal .modal-body { padding: .75rem; }
  #requestDetailsModal .quote-panel { padding: .9rem; }
}

@media (max-width: 991.98px) {
  .stat-card { padding: .85rem 1rem; min-height: 78px; }
  .stat-value { font-size: 1.3rem; }
  .request-list { max-height: 55vh; }
}

@media (max-width: 767.98px) {
  .staff-match-row { flex-wrap: wrap; }
  .request-list { max-height: 45vh; }

  .stat-card { padding: .65rem .5rem; border-radius: 10px; min-height: 64px; }
  .stat-label { font-size: .54rem; letter-spacing: 0; line-height: 1.15; }
  .stat-value { font-size: .95rem; margin-top: .08rem; }

  .card-header { padding: .8rem 1rem; }
  .card-header h2 { font-size: .95rem; }
  .card-header p { font-size: .76rem; }

  .request-card { padding: .75rem .8rem; }
  .request-card .request-title { font-size: .82rem; }
  .request-card .request-company { font-size: .72rem; }
  .request-card .request-contract { font-size: .72rem; margin-top: .4rem; }
  .request-card .request-received { font-size: .66rem; }
  .badge-new { font-size: .62rem; padding: .24rem .55rem; }
  .skill-badge { font-size: .64rem; padding: .22rem .55rem; }

  .request-summary { padding: .9rem 1rem; }
  .summary-title { font-size: .95rem; }
  .summary-value { font-size: 1.05rem; }
  .summary-meta { font-size: .8rem; }
  .section-label { font-size: .64rem; }

  .staff-heading h3 { font-size: .85rem; }
  .staff-match-row { padding: .75rem .85rem; gap: .65rem; }
  .staff-avatar { width: 32px; height: 32px; font-size: .68rem; }
  .staff-name { font-size: .82rem; }
  .staff-meta { font-size: .68rem; }
  .match-pill { font-size: .6rem; padding: .2rem .5rem; }
  .btn-assign { font-size: .74rem; padding: .38rem .85rem; width: 100%; margin-top: .4rem; }
  .staff-match-row { flex-direction: column; }
  .staff-match-row > .flex-grow-1 { width: 100%; }

  .details-heading { font-size: .8rem; gap: .4rem; }
  .details-heading .step-icon { width: 24px; height: 24px; font-size: .66rem; }
  .details-value { font-size: .8rem; }
  .details-section, .quote-panel { padding: .8rem .9rem; }

  .table thead th { font-size: .62rem; padding: .5rem .5rem; }
  .table td { font-size: .78rem; padding: .5rem; }
  .items-table th, .items-table td { font-size: .7rem; padding: .35rem; }

  #requestDetailsModal .modal-header { padding: .65rem .85rem; }
  #requestDetailsModal .modal-eyebrow { font-size: .6rem; }
  #requestDetailsModal .modal-main-title { font-size: .8rem; }
  #requestDetailsModal .btn-close { transform: scale(.85); }

  .btn-see-more { font-size: .76rem; }
  .status-pill { font-size: .62rem; padding: .24rem .55rem; }
}

@media (max-width: 575.98px) {
  .dashboard-title { font-size: .95rem; }
  .dashboard-subtitle { font-size: .68rem; }

  .stat-card { padding: .5rem .4rem; min-height: 58px; border-radius: 8px; }
  .stat-value { font-size: .85rem; margin-top: .05rem; }
  .stat-label { font-size: .48rem; letter-spacing: 0; line-height: 1.15; }

  .request-summary { padding: .75rem .85rem; }
  .summary-value { font-size: .95rem; }

  .staff-avatar { width: 26px; height: 26px; font-size: .58rem; }
  .step-icon { width: 22px; height: 22px; font-size: .6rem; }

  .card-header h2 { font-size: .85rem; }
  .card-header p { font-size: .68rem; }

  #requestDetailsModal .modal-main-title { font-size: .7rem; }
  #requestDetailsModal .modal-eyebrow { font-size: .52rem; }
}
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Resource Matching</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Assign the most suitable staff to approved service engagements.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="row g-3 mb-3 align-items-stretch">
        <div class="col-4 col-xl-2 d-flex">
          <div class="stat-card">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value"><?= $pendingCount ?></div>
          </div>
        </div>
        <div class="col-4 col-xl-2 d-flex">
          <div class="stat-card">
            <div class="stat-label">Total Staff</div>
            <div class="stat-value"><?= $totalStaffCount ?></div>
          </div>
        </div>
        <div class="col-4 col-xl-2 d-flex">
          <div class="stat-card">
            <div class="stat-label">Available Staff</div>
            <div class="stat-value" style="color:var(--success-text);"><?= $availableStaffCount ?></div>
          </div>
        </div>
        <div class="col-4 col-xl-2 d-flex">
          <div class="stat-card">
            <div class="stat-label">On Assignment</div>
            <div class="stat-value" style="color:var(--warn-text);"><?= $onAssignmentCount ?></div>
          </div>
        </div>
        <div class="col-4 col-xl-2 d-flex">
          <div class="stat-card">
            <div class="stat-label">In Progress</div>
            <div class="stat-value"><?= $inProgressCount ?></div>
          </div>
        </div>
        <div class="col-4 col-xl-2 d-flex">
          <div class="stat-card">
            <div class="stat-label">Total Assigned</div>
            <div class="stat-value"><?= $totalAssigned ?></div>
          </div>
        </div>
      </div>

      <div class="row g-3">

        <div class="col-lg-5">
          <section class="card h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Pending Service Requests</h2>
              <p class="small mb-0">Select a request to review its details and find suitable staff.</p>
            </div>
            <div class="card-body p-3">
              <?php if (!empty($pendingRequests)): ?>
                <div class="input-group mb-3">
                  <span class="input-group-text" style="background-color:var(--card); border-color:var(--line);">
                    <i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i>
                  </span>
                  <input type="text" id="requestSearchInput" class="form-control" placeholder="Search by request, client, or contract">
                </div>
              <?php endif; ?>

              <div class="request-list">
                <?php if (empty($pendingRequests)): ?>
                  <div class="empty-state text-center py-5">
                    <i class="fa-regular fa-folder-open fs-3 mb-2 d-block"></i>
                    <p class="small mb-0">No pending service requests.</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($pendingRequests as $request): ?>
                    <?php $payload = buildRequestPayload($request, $itemsByQuotation); ?>
                    <div class="request-card"
                      data-request='<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>'
                      data-search="<?= htmlspecialchars(strtolower($request['request_title'] . ' ' . $request['company_name'] . ' ' . $request['contract_number'] . ' ' . ($request['required_skill'] ?? ''))) ?>">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div style="min-width:0;">
                          <div class="request-title"><?= htmlspecialchars($request['request_title']) ?></div>
                          <div class="request-company"><?= htmlspecialchars($request['company_name']) ?></div>
                        </div>
                        <span class="badge-new">New</span>
                      </div>
                      <div class="request-contract">
                        <?= htmlspecialchars($request['contract_number']) ?> &middot; &#8369;<?= number_format((float) $request['total_amount'], 2) ?>
                      </div>
                      <div class="d-flex justify-content-between align-items-center mt-2 gap-2">
                        <div>
                          <?php if (!empty($request['required_skill'])): ?>
                            <span class="skill-badge mb-0"><?= htmlspecialchars($request['required_skill']) ?></span>
                          <?php endif; ?>
                        </div>
                        <span class="request-received">Received <?= htmlspecialchars(formatDisplayDate($request['created_at']) ?? '') ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                  <div id="requestSearchEmpty" class="empty-state text-center py-4 d-none">
                    <p class="small mb-0">No requests match your search.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </section>
        </div>

        <div class="col-lg-7">
          <section class="card h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Request Review and Staff Recommendation</h2>
              <p class="small mb-0" id="matching_subtitle">Select a service request first.</p>
            </div>
            <div class="card-body p-3">

              <div id="matching_empty_state" class="empty-state text-center py-5">
                <i class="fa-regular fa-hand-pointer fs-3 mb-2 d-block"></i>
                <p class="small mb-0">Choose a service request on the left to view its details and recommended staff.</p>
              </div>

              <div id="matching_content" class="d-none">

                <form method="POST" id="assignForm">
                  <input type="hidden" name="action" value="assign">
                  <input type="hidden" name="request_id" id="assign_request_id">
                  <input type="hidden" name="user_id" id="assign_user_id">
                </form>

                <div class="request-summary">
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div style="min-width:0;">
                      <div class="section-label" id="selected_contract_number"></div>
                      <div class="summary-title" id="selected_request_title"></div>
                      <div class="small" id="selected_request_company" style="color:var(--ink-soft);"></div>
                    </div>
                    <div class="text-md-end">
                      <div class="section-label">Contract Value</div>
                      <div class="summary-value" id="selected_contract_value"></div>
                    </div>
                  </div>

                  <div class="row g-3 mt-1">
                    <div class="col-sm-4">
                      <div class="section-label">Contact Person</div>
                      <div class="summary-meta" id="summary_contact_person"></div>
                    </div>
                    <div class="col-sm-4">
                      <div class="section-label">Contract Duration</div>
                      <div class="summary-meta" id="summary_duration"></div>
                    </div>
                    <div class="col-sm-4">
                      <div class="section-label">Required Skill</div>
                      <div id="required_skill_display"></div>
                    </div>
                  </div>

                  <button type="button" class="btn-see-more" id="toggleDetailsBtn" data-bs-toggle="modal" data-bs-target="#requestDetailsModal">
                    <span>See full details</span>
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </button>
                </div>

                <div class="staff-heading">
                  <h3>Recommended Staff</h3>
                  <span class="small" id="staff_count_label" style="color:var(--ink-soft);"></span>
                </div>

                <div id="staff_match_list" class="d-flex flex-column gap-2"></div>

              </div>

            </div>
          </section>
        </div>

      </div>

      <section class="card mt-3 overflow-hidden">
        <div class="card-header">
          <h2 class="h6 fw-bold mb-0">Recently Assigned</h2>
          <p class="small mb-0">The ten most recent staff assignments.</p>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Request</th>
                <th scope="col" class="d-none d-md-table-cell">Client</th>
                <th scope="col">Assigned To</th>
                <th scope="col" class="d-none d-lg-table-cell">Assigned By</th>
                <th scope="col" class="d-none d-lg-table-cell">Date</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($assignedRequests)): ?>
                <tr>
                  <td colspan="6" class="text-center py-4" style="color:var(--ink-soft);">No assignments yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($assignedRequests as $row): ?>
                  <?php
                    $statusClass = 'status-default';
                    if ($row['status'] === 'In Progress') { $statusClass = 'status-in-progress'; }
                    if ($row['status'] === 'Completed') { $statusClass = 'status-completed'; }
                  ?>
                  <tr>
                    <td class="small fw-semibold"><?= htmlspecialchars($row['request_title']) ?></td>
                    <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($row['company_name']) ?></td>
                    <td class="small"><?= htmlspecialchars(trim(($row['staff_firstname'] ?? '') . ' ' . ($row['staff_lastname'] ?? ''))) ?></td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars(trim(($row['assigned_by_firstname'] ?? '-') . ' ' . ($row['assigned_by_lastname'] ?? ''))) ?></td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars(formatDisplayDate($row['updated_at']) ?? '') ?></td>
                    <td class="small">
                      <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

    </main>

  </div>

</div>

<div class="modal fade" id="confirmAssignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold">Confirm Assignment</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small mb-3" style="color:var(--ink-soft);">Please review the assignment below before confirming.</p>
        <div class="confirm-summary">
          <div class="row-line"><span>Request</span><span id="confirm_request_title"></span></div>
          <div class="row-line"><span>Client</span><span id="confirm_client_name"></span></div>
          <div class="row-line"><span>Assigned Staff</span><span id="confirm_staff_name"></span></div>
          <div class="row-line"><span>Skill Match</span><span id="confirm_skill_match"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-confirm px-4" id="confirm_assign_btn">Confirm Assignment</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">

      <div class="modal-header">
        <div class="modal-header-col">
          <span class="modal-eyebrow" id="details_eyebrow">Service Request</span>
        </div>
        <div class="modal-header-col text-center">
          <h2 class="modal-main-title" id="details_modal_title">Full Details</h2>
        </div>
        <div class="modal-header-col d-flex justify-content-end">
          <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>

      <div class="modal-body">
        <div class="quote-shell">
          <div class="row g-3">

            <div class="col-lg-8">
              <div class="d-flex flex-column gap-3">

                <div class="quote-panel">
                  <div class="details-heading">
                    <span class="step-icon"><i class="fa-solid fa-building"></i></span>
                    Client Information
                  </div>
                  <div class="row g-3">
                    <div class="col-sm-6 col-xl-4">
                      <div class="section-label">Company</div>
                      <div class="details-value" id="detail_company"></div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                      <div class="section-label">Contact Person</div>
                      <div class="details-value" id="detail_contact_person"></div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                      <div class="section-label">Industry</div>
                      <div class="details-value" id="detail_industry"></div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                      <div class="section-label">Email</div>
                      <div class="details-value" id="detail_email"></div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                      <div class="section-label">Contact Number</div>
                      <div class="details-value" id="detail_contact_number"></div>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                      <div class="section-label">Address</div>
                      <div class="details-value" id="detail_address"></div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="details-heading">
                    <span class="step-icon"><i class="fa-solid fa-clipboard-list"></i></span>
                    Service Request
                  </div>
                  <div class="row g-3">
                    <div class="col-sm-6">
                      <div class="section-label">Date Received</div>
                      <div class="details-value" id="detail_received_at"></div>
                    </div>
                    <div class="col-sm-6">
                      <div class="section-label">Approved Contract</div>
                      <div class="details-value" id="detail_contract_approval"></div>
                    </div>
                    <div class="col-sm-6">
                      <div class="section-label">Required Skill</div>
                      <div id="detail_required_skill_display"></div>
                    </div>
                    <div class="col-12">
                      <div class="section-label">Request Details</div>
                      <div class="clamp-block">
                        <div class="clamp-text" id="detail_request_details"></div>
                        <button type="button" class="clamp-toggle d-none">See more</button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="details-heading">
                    <span class="step-icon"><i class="fa-solid fa-bullseye"></i></span>
                    Project Scope
                  </div>
                  <div class="clamp-block">
                    <div class="clamp-text" id="detail_scope"></div>
                    <button type="button" class="clamp-toggle d-none">See more</button>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="details-heading">
                    <span class="step-icon"><i class="fa-solid fa-file-contract"></i></span>
                    Terms and Conditions
                  </div>
                  <div class="clamp-block">
                    <div class="clamp-text" id="detail_terms"></div>
                    <button type="button" class="clamp-toggle d-none">See more</button>
                  </div>
                </div>

              </div>
            </div>

            <div class="col-lg-4">
              <div class="quote-sticky d-flex flex-column gap-3">

                <div class="quote-panel">
                  <div class="details-heading">
                    <span class="step-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                    Quotation <span class="fw-normal" style="color:var(--ink-soft); font-size:.78rem;" id="detail_quotation_number"></span>
                  </div>
                  <div class="table-responsive mb-3">
                    <table class="table table-sm items-table align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Description</th>
                          <th class="text-end">Qty</th>
                          <th class="text-end">Unit Price</th>
                          <th class="text-end">Total</th>
                        </tr>
                      </thead>
                      <tbody id="detail_items_body"></tbody>
                    </table>
                  </div>
                  <div class="totals-line"><span>Subtotal</span><span id="detail_subtotal"></span></div>
                  <div class="totals-line"><span>Tax</span><span id="detail_tax"></span></div>
                  <div class="totals-line grand"><span>Total</span><span id="detail_total"></span></div>

                  <div class="mt-3">
                    <div class="section-label">Quotation Valid Until</div>
                    <div class="details-value" id="detail_valid_until"></div>
                  </div>
                  <div class="mt-3">
                    <div class="section-label">Quotation Notes</div>
                    <div class="clamp-block">
                      <div class="clamp-text" id="detail_quotation_notes"></div>
                      <button type="button" class="clamp-toggle d-none">See more</button>
                    </div>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
const staffData = <?= json_encode($staffPayload) ?>;

const EMPTY_VALUE = '\u2014';
const CLAMP_CHARACTER_LIMIT = 220;
const CLAMP_LINE_LIMIT = 3;

let selectedRequest = null;

const matchingEmptyState = document.getElementById('matching_empty_state');
const matchingContent = document.getElementById('matching_content');
const matchingSubtitle = document.getElementById('matching_subtitle');
const requiredSkillDisplay = document.getElementById('required_skill_display');
const staffMatchList = document.getElementById('staff_match_list');
const staffCountLabel = document.getElementById('staff_count_label');

function escapeHtml(value) {
  const element = document.createElement('div');
  element.textContent = (value === null || value === undefined) ? '' : String(value);
  return element.innerHTML;
}

function hasText(value) {
  return value !== null && value !== undefined && String(value).trim() !== '';
}

function setText(elementId, value, fallback) {
  document.getElementById(elementId).textContent = hasText(value) ? value : (fallback !== undefined ? fallback : EMPTY_VALUE);
}

function formatTerms(text) {
  if (!hasText(text)) return '';
  let formatted = String(text).trim();
  if (formatted.indexOf('\n') === -1) {
    formatted = formatted.replace(/\s+(?=\d{1,2}\.\s+[A-Z][A-Z\s&,\/]{3,}\s)/g, '\n\n');
    formatted = formatted.replace(/\s+-\s+(?=[A-Z0-9])/g, '\n- ');
  }
  return formatted;
}

function setClampText(elementId, value, fallback) {
  const textElement = document.getElementById(elementId);
  const toggleButton = textElement.parentElement.querySelector('.clamp-toggle');
  const content = hasText(value) ? String(value) : fallback;
  const lineCount = (content.match(/\n/g) || []).length;

  textElement.textContent = content;
  textElement.classList.remove('is-expanded');
  toggleButton.textContent = 'See more';
  toggleButton.classList.toggle('d-none', !(content.length > CLAMP_CHARACTER_LIMIT || lineCount >= CLAMP_LINE_LIMIT));
}

document.addEventListener('click', function (event) {
  const toggleButton = event.target.closest('.clamp-toggle');
  if (!toggleButton) return;
  const textElement = toggleButton.parentElement.querySelector('.clamp-text');
  const isExpanded = textElement.classList.toggle('is-expanded');
  toggleButton.textContent = isExpanded ? 'See less' : 'See more';
});

function renderRequiredSkillInto(elementId, requiredSkill) {
  const el = document.getElementById(elementId);
  if (hasText(requiredSkill)) {
    el.innerHTML = '<span class="skill-badge matched mb-0">' + escapeHtml(requiredSkill) + '</span>';
  } else {
    el.innerHTML = '<span class="summary-meta">Not specified</span>';
  }
}

function renderQuotationItems(items) {
  const itemsBody = document.getElementById('detail_items_body');
  itemsBody.innerHTML = '';

  if (items.length === 0) {
    itemsBody.innerHTML = '<tr><td colspan="4" class="text-center" style="color:var(--ink-soft);">No items recorded.</td></tr>';
    return;
  }

  items.forEach(function (item) {
    const row = document.createElement('tr');
    row.innerHTML =
      '<td>' + escapeHtml(item.description) + '</td>' +
      '<td class="text-end">' + escapeHtml(item.quantity) + '</td>' +
      '<td class="text-end">\u20B1' + escapeHtml(item.unit_price) + '</td>' +
      '<td class="text-end fw-semibold">\u20B1' + escapeHtml(item.line_total) + '</td>';
    itemsBody.appendChild(row);
  });
}

function renderSelectedRequest(request) {
  const duration = (request.start_date || EMPTY_VALUE) + ' \u2013 ' + (request.end_date || EMPTY_VALUE);
  const approval = request.approved_by
    ? request.contract_number + ' \u00B7 approved by ' + request.approved_by + (request.approved_at ? ' on ' + request.approved_at : '')
    : request.contract_number;

  setText('selected_contract_number', request.contract_number);
  setText('selected_request_title', request.request_title);
  setText('selected_request_company', request.company);
  document.getElementById('selected_contract_value').textContent = '\u20B1' + request.contract_total;
  setText('summary_contact_person', request.contact_person);
  document.getElementById('summary_duration').textContent = duration;
  renderRequiredSkillInto('required_skill_display', request.required_skill);

  document.getElementById('details_eyebrow').textContent = request.contract_number || 'Service Request';
  document.getElementById('details_modal_title').textContent = request.company || 'Full Details';

  setText('detail_company', request.company);
  setText('detail_contact_person', request.contact_person);
  setText('detail_industry', request.industry);
  setText('detail_email', request.email);
  setText('detail_contact_number', request.contact_number);
  setText('detail_address', request.address);

  setText('detail_received_at', request.received_at);
  setText('detail_contract_approval', approval);
  renderRequiredSkillInto('detail_required_skill_display', request.required_skill);
  setClampText('detail_request_details', request.request_details, 'No additional details were provided for this request.');
  setClampText('detail_scope', request.scope_summary, 'No project scope provided.');
  setClampText('detail_terms', formatTerms(request.terms_conditions), 'No terms and conditions provided.');
  setClampText('detail_quotation_notes', request.quotation_notes, 'No notes provided.');

  setText('detail_quotation_number', request.quotation_number ? '\u00B7 ' + request.quotation_number : '', '');
  renderQuotationItems(request.items);
  document.getElementById('detail_subtotal').textContent = '\u20B1' + request.subtotal;
  document.getElementById('detail_tax').textContent = '\u20B1' + request.tax_amount + ' (' + request.tax_rate + '%)';
  document.getElementById('detail_total').textContent = '\u20B1' + request.contract_total;
  setText('detail_valid_until', request.valid_until, 'No expiry set');
}

function getInitials(name) {
  return name.split(' ').filter(Boolean).slice(0, 2).map(function (part) {
    return part.charAt(0).toUpperCase();
  }).join('');
}

function skillMatches(staff, requiredSkill) {
  return staff.skills.some(function (skill) {
    return skill.toLowerCase() === requiredSkill.toLowerCase();
  });
}

function rankStaff(requiredSkill) {
  const hasRequirement = hasText(requiredSkill);

  return staffData.map(function (staff) {
    return Object.assign({}, staff, {
      hasSkill: hasRequirement ? skillMatches(staff, requiredSkill) : null
    });
  }).sort(function (a, b) {
    if (hasRequirement && a.hasSkill !== b.hasSkill) return a.hasSkill ? -1 : 1;
    if (a.status !== b.status) return a.status === 'Active' ? -1 : 1;
    return a.workload - b.workload;
  });
}

function buildMatchPill(staff, requiredSkill, isBest) {
  if (isBest) return '<span class="match-pill match-best">Best Match</span>';
  if (!hasText(requiredSkill)) return '';
  if (staff.hasSkill) return '<span class="match-pill match-high">Skill Match</span>';
  return '<span class="match-pill match-none">No Skill Match</span>';
}

function buildSkillBadges(staff, requiredSkill) {
  if (staff.skills.length === 0) {
    return '<span class="staff-meta">No skills on record</span>';
  }
  return staff.skills.map(function (skill) {
    const isMatch = hasText(requiredSkill) && skill.toLowerCase() === requiredSkill.toLowerCase();
    return '<span class="skill-badge' + (isMatch ? ' matched' : '') + '">' + escapeHtml(skill) + '</span>';
  }).join('');
}

function renderStaffMatches(requiredSkill) {
  const rankedStaff = rankStaff(requiredSkill);
  staffMatchList.innerHTML = '';
  const busyCount = rankedStaff.filter(function (staff) { return staff.workload > 0; }).length;
  staffCountLabel.textContent = rankedStaff.length + ' staff member(s) \u00B7 ' + busyCount + ' with active task(s)';

  if (rankedStaff.length === 0) {
    staffMatchList.innerHTML = '<p class="small text-center py-3 mb-0" style="color:var(--ink-soft);">No staff accounts found.</p>';
    return;
  }

  const bestCandidate = rankedStaff.find(function (staff) {
    const skillOk = !hasText(requiredSkill) || staff.hasSkill;
    return staff.status === 'Active' && skillOk;
  });

  rankedStaff.forEach(function (staff) {
    const isBest = bestCandidate && bestCandidate.user_id === staff.user_id;
    const row = document.createElement('div');
    row.className = 'staff-match-row' + (isBest ? ' is-best' : '');

    row.innerHTML =
      '<span class="staff-avatar">' + escapeHtml(getInitials(staff.name)) + '</span>' +
      '<div class="flex-grow-1" style="min-width:0;">' +
        '<div class="d-flex align-items-center flex-wrap gap-2 mb-1">' +
          '<span class="staff-name">' + escapeHtml(staff.name) + '</span>' +
          buildMatchPill(staff, requiredSkill, isBest) +
        '</div>' +
        '<div class="mb-1">' + buildSkillBadges(staff, requiredSkill) + '</div>' +
        '<div class="staff-meta">' + (staff.workload === 0 ? 'Available &middot; no active tasks' : staff.workload + ' active task(s)') + ' &middot; ' + escapeHtml(staff.status) + '</div>' +
      '</div>' +
      '<button type="button" class="btn btn-assign flex-shrink-0"' + (staff.status !== 'Active' ? ' disabled' : '') + '>Assign</button>';

    row.querySelector('button').addEventListener('click', function () {
      openConfirmModal(staff, requiredSkill);
    });

    staffMatchList.appendChild(row);
  });
}

function selectRequest(card) {
  document.querySelectorAll('.request-card').forEach(function (item) { item.classList.remove('active'); });
  card.classList.add('active');

  selectedRequest = JSON.parse(card.dataset.request);

  renderSelectedRequest(selectedRequest);
  renderStaffMatches(selectedRequest.required_skill || '');

  matchingSubtitle.textContent = 'Review the request, then assign a suitable staff member.';
  matchingEmptyState.classList.add('d-none');
  matchingContent.classList.remove('d-none');
}

document.querySelectorAll('.request-card').forEach(function (card) {
  card.addEventListener('click', function () { selectRequest(card); });
});

const requestSearchInput = document.getElementById('requestSearchInput');
if (requestSearchInput) {
  requestSearchInput.addEventListener('input', function () {
    const query = requestSearchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    document.querySelectorAll('.request-card').forEach(function (card) {
      const isMatch = card.dataset.search.indexOf(query) !== -1;
      card.classList.toggle('d-none', !isMatch);
      if (isMatch) visibleCount++;
    });

    document.getElementById('requestSearchEmpty').classList.toggle('d-none', visibleCount > 0);
  });
}

function openConfirmModal(staff, requiredSkill) {
  let skillMatchLabel = 'No skill required';
  if (hasText(requiredSkill)) {
    skillMatchLabel = staff.hasSkill ? 'Matches ' + requiredSkill : 'Does not match ' + requiredSkill;
  }

  document.getElementById('confirm_request_title').textContent = selectedRequest.request_title;
  document.getElementById('confirm_client_name').textContent = selectedRequest.company;
  document.getElementById('confirm_staff_name').textContent = staff.name;
  document.getElementById('confirm_skill_match').textContent = skillMatchLabel;

  const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmAssignModal'));
  modal.show();

  document.getElementById('confirm_assign_btn').onclick = function () {
    document.getElementById('assign_request_id').value = selectedRequest.request_id;
    document.getElementById('assign_user_id').value = staff.user_id;
    document.getElementById('assignForm').submit();
  };
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>