<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

function generateContractNumber(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE contract_number LIKE ?");
    $stmt->execute(["SOW-{$year}-%"]);
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('SOW-%s-%04d', $year, $count);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'generate_contract') {
        $quotationId = $_POST['quotation_id'] ?? null;
        $startDate   = $_POST['start_date'] ?: null;
        $endDate     = $_POST['end_date'] ?: null;
        $termsConditions = trim($_POST['terms_conditions'] ?? '');

        if (!$quotationId) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Please select an approved quotation.';
            header('Location: sow_contracts.php');
            exit;
        }

        $quoteStmt = $pdo->prepare(
            "SELECT quotation_id, request_id, client_id, project_scope, total_amount
             FROM quotations WHERE quotation_id = ? AND status = 'Approved'"
        );
        $quoteStmt->execute([$quotationId]);
        $quote = $quoteStmt->fetch();

        if (!$quote) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Selected quotation was not found or is not yet approved.';
            header('Location: sow_contracts.php');
            exit;
        }

        $dupStmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE quotation_id = ?');
        $dupStmt->execute([$quotationId]);
        if ((int) $dupStmt->fetchColumn() > 0) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'A contract already exists for this quotation.';
            header('Location: sow_contracts.php');
            exit;
        }

        $contractNumber = generateContractNumber($pdo);

        $insertStmt = $pdo->prepare(
            "INSERT INTO contracts
                (contract_number, quotation_id, request_id, client_id, scope_summary, terms_conditions, total_amount, start_date, end_date, status, prepared_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?)"
        );
        $insertStmt->execute([
            $contractNumber, $quote['quotation_id'], $quote['request_id'], $quote['client_id'],
            $quote['project_scope'], $termsConditions, $quote['total_amount'], $startDate, $endDate, $_SESSION['user_id'],
        ]);

        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = "Contract {$contractNumber} generated as Draft.";
        header('Location: sow_contracts.php');
        exit;
    }

    if ($action === 'update_status') {
        $contractId = $_POST['contract_id'] ?? null;
        $newStatus  = $_POST['new_status'] ?? '';
        $validStatuses = ['Draft', 'Approved', 'Rejected'];

        if ($contractId && in_array($newStatus, $validStatuses, true)) {
            $checkStmt = $pdo->prepare("SELECT status FROM contracts WHERE contract_id = ?");
            $checkStmt->execute([$contractId]);
            $currentStatus = $checkStmt->fetchColumn();

            if ($currentStatus !== 'Draft') {
                $_SESSION['alert_type'] = 'error';
                $_SESSION['alert_message'] = 'Only Draft contracts can be approved or rejected.';
                header('Location: sow_contracts.php');
                exit;
            }

            if ($newStatus === 'Approved') {
                $stmt = $pdo->prepare(
                    "UPDATE contracts SET status = ?, approved_by = ?, approved_at = NOW() WHERE contract_id = ?"
                );
                $stmt->execute([$newStatus, $_SESSION['user_id'], $contractId]);
            } else {
                $stmt = $pdo->prepare('UPDATE contracts SET status = ? WHERE contract_id = ?');
                $stmt->execute([$newStatus, $contractId]);
            }

            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = "Contract marked as {$newStatus}.";
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Unable to update contract status.';
        }

        header('Location: sow_contracts.php');
        exit;
    }

    if ($action === 'add_revision') {
        $contractId = $_POST['contract_id'] ?? null;
        $revisionNote = trim($_POST['revision_note'] ?? '');

        if ($contractId && $revisionNote !== '') {
            $checkStmt = $pdo->prepare("SELECT status FROM contracts WHERE contract_id = ?");
            $checkStmt->execute([$contractId]);
            $currentStatus = $checkStmt->fetchColumn();

            if ($currentStatus !== 'Draft') {
                $_SESSION['alert_type'] = 'error';
                $_SESSION['alert_message'] = 'Revisions can only be added to Draft contracts.';
                header('Location: sow_contracts.php');
                exit;
            }

            $pdo->beginTransaction();

            $insertRevision = $pdo->prepare(
                'INSERT INTO contract_revisions (contract_id, revision_note, revised_by) VALUES (?, ?, ?)'
            );
            $insertRevision->execute([$contractId, $revisionNote, $_SESSION['user_id']]);

            $updateContract = $pdo->prepare("UPDATE contracts SET status = 'Draft' WHERE contract_id = ?");
            $updateContract->execute([$contractId]);

            $pdo->commit();

            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Revision recorded. Contract reverted to Draft.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Please provide a revision note.';
        }

        header('Location: sow_contracts.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$availableQuotationsStmt = $pdo->query(
    "SELECT q.quotation_id, q.quotation_number, q.total_amount, q.subtotal, q.tax_rate, q.tax_amount,
            q.project_scope, q.valid_until, q.notes,
            c.client_id, c.company_name, c.contact_person, c.email, c.contact_number, c.address, c.industry,
            sr.request_title, sr.request_details, sr.required_skill
     FROM quotations q
     INNER JOIN clients c ON q.client_id = c.client_id
     INNER JOIN service_requests sr ON q.request_id = sr.request_id
     LEFT JOIN contracts co ON co.quotation_id = q.quotation_id
     WHERE q.status = 'Approved' AND co.contract_id IS NULL
     ORDER BY q.created_at DESC"
);
$availableQuotations = $availableQuotationsStmt->fetchAll();

$availableQuotationItemsStmt = $pdo->query('SELECT * FROM quotation_items ORDER BY quotation_id ASC, sort_order ASC');
$itemsByAvailableQuotation = [];
foreach ($availableQuotationItemsStmt->fetchAll() as $row) {
    $itemsByAvailableQuotation[$row['quotation_id']][] = $row;
}

$searchTerm = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$perPage    = 8;
$page       = max(1, (int) ($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

$statsStmt = $pdo->query("SELECT status, total_amount FROM contracts");
$statsRows = $statsStmt->fetchAll();

$statusCounts = ['Draft' => 0, 'Approved' => 0, 'Rejected' => 0];
$totalApprovedValue = 0.0;
foreach ($statsRows as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']]++;
    }
    if ($row['status'] === 'Approved') {
        $totalApprovedValue += (float) $row['total_amount'];
    }
}
$totalContracts = count($statsRows);

$baseQuery = "FROM contracts ct
     INNER JOIN clients c ON ct.client_id = c.client_id
     INNER JOIN service_requests sr ON ct.request_id = sr.request_id
     INNER JOIN quotations q ON ct.quotation_id = q.quotation_id
     LEFT JOIN users pb ON ct.prepared_by = pb.user_id
     LEFT JOIN users ab ON ct.approved_by = ab.user_id
     WHERE 1=1";
$queryParams = [];

if ($searchTerm !== '') {
    $baseQuery .= " AND (ct.contract_number LIKE ? OR c.company_name LIKE ? OR sr.request_title LIKE ?)";
    $like = '%' . $searchTerm . '%';
    $queryParams[] = $like;
    $queryParams[] = $like;
    $queryParams[] = $like;
}

if ($dateFilter !== '') {
    $baseQuery .= " AND DATE(ct.created_at) = ?";
    $queryParams[] = $dateFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $baseQuery");
$countStmt->execute($queryParams);
$filteredContractCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($filteredContractCount / $perPage));

$listQuery = "SELECT ct.contract_id, ct.contract_number, ct.quotation_id, ct.status, ct.total_amount, ct.start_date, ct.end_date,
            ct.scope_summary, ct.terms_conditions, ct.created_at, ct.approved_at,
            c.company_name, c.contact_person, c.email, c.contact_number, c.address, c.industry,
            sr.request_title, sr.request_details, sr.required_skill,
            q.quotation_number, q.subtotal, q.tax_rate, q.tax_amount, q.valid_until AS quotation_valid_until, q.notes AS quotation_notes,
            pb.firstname AS prepared_firstname, pb.lastname AS prepared_lastname,
            ab.firstname AS approved_firstname, ab.lastname AS approved_lastname
     $baseQuery
     ORDER BY ct.created_at DESC
     LIMIT $perPage OFFSET $offset";
$contractsStmt = $pdo->prepare($listQuery);
$contractsStmt->execute($queryParams);
$contracts = $contractsStmt->fetchAll();

$revisionsStmt = $pdo->query(
    "SELECT cr.contract_id, cr.revision_note, cr.created_at, u.firstname, u.lastname
     FROM contract_revisions cr
     LEFT JOIN users u ON cr.revised_by = u.user_id
     ORDER BY cr.created_at DESC"
);
$revisionsByContract = [];
foreach ($revisionsStmt->fetchAll() as $row) {
    $revisionsByContract[$row['contract_id']][] = $row;
}

function contractStatusClass(string $status): string
{
    return match ($status) {
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        default => 'status-draft',
    };
}

function buildContractPageUrl(int $targetPage, string $searchTerm, string $dateFilter): string
{
    $params = ['page' => $targetPage];
    if ($searchTerm !== '') {
        $params['search'] = $searchTerm;
    }
    if ($dateFilter !== '') {
        $params['date'] = $dateFilter;
    }
    return '?' . http_build_query($params);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>SOW and Contract Automation</title>
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

.btn-ghost {
  background-color: var(--navy-soft);
  color: var(--navy);
  border: 1px solid var(--line);
  border-radius: 7px;
  font-weight: 600;
  font-size: .8rem;
}
.btn-ghost:hover { background-color: #E4E8F0; color: var(--navy); }

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
.btn-reset:disabled,
.btn-reset.disabled {
  background-color: var(--danger);
  border-color: var(--danger);
  color: #fff;
  opacity: 1;
  cursor: default;
  pointer-events: none;
}

.btn-approve {
  background-color: var(--success);
  color: #fff;
  border: 1px solid var(--success);
  border-radius: 7px;
  font-weight: 600;
  font-size: .78rem;
}
.btn-approve:hover { background-color: #0F5F49; border-color: #0F5F49; color: #fff; }

.btn-reject {
  background-color: var(--danger);
  color: #fff;
  border: 1px solid var(--danger);
  border-radius: 7px;
  font-weight: 600;
  font-size: .78rem;
}
.btn-reject:hover { background-color: #93382A; border-color: #93382A; color: #fff; }

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

.status-pill {
  font-size: .7rem;
  font-weight: 700;
  padding: .32rem .7rem;
  border-radius: 999px;
  white-space: nowrap;
  letter-spacing: .01em;
  border: 1px solid transparent;
  display: inline-block;
}
.status-draft { background-color: var(--navy-soft); color: var(--slate); border-color: var(--line); }
.status-approved { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }

.status-tabs {
  display: flex;
  gap: .4rem;
  flex-wrap: nowrap;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  border-bottom: 1px solid var(--line);
  padding: 0 1.15rem;
  background-color: var(--card);
  border-radius: 12px 12px 0 0;
}
.status-tabs::-webkit-scrollbar { display: none; }

.status-tab {
  border: none;
  background: none;
  padding: .8rem .3rem;
  font-size: .82rem;
  font-weight: 600;
  color: var(--ink-soft);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  display: flex;
  align-items: center;
  gap: .4rem;
  white-space: nowrap;
  flex: 0 0 auto;
}
.status-tab:hover { color: var(--navy-deep); }
.status-tab.active { color: var(--indigo-text); border-bottom-color: var(--indigo); }

.status-tab .count-badge {
  background-color: var(--navy-soft);
  color: var(--slate);
  font-size: .68rem;
  font-weight: 700;
  padding: .1rem .45rem;
  border-radius: 999px;
}
.status-tab.active .count-badge { background-color: var(--indigo-soft); color: var(--indigo-text); }

.quotation-pick-card {
  border: 1px solid var(--line);
  border-radius: 10px;
  cursor: pointer;
  transition: border-color .15s ease, background-color .15s ease;
}
.quotation-pick-card:hover { border-color: var(--indigo); }
.quotation-pick-card.active { border-color: var(--indigo); background-color: var(--indigo-soft); }

.quotation-preview-panel {
  border: 1px solid var(--line);
  border-radius: 10px;
  background-color: var(--navy-soft);
  padding: 1rem 1.1rem;
  height: 100%;
}
.quotation-preview-empty {
  color: var(--ink-soft);
  font-size: .85rem;
  text-align: center;
  padding: 2rem 1rem;
}
.quotation-preview-content { display: none; }
.quotation-preview-content.is-visible { display: block; }
.quotation-preview-content .view-section-label { margin-top: .75rem; }
.quotation-preview-content .view-section-label:first-child { margin-top: 0; }
.quotation-preview-items-table th, .quotation-preview-items-table td {
  font-size: .78rem;
  padding: .35rem .4rem;
}

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }

.revision-item {
  border-left: 2px solid var(--line);
  padding: .1rem 0 .1rem .9rem;
  margin-left: .3rem;
  position: relative;
}
.revision-item::before {
  content: '';
  position: absolute;
  left: -5px;
  top: .35rem;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--warn);
}

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.15rem;
}
.stat-card .stat-label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); }
.stat-card .stat-value { font-family: 'Lexend', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--navy-deep); margin-top: .15rem; word-break: break-word; }

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

