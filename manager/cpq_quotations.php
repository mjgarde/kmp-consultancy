<?php
session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

const QUOTATION_STATUSES = ['Draft', 'Approved', 'Rejected', 'Revert'];
const LOCKED_MESSAGE = 'This service request is already completed by staff, so this quotation is locked.';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $type, string $message): void
{
    $_SESSION['alert_type'] = $type;
    $_SESSION['alert_message'] = $message;
    header('Location: cpq_quotations.php');
    exit;
}

function generateQuotationNumber(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT quotation_number FROM quotations WHERE quotation_number LIKE ? ORDER BY quotation_number DESC LIMIT 1");
    $stmt->execute(["QT-{$year}-%"]);
    $last = $stmt->fetchColumn();
    $next = $last ? ((int) substr($last, -4)) + 1 : 1;
    return sprintf('QT-%s-%04d', $year, $next);
}

function collectItems(array $descriptions, array $quantities, array $unitPrices): array
{
    $items = [];
    $subtotal = 0.0;
    foreach ($descriptions as $index => $description) {
        $description = trim((string) $description);
        $quantity = (float) ($quantities[$index] ?? 0);
        $unitPrice = (float) ($unitPrices[$index] ?? 0);
        if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
            continue;
        }
        $lineTotal = round($quantity * $unitPrice, 2);
        $subtotal += $lineTotal;
        $items[] = [$description, $quantity, $unitPrice, $lineTotal, count($items)];
    }
    return [$items, round($subtotal, 2)];
}

function requestHasApproved(PDO $pdo, int $requestId, int $excludeQuotationId = 0): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotations WHERE request_id = ? AND status = 'Approved' AND quotation_id <> ?");
    $stmt->execute([$requestId, $excludeQuotationId]);
    return (int) $stmt->fetchColumn() > 0;
}

function requestIsCompleted(PDO $pdo, int $requestId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE request_id = ? AND status = 'Completed'");
    $stmt->execute([$requestId]);
    return (int) $stmt->fetchColumn() > 0;
}

function removeContractsForQuotation(PDO $pdo, int $quotationId): int
{
    $pdo->prepare(
        'DELETE cr FROM contract_revisions cr
         INNER JOIN contracts ct ON cr.contract_id = ct.contract_id
         WHERE ct.quotation_id = ?'
    )->execute([$quotationId]);

    $stmt = $pdo->prepare('DELETE FROM contracts WHERE quotation_id = ?');
    $stmt->execute([$quotationId]);

    return $stmt->rowCount();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_quotation') {
            $requestId    = (int) ($_POST['request_id'] ?? 0);
            $projectScope = trim($_POST['project_scope'] ?? '');
            $taxRate      = max(0, (float) ($_POST['tax_rate'] ?? 0));
            $validUntil   = ($_POST['valid_until'] ?? '') ?: null;
            $targetStatus = 'Draft';

            if ($requestId <= 0) {
                flash('error', 'Please select a service transaction.');
            }

            $reqStmt = $pdo->prepare(
                "SELECT sr.client_id
                 FROM service_requests sr
                 LEFT JOIN quotations q ON q.request_id = sr.request_id AND q.status = 'Approved'
                 WHERE sr.request_id = ? AND q.quotation_id IS NULL"
            );
            $reqStmt->execute([$requestId]);
            $req = $reqStmt->fetch();

            if (!$req) {
                flash('error', 'This service transaction already has an approved quotation or was not found.');
            }

            [$items, $subtotal] = collectItems(
                $_POST['item_description'] ?? [],
                $_POST['item_quantity'] ?? [],
                $_POST['item_unit_price'] ?? []
            );

            if (empty($items)) {
                flash('error', 'Please add at least one valid scope item.');
            }

            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);
            $quotationNumber = generateQuotationNumber($pdo);

            $pdo->beginTransaction();

            $insertQuotation = $pdo->prepare(
                "INSERT INTO quotations
                    (quotation_number, request_id, client_id, project_scope, status, subtotal, tax_rate, tax_amount, total_amount, valid_until, prepared_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertQuotation->execute([
                $quotationNumber, $requestId, $req['client_id'], $projectScope, $targetStatus,
                $subtotal, $taxRate, $taxAmount, $totalAmount, $validUntil, $_SESSION['user_id'],
            ]);
            $quotationId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                "INSERT INTO quotation_items (quotation_id, description, quantity, unit_price, line_total, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($items as $item) {
                $insertItem->execute([$quotationId, $item[0], $item[1], $item[2], $item[3], $item[4]]);
            }

            $pdo->commit();

            flash('success', "Quotation {$quotationNumber} saved as draft.");
        }

        if ($action === 'edit_quotation') {
            $quotationId  = (int) ($_POST['quotation_id'] ?? 0);
            $projectScope = trim($_POST['edit_project_scope'] ?? '');
            $taxRate      = max(0, (float) ($_POST['edit_tax_rate'] ?? 0));
            $validUntil   = ($_POST['edit_valid_until'] ?? '') ?: null;

            $checkStmt = $pdo->prepare('SELECT quotation_id, request_id, status FROM quotations WHERE quotation_id = ?');
            $checkStmt->execute([$quotationId]);
            $existing = $checkStmt->fetch();

            if (!$existing) {
                flash('error', 'Quotation not found.');
            }

            if (requestIsCompleted($pdo, (int) $existing['request_id'])) {
                flash('error', LOCKED_MESSAGE);
            }

            $targetStatus = 'Revert';

            [$items, $subtotal] = collectItems(
                $_POST['edit_item_description'] ?? [],
                $_POST['edit_item_quantity'] ?? [],
                $_POST['edit_item_unit_price'] ?? []
            );

            if (empty($items)) {
                flash('error', 'Please keep at least one valid scope item.');
            }

            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $pdo->beginTransaction();

            $updateQuotation = $pdo->prepare(
                "UPDATE quotations
                 SET project_scope = ?, status = ?,
                     subtotal = ?, tax_rate = ?, tax_amount = ?, total_amount = ?, valid_until = ?
                 WHERE quotation_id = ?"
            );
            $updateQuotation->execute([
                $projectScope, $targetStatus,
                $subtotal, $taxRate, $taxAmount, $totalAmount, $validUntil, $quotationId,
            ]);

            $deleteItems = $pdo->prepare('DELETE FROM quotation_items WHERE quotation_id = ?');
            $deleteItems->execute([$quotationId]);

            $insertItem = $pdo->prepare(
                "INSERT INTO quotation_items (quotation_id, description, quantity, unit_price, line_total, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($items as $item) {
                $insertItem->execute([$quotationId, $item[0], $item[1], $item[2], $item[3], $item[4]]);
            }

            $removedContracts = removeContractsForQuotation($pdo, $quotationId);

            $pdo->commit();

            if ($removedContracts > 0) {
                flash('success', 'Quotation reverted. The linked contract was removed; approve the quotation again to generate a new contract.');
            }

            flash('success', 'Quotation updated successfully.');
        }

        if (in_array($action, ['approve_quotation', 'reject_quotation', 'reopen_quotation'], true)) {
            $quotationId = (int) ($_POST['quotation_id'] ?? 0);

            $transitions = [
                'approve_quotation' => ['from' => ['Draft', 'Revert'], 'to' => 'Approved', 'done' => 'approved'],
                'reject_quotation'  => ['from' => ['Draft', 'Revert'], 'to' => 'Rejected', 'done' => 'rejected'],
                'reopen_quotation'  => ['from' => ['Rejected'], 'to' => 'Draft', 'done' => 'moved back to draft'],
            ];
            $rule = $transitions[$action];

            $stmt = $pdo->prepare('SELECT quotation_id, quotation_number, request_id, status FROM quotations WHERE quotation_id = ?');
            $stmt->execute([$quotationId]);
            $existing = $stmt->fetch();

            if (!$existing) {
                flash('error', 'Quotation not found.');
            }

            if (requestIsCompleted($pdo, (int) $existing['request_id'])) {
                flash('error', LOCKED_MESSAGE);
            }

            if (!in_array($existing['status'], $rule['from'], true)) {
                flash('error', 'Only ' . implode(' or ', $rule['from']) . " quotations can be {$rule['done']}.");
            }

            if ($rule['to'] === 'Approved' && requestHasApproved($pdo, (int) $existing['request_id'], $quotationId)) {
                flash('error', 'This service transaction already has an approved quotation.');
            }

            $update = $pdo->prepare('UPDATE quotations SET status = ? WHERE quotation_id = ?');
            $update->execute([$rule['to'], $quotationId]);

            flash('success', "Quotation {$existing['quotation_number']} {$rule['done']}.");
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('cpq_quotations: ' . $ex->getMessage());
        flash('error', 'Something went wrong while saving. Please try again.');
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$requestsStmt = $pdo->query(
    "SELECT sr.request_id, sr.request_title, sr.required_skill, sr.status,
            c.client_id, c.company_name
     FROM service_requests sr
     INNER JOIN clients c ON sr.client_id = c.client_id
     LEFT JOIN quotations q ON q.request_id = sr.request_id AND q.status = 'Approved'
     WHERE sr.status = 'New' AND q.quotation_id IS NULL
     ORDER BY sr.created_at DESC"
);
$serviceRequests = $requestsStmt->fetchAll();

$searchTerm   = trim($_GET['search'] ?? '');
$dateFilter   = trim($_GET['date'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', QUOTATION_STATUSES, true) ? $_GET['status'] : '';
$perPage      = 10;
$page         = max(1, (int) ($_GET['page'] ?? 1));

$baseQuery = "FROM quotations q
     INNER JOIN clients c ON q.client_id = c.client_id
     INNER JOIN service_requests sr ON q.request_id = sr.request_id
     LEFT JOIN users u ON q.prepared_by = u.user_id
     WHERE 1=1";
$baseParams = [];

if ($searchTerm !== '') {
    $baseQuery .= " AND (q.quotation_number LIKE ? OR c.company_name LIKE ? OR sr.request_title LIKE ?)";
    $like = '%' . $searchTerm . '%';
    array_push($baseParams, $like, $like, $like);
}

if ($dateFilter !== '') {
    $baseQuery .= " AND DATE(q.created_at) = ?";
    $baseParams[] = $dateFilter;
}

$statsStmt = $pdo->prepare("SELECT q.status, COUNT(*) AS cnt, COALESCE(SUM(q.total_amount), 0) AS total $baseQuery GROUP BY q.status");
$statsStmt->execute($baseParams);
$statusCounts = ['Draft' => 0, 'Approved' => 0, 'Rejected' => 0, 'Revert' => 0];
$totalApprovedValue = 0.0;
foreach ($statsStmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['cnt'];
    if ($row['status'] === 'Approved') {
        $totalApprovedValue = (float) $row['total'];
    }
}
$totalQuotations = array_sum($statusCounts);

$listQuery = $baseQuery;
$listParams = $baseParams;
if ($statusFilter !== '') {
    $listQuery .= " AND q.status = ?";
    $listParams[] = $statusFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $listQuery");
$countStmt->execute($listParams);
$filteredQuotationCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($filteredQuotationCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$quotationsStmt = $pdo->prepare(
    "SELECT q.quotation_id, q.quotation_number, q.status,
            q.subtotal, q.tax_rate, q.tax_amount, q.total_amount, q.project_scope, q.valid_until, q.created_at,
            c.company_name, sr.request_title, sr.status AS request_status,
            (SELECT COUNT(*) FROM contracts ct WHERE ct.quotation_id = q.quotation_id) AS contract_count,
            u.firstname AS prepared_by_firstname, u.lastname AS prepared_by_lastname
     $listQuery
     ORDER BY q.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$quotationsStmt->execute($listParams);
$quotations = $quotationsStmt->fetchAll();

$itemsByQuotation = [];
$quotationIds = array_column($quotations, 'quotation_id');
if (!empty($quotationIds)) {
    $placeholders = implode(',', array_fill(0, count($quotationIds), '?'));
    $itemsStmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id IN ($placeholders) ORDER BY quotation_id ASC, sort_order ASC");
    $itemsStmt->execute($quotationIds);
    foreach ($itemsStmt->fetchAll() as $row) {
        $itemsByQuotation[$row['quotation_id']][] = $row;
    }
}

function buildQuotationPageUrl(int $targetPage, string $searchTerm, string $dateFilter, string $statusFilter): string
{
    $params = ['page' => $targetPage];
    if ($searchTerm !== '') {
        $params['search'] = $searchTerm;
    }
    if ($dateFilter !== '') {
        $params['date'] = $dateFilter;
    }
    if ($statusFilter !== '') {
        $params['status'] = $statusFilter;
    }
    return '?' . http_build_query($params);
}

function statusClass(string $status): string
{
    return 'status-' . strtolower($status);
}

$statusTabs = [
    ''         => ['label' => 'All', 'count' => $totalQuotations],
    'Draft'    => ['label' => 'Draft', 'count' => $statusCounts['Draft']],
    'Approved' => ['label' => 'Approved', 'count' => $statusCounts['Approved']],
    'Rejected' => ['label' => 'Rejected', 'count' => $statusCounts['Rejected']],
    'Revert'   => ['label' => 'Revert', 'count' => $statusCounts['Revert']],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>CPQ and Scope Builder</title>
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
  --success-border: #BFE3D3;

  --warn: #B7791F;
  --warn-soft: #FBF0DD;
  --warn-text: #8A5A15;
  --warn-border: #EFD8A8;

  --danger: #B4432F;
  --danger-soft: #FAECE8;
  --danger-text: #93382A;
  --danger-border: #EDC7BC;

  --ink: #1A2233;
  --ink-soft: #667085;
  --line: #E2E5EB;
  --canvas: #FFFFFF;
  --card: #FFFFFF;
}

* { -webkit-tap-highlight-color: transparent; }

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  overflow-x: hidden;
}

.dashboard-layout, .dashboard-main, .dashboard-content {
  background-color: var(--canvas) !important;
}

.dashboard-main { min-width: 0; max-width: 100%; }

.dashboard-title, h1, h2, h3 {
  font-family: 'Lexend', 'Inter', sans-serif;
}

.dashboard-title { color: var(--navy-deep); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background-color: #fff; }

.card { border-radius: 12px; border: 1px solid var(--line); box-shadow: none; }

.card-header {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card) !important;
  border-radius: 12px 12px 0 0 !important;
  padding: 1rem 1.15rem;
}

.card-header h2 { color: var(--ink); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; }

.form-control, .form-select { font-size: .875rem; }

.form-control:focus, .form-select:focus {
  border-color: var(--indigo);
  box-shadow: 0 0 0 .2rem rgba(59,78,138,.13);
  outline: none;
}

.form-control:hover, .form-select:hover { border-color: #C6CCD8; }

.form-label { font-size: .8rem; font-weight: 600; color: var(--slate); text-transform: uppercase; letter-spacing: .02em; }

.btn-primary-solid {
  background-color: var(--navy-deep);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
}
.btn-primary-solid:hover { background-color: #060B14; color: #fff; }

.btn-teal-solid {
  background-color: var(--indigo);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
}
.btn-teal-solid:hover { background-color: var(--indigo-text); color: #fff; }

.btn-approve-solid {
  background-color: var(--success);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
}
.btn-approve-solid:hover { background-color: var(--success-text); color: #fff; }

.btn-ghost {
  background-color: var(--navy-soft);
  color: var(--navy);
  border: 1px solid var(--line);
  border-radius: 7px;
  font-weight: 600;
  font-size: .8rem;
}
.btn-ghost:hover { background-color: #E4E8F0; color: var(--navy); }

.btn-action {
  width: 34px;
  height: 34px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: 8px;
  color: #fff;
  font-size: .8rem;
  transition: background-color .15s ease, transform .1s ease, box-shadow .15s ease;
}
.btn-action:active { transform: scale(.96); }
.btn-action:focus-visible { outline: none; box-shadow: 0 0 0 .2rem rgba(30, 41, 59, .22); }

.btn-action-view { background-color: #2F4A6D; }
.btn-action-view:hover { background-color: #263C59; color: #fff; }

.btn-action-edit { background-color: #3B4E8A; }
.btn-action-edit:hover { background-color: #2E3E70; color: #fff; }

.btn-action-approve { background-color: var(--success); }
.btn-action-approve:hover { background-color: var(--success-text); color: #fff; }

.btn-action-reject { background-color: var(--danger); }
.btn-action-reject:hover { background-color: #9C3A29; color: #fff; }

.btn-action-reopen { background-color: var(--slate-soft); }
.btn-action-reopen:hover { background-color: var(--slate); color: #fff; }

.btn-reset {
  background-color: var(--danger);
  color: #fff;
  border: 1px solid var(--danger);
  border-radius: 8px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
}
.btn-reset:hover { background-color: #9C3A29; border-color: #9C3A29; color: #fff; }
.btn-reset:active { background-color: #8A3324; border-color: #8A3324; color: #fff; }
.btn-reset:focus-visible {
  outline: none;
  box-shadow: 0 0 0 .2rem rgba(180, 67, 47, .25);
}

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .7rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  background-color: var(--navy-soft) !important;
  white-space: nowrap;
}

.table td { border-bottom: 1px solid var(--line); color: var(--ink); vertical-align: middle; }
.table-hover tbody tr:hover { background-color: var(--navy-soft); }

.status-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  min-width: 92px;
  padding: .35rem .75rem;
  border-radius: 6px;
  font-size: .75rem;
  font-weight: 700;
  line-height: 1.2;
  white-space: nowrap;
  color: #FFFFFF;
  border: none;
}
.status-draft    { background-color: #CA8A04; color: #FFFFFF; }
.status-revert   { background-color: #3B4E8A; color: #FFFFFF; }
.status-approved { background-color: var(--success); color: #FFFFFF; }
.status-rejected { background-color: var(--danger); color: #FFFFFF; }

.completed-mark {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .15rem;
  line-height: 1.1;
  color: var(--success);
  min-width: 72px;
}
.completed-mark i {
  font-size: 1.05rem;
  color: var(--success);
}
.completed-mark span {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--success);
}

.status-tabs { display: flex; gap: .5rem; flex-wrap: wrap; padding: 4px; }

.status-tab {
  --tab-color: #1F2937;
  --tab-text: #FFFFFF;
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  padding: .4rem .85rem;
  border-radius: 8px;
  border: 2px solid var(--tab-color);
  background-color: var(--tab-color);
  color: #FFFFFF !important;
  font-size: .8rem;
  font-weight: 600;
  text-decoration: none;
  transition: box-shadow .15s ease, transform .1s ease;
}
.status-tab.tab-all      { --tab-color: #1F2937; --tab-text: #FFFFFF; }
.status-tab.tab-draft    { --tab-color: #CA8A04; --tab-text: #FFFFFF; }
.status-tab.tab-approved { --tab-color: var(--success); --tab-text: #FFFFFF; }
.status-tab.tab-rejected { --tab-color: var(--danger); --tab-text: #FFFFFF; }
.status-tab.tab-revert   { --tab-color: #3B4E8A; --tab-text: #FFFFFF; }

.status-tab:hover {
  color: #FFFFFF !important;
  transform: translateY(-1px);
}

.status-tab.is-active {
  box-shadow: 0 0 0 3px #fff, 0 0 0 5px var(--tab-color);
}
.status-tab.is-active:hover { color: #FFFFFF !important; }

.status-tab .tab-count { font-size: .72rem; color: #FFFFFF; opacity: 1; }

.search-spinner {
  display: none;
  width: 14px;
  height: 14px;
  border: 2px solid var(--line);
  border-top-color: var(--indigo);
  border-radius: 50%;
  animation: searchspin .7s linear infinite;
}
.search-spinner.is-active { display: inline-block; }
@keyframes searchspin { to { transform: rotate(360deg); } }

.item-row { background-color: var(--navy-soft); border-radius: 10px; padding: .75rem; border: 1px solid var(--line); }

.line-total-display {
  font-weight: 700;
  font-size: .85rem;
  color: var(--indigo-text);
}

.totals-box {
  background-color: var(--indigo-soft);
  border-radius: 10px;
  padding: 1rem 1.15rem;
  border: 1px solid #DADFEE;
}

.totals-box .row-line {
  display: flex;
  justify-content: space-between;
  gap: .75rem;
  font-size: .85rem;
  color: var(--slate);
  margin-bottom: .35rem;
}

.totals-box .row-line.grand {
  color: var(--indigo-text);
  font-weight: 700;
  font-size: 1.05rem;
  margin-top: .5rem;
  padding-top: .5rem;
  border-top: 1px solid #C6CDE6;
}

.btn-close-remove {
  background-color: #9B2C2C;
  color: #F5E1E1;
  border: 1px solid #7F1D1D;
  border-radius: 7px;
  font-size: .85rem;
  line-height: 1;
  padding: .35rem .55rem;
  transition: background-color .15s ease, border-color .15s ease, color .15s ease;
}
.btn-close-remove:hover { background-color: #7F1D1D; border-color: #631717; color: #fff; }
.btn-close-remove:active { background-color: #6B1717; border-color: #501111; color: #fff; }
.btn-close-remove:focus-visible {
  outline: none;
  box-shadow: 0 0 0 .2rem rgba(155, 44, 44, .25);
}

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.summary-bar {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  background-color: #fff;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .75rem 1.15rem;
}

.summary-item {
  display: flex;
  align-items: baseline;
  gap: .65rem;
  min-width: 0;
}

.summary-label {
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--ink-soft);
  white-space: nowrap;
}

.summary-value {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--navy-deep);
  word-break: break-word;
}

.summary-money { color: var(--success-text); }

.summary-divider {
  width: 1px;
  align-self: stretch;
  background-color: var(--line);
}

.pagination .page-link { color: var(--indigo-text); border-color: var(--line); }
.pagination .page-item.active .page-link { background-color: var(--indigo); border-color: var(--indigo); color: #fff; }
.pagination .page-item.disabled .page-link { color: #adb5bd; }

.view-section-label {
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin-bottom: .3rem;
}

.view-notes-box {
  background-color: var(--navy-soft);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .75rem .9rem;
  white-space: pre-line;
  word-break: break-word;
  min-height: 2.5rem;
  color: var(--ink);
}

.request-preview-box {
  background-color: var(--navy-soft);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .85rem 1rem;
  display: none;
}
.request-preview-box.is-visible { display: block; }
.request-preview-box .view-section-label { margin-bottom: .15rem; }

#newQuotationModal .modal-content,
#viewQuotationModal .modal-content,
#editQuotationModal .modal-content { border-radius: 0; }

#quotationForm, #editQuotationForm {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

#newQuotationModal .modal-header,
#viewQuotationModal .modal-header,
#editQuotationModal .modal-header {
  padding: .9rem 1.25rem;
  background-color: #fff;
  border-bottom: 1px solid var(--line);
}

#newQuotationModal .modal-body,
#viewQuotationModal .modal-body,
#editQuotationModal .modal-body {
  flex: 1 1 auto;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  background-color: var(--navy-soft);
  padding: 1.25rem;
}

#newQuotationModal .modal-footer,
#viewQuotationModal .modal-footer,
#editQuotationModal .modal-footer {
  background-color: #fff;
  padding: .75rem 1.25rem;
  border-top: 1px solid var(--line);
}

.quote-shell {
  max-width: 1400px;
  margin: 0 auto;
}

.quote-panel {
  background-color: #fff;
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.1rem 1.25rem;
}

.quote-panel-title {
  display: flex;
  align-items: center;
  gap: .55rem;
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: .95rem;
  font-weight: 600;
  color: var(--navy-deep);
  margin-bottom: 1rem;
}

.quote-panel-title .step-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background-color: var(--indigo-soft);
  color: var(--indigo-text);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .75rem;
  flex-shrink: 0;
}

.quote-sticky {
  position: sticky;
  top: 0;
}

.mobile-row-label { display: none; }

@media (max-width: 1199.98px) {
  .quote-panel { padding: 1rem 1.05rem; }
}

@media (max-width: 991.98px) {
  .quote-sticky { position: static; }
  .dashboard-title { font-size: 1rem !important; }
  .dashboard-subtitle { font-size: .78rem !important; }
  .table td, .table th { font-size: .8rem; }
  .quote-panel-title { font-size: .88rem; margin-bottom: .8rem; }
  .quote-panel-title .step-icon { width: 24px; height: 24px; font-size: .68rem; }
  #newQuotationModal .modal-body,
  #viewQuotationModal .modal-body,
  #editQuotationModal .modal-body { padding: .9rem; }
}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .65rem !important; }
  .dashboard-topbar { padding-left: .65rem !important; padding-right: .65rem !important; }
  .dashboard-title { font-size: .92rem !important; }

  .card { border-radius: 10px; }

  .form-control, .form-select, .input-group-text { font-size: .8rem; padding-top: .4rem; padding-bottom: .4rem; }
  .input-group-text i { font-size: .78rem; }
  .btn { font-size: .8rem; }
  .btn-ghost { font-size: .74rem; padding: .32rem .55rem; }

  .summary-bar {
    gap: .75rem;
    padding: .6rem .8rem;
  }
  .summary-item {
    flex: 1 1 0;
    flex-direction: column;
    align-items: flex-start;
    gap: .1rem;
  }
  .summary-label { font-size: .6rem; letter-spacing: .04em; }
  .summary-value { font-size: .98rem; }

  .table-responsive { overflow: visible; }
  #quotationsTable thead { display: none; }
  #quotationsTable, #quotationsTable tbody, #quotationsTable tr, #quotationsTable td { display: block; width: 100%; }
  #quotationsTable tbody tr {
    border: 1px solid var(--line);
    border-radius: 10px;
    margin: .6rem .65rem;
    padding: .55rem .2rem .45rem;
    background-color: #fff;
  }
  #quotationsTable.table-hover tbody tr:hover { background-color: #fff; }
  #quotationsTable tbody tr td {
    display: flex !important;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    border: none;
    padding: .28rem .7rem;
    font-size: .78rem;
    text-align: right;
  }
  #quotationsTable tbody tr td .mobile-row-label {
    display: block;
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--ink-soft);
    text-align: left;
    flex: 0 0 auto;
    padding-top: .1rem;
  }
  #quotationsTable tbody tr td .cell-body { flex: 1 1 auto; min-width: 0; word-break: break-word; }
  #quotationsTable tbody tr td.cell-actions {
    justify-content: flex-end;
    border-top: 1px solid var(--line);
    margin-top: .35rem;
    padding-top: .5rem;
  }
  #quotationsTable tbody tr td.cell-actions .mobile-row-label { display: none; }
  #quotationsTable tbody tr td.cell-empty { display: block !important; text-align: center; }

  .card-footer .pagination .page-link { font-size: .75rem; padding: .25rem .5rem; }

  .modal-header { padding: .7rem .8rem !important; }
  .modal-title { font-size: .95rem !important; }
  #newQuotationModal .modal-body,
  #viewQuotationModal .modal-body,
  #editQuotationModal .modal-body { padding: .65rem; }
  #newQuotationModal .modal-footer,
  #viewQuotationModal .modal-footer,
  #editQuotationModal .modal-footer { padding: .6rem .8rem; }
  #newQuotationModal .modal-footer .btn,
  #editQuotationModal .modal-footer .btn { flex: 1 1 0; }
  .quote-panel { padding: .8rem .85rem; border-radius: 10px; }
  .quote-panel-title { font-size: .84rem; margin-bottom: .7rem; }
  .quote-panel-title .step-icon { width: 22px; height: 22px; font-size: .62rem; }
  .view-section-label { font-size: .62rem; }
  .view-notes-box { padding: .6rem .7rem; font-size: .8rem; }
  .request-preview-box { padding: .7rem .75rem; }
  .form-label { font-size: .68rem; }
  .item-row { padding: .6rem; }
  .line-total-display { font-size: .78rem; }
  .totals-box { padding: .75rem .85rem; }
  .totals-box .row-line { font-size: .78rem; }
  .totals-box .row-line.grand { font-size: .95rem; }
  #view_items_body td, #viewQuotationModal .table thead th { font-size: .72rem; }
}

@media (max-width: 575.98px) {
  .dashboard-content { padding: .5rem !important; }
  #quotationsTable tbody tr td { font-size: .74rem; }
  .modal-title { font-size: .88rem !important; }
}

#newQuotationModal .modal-content,
#viewQuotationModal .modal-content,
#editQuotationModal .modal-content {
  background-color: #FFFEFC;
}

#newQuotationModal .modal-header,
#viewQuotationModal .modal-header,
#editQuotationModal .modal-header {
  background-color: #FFFEFC;
  border-bottom-color: #E6E2DA;
}

#newQuotationModal .modal-body,
#viewQuotationModal .modal-body,
#editQuotationModal .modal-body {
  background-color: #F6F4EF;
}

#newQuotationModal .modal-footer,
#viewQuotationModal .modal-footer,
#editQuotationModal .modal-footer {
  background-color: #FFFEFC;
  border-top-color: #E6E2DA;
}

#newQuotationModal .quote-panel,
#viewQuotationModal .quote-panel,
#editQuotationModal .quote-panel {
  background-color: #FFFEFC;
  border-color: #E6E2DA;
  box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
}

#newQuotationModal .quote-panel-title,
#viewQuotationModal .quote-panel-title,
#editQuotationModal .quote-panel-title {
  color: #2B3134;
}

#newQuotationModal .item-row,
#viewQuotationModal .item-row,
#editQuotationModal .item-row {
  background-color: #FAF8F4;
  border-color: #E6E2DA;
}

#newQuotationModal .request-preview-box,
#viewQuotationModal .request-preview-box {
  background-color: #F6F4EF;
  border-color: #E6E2DA;
}

#newQuotationModal .view-notes-box,
#viewQuotationModal .view-notes-box {
  background-color: #F6F4EF;
  border-color: #E6E2DA;
}

#newQuotationModal .table thead th,
#viewQuotationModal .table thead th,
#editQuotationModal .table thead th {
  background-color: #F6F4EF !important;
  color: #6E7275;
}

#newQuotationModal .form-label,
#viewQuotationModal .form-label,
#editQuotationModal .form-label {
  color: #55595C;
}

#newQuotationModal .form-control:hover,
#newQuotationModal .form-select:hover,
#viewQuotationModal .form-control:hover,
#viewQuotationModal .form-select:hover,
#editQuotationModal .form-control:hover,
#editQuotationModal .form-select:hover {
  border-color: #CFCAC0;
}

#newQuotationModal .form-control:focus,
#newQuotationModal .form-select:focus,
#viewQuotationModal .form-control:focus,
#viewQuotationModal .form-select:focus,
#editQuotationModal .form-control:focus,
#editQuotationModal .form-select:focus {
  border-color: #2F6F6A;
  box-shadow: 0 0 0 .2rem rgba(47, 111, 106, .14);
}

#newQuotationModal .totals-box,
#viewQuotationModal .totals-box,
#editQuotationModal .totals-box {
  border-color: #CFE2DE;
}

#newQuotationModal .totals-box .row-line.grand,
#viewQuotationModal .totals-box .row-line.grand,
#editQuotationModal .totals-box .row-line.grand {
  border-top-color: #BBD5D0;
}

#newQuotationModal .view-section-label,
#viewQuotationModal .view-section-label,
#editQuotationModal .view-section-label {
  color: #6E7275;
}

#newQuotationModal .modal-title,
#viewQuotationModal .modal-title,
#editQuotationModal .modal-title {
  color: #2B3134;
}

.quotation-row { cursor: pointer; }

.revert-notice {
  display: flex;
  align-items: center;
  gap: .6rem;
  background-color: #E9ECF6;
  border: 1px solid #C9D0E8;
  border-left: 4px solid #3B4E8A;
  border-radius: 8px;
  padding: .65rem .9rem;
  color: #2E3E70;
  font-size: .82rem;
}

.btn-action-text {
  width: auto;
  padding: 0 .8rem;
  gap: .4rem;
  font-weight: 600;
  font-size: .78rem;
}

#receiptPrintArea { display: none; }

@media print {
  @page { margin: 0.3in; }
  body * { visibility: hidden; }
  #receiptPrintArea, #receiptPrintArea * { visibility: visible; }
  #receiptPrintArea {
    display: flex !important;
    justify-content: center;
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
  }
  .receipt-box {
    font-family: "Courier New", Courier, monospace;
    color: #000 !important;
    background: #fff;
    padding: 20px;
    font-size: 20px;
    font-weight: 700;
    width: 620px;
    text-align: center;
  }
  .receipt-box, .receipt-box * {
    color: #000 !important;
    opacity: 1 !important;
    text-decoration: none !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .receipt-box .r-center { text-align: center; }
  .receipt-box .r-title { font-size: 24px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
  .receipt-box .r-sub { font-size: 17px; margin-top: 4px; font-weight: 700; }
  .receipt-box .r-line { border: none; border-top: 2px dashed #000; margin: 14px 0; }
  .receipt-box .r-row { display: flex; justify-content: space-between; gap: 10px; margin: 5px 0; text-align: left; }
  .receipt-box .r-item { margin: 10px 0; text-align: left; }
  .receipt-box .r-item-desc { font-weight: 800; }
  .receipt-box .r-total-row { display: flex; justify-content: space-between; font-weight: 800; font-size: 22px; margin-top: 8px; }
}
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/manager/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">CPQ and Scope Builder</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Configure project scope, estimate costs, and generate client quotations.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="card mb-3">
        <div class="card-body p-2 p-md-3">
          <form method="GET" class="row g-2 align-items-center" id="quotationFilterForm">
            <input type="hidden" name="status" id="quotationStatusInput" value="<?= e($statusFilter) ?>">
            <div class="col-12 col-lg-6">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
                <input type="text" name="search" id="quotationSearchInput" class="form-control" placeholder="Search quotation #, company, or transaction" value="<?= e($searchTerm) ?>" autocomplete="off">
                <span class="input-group-text bg-white"><span class="search-spinner" id="searchSpinner"></span></span>
              </div>
            </div>
            <div class="col-8 col-sm-9 col-lg-3">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-regular fa-calendar" style="color:var(--ink-soft);"></i></span>
                <input type="date" name="date" id="quotationDateInput" class="form-control" value="<?= e($dateFilter) ?>">
              </div>
            </div>
            <div class="col-4 col-sm-3 col-lg-1">
              <a href="cpq_quotations.php" class="btn btn-reset w-100" title="Reset filters">
                <i class="fa-solid fa-rotate-left"></i><span class="d-none d-sm-inline d-lg-none">Reset</span>
              </a>
            </div>
            <div class="col-12 col-lg-2">
              <button type="button" class="btn btn-teal-solid w-100" data-bs-toggle="modal" data-bs-target="#newQuotationModal">
                <i class="fa-solid fa-plus me-1"></i> New Quotation
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="summary-bar mb-3">
        <div class="summary-item">
          <span class="summary-label">Total Quotations</span>
          <span class="summary-value"><?= (int) $totalQuotations ?></span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
          <span class="summary-label">Approved Value</span>
          <span class="summary-value summary-money">&#8369;<?= number_format($totalApprovedValue, 2) ?></span>
        </div>
      </div>

      <div class="status-tabs mb-3">
        <?php foreach ($statusTabs as $tabValue => $tab): ?>
          <?php $tabClass = 'tab-' . ($tabValue === '' ? 'all' : strtolower((string) $tabValue)); ?>
          <a class="status-tab <?= $tabClass ?> <?= $statusFilter === (string) $tabValue ? 'is-active' : '' ?>" href="<?= buildQuotationPageUrl(1, $searchTerm, $dateFilter, (string) $tabValue) ?>">
            <?= e($tab['label']) ?> <span class="tab-count"><?= (int) $tab['count'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <section class="card overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="quotationsTable">
            <thead>
              <tr>
                <th scope="col">Quotation #</th>
                <th scope="col">Client / Transaction</th>
                <th scope="col" class="d-none d-md-table-cell">Prepared By</th>
                <th scope="col">Total</th>
                <th scope="col">Status</th>
                <th scope="col" class="d-none d-lg-table-cell">Valid Until</th>
                <th scope="col" class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="quotationsTableBody">
              <?php if (empty($quotations)): ?>
                <tr>
                  <td colspan="7" class="cell-empty">
                    <div class="empty-state text-center py-5">
                      <i class="fa-regular fa-file-lines fs-3 mb-2 d-block"></i>
                      <p class="small mb-0">No quotations found.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($quotations as $q): ?>
                  <?php
                    $qItems = $itemsByQuotation[$q['quotation_id']] ?? [];
                    $preparedBy = trim(($q['prepared_by_firstname'] ?? '') . ' ' . ($q['prepared_by_lastname'] ?? ''));
                    $isLocked = ($q['request_status'] ?? '') === 'Completed';
                    $viewPayload = [
                        'number' => $q['quotation_number'],
                        'company' => $q['company_name'],
                        'request' => $q['request_title'],
                        'status' => $q['status'],
                        'project_scope' => $q['project_scope'] ?? '',
                        'subtotal' => number_format((float) $q['subtotal'], 2),
                        'tax_rate' => rtrim(rtrim(number_format((float) $q['tax_rate'], 2), '0'), '.'),
                        'tax_amount' => number_format((float) $q['tax_amount'], 2),
                        'total' => number_format((float) $q['total_amount'], 2),
                        'valid_until' => $q['valid_until'] ? date('M d, Y', strtotime($q['valid_until'])) : null,
                        'prepared_by' => $preparedBy,
                        'created_at' => $q['created_at'] ? date('M d, Y g:i A', strtotime($q['created_at'])) : null,
                        'items' => array_map(function ($it) {
                            return [
                                'description' => $it['description'],
                                'quantity' => rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.'),
                                'unit_price' => number_format((float) $it['unit_price'], 2),
                                'line_total' => number_format((float) $it['line_total'], 2),
                            ];
                        }, $qItems),
                    ];
                    $editPayload = array_map(function ($it) {
                        return [
                            'description' => $it['description'],
                            'quantity' => (float) $it['quantity'],
                            'unit_price' => (float) $it['unit_price'],
                        ];
                    }, $qItems);
                  ?>
                  <tr class="quotation-row" data-quotation="<?= e(json_encode($viewPayload)) ?>">
                    <td class="small fw-semibold">
                      <span class="mobile-row-label">Quotation #</span>
                      <span class="cell-body fw-semibold"><?= e($q['quotation_number']) ?></span>
                    </td>
                    <td class="small">
                      <span class="mobile-row-label">Client</span>
                      <span class="cell-body">
                        <span class="fw-semibold d-block"><?= e($q['company_name']) ?></span>
                        <span class="d-block" style="color:var(--ink-soft); font-size:.75rem;"><?= e($q['request_title']) ?></span>
                      </span>
                    </td>
                    <td class="small d-none d-md-table-cell">
                      <span class="mobile-row-label">Prepared By</span>
                      <span class="cell-body"><?= $preparedBy !== '' ? e($preparedBy) : '&mdash;' ?></span>
                    </td>
                    <td class="small fw-semibold">
                      <span class="mobile-row-label">Total</span>
                      <span class="cell-body fw-semibold">&#8369;<?= number_format((float) $q['total_amount'], 2) ?></span>
                    </td>
                    <td class="small">
                      <span class="mobile-row-label">Status</span>
                      <span class="cell-body"><span class="status-badge <?= statusClass($q['status']) ?>"><?= e($q['status']) ?></span></span>
                    </td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);">
                      <span class="mobile-row-label">Valid Until</span>
                      <span class="cell-body"><?= $q['valid_until'] ? e(date('M d, Y', strtotime($q['valid_until']))) : '&mdash;' ?></span>
                    </td>
                    <td class="text-end cell-actions">
                      <span class="mobile-row-label">Action</span>
                      <span class="cell-body d-flex justify-content-end gap-1 flex-wrap">
                        <?php if ($isLocked): ?>
                          <span class="completed-mark">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Completed</span>
                          </span>
                        <?php else: ?>
                          <?php if (in_array($q['status'], ['Draft', 'Revert'], true)): ?>
                            <button type="button" class="btn-action btn-action-text btn-action-approve status-action-btn" title="Approve"
                              data-action="approve_quotation" data-quotation-id="<?= (int) $q['quotation_id'] ?>" data-number="<?= e($q['quotation_number']) ?>">
                              <i class="fa-solid fa-check"></i><span>Approve</span>
                            </button>
                            <button type="button" class="btn-action btn-action-text btn-action-reject status-action-btn" title="Reject"
                              data-action="reject_quotation" data-quotation-id="<?= (int) $q['quotation_id'] ?>" data-number="<?= e($q['quotation_number']) ?>">
                              <i class="fa-solid fa-xmark"></i><span>Reject</span>
                            </button>
                          <?php elseif ($q['status'] === 'Rejected'): ?>
                            <button type="button" class="btn-action btn-action-text btn-action-reopen status-action-btn" title="Move back to draft"
                              data-action="reopen_quotation" data-quotation-id="<?= (int) $q['quotation_id'] ?>" data-number="<?= e($q['quotation_number']) ?>">
                              <i class="fa-solid fa-rotate-left"></i><span>Reopen</span>
                            </button>
                          <?php endif; ?>
                          <?php $editLabel = $q['status'] === 'Revert' ? 'Edit' : 'Revert'; ?>
                          <button type="button" class="btn-action btn-action-text btn-action-edit edit-quotation-btn" title="<?= e($editLabel) ?> quotation"
                            data-quotation-id="<?= (int) $q['quotation_id'] ?>"
                            data-number="<?= e($q['quotation_number']) ?>"
                            data-project-scope="<?= e($q['project_scope'] ?? '') ?>"
                            data-tax-rate="<?= e(rtrim(rtrim(number_format((float) $q['tax_rate'], 2), '0'), '.')) ?>"
                            data-valid-until="<?= e($q['valid_until'] ?? '') ?>"
                            data-has-contract="<?= (int) $q['contract_count'] > 0 ? '1' : '0' ?>"
                            data-items="<?= e(json_encode($editPayload)) ?>">
                            <i class="fa-solid fa-pen"></i><span><?= e($editLabel) ?></span>
                          </button>
                        <?php endif; ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
          <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3" style="border-top:1px solid var(--line);">
            <span class="small" style="color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredQuotationCount ?> total</span>
            <nav aria-label="Quotations pagination">
              <ul class="pagination pagination-sm mb-0 flex-wrap">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildQuotationPageUrl($page - 1, $searchTerm, $dateFilter, $statusFilter) ?>">Prev</a>
                </li>
                <?php $lastRendered = 0; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <?php if ($i !== 1 && $i !== $totalPages && abs($i - $page) > 1) { continue; } ?>
                  <?php if ($i - $lastRendered > 1): ?>
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                  <?php endif; ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= buildQuotationPageUrl($i, $searchTerm, $dateFilter, $statusFilter) ?>"><?= $i ?></a>
                  </li>
                  <?php $lastRendered = $i; ?>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildQuotationPageUrl($page + 1, $searchTerm, $dateFilter, $statusFilter) ?>">Next</a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<div class="modal fade" id="newQuotationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <form method="POST" id="quotationForm">
        <input type="hidden" name="action" value="create_quotation">

        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold mb-0">New Quotation</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="quote-shell">
            <div class="row g-2 g-md-3">

              <div class="col-lg-8">
                <div class="d-flex flex-column gap-2 gap-md-3">

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-clipboard-list"></i></span>
                      Service Transaction
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Select Transaction</label>
                      <select class="form-select" name="request_id" id="requestSelect" required>
                        <option value="" selected disabled>Select a service transaction</option>
                        <?php foreach ($serviceRequests as $req): ?>
                          <option value="<?= (int) $req['request_id'] ?>"
                            data-company="<?= e($req['company_name']) ?>"
                            data-title="<?= e($req['request_title']) ?>"
                            data-skill="<?= e($req['required_skill'] ?? '') ?>">
                            <?= e($req['company_name']) ?> &mdash; <?= e($req['request_title']) ?> (<?= e($req['status']) ?>)
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <div class="request-preview-box" id="requestPreviewBox">
                      <div class="row g-2 g-md-3">
                        <div class="col-12 col-sm-6">
                          <div class="view-section-label">Client</div>
                          <div class="small fw-semibold" id="requestPreviewCompany"></div>
                        </div>
                        <div class="col-12 col-sm-6">
                          <div class="view-section-label">Title</div>
                          <div class="small fw-semibold" id="requestPreviewTitle"></div>
                        </div>
                        <div class="col-12">
                          <div class="view-section-label">Required Skill</div>
                          <div class="small" id="requestPreviewSkill"></div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-bullseye"></i></span>
                      Project Scope
                    </div>
                    <textarea class="form-control" name="project_scope" rows="3" placeholder="Describe the overall project scope and deliverables"></textarea>
                  </div>

                  <div class="quote-panel">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                      <div class="quote-panel-title mb-0">
                      <span class="step-icon"><i class="fa-solid fa-list-check"></i></span>
                      Scope Items
                    </div>
                      <button type="button" class="btn btn-ghost btn-sm text-nowrap" id="addItemBtn">
                        <i class="fa-solid fa-plus me-1"></i> Add Item
                      </button>
                    </div>
                    <div id="itemsContainer" class="d-flex flex-column gap-2"></div>
                  </div>

                </div>
              </div>

              <div class="col-lg-4">
                <div class="quote-sticky d-flex flex-column gap-2 gap-md-3">

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-sliders"></i></span>
                      Quotation Details
                    </div>
                    <div class="row g-2 g-md-3">
                      <div class="col-6 col-lg-12 col-xl-6">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" class="form-control" name="tax_rate" id="taxRateInput" step="0.01" min="0" value="12">
                      </div>
                      <div class="col-6 col-lg-12 col-xl-6">
                        <label class="form-label">Valid Until</label>
                        <input type="date" class="form-control" name="valid_until" min="<?= date('Y-m-d') ?>">
                      </div>
                    </div>
                  </div>

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-calculator"></i></span>
                      Summary
                    </div>
                    <div class="totals-box">
                      <div class="row-line"><span>Subtotal</span><span id="subtotalDisplay">&#8369;0.00</span></div>
                      <div class="row-line"><span>Tax</span><span id="taxDisplay">&#8369;0.00</span></div>
                      <div class="row-line grand"><span>Total</span><span id="totalDisplay">&#8369;0.00</span></div>
                    </div>
                  </div>

                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid px-4 w-100 w-sm-auto">Save Quotation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editQuotationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <form method="POST" id="editQuotationForm">
        <input type="hidden" name="action" value="edit_quotation">
        <input type="hidden" name="quotation_id" id="edit_quotation_id">

        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold mb-0">Revert Quotation <span id="edit_quotation_number_label" style="color:var(--ink-soft); font-weight:600;"></span></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="quote-shell">
            <div class="row g-2 g-md-3">

              <div class="col-12 d-none" id="editContractNotice">
                <div class="revert-notice">
                  <i class="fa-solid fa-triangle-exclamation"></i>
                  <span>This quotation already has a contract. Saving will remove that contract, and the quotation must be approved again before a new contract can be generated.</span>
                </div>
              </div>

              <div class="col-lg-8">
                <div class="d-flex flex-column gap-2 gap-md-3">

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-bullseye"></i></span>
                      Project Scope
                    </div>
                    <textarea class="form-control" name="edit_project_scope" id="edit_project_scope" rows="3" placeholder="Describe the overall project scope and deliverables"></textarea>
                  </div>

                  <div class="quote-panel">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                      <div class="quote-panel-title mb-0">
                      <span class="step-icon"><i class="fa-solid fa-list-check"></i></span>
                      Scope Items
                    </div>
                      <button type="button" class="btn btn-ghost btn-sm text-nowrap" id="editAddItemBtn">
                        <i class="fa-solid fa-plus me-1"></i> Add Item
                      </button>
                    </div>
                    <div id="editItemsContainer" class="d-flex flex-column gap-2"></div>
                  </div>

                </div>
              </div>

              <div class="col-lg-4">
                <div class="quote-sticky d-flex flex-column gap-2 gap-md-3">

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-sliders"></i></span>
                      Quotation Details
                    </div>
                    <div class="row g-2 g-md-3">
                      <div class="col-6 col-lg-12 col-xl-6">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" class="form-control" name="edit_tax_rate" id="edit_tax_rate" step="0.01" min="0" value="12">
                      </div>
                      <div class="col-6 col-lg-12 col-xl-6">
                        <label class="form-label">Valid Until</label>
                        <input type="date" class="form-control" name="edit_valid_until" id="edit_valid_until">
                      </div>
                    </div>
                  </div>

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-calculator"></i></span>
                      Summary
                    </div>
                    <div class="totals-box">
                      <div class="row-line"><span>Subtotal</span><span id="editSubtotalDisplay">&#8369;0.00</span></div>
                      <div class="row-line"><span>Tax</span><span id="editTaxDisplay">&#8369;0.00</span></div>
                      <div class="row-line grand"><span>Total</span><span id="editTotalDisplay">&#8369;0.00</span></div>
                    </div>
                  </div>

                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid px-4 w-100 w-sm-auto">Save &amp; Revert</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewQuotationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold mb-0 d-flex align-items-center gap-2">
          <span id="view_quotation_number">Quotation</span>
          <span class="status-badge" id="view_status_badge"></span>
        </h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="quote-shell">
          <div class="row g-2 g-md-3">

            <div class="col-lg-8">
              <div class="d-flex flex-column gap-2 gap-md-3">

                <div class="quote-panel">
                  <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-circle-info"></i></span>
                      Overview
                    </div>
                  <div class="row g-2 g-md-3">
                    <div class="col-12 col-sm-6">
                      <div class="view-section-label">Client</div>
                      <div class="fw-semibold" id="view_client_name"></div>
                      <div class="small" id="view_request_title" style="color:var(--ink-soft);"></div>
                    </div>
                    <div class="col-6 col-sm-6">
                      <div class="view-section-label">Valid Until</div>
                      <div class="small fw-semibold" id="view_valid_until"></div>
                    </div>
                    <div class="col-6 col-sm-6">
                      <div class="view-section-label">Prepared By</div>
                      <div class="small" id="view_prepared_by"></div>
                    </div>
                    <div class="col-6 col-sm-6">
                      <div class="view-section-label">Created</div>
                      <div class="small" id="view_created_at"></div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-bullseye"></i></span>
                      Project Scope
                    </div>
                  <div class="view-notes-box" id="view_project_scope"></div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-list-check"></i></span>
                      Scope Items
                    </div>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead>
                        <tr>
                          <th class="small">Description</th>
                          <th class="small text-end">Qty</th>
                          <th class="small text-end">Unit Price</th>
                          <th class="small text-end">Total</th>
                        </tr>
                      </thead>
                      <tbody id="view_items_body"></tbody>
                    </table>
                  </div>
                </div>

              </div>
            </div>

            <div class="col-lg-4">
              <div class="quote-sticky d-flex flex-column gap-2 gap-md-3">

                <div class="quote-panel">
                  <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-calculator"></i></span>
                      Summary
                    </div>
                  <div class="totals-box">
                    <div class="row-line"><span>Subtotal</span><span id="view_subtotal"></span></div>
                    <div class="row-line"><span>Tax</span><span id="view_tax"></span></div>
                    <div class="row-line grand"><span>Total</span><span id="view_total"></span></div>
                  </div>
                  <button type="button" class="btn w-100 mt-3" id="printReceiptBtnSummary" style="background-color: transparent; border: none; color: var(--navy); font-weight: 600; box-shadow: none;">
                    <i class="fa-solid fa-print me-1"></i> Print Receipt
                  </button>
                </div>

              </div>
            </div>

          </div>
        </div>
      </div>

      <div class="modal-footer">
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:12px;">
      <form method="POST" id="statusForm">
        <input type="hidden" name="action" id="status_action">
        <input type="hidden" name="quotation_id" id="status_quotation_id">

        <div class="modal-header" style="border-bottom:1px solid var(--line);">
          <h2 class="modal-title h6 fw-bold mb-0" id="status_title"></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <p class="small mb-0" id="status_text"></p>
        </div>

        <div class="modal-footer" style="border-top:1px solid var(--line); gap:.5rem;">
          <button type="submit" class="btn" id="status_confirm_btn"></button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="receiptPrintArea"></div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
let itemIndex = 0;
const itemsContainer = document.getElementById('itemsContainer');
const addItemBtn = document.getElementById('addItemBtn');
const taxRateInput = document.getElementById('taxRateInput');
const requestSelect = document.getElementById('requestSelect');
const requestPreviewBox = document.getElementById('requestPreviewBox');
const requestPreviewCompany = document.getElementById('requestPreviewCompany');
const requestPreviewTitle = document.getElementById('requestPreviewTitle');
const requestPreviewSkill = document.getElementById('requestPreviewSkill');

const filterForm = document.getElementById('quotationFilterForm');
const searchInput = document.getElementById('quotationSearchInput');
const dateInput = document.getElementById('quotationDateInput');
const statusInput = document.getElementById('quotationStatusInput');
const searchSpinner = document.getElementById('searchSpinner');

function submitFilters() {
  const params = new URLSearchParams();
  const term = searchInput.value.trim();
  if (term !== '') params.set('search', term);
  if (dateInput.value !== '') params.set('date', dateInput.value);
  if (statusInput.value !== '') params.set('status', statusInput.value);
  params.set('focus', '1');
  window.location.href = 'cpq_quotations.php?' + params.toString();
}

let filterTimer = null;

searchInput.addEventListener('input', function () {
  searchSpinner.classList.add('is-active');
  clearTimeout(filterTimer);
  filterTimer = setTimeout(submitFilters, 450);
});

searchInput.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    clearTimeout(filterTimer);
    submitFilters();
  }
});