.view-text-box {
  background-color: var(--navy-soft);
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .75rem .9rem;
  white-space: pre-line;
  word-break: break-word;
  color: var(--ink);
}

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

#generateContractModal .modal-content,
#viewContractModal .modal-content {
  border-radius: 0;
  background-color: #FFFEFC;
}

#generateContractModal .modal-header,
#viewContractModal .modal-header {
  padding: .9rem 1.25rem;
  background-color: #FFFEFC;
  border-bottom: 1px solid #E6E2DA;
}

#generateContractModal .modal-body,
#viewContractModal .modal-body {
  flex: 1 1 auto;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  background-color: #F6F4EF;
  padding: 1.25rem;
}

#generateContractModal .modal-footer {
  background-color: #FFFEFC;
  padding: .75rem 1.25rem;
  border-top: 1px solid #E6E2DA;
}

#generateContractForm {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
}

.quote-shell {
  max-width: 1400px;
  margin: 0 auto;
}

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
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: .95rem;
  font-weight: 600;
  color: #2B3134;
  margin-bottom: 1rem;
}

.quote-panel-title .step-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background-color: #E3EFEC;
  color: #245853;
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

@media (max-width: 991.98px) {
  .quote-sticky { position: static; }
}

#generateContractModal .quotation-pick-card {
  background-color: #FFFEFC;
  border-color: #E6E2DA;
}
#generateContractModal .quotation-pick-card:hover { border-color: #2F6F6A; }
#generateContractModal .quotation-pick-card.active {
  border-color: #2F6F6A;
  background-color: #E3EFEC;
}
#generateContractModal .quotation-preview-panel {
  background-color: #F6F4EF;
  border-color: #E6E2DA;
}
#viewContractModal .view-text-box {
  background-color: #F6F4EF;
  border-color: #E6E2DA;
}
#generateContractModal .view-section-label,
#viewContractModal .view-section-label {
  color: #6E7275;
}
#generateContractModal .form-label,
#viewContractModal .form-label,
#addRevisionModal .form-label {
  color: #55595C;
}
#generateContractModal .form-control:hover,
#generateContractModal .form-select:hover,
#viewContractModal .form-control:hover,
#viewContractModal .form-select:hover,
#addRevisionModal .form-control:hover {
  border-color: #CFCAC0;
}
#generateContractModal .form-control:focus,
#generateContractModal .form-select:focus,
#viewContractModal .form-control:focus,
#viewContractModal .form-select:focus,
#addRevisionModal .form-control:focus {
  border-color: #2F6F6A;
  box-shadow: 0 0 0 .2rem rgba(47, 111, 106, .14);
}
#generateContractModal .modal-title,
#viewContractModal .modal-title,
#addRevisionModal .modal-title {
  color: #2B3134;
}
#generateContractModal .table thead th,
#viewContractModal .table thead th {
  background-color: #F6F4EF !important;
  color: #6E7275;
  border-bottom-color: #E6E2DA !important;
}
#generateContractModal .table td,
#viewContractModal .table td {
  border-bottom-color: #E6E2DA;
}