dateInput.addEventListener('change', function () {
  clearTimeout(filterTimer);
  searchSpinner.classList.add('is-active');
  submitFilters();
});

filterForm.addEventListener('submit', function (e) {
  e.preventDefault();
  clearTimeout(filterTimer);
  submitFilters();
});

window.addEventListener('DOMContentLoaded', function () {
  const params = new URLSearchParams(window.location.search);
  if (params.get('focus') === '1') {
    searchInput.focus();
    const len = searchInput.value.length;
    searchInput.setSelectionRange(len, len);
  }
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = (str === null || str === undefined) ? '' : String(str);
  return div.innerHTML;
}

function updateRequestPreview() {
  const option = requestSelect.options[requestSelect.selectedIndex];
  if (!option || !option.value) {
    requestPreviewBox.classList.remove('is-visible');
    return;
  }
  requestPreviewCompany.textContent = option.dataset.company || '';
  requestPreviewTitle.textContent = option.dataset.title || '';
  requestPreviewSkill.textContent = option.dataset.skill || 'Not specified / any skill';
  requestPreviewBox.classList.add('is-visible');
}

requestSelect.addEventListener('change', updateRequestPreview);

function buildItemRow(prefix, description, quantity, unitPrice) {
  const row = document.createElement('div');
  row.className = 'item-row';
  row.innerHTML =
    '<div class="row g-2 align-items-end">' +
      '<div class="col-12 col-sm-5">' +
        '<label class="form-label mb-1" style="font-size:.72rem;">Description</label>' +
        '<input type="text" class="form-control form-control-sm item-desc" name="' + prefix + 'item_description[]" placeholder="e.g. Business process audit" required>' +
      '</div>' +
      '<div class="col-6 col-sm-2">' +
        '<label class="form-label mb-1" style="font-size:.72rem;">Qty</label>' +
        '<input type="number" class="form-control form-control-sm item-qty" name="' + prefix + 'item_quantity[]" min="0.01" step="0.01" required>' +
      '</div>' +
      '<div class="col-6 col-sm-2">' +
        '<label class="form-label mb-1" style="font-size:.72rem;">Unit Price</label>' +
        '<input type="number" class="form-control form-control-sm item-price" name="' + prefix + 'item_unit_price[]" min="0" step="0.01" required>' +
      '</div>' +
      '<div class="col-8 col-sm-2 text-end">' +
        '<div class="line-total-display item-line-total">\u20B10.00</div>' +
      '</div>' +
      '<div class="col-4 col-sm-1 text-end">' +
        '<button type="button" class="btn-close-remove remove-item-btn" aria-label="Remove item"><i class="fa-solid fa-trash-can"></i></button>' +
      '</div>' +
    '</div>';

  row.querySelector('.item-desc').value = description;
  row.querySelector('.item-qty').value = quantity;
  row.querySelector('.item-price').value = unitPrice;
  return row;
}

function calculate(container, taxInput, subtotalEl, taxEl, totalEl) {
  let subtotal = 0;
  container.querySelectorAll('.item-row').forEach(function (row) {
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const lineTotal = qty * price;
    row.querySelector('.item-line-total').textContent = '\u20B1' + lineTotal.toFixed(2);
    subtotal += lineTotal;
  });
  const taxAmount = subtotal * ((parseFloat(taxInput.value) || 0) / 100);
  document.getElementById(subtotalEl).textContent = '\u20B1' + subtotal.toFixed(2);
  document.getElementById(taxEl).textContent = '\u20B1' + taxAmount.toFixed(2);
  document.getElementById(totalEl).textContent = '\u20B1' + (subtotal + taxAmount).toFixed(2);
}

function recalculateTotals() {
  calculate(itemsContainer, taxRateInput, 'subtotalDisplay', 'taxDisplay', 'totalDisplay');
}

function addItemRow() {
  const row = buildItemRow('', '', 1, 0);
  itemsContainer.appendChild(row);
  row.querySelector('.item-qty').addEventListener('input', recalculateTotals);
  row.querySelector('.item-price').addEventListener('input', recalculateTotals);
  row.querySelector('.remove-item-btn').addEventListener('click', function () {
    row.remove();
    recalculateTotals();
  });
  recalculateTotals();
}

addItemBtn.addEventListener('click', addItemRow);
taxRateInput.addEventListener('input', recalculateTotals);

document.getElementById('newQuotationModal').addEventListener('show.bs.modal', function () {
  itemsContainer.innerHTML = '';
  itemIndex = 0;
  addItemRow();
  requestSelect.selectedIndex = 0;
  requestPreviewBox.classList.remove('is-visible');
  taxRateInput.value = 12;
  recalculateTotals();
});

const editItemsContainer = document.getElementById('editItemsContainer');
const editAddItemBtn = document.getElementById('editAddItemBtn');
const editTaxRateInput = document.getElementById('edit_tax_rate');

function recalculateEditTotals() {
  calculate(editItemsContainer, editTaxRateInput, 'editSubtotalDisplay', 'editTaxDisplay', 'editTotalDisplay');
}

function addEditItemRow(description, quantity, unitPrice) {
  const qty = (quantity !== undefined && quantity !== null) ? quantity : 1;
  const price = (unitPrice !== undefined && unitPrice !== null) ? unitPrice : 0;
  const row = buildItemRow('edit_', description || '', qty, price);
  editItemsContainer.appendChild(row);
  row.querySelector('.item-qty').addEventListener('input', recalculateEditTotals);
  row.querySelector('.item-price').addEventListener('input', recalculateEditTotals);
  row.querySelector('.remove-item-btn').addEventListener('click', function () {
    row.remove();
    recalculateEditTotals();
  });
  recalculateEditTotals();
}

editAddItemBtn.addEventListener('click', function () {
  addEditItemRow('', 1, 0);
});
editTaxRateInput.addEventListener('input', recalculateEditTotals);

document.querySelectorAll('.edit-quotation-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.getElementById('edit_quotation_id').value = this.dataset.quotationId;
    document.getElementById('edit_quotation_number_label').textContent = this.dataset.number ? ('\u2014 ' + this.dataset.number) : '';
    document.getElementById('edit_project_scope').value = this.dataset.projectScope || '';
    document.getElementById('edit_tax_rate').value = this.dataset.taxRate || '12';
    document.getElementById('edit_valid_until').value = this.dataset.validUntil || '';
    document.getElementById('editContractNotice').classList.toggle('d-none', this.dataset.hasContract !== '1');

    editItemsContainer.innerHTML = '';

    let items = [];
    try {
      items = JSON.parse(this.dataset.items || '[]');
    } catch (err) {
      items = [];
    }

    if (items.length === 0) {
      addEditItemRow('', 1, 0);
    } else {
      items.forEach(function (item) {
        addEditItemRow(item.description, item.quantity, item.unit_price);
      });
    }

    recalculateEditTotals();
    new bootstrap.Modal(document.getElementById('editQuotationModal')).show();
  });
});

let currentQuotationData = null;

document.querySelectorAll('.quotation-row').forEach(function (row) {
  row.addEventListener('click', function (e) {
    if (e.target.closest('.cell-actions')) return;
    const data = JSON.parse(this.dataset.quotation);
    currentQuotationData = data;

    document.getElementById('view_quotation_number').textContent = data.number;
    document.getElementById('printReceiptBtnSummary').style.display = data.status === 'Approved' ? '' : 'none';
    const badge = document.getElementById('view_status_badge');
    badge.textContent = data.status;
    badge.className = 'status-badge status-' + data.status.toLowerCase();

    document.getElementById('view_client_name').textContent = data.company;
    document.getElementById('view_request_title').textContent = data.request;
    document.getElementById('view_subtotal').textContent = '\u20B1' + data.subtotal;
    document.getElementById('view_tax').textContent = '\u20B1' + data.tax_amount + ' (' + data.tax_rate + '%)';
    document.getElementById('view_total').textContent = '\u20B1' + data.total;
    document.getElementById('view_valid_until').textContent = data.valid_until || '\u2014';
    document.getElementById('view_prepared_by').textContent = (data.prepared_by && data.prepared_by.trim() !== '') ? data.prepared_by : '\u2014';
    document.getElementById('view_created_at').textContent = data.created_at || '\u2014';
    document.getElementById('view_project_scope').textContent = (data.project_scope && data.project_scope.trim() !== '') ? data.project_scope : 'No project scope provided.';

    const body = document.getElementById('view_items_body');
    body.innerHTML = '';

    if (data.items.length === 0) {
      body.innerHTML = '<tr><td colspan="4" class="small text-center" style="color:var(--ink-soft);">No items recorded.</td></tr>';
    } else {
      data.items.forEach(function (item) {
        const tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="small">' + escapeHtml(item.description) + '</td>' +
          '<td class="small text-end">' + escapeHtml(item.quantity) + '</td>' +
          '<td class="small text-end">\u20B1' + escapeHtml(item.unit_price) + '</td>' +
          '<td class="small text-end fw-semibold">\u20B1' + escapeHtml(item.line_total) + '</td>';
        body.appendChild(tr);
      });
    }

    new bootstrap.Modal(document.getElementById('viewQuotationModal')).show();
  });
});