#addRevisionModal .modal-content {
  background-color: #FFFEFC;
  border-radius: 14px;
}
#addRevisionModal .modal-header {
  background-color: #FFFEFC;
  border-bottom-color: #E6E2DA;
}
#addRevisionModal .modal-body {
  background-color: #F6F4EF;
}
#addRevisionModal .modal-footer {
  background-color: #FFFEFC;
  border-top-color: #E6E2DA;
}

#generateContractModal,
#viewContractModal,
#addRevisionModal {
  --indigo: #2F6F6A;
  --indigo-soft: #E3EFEC;
  --indigo-text: #245853;
}

.btn-print {
  display: inline-flex;
  align-items: center;
  gap: .55rem;
  background-color: transparent;
  color: #2B3134;
  border: none;
  border-radius: 8px;
  font-size: .95rem;
  font-weight: 700;
  padding: .45rem .9rem;
  transition: background-color .15s ease;
}
.btn-print:hover,
.btn-print:focus-visible {
  background-color: #EAE7E0;
  color: #2B3134;
}
.btn-print:active { background-color: #E0DCD3; }
.btn-print i { font-size: 1.05rem; }

.modal-header-col { flex: 1 1 0; min-width: 0; }
#view_contract_number { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.view-kv-value { font-size: .9rem; color: var(--ink); word-break: break-word; }

.mobile-row-label { display: none; }

@media (max-width: 1199.98px) {
  .stat-card { padding: .85rem .95rem; }
  .stat-card .stat-value { font-size: 1.3rem; }
  .quote-panel { padding: 1rem 1.05rem; }
}

@media (max-width: 991.98px) {
  .dashboard-title { font-size: 1rem !important; }
  .dashboard-subtitle { font-size: .78rem !important; }
  .stat-card { padding: .75rem .85rem; }
  .stat-card .stat-label { font-size: .66rem; }
  .stat-card .stat-value { font-size: 1.15rem; }
  .status-tab { font-size: .78rem; padding: .7rem .25rem; }
  .table td, .table th { font-size: .8rem; }
  .quote-panel-title { font-size: .88rem; margin-bottom: .8rem; }
  .quote-panel-title .step-icon { width: 24px; height: 24px; font-size: .68rem; }
  #generateContractModal .modal-body,
  #viewContractModal .modal-body { padding: .9rem; }
  .view-kv-value { font-size: .84rem; }
}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .65rem !important; }
  .dashboard-topbar { padding-left: .65rem !important; padding-right: .65rem !important; }
  .dashboard-title { font-size: .92rem !important; }

  .card { border-radius: 10px; }

  .form-control, .form-select, .input-group-text { font-size: .8rem; padding-top: .4rem; padding-bottom: .4rem; }
  .input-group-text i { font-size: .78rem; }
  .btn { font-size: .8rem; }
  .btn-ghost, .btn-approve, .btn-reject { font-size: .74rem; padding: .32rem .55rem; }

  .stat-card { padding: .6rem .7rem; border-radius: 10px; }
  .stat-card .stat-label { font-size: .6rem; letter-spacing: .03em; }
  .stat-card .stat-value { font-size: 1rem; }
  .stat-card .stat-value.stat-money { font-size: .82rem !important; }

  .status-tabs { padding: 0 .65rem; gap: .75rem; }
  .status-tab { font-size: .74rem; padding: .6rem .15rem; gap: .3rem; }
  .status-tab .count-badge { font-size: .6rem; padding: .05rem .38rem; }

  .table-responsive { overflow: visible; }
  #contractsTable thead { display: none; }
  #contractsTable, #contractsTable tbody, #contractsTable tr, #contractsTable td { display: block; width: 100%; }
  #contractsTable tbody tr[data-status] {
    border: 1px solid var(--line);
    border-radius: 10px;
    margin: .6rem .65rem;
    padding: .55rem .2rem .45rem;
    background-color: #fff;
  }
  #contractsTable.table-hover tbody tr:hover { background-color: #fff; }
  #contractsTable tbody tr[data-status] td {
    display: flex !important;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    border: none;
    padding: .28rem .7rem;
    font-size: .78rem;
    text-align: right;
  }
  #contractsTable tbody tr[data-status] td .mobile-row-label {
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
  #contractsTable tbody tr[data-status] td .cell-body { flex: 1 1 auto; min-width: 0; word-break: break-word; }
  #contractsTable tbody tr[data-status] td.cell-actions {
    justify-content: flex-end;
    border-top: 1px solid var(--line);
    margin-top: .35rem;
    padding-top: .5rem;
  }
  #contractsTable tbody tr[data-status] td.cell-actions .mobile-row-label { display: none; }

  .card-footer .pagination .page-link { font-size: .75rem; padding: .25rem .5rem; }

  .modal-header { padding: .7rem .8rem !important; }
  .modal-title { font-size: .95rem !important; }
  #generateContractModal .modal-body,
  #viewContractModal .modal-body { padding: .65rem; }
  #generateContractModal .modal-footer { padding: .6rem .8rem; }
  .quote-panel { padding: .8rem .85rem; border-radius: 10px; }
  .quote-panel-title { font-size: .84rem; margin-bottom: .7rem; }
  .quote-panel-title .step-icon { width: 22px; height: 22px; font-size: .62rem; }
  .view-section-label { font-size: .62rem; }
  .view-kv-value { font-size: .8rem; }
  .view-text-box { padding: .6rem .7rem; font-size: .8rem; }
  .form-label { font-size: .68rem; }
  .status-pill { font-size: .64rem; padding: .25rem .55rem; }
  .btn-print { font-size: .82rem; padding: .35rem .6rem; }
  .btn-print i { font-size: .9rem; }
  .quotation-preview-items-table th, .quotation-preview-items-table td { font-size: .72rem; padding: .28rem .3rem; }
  #view_items_body td, #viewContractModal .table thead th { font-size: .72rem; }
  .revision-item { padding-left: .7rem; }
  #quotationPickList { max-height: 240px !important; }
  #view_revisions_list { max-height: 180px !important; }
}

@media (max-width: 575.98px) {
  .dashboard-content { padding: .5rem !important; }
  .stat-card .stat-value { font-size: .95rem; }
  #contractsTable tbody tr[data-status] td { font-size: .74rem; }
  .modal-title { font-size: .88rem !important; }
}

#printArea { display: none; }

#printArea {
  color: #111;
  font-family: 'Inter', Arial, Helvetica, sans-serif;
  font-size: 10pt;
  line-height: 1.5;
}

#printArea .p-letterhead {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid #111;
}
#printArea .p-brand { display: flex; align-items: center; gap: 12px; }
#printArea .p-brand img { width: 60px; height: 60px; object-fit: contain; }
#printArea .p-company { font-size: 14pt; font-weight: 700; letter-spacing: .03em; line-height: 1.2; }
#printArea .p-tagline { font-size: 9pt; color: #555; font-style: italic; margin-top: 2px; }
#printArea .p-docmeta { text-align: right; font-size: 9pt; color: #333; line-height: 1.6; }
#printArea .p-docmeta strong { font-size: 11pt; color: #000; }

#printArea .p-title {
  text-align: center;
  font-size: 13pt;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  margin: 18px 0 4px;
}
#printArea .p-subtitle {
  text-align: center;
  font-size: 9.5pt;
  color: #444;
  margin-bottom: 14px;
}

#printArea .p-section { margin-top: 14px; }
#printArea .p-h {
  font-size: 9.5pt;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  padding-bottom: 3px;
  border-bottom: 1px solid #888;
  margin-bottom: 7px;
  break-after: avoid;
  page-break-after: avoid;
}

#printArea table { width: 100%; border-collapse: collapse; }
#printArea .p-kv td { padding: 3px 0; vertical-align: top; }
#printArea .p-kv td.k { width: 26%; color: #444; font-weight: 600; padding-right: 10px; }
#printArea .p-kv td.v { width: 24%; padding-right: 14px; }

#printArea .p-items th {
  background: #EDEDED;
  border: 1px solid #999;
  padding: 5px 7px;
  font-size: 9pt;
  text-align: left;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
#printArea .p-items td {
  border: 1px solid #bbb;
  padding: 5px 7px;
  font-size: 9.5pt;
  vertical-align: top;
}
#printArea .p-items .r { text-align: right; white-space: nowrap; }
#printArea .p-items .c { text-align: center; width: 6%; }
#printArea .p-items tr { break-inside: avoid; page-break-inside: avoid; }

#printArea .p-totals { width: 46%; margin-left: auto; margin-top: 8px; }
#printArea .p-totals td { padding: 3px 7px; font-size: 9.5pt; }
#printArea .p-totals td.r { text-align: right; }
#printArea .p-totals tr.grand td {
  border-top: 1.5px solid #111;
  font-weight: 700;
  font-size: 11pt;
  padding-top: 6px;
}

#printArea .p-text {
  white-space: pre-line;
  word-break: break-word;
  text-align: justify;
}

#printArea .p-rev { margin-bottom: 7px; break-inside: avoid; page-break-inside: avoid; }
#printArea .p-rev .meta { font-size: 8.5pt; color: #555; }

#printArea .p-sign {
  display: flex;
  gap: 40px;
  margin-top: 34px;
  break-inside: avoid;
  page-break-inside: avoid;
}
#printArea .p-sign > div { flex: 1; }
#printArea .p-sign .line { border-bottom: 1px solid #111; height: 46px; }
#printArea .p-sign .who { font-weight: 700; margin-top: 4px; }
#printArea .p-sign .role { font-size: 9pt; color: #444; }

#printArea .p-footer {
  margin-top: 26px;
  padding-top: 8px;
  border-top: 1px solid #aaa;
  text-align: center;
  font-size: 8.5pt;
  color: #555;
}