const statusConfig = {
  approve_quotation: {
    title: 'Approve quotation',
    text: 'This quotation will be marked as approved.',
    button: 'Approve',
    buttonClass: 'btn btn-approve-solid'
  },
  reject_quotation: {
    title: 'Reject quotation',
    text: 'This quotation will be marked as rejected. You can still edit it or move it back to draft later.',
    button: 'Reject',
    buttonClass: 'btn btn-reset'
  },
  reopen_quotation: {
    title: 'Move back to draft',
    text: 'This quotation will return to draft so it can be revised and approved again.',
    button: 'Move to Draft',
    buttonClass: 'btn btn-primary-solid'
  }
};

document.querySelectorAll('.status-action-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const cfg = statusConfig[this.dataset.action];
    if (!cfg) return;

    document.getElementById('status_action').value = this.dataset.action;
    document.getElementById('status_quotation_id').value = this.dataset.quotationId;
    document.getElementById('status_title').textContent = cfg.title + ' ' + (this.dataset.number || '');
    document.getElementById('status_text').textContent = cfg.text;

    const confirmBtn = document.getElementById('status_confirm_btn');
    confirmBtn.textContent = cfg.button;
    confirmBtn.className = cfg.buttonClass;

    new bootstrap.Modal(document.getElementById('statusModal')).show();
  });
});

document.getElementById('printReceiptBtnSummary').addEventListener('click', function () {
  if (!currentQuotationData || currentQuotationData.status !== 'Approved') return;
  printReceipt(currentQuotationData);
});

function printReceipt(data) {
  let itemsHtml = '';
  if (data.items.length === 0) {
    itemsHtml = '<div class="r-row"><span>No items recorded.</span></div>';
  } else {
    data.items.forEach(function (item) {
      itemsHtml +=
        '<div class="r-item">' +
          '<div class="r-item-desc">' + escapeHtml(item.description) + '</div>' +
          '<div class="r-row">' +
            '<span>' + escapeHtml(item.quantity) + ' x \u20B1' + escapeHtml(item.unit_price) + '</span>' +
            '<span>\u20B1' + escapeHtml(item.line_total) + '</span>' +
          '</div>' +
        '</div>';
    });
  }

  const html =
    '<div class="receipt-box">' +
      '<div class="r-center">' +
        '<div class="r-title">KMP Integrated Enterprise, Inc.</div>' +
        '<div class="r-sub">Quotation Receipt</div>' +
        '<div class="r-sub">' + escapeHtml(data.number) + '</div>' +
      '</div>' +
      '<hr class="r-line">' +
      '<div class="r-row"><span>Status:</span><span>' + escapeHtml(data.status) + '</span></div>' +
      '<div class="r-row"><span>Client:</span><span>' + escapeHtml(data.company) + '</span></div>' +
      '<div class="r-row"><span>Transaction:</span><span>' + escapeHtml(data.request) + '</span></div>' +
      '<div class="r-row"><span>Date:</span><span>' + escapeHtml(data.created_at || '-') + '</span></div>' +
      '<div class="r-row"><span>Valid Until:</span><span>' + escapeHtml(data.valid_until || '-') + '</span></div>' +
      '<div class="r-row"><span>Prepared By:</span><span>' + escapeHtml(data.prepared_by || '-') + '</span></div>' +
      '<hr class="r-line">' +
      '<div class="r-center" style="font-weight:bold;">Scope Items</div>' +
      itemsHtml +
      '<hr class="r-line">' +
      '<div class="r-row"><span>Subtotal</span><span>\u20B1' + escapeHtml(data.subtotal) + '</span></div>' +
      '<div class="r-row"><span>Tax (' + escapeHtml(data.tax_rate) + '%)</span><span>\u20B1' + escapeHtml(data.tax_amount) + '</span></div>' +
      '<hr class="r-line">' +
      '<div class="r-total-row"><span>TOTAL</span><span>\u20B1' + escapeHtml(data.total) + '</span></div>' +
    '</div>';

  document.getElementById('receiptPrintArea').innerHTML = html;
  window.print();
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>