@media print {
  @page { size: A4; margin: 15mm 14mm; }

  html, body { background: #fff !important; }
  body > *:not(#printArea) { display: none !important; }
  #printArea { display: block !important; }
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">SOW and Contract Automation</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Generate, approve, and manage Statements of Work and contracts.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="card mb-3">
        <div class="card-body p-2 p-md-3">
          <form method="GET" class="row g-2 align-items-center" id="contractFilterForm">
            <div class="col-12 col-lg-6">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
                <input type="text" name="search" id="contractSearchInput" class="form-control" placeholder="Search contract #, company, or request" value="<?= htmlspecialchars($searchTerm) ?>" autocomplete="off">
                <span class="input-group-text bg-white"><span class="search-spinner" id="searchSpinner"></span></span>
              </div>
            </div>
            <div class="col-8 col-sm-9 col-lg-3">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-regular fa-calendar" style="color:var(--ink-soft);"></i></span>
                <input type="date" name="date" id="contractDateInput" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
              </div>
            </div>
            <div class="col-4 col-sm-3 col-lg-1">
              <a href="sow_contracts.php" class="btn btn-reset w-100" title="Reset filters">
                <i class="fa-solid fa-rotate-left"></i><span class="d-none d-sm-inline d-lg-none">Reset</span>
              </a>
            </div>
            <div class="col-12 col-lg-2">
              <button type="button" class="btn btn-teal-solid w-100" data-bs-toggle="modal" data-bs-target="#generateContractModal" <?= empty($availableQuotations) ? 'disabled' : '' ?>>
                <i class="fa-solid fa-plus me-1"></i> Generate
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-label">Total Contracts</div>
            <div class="stat-value"><?= (int) $totalContracts ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-label">Pending (Draft)</div>
            <div class="stat-value"><?= (int) $statusCounts['Draft'] ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-label">Approved</div>
            <div class="stat-value" style="color:var(--success-text);"><?= (int) $statusCounts['Approved'] ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-label">Approved Value</div>
            <div class="stat-value stat-money" style="font-size:1.15rem; color:var(--success-text);">&#8369;<?= number_format($totalApprovedValue, 2) ?></div>
          </div>
        </div>
      </div>

      <section class="card overflow-hidden">
        <nav class="status-tabs">
          <button type="button" class="status-tab active" data-filter="all">
            All <span class="count-badge"><?= (int) $totalContracts ?></span>
          </button>
          <button type="button" class="status-tab" data-filter="Draft">
            Draft <span class="count-badge"><?= (int) $statusCounts['Draft'] ?></span>
          </button>
          <button type="button" class="status-tab" data-filter="Approved">
            Approved <span class="count-badge"><?= (int) $statusCounts['Approved'] ?></span>
          </button>
          <button type="button" class="status-tab" data-filter="Rejected">
            Rejected <span class="count-badge"><?= (int) $statusCounts['Rejected'] ?></span>
          </button>
        </nav>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="contractsTable">
            <thead>
              <tr>
                <th scope="col">Contract #</th>
                <th scope="col">Client / Request</th>
                <th scope="col" class="d-none d-md-table-cell">Quotation</th>
                <th scope="col">Total</th>
                <th scope="col" class="d-none d-lg-table-cell">Duration</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="contractsTableBody">
              <?php if (empty($contracts)): ?>
                <tr>
                  <td colspan="7">
                    <div class="empty-state text-center py-5">
                      <i class="fa-regular fa-file-lines fs-3 mb-2 d-block"></i>
                      <p class="small mb-0">No contracts found.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($contracts as $ct): ?>
                  <tr data-status="<?= htmlspecialchars($ct['status']) ?>">
                    <td class="small fw-semibold">
                      <span class="mobile-row-label">Contract #</span>
                      <span class="cell-body fw-semibold"><?= htmlspecialchars($ct['contract_number']) ?></span>
                    </td>
                    <td class="small">
                      <span class="mobile-row-label">Client</span>
                      <span class="cell-body">
                        <span class="fw-semibold d-block"><?= htmlspecialchars($ct['company_name']) ?></span>
                        <span class="d-block" style="color:var(--ink-soft); font-size:.75rem;"><?= htmlspecialchars($ct['request_title']) ?></span>
                      </span>
                    </td>
                    <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);">
                      <span class="mobile-row-label">Quotation</span>
                      <span class="cell-body"><?= htmlspecialchars($ct['quotation_number']) ?></span>
                    </td>
                    <td class="small fw-semibold">
                      <span class="mobile-row-label">Total</span>
                      <span class="cell-body fw-semibold">&#8369;<?= number_format((float) $ct['total_amount'], 2) ?></span>
                    </td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);">
                      <span class="mobile-row-label">Duration</span>
                      <span class="cell-body">
                        <?= $ct['start_date'] ? htmlspecialchars(date('M d, Y', strtotime($ct['start_date']))) : '&mdash;' ?>
                        &ndash;
                        <?= $ct['end_date'] ? htmlspecialchars(date('M d, Y', strtotime($ct['end_date']))) : '&mdash;' ?>
                      </span>
                    </td>
                    <td>
                      <span class="mobile-row-label">Status</span>
                      <span class="cell-body"><span class="status-pill <?= contractStatusClass($ct['status']) ?>"><?= htmlspecialchars($ct['status']) ?></span></span>
                    </td>
                    <td class="text-end cell-actions">
                      <span class="mobile-row-label">Action</span>
                      <span class="cell-body d-flex justify-content-end gap-1 flex-wrap">
                        <button type="button" class="btn btn-ghost btn-sm view-contract-btn" title="View contract"
                          data-contract='<?= htmlspecialchars(json_encode([
                            "contract_id" => $ct["contract_id"],
                            "number" => $ct["contract_number"],
                            "status" => $ct["status"],
                            "created_at" => $ct["created_at"] ? date('M d, Y', strtotime($ct["created_at"])) : null,

                            "company" => $ct["company_name"],
                            "contact_person" => $ct["contact_person"],
                            "email" => $ct["email"],
                            "contact_number" => $ct["contact_number"],
                            "address" => $ct["address"],
                            "industry" => $ct["industry"],

                            "request" => $ct["request_title"],
                            "request_details" => $ct["request_details"],
                            "required_skill" => $ct["required_skill"],

                            "quotation_number" => $ct["quotation_number"],
                            "subtotal" => number_format((float) $ct["subtotal"], 2),
                            "tax_rate" => rtrim(rtrim(number_format((float) $ct["tax_rate"], 2), '0'), '.'),
                            "tax_amount" => number_format((float) $ct["tax_amount"], 2),
                            "total" => number_format((float) $ct["total_amount"], 2),
                            "quotation_valid_until" => $ct["quotation_valid_until"] ? date('M d, Y', strtotime($ct["quotation_valid_until"])) : null,
                            "quotation_notes" => $ct["quotation_notes"],
                            "items" => array_map(function ($it) {
                                return [
                                    "description" => $it["description"],
                                    "quantity" => rtrim(rtrim(number_format((float) $it["quantity"], 2), '0'), '.'),
                                    "unit_price" => number_format((float) $it["unit_price"], 2),
                                    "line_total" => number_format((float) $it["line_total"], 2),
                                ];
                            }, $itemsByAvailableQuotation[$ct["quotation_id"]] ?? []),

                            "start_date" => $ct["start_date"] ? date('M d, Y', strtotime($ct["start_date"])) : null,
                            "end_date" => $ct["end_date"] ? date('M d, Y', strtotime($ct["end_date"])) : null,
                            "scope_summary" => $ct["scope_summary"],
                            "terms_conditions" => $ct["terms_conditions"],

                            "prepared_by" => trim(($ct["prepared_firstname"] ?? '') . ' ' . ($ct["prepared_lastname"] ?? '')),
                            "approved_by" => $ct["approved_firstname"] ? trim($ct["approved_firstname"] . ' ' . $ct["approved_lastname"]) : null,
                            "approved_at" => $ct["approved_at"] ? date('M d, Y', strtotime($ct["approved_at"])) : null,

                            "revisions" => array_map(function ($r) {
                                return [
                                    "note" => $r["revision_note"],
                                    "by" => trim(($r["firstname"] ?? '') . ' ' . ($r["lastname"] ?? '')),
                                    "date" => $r["created_at"] ? date('M d, Y g:i A', strtotime($r["created_at"])) : '',
                                ];
                            }, $revisionsByContract[$ct["contract_id"]] ?? []),
                          ]), ENT_QUOTES) ?>'>
                          <i class="fa-solid fa-eye"></i>
                        </button>
                        <?php if ($ct['status'] === 'Draft'): ?>
                          <form method="POST" class="d-inline-block m-0">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="contract_id" value="<?= (int) $ct['contract_id'] ?>">
                            <button type="submit" name="new_status" value="Approved" class="btn btn-approve btn-sm">
                              <i class="fa-solid fa-check me-1"></i>Approve
                            </button>
                          </form>
                          <form method="POST" class="d-inline-block m-0">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="contract_id" value="<?= (int) $ct['contract_id'] ?>">
                            <button type="submit" name="new_status" value="Rejected" class="btn btn-reject btn-sm">
                              <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>
                          </form>
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
            <span class="small" style="color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredContractCount ?> total</span>
            <nav aria-label="Contracts pagination">
              <ul class="pagination pagination-sm mb-0 flex-wrap">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildContractPageUrl($page - 1, $searchTerm, $dateFilter) ?>">Prev</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= buildContractPageUrl($i, $searchTerm, $dateFilter) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildContractPageUrl($page + 1, $searchTerm, $dateFilter) ?>">Next</a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<div class="modal fade" id="generateContractModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <form method="POST" id="generateContractForm">
        <input type="hidden" name="action" value="generate_contract">
        <input type="hidden" name="quotation_id" id="generate_quotation_id">

        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold mb-0">Generate Contract</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="quote-shell">
            <div class="row g-2 g-md-3">

              <div class="col-lg-5">
                <div class="d-flex flex-column gap-2 gap-md-3">

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-file-invoice"></i></span>
                      Approved Quotation
                    </div>
                    <div class="d-flex flex-column gap-2" id="quotationPickList" style="max-height:340px; overflow-y:auto;">
                      <?php foreach ($availableQuotations as $q): ?>
                        <?php
                          $quotationItems = array_map(function ($it) {
                              return [
                                  'description' => $it['description'],
                                  'quantity' => rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.'),
                                  'unit_price' => number_format((float) $it['unit_price'], 2),
                                  'line_total' => number_format((float) $it['line_total'], 2),
                              ];
                          }, $itemsByAvailableQuotation[$q['quotation_id']] ?? []);
                        ?>
                        <div class="quotation-pick-card p-2 p-md-3" data-quotation-id="<?= $q['quotation_id'] ?>"
                          data-preview='<?= htmlspecialchars(json_encode([
                            'quotation_number' => $q['quotation_number'],
                            'company' => $q['company_name'],
                            'contact_person' => $q['contact_person'],
                            'email' => $q['email'],
                            'contact_number' => $q['contact_number'],
                            'address' => $q['address'],
                            'industry' => $q['industry'],
                            'request_title' => $q['request_title'],
                            'request_details' => $q['request_details'],
                            'required_skill' => $q['required_skill'],
                            'project_scope' => $q['project_scope'],
                            'subtotal' => number_format((float) $q['subtotal'], 2),
                            'tax_rate' => $q['tax_rate'],
                            'tax_amount' => number_format((float) $q['tax_amount'], 2),
                            'total' => number_format((float) $q['total_amount'], 2),
                            'valid_until' => $q['valid_until'] ? date('M d, Y', strtotime($q['valid_until'])) : null,
                            'notes' => $q['notes'],
                            'items' => $quotationItems,
                          ]), ENT_QUOTES) ?>'>
                          <div class="d-flex justify-content-between align-items-start gap-2">
                            <div style="min-width:0;">
                              <div class="fw-semibold small"><?= htmlspecialchars($q['quotation_number']) ?></div>
                              <div class="small" style="color:var(--ink-soft); word-break:break-word;"><?= htmlspecialchars($q['company_name']) ?> &mdash; <?= htmlspecialchars($q['request_title']) ?></div>
                            </div>
                            <div class="fw-semibold small text-nowrap" style="color:var(--indigo-text);">&#8369;<?= number_format((float) $q['total_amount'], 2) ?></div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-calendar-days"></i></span>
                      Contract Duration
                    </div>
                    <div class="row g-2 g-md-3">
                      <div class="col-6">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date">
                      </div>
                      <div class="col-6">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date">
                      </div>
                    </div>
                  </div>

                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-file-contract"></i></span>
                      Terms and Conditions
                    </div>
                    <textarea class="form-control" name="terms_conditions" rows="5" placeholder="Payment terms, confidentiality, termination clause, and other standard agreement terms"></textarea>
                    <div class="form-text small">The project scope is pulled automatically from the selected quotation.</div>
                  </div>

                </div>
              </div>

              <div class="col-lg-7">
                <div class="quote-sticky">
                  <div class="quote-panel">
                    <div class="quote-panel-title">
                      <span class="step-icon"><i class="fa-solid fa-circle-info"></i></span>
                      Full Details
                    </div>
                    <div class="quotation-preview-panel">
                      <div class="quotation-preview-empty" id="quotationPreviewEmpty">
                        Select a quotation on the left to review the full client, request, and quotation details before generating the contract.
                      </div>
                      <div class="quotation-preview-content" id="quotationPreviewContent">

                        <div class="view-section-label">Client</div>
                        <div class="fw-semibold small" id="preview_company"></div>
                        <div class="small" id="preview_contact_person"></div>
                        <div class="small" id="preview_email" style="word-break:break-word;"></div>
                        <div class="small" id="preview_contact_number"></div>
                        <div class="small" id="preview_address" style="word-break:break-word;"></div>
                        <div class="small" id="preview_industry"></div>

                        <div class="view-section-label">Service Request</div>
                        <div class="fw-semibold small" id="preview_request_title"></div>
                        <div class="small mb-1" id="preview_required_skill"></div>
                        <div class="small" id="preview_request_details" style="white-space:pre-line;"></div>

                        <div class="view-section-label">Project Scope</div>
                        <div class="small" id="preview_project_scope" style="white-space:pre-line;"></div>

                        <div class="view-section-label">Quotation Items</div>
                        <div class="table-responsive">
                          <table class="table table-sm quotation-preview-items-table mb-1">
                            <thead>
                              <tr>
                                <th>Description</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                              </tr>
                            </thead>
                            <tbody id="preview_items_body"></tbody>
                          </table>
                        </div>
                        <div class="small text-end" id="preview_subtotal"></div>
                        <div class="small text-end" id="preview_tax"></div>
                        <div class="fw-bold small text-end" id="preview_total" style="color:var(--indigo-text);"></div>

                        <div class="view-section-label">Valid Until</div>
                        <div class="small" id="preview_valid_until"></div>

                        <div class="view-section-label">Quotation Notes</div>
                        <div class="small" id="preview_notes" style="white-space:pre-line;"></div>

                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid px-4 w-100 w-sm-auto" id="generateContractSubmitBtn" disabled>Generate as Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewContractModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold mb-0 modal-header-col" id="view_contract_number">Contract</h2>
        <div class="modal-header-col text-center">
          <button type="button" class="btn btn-print" id="printContractBtn">
            <i class="fa-solid fa-print"></i> <span class="d-none d-sm-inline">Print</span>
          </button>
        </div>
        <div class="modal-header-col d-flex justify-content-end">
          <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
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
                      <div class="fw-semibold" id="view_contract_company"></div>
                      <div class="small" id="view_contract_request" style="color:var(--ink-soft);"></div>
                    </div>
                    <div class="col-6 col-sm-3">
                      <div class="view-section-label">Quotation</div>
                      <div class="fw-semibold small" id="view_contract_quotation"></div>
                    </div>
                    <div class="col-6 col-sm-3">
                      <div class="view-section-label">Status</div>
                      <span class="status-pill" id="view_contract_status"></span>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-building"></i></span>
                    Client Information
                  </div>
                  <div class="row g-2 g-md-3">
                    <div class="col-12 col-sm-6 col-xl-4">
                      <div class="view-section-label">Contact Person</div>
                      <div class="view-kv-value" id="view_client_contact"></div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-4">
                      <div class="view-section-label">Email</div>
                      <div class="view-kv-value" id="view_client_email"></div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-4">
                      <div class="view-section-label">Contact Number</div>
                      <div class="view-kv-value" id="view_client_number"></div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-8">
                      <div class="view-section-label">Address</div>
                      <div class="view-kv-value" id="view_client_address"></div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-4">
                      <div class="view-section-label">Industry</div>
                      <div class="view-kv-value" id="view_client_industry"></div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-clipboard-list"></i></span>
                    Service Request
                  </div>
                  <div class="row g-2 g-md-3">
                    <div class="col-12 col-sm-6">
                      <div class="view-section-label">Request Title</div>
                      <div class="view-kv-value fw-semibold" id="view_request_title"></div>
                    </div>
                    <div class="col-12 col-sm-6">
                      <div class="view-section-label">Required Skill</div>
                      <div class="view-kv-value" id="view_request_skill"></div>
                    </div>
                    <div class="col-12">
                      <div class="view-section-label">Details</div>
                      <div class="view-text-box" id="view_request_details"></div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-bullseye"></i></span>
                    Project Scope
                  </div>
                  <div class="view-text-box" id="view_contract_scope"></div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-list-check"></i></span>
                    Quotation Items
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

                <div class="quote-panel">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-file-contract"></i></span>
                    Terms and Conditions
                  </div>
                  <div class="view-text-box" id="view_contract_terms"></div>
                </div>

                <div class="quote-panel" id="view_revisions_wrap">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    Revision History
                  </div>
                  <div id="view_revisions_list" class="d-flex flex-column gap-2" style="max-height:220px; overflow-y:auto;"></div>
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
                  <div class="row g-2 g-md-3">
                    <div class="col-12">
                      <div class="d-flex justify-content-between small"><span style="color:var(--ink-soft);">Subtotal</span><span id="view_contract_subtotal"></span></div>
                      <div class="d-flex justify-content-between small mt-1"><span style="color:var(--ink-soft);">Tax</span><span id="view_contract_tax"></span></div>
                      <div class="d-flex justify-content-between fw-bold mt-2 pt-2" style="border-top:1px solid #E6E2DA; color:var(--indigo-text); font-size:1.05rem;">
                        <span>Total</span><span id="view_contract_total"></span>
                      </div>
                    </div>
                    <div class="col-6 col-lg-12">
                      <div class="view-section-label">Contract Duration</div>
                      <div class="small fw-semibold" id="view_contract_duration"></div>
                    </div>
                    <div class="col-6 col-lg-12">
                      <div class="view-section-label">Quotation Valid Until</div>
                      <div class="small" id="view_contract_valid_until"></div>
                    </div>
                    <div class="col-6 col-lg-12">
                      <div class="view-section-label">Prepared By</div>
                      <div class="small" id="view_contract_prepared"></div>
                    </div>
                    <div class="col-6 col-lg-12" id="view_contract_approved_wrap">
                      <div class="view-section-label">Approved By</div>
                      <div class="small" id="view_contract_approved"></div>
                    </div>
                  </div>
                </div>

                <div class="quote-panel">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-note-sticky"></i></span>
                    Quotation Notes
                  </div>
                  <div class="view-text-box" id="view_quotation_notes"></div>
                </div>

                <div class="quote-panel" id="view_status_actions">
                  <div class="quote-panel-title">
                    <span class="step-icon"><i class="fa-solid fa-gavel"></i></span>
                    Actions
                  </div>
                  <form method="POST" id="contractStatusForm" class="d-flex flex-column gap-2">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="contract_id" id="status_contract_id">
                    <button type="submit" name="new_status" value="Approved" class="btn btn-approve w-100">
                      <i class="fa-solid fa-check me-1"></i> Approve Contract
                    </button>
                    <button type="submit" name="new_status" value="Rejected" class="btn btn-reject w-100">
                      <i class="fa-solid fa-xmark me-1"></i> Reject Contract
                    </button>
                  </form>
                  <button type="button" class="btn btn-ghost w-100 mt-2" data-bs-toggle="modal" data-bs-target="#addRevisionModal">
                    <i class="fa-solid fa-pen me-1"></i> Add Revision
                  </button>
                </div>

              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="addRevisionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_revision">
        <input type="hidden" name="contract_id" id="revision_contract_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Add Revision</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Revision Note</label>
          <textarea class="form-control" name="revision_note" rows="3" placeholder="Describe what changed in this revision" required></textarea>
          <div class="form-text small">This will revert the contract status back to Draft.</div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid w-100 w-sm-auto">Save Revision</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="printArea" aria-hidden="true"></div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
const quotationPreviewEmpty = document.getElementById('quotationPreviewEmpty');
const quotationPreviewContent = document.getElementById('quotationPreviewContent');

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = (str === null || str === undefined) ? '' : String(str);
  return div.innerHTML;
}

function hasText(v) {
  return v !== null && v !== undefined && String(v).trim() !== '';
}

const filterForm = document.getElementById('contractFilterForm');
const searchInput = document.getElementById('contractSearchInput');
const dateInput = document.getElementById('contractDateInput');
const searchSpinner = document.getElementById('searchSpinner');

function submitFilters() {
  const params = new URLSearchParams();
  const term = searchInput.value.trim();
  const dateVal = dateInput.value;
  if (term !== '') params.set('search', term);
  if (dateVal !== '') params.set('date', dateVal);
  params.set('focus', '1');
  const query = params.toString();
  window.location.href = 'sow_contracts.php' + (query ? '?' + query : '');
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

document.querySelectorAll('.quotation-pick-card').forEach(function (cardEl) {
  cardEl.addEventListener('click', function () {
    document.querySelectorAll('.quotation-pick-card').forEach(function (c) { c.classList.remove('active'); });
    cardEl.classList.add('active');
    document.getElementById('generate_quotation_id').value = cardEl.dataset.quotationId;
    document.getElementById('generateContractSubmitBtn').disabled = false;

    const data = JSON.parse(cardEl.dataset.preview);

    document.getElementById('preview_company').textContent = data.company;
    document.getElementById('preview_contact_person').textContent = data.contact_person;
    document.getElementById('preview_email').textContent = data.email;
    document.getElementById('preview_contact_number').textContent = data.contact_number;
    document.getElementById('preview_address').textContent = data.address;
    document.getElementById('preview_industry').textContent = data.industry || 'Industry not specified';

    document.getElementById('preview_request_title').textContent = data.request_title;
    document.getElementById('preview_required_skill').textContent = data.required_skill ? ('Required skill: ' + data.required_skill) : 'Required skill: not specified';
    document.getElementById('preview_request_details').textContent = data.request_details || 'No additional details were provided for this request.';

    document.getElementById('preview_project_scope').textContent = data.project_scope || 'No project scope provided.';

    const itemsBody = document.getElementById('preview_items_body');
    itemsBody.innerHTML = '';
    data.items.forEach(function (item) {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + escapeHtml(item.description) + '</td>' +
        '<td class="text-end">' + escapeHtml(item.quantity) + '</td>' +
        '<td class="text-end">\u20B1' + escapeHtml(item.unit_price) + '</td>' +
        '<td class="text-end">\u20B1' + escapeHtml(item.line_total) + '</td>';
      itemsBody.appendChild(tr);
    });

    document.getElementById('preview_subtotal').textContent = 'Subtotal: \u20B1' + data.subtotal;
    document.getElementById('preview_tax').textContent = 'Tax (' + data.tax_rate + '%): \u20B1' + data.tax_amount;
    document.getElementById('preview_total').textContent = 'Total: \u20B1' + data.total;

    document.getElementById('preview_valid_until').textContent = data.valid_until || 'No expiry set';
    document.getElementById('preview_notes').textContent = (data.notes && data.notes.trim() !== '') ? data.notes : 'No notes provided.';

    quotationPreviewEmpty.style.display = 'none';
    quotationPreviewContent.classList.add('is-visible');

    if (window.innerWidth < 992) {
      document.getElementById('quotationPreviewContent').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  });
});

document.getElementById('generateContractModal').addEventListener('hidden.bs.modal', function () {
  document.querySelectorAll('.quotation-pick-card').forEach(function (c) { c.classList.remove('active'); });
  document.getElementById('generate_quotation_id').value = '';
  document.getElementById('generateContractSubmitBtn').disabled = true;
  quotationPreviewEmpty.style.display = '';
  quotationPreviewContent.classList.remove('is-visible');
});

const contractStatusClassMap = {
  Draft: 'status-draft',
  Approved: 'status-approved',
  Rejected: 'status-rejected',
};

let currentContract = null;

document.querySelectorAll('.view-contract-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const data = JSON.parse(this.dataset.contract);
    currentContract = data;

    document.getElementById('view_contract_number').textContent = data.number;
    document.getElementById('view_contract_company').textContent = data.company;
    document.getElementById('view_contract_request').textContent = data.request;
    document.getElementById('view_contract_quotation').textContent = data.quotation_number;

    const statusPill = document.getElementById('view_contract_status');
    statusPill.textContent = data.status;
    statusPill.className = 'status-pill ' + (contractStatusClassMap[data.status] || 'status-draft');

    document.getElementById('view_client_contact').textContent = data.contact_person || '\u2014';
    document.getElementById('view_client_email').textContent = data.email || '\u2014';
    document.getElementById('view_client_number').textContent = data.contact_number || '\u2014';
    document.getElementById('view_client_address').textContent = data.address || '\u2014';
    document.getElementById('view_client_industry').textContent = data.industry || '\u2014';

    document.getElementById('view_request_title').textContent = data.request;
    document.getElementById('view_request_skill').textContent = data.required_skill || 'Not specified';
    document.getElementById('view_request_details').textContent = hasText(data.request_details) ? data.request_details : 'No additional details were provided for this request.';

    document.getElementById('view_contract_scope').textContent = hasText(data.scope_summary) ? data.scope_summary : 'No project scope provided.';
    document.getElementById('view_contract_terms').textContent = hasText(data.terms_conditions) ? data.terms_conditions : 'No terms and conditions provided.';

    const itemsBody = document.getElementById('view_items_body');
    itemsBody.innerHTML = '';
    if (data.items.length === 0) {
      itemsBody.innerHTML = '<tr><td colspan="4" class="small text-center" style="color:var(--ink-soft);">No items recorded.</td></tr>';
    } else {
      data.items.forEach(function (item) {
        const tr = document.createElement('tr');
        tr.innerHTML =
          '<td class="small">' + escapeHtml(item.description) + '</td>' +
          '<td class="small text-end">' + escapeHtml(item.quantity) + '</td>' +
          '<td class="small text-end">\u20B1' + escapeHtml(item.unit_price) + '</td>' +
          '<td class="small text-end fw-semibold">\u20B1' + escapeHtml(item.line_total) + '</td>';
        itemsBody.appendChild(tr);
      });
    }

    document.getElementById('view_contract_subtotal').textContent = '\u20B1' + data.subtotal;
    document.getElementById('view_contract_tax').textContent = '\u20B1' + data.tax_amount + ' (' + data.tax_rate + '%)';
    document.getElementById('view_contract_total').textContent = '\u20B1' + data.total;
    document.getElementById('view_contract_duration').textContent =
      (data.start_date || '\u2014') + ' \u2013 ' + (data.end_date || '\u2014');
    document.getElementById('view_contract_valid_until').textContent = data.quotation_valid_until || 'No expiry set';
    document.getElementById('view_contract_prepared').textContent = data.prepared_by || '\u2014';
    document.getElementById('view_quotation_notes').textContent = hasText(data.quotation_notes) ? data.quotation_notes : 'No notes provided.';

    const approvedWrap = document.getElementById('view_contract_approved_wrap');
    if (data.approved_by) {
      approvedWrap.classList.remove('d-none');
      document.getElementById('view_contract_approved').textContent = data.approved_by + (data.approved_at ? ' \u00B7 ' + data.approved_at : '');
    } else {
      approvedWrap.classList.add('d-none');
    }

    document.getElementById('status_contract_id').value = data.contract_id;
    document.getElementById('revision_contract_id').value = data.contract_id;

    const actionsPanel = document.getElementById('view_status_actions');
    actionsPanel.style.display = (data.status === 'Draft') ? '' : 'none';

    document.getElementById('printContractBtn').style.display = (data.status === 'Draft') ? 'none' : '';

    const revisionsList = document.getElementById('view_revisions_list');
    revisionsList.innerHTML = '';
    if (data.revisions.length === 0) {
      revisionsList.innerHTML = '<p class="small mb-0" style="color:var(--ink-soft);">No revisions recorded.</p>';
    } else {
      data.revisions.forEach(function (rev) {
        const div = document.createElement('div');
        div.className = 'revision-item';
        div.innerHTML =
          '<div class="small" style="white-space:pre-line;">' + escapeHtml(rev.note) + '</div>' +
          '<div class="small" style="color:var(--ink-soft); font-size:.7rem;">' + escapeHtml(rev.by || 'Unknown') + ' &middot; ' + escapeHtml(rev.date) + '</div>';
        revisionsList.appendChild(div);
      });
    }

    const modal = new bootstrap.Modal(document.getElementById('viewContractModal'));
    modal.show();
  });
});

function formatTerms(text) {
  if (!hasText(text)) return 'No terms and conditions provided.';
  let t = String(text).trim();
  if (t.indexOf('\n') === -1) {
    t = t.replace(/\s+(?=\d{1,2}\.\s+[A-Z][A-Z\s&,\/]{3,}\s)/g, '\n\n');
    t = t.replace(/\s+-\s+(?=[A-Z0-9])/g, '\n- ');
  }
  return t;
}

function buildPrintHtml(d) {
  const e = escapeHtml;
  const val = function (x) { return hasText(x) ? e(x) : '&mdash;'; };
  const printedOn = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });

  let itemRows = '';
  d.items.forEach(function (it, i) {
    itemRows +=
      '<tr>' +
        '<td class="c">' + (i + 1) + '</td>' +
        '<td>' + e(it.description) + '</td>' +
        '<td class="r">' + e(it.quantity) + '</td>' +
        '<td class="r">&#8369;' + e(it.unit_price) + '</td>' +
        '<td class="r">&#8369;' + e(it.line_total) + '</td>' +
      '</tr>';
  });
  if (itemRows === '') {
    itemRows = '<tr><td colspan="5" class="c" style="width:auto;">No items recorded.</td></tr>';
  }

  let revisionHtml = '';
  if (d.revisions.length === 0) {
    revisionHtml = '<div>No revisions recorded.</div>';
  } else {
    const total = d.revisions.length;
    d.revisions.forEach(function (r, i) {
      revisionHtml +=
        '<div class="p-rev">' +
          '<div><strong>Revision ' + (total - i) + '</strong></div>' +
          '<div style="white-space:pre-line;">' + e(r.note) + '</div>' +
          '<div class="meta">' + e(r.by || 'Unknown') + (r.date ? ' &middot; ' + e(r.date) : '') + '</div>' +
        '</div>';
    });
  }

  return '' +
    '<div class="p-letterhead">' +
      '<div class="p-brand">' +
        '<img src="../assets/img/system_img/logo.png" alt="KMP Integrated Enterprise, Inc.">' +
        '<div>' +
          '<div class="p-company">KMP INTEGRATED ENTERPRISE, INC.</div>' +
          '<div class="p-tagline">Shaping Smarter Solutions.</div>' +
        '</div>' +
      '</div>' +
      '<div class="p-docmeta">' +
        '<div><strong>' + e(d.number) + '</strong></div>' +
        '<div>Status: ' + e(d.status) + '</div>' +
        '<div>Date Created: ' + val(d.created_at) + '</div>' +
        '<div>Date Printed: ' + e(printedOn) + '</div>' +
      '</div>' +
    '</div>' +

    '<div class="p-title">Statement of Work and Contract</div>' +
    '<div class="p-subtitle">' + e(d.request) + '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">1. Contract Overview</div>' +
      '<table class="p-kv">' +
        '<tr><td class="k">Contract No.</td><td class="v">' + val(d.number) + '</td>' +
            '<td class="k">Quotation No.</td><td class="v">' + val(d.quotation_number) + '</td></tr>' +
        '<tr><td class="k">Start Date</td><td class="v">' + val(d.start_date) + '</td>' +
            '<td class="k">End Date</td><td class="v">' + val(d.end_date) + '</td></tr>' +
        '<tr><td class="k">Contract Value</td><td class="v"><strong>&#8369;' + e(d.total) + '</strong></td>' +
            '<td class="k">Quotation Valid Until</td><td class="v">' + val(d.quotation_valid_until) + '</td></tr>' +
      '</table>' +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">2. Client Information</div>' +
      '<table class="p-kv">' +
        '<tr><td class="k">Company</td><td class="v"><strong>' + val(d.company) + '</strong></td>' +
            '<td class="k">Industry</td><td class="v">' + val(d.industry) + '</td></tr>' +
        '<tr><td class="k">Contact Person</td><td class="v">' + val(d.contact_person) + '</td>' +
            '<td class="k">Contact Number</td><td class="v">' + val(d.contact_number) + '</td></tr>' +
        '<tr><td class="k">Email</td><td class="v">' + val(d.email) + '</td>' +
            '<td class="k">Address</td><td class="v">' + val(d.address) + '</td></tr>' +
      '</table>' +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">3. Service Request</div>' +
      '<table class="p-kv">' +
        '<tr><td class="k">Request Title</td><td class="v" colspan="3">' + val(d.request) + '</td></tr>' +
        '<tr><td class="k">Required Skill</td><td class="v" colspan="3">' + val(d.required_skill) + '</td></tr>' +
        '<tr><td class="k">Details</td><td class="v" colspan="3" style="white-space:pre-line;">' +
          (hasText(d.request_details) ? e(d.request_details) : 'No additional details were provided for this request.') +
        '</td></tr>' +
      '</table>' +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">4. Project Scope</div>' +
      '<div class="p-text">' + (hasText(d.scope_summary) ? e(d.scope_summary) : 'No project scope provided.') + '</div>' +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">5. Scope Items and Fees</div>' +
      '<table class="p-items">' +
        '<thead><tr>' +
          '<th class="c">#</th><th>Description</th>' +
          '<th style="text-align:right;">Qty</th><th style="text-align:right;">Unit Price</th><th style="text-align:right;">Amount</th>' +
        '</tr></thead>' +
        '<tbody>' + itemRows + '</tbody>' +
      '</table>' +
      '<table class="p-totals">' +
        '<tr><td>Subtotal</td><td class="r">&#8369;' + e(d.subtotal) + '</td></tr>' +
        '<tr><td>Tax (' + e(d.tax_rate) + '%)</td><td class="r">&#8369;' + e(d.tax_amount) + '</td></tr>' +
        '<tr class="grand"><td>Total</td><td class="r">&#8369;' + e(d.total) + '</td></tr>' +
      '</table>' +
    '</div>' +

    (hasText(d.quotation_notes)
      ? '<div class="p-section"><div class="p-h">Quotation Notes</div><div class="p-text">' + e(d.quotation_notes) + '</div></div>'
      : '') +

    '<div class="p-section">' +
      '<div class="p-h">6. Terms and Conditions</div>' +
      '<div class="p-text">' + e(formatTerms(d.terms_conditions)) + '</div>' +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">7. Revision History</div>' +
      revisionHtml +
    '</div>' +

    '<div class="p-section">' +
      '<div class="p-h">8. Authorization</div>' +
      '<table class="p-kv">' +
        '<tr><td class="k">Prepared By</td><td class="v">' + val(d.prepared_by) + '</td>' +
            '<td class="k">Approved By</td><td class="v">' + (d.approved_by ? e(d.approved_by) + (d.approved_at ? ' (' + e(d.approved_at) + ')' : '') : '&mdash;') + '</td></tr>' +
      '</table>' +
    '</div>' +

    '<div class="p-sign">' +
      '<div>' +
        '<div class="line"></div>' +
        '<div class="who">' + (d.approved_by ? e(d.approved_by) : '&nbsp;') + '</div>' +
        '<div class="role">Authorized Representative<br>KMP Integrated Enterprise, Inc.</div>' +
        '<div class="role">Date: ____________________</div>' +
      '</div>' +
      '<div>' +
        '<div class="line"></div>' +
        '<div class="who">' + (d.contact_person ? e(d.contact_person) : '&nbsp;') + '</div>' +
        '<div class="role">Authorized Representative<br>' + e(d.company) + '</div>' +
        '<div class="role">Date: ____________________</div>' +
      '</div>' +
    '</div>' +

    '<div class="p-footer">' +
      'KMP Integrated Enterprise, Inc. &middot; Shaping Smarter Solutions.<br>' +
      'This document was generated by the KMP ConsultHub system. Contract No. ' + e(d.number) +
    '</div>';
}

document.getElementById('printContractBtn').addEventListener('click', function () {
  if (!currentContract || currentContract.status === 'Draft') return;

  const printArea = document.getElementById('printArea');
  printArea.innerHTML = buildPrintHtml(currentContract);

  const previousTitle = document.title;
  document.title = currentContract.number;

  const images = Array.from(printArea.querySelectorAll('img'));
  const waitForImages = Promise.all(images.map(function (img) {
    return img.complete ? Promise.resolve() : new Promise(function (resolve) {
      img.onload = img.onerror = resolve;
    });
  }));

  waitForImages.then(function () {
    window.print();
    document.title = previousTitle;
  });
});

document.querySelectorAll('.status-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.status-tab').forEach(function (t) { t.classList.remove('active'); });
    tab.classList.add('active');
    const filter = tab.dataset.filter;
    document.querySelectorAll('#contractsTableBody tr[data-status]').forEach(function (row) {
      row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
    });
  });
});

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>