<?php

session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['manager_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

function generateQuotationNumber(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotations WHERE quotation_number LIKE ?");
    $stmt->execute(["QT-{$year}-%"]);
    $count = (int) $stmt->fetchColumn() + 1;
    return sprintf('QT-%s-%04d', $year, $count);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'create_quotation') {
        $requestId   = $_POST['request_id'] ?? null;
        $projectScope = trim($_POST['project_scope'] ?? '');
        $taxRate     = (float) ($_POST['tax_rate'] ?? 0);
        $validUntil  = $_POST['valid_until'] ?: null;
        $notes       = trim($_POST['notes'] ?? '');
        $descriptions = $_POST['item_description'] ?? [];
        $quantities   = $_POST['item_quantity'] ?? [];
        $unitPrices   = $_POST['item_unit_price'] ?? [];

        if (!$requestId || empty($descriptions)) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Please select a service request and add at least one scope item.';
            header('Location: cpq_quotations.php');
            exit;
        }

        $reqStmt = $pdo->prepare("SELECT client_id FROM service_requests WHERE request_id = ?");
        $reqStmt->execute([$requestId]);
        $req = $reqStmt->fetch();

        if (!$req) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Selected service request was not found.';
            header('Location: cpq_quotations.php');
            exit;
        }

        $subtotal = 0.0;
        $items = [];
        foreach ($descriptions as $index => $description) {
            $description = trim($description);
            $quantity = (float) ($quantities[$index] ?? 0);
            $unitPrice = (float) ($unitPrices[$index] ?? 0);
            if ($description === '' || $quantity <= 0) {
                continue;
            }
            $lineTotal = round($quantity * $unitPrice, 2);
            $subtotal += $lineTotal;
            $items[] = [$description, $quantity, $unitPrice, $lineTotal, $index];
        }

        if (empty($items)) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Please add at least one valid scope item.';
            header('Location: cpq_quotations.php');
            exit;
        }

        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $totalAmount = round($subtotal + $taxAmount, 2);
        $quotationNumber = generateQuotationNumber($pdo);

        $pdo->beginTransaction();

        $insertQuotation = $pdo->prepare(
            "INSERT INTO quotations
                (quotation_number, request_id, client_id, project_scope, status, subtotal, tax_rate, tax_amount, total_amount, valid_until, notes, prepared_by)
             VALUES (?, ?, ?, ?, 'Draft', ?, ?, ?, ?, ?, ?, ?)"
        );
        $insertQuotation->execute([
            $quotationNumber, $requestId, $req['client_id'], $projectScope,
            $subtotal, $taxRate, $taxAmount, $totalAmount, $validUntil, $notes, $_SESSION['manager_id'],
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

        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = "Quotation {$quotationNumber} created successfully.";
        header('Location: cpq_quotations.php');
        exit;
    }

    if ($action === 'update_status') {
        $quotationId = $_POST['quotation_id'] ?? null;
        $newStatus   = $_POST['new_status'] ?? '';
        $validStatuses = ['Draft', 'Approved', 'Rejected'];

        if ($quotationId && in_array($newStatus, $validStatuses, true)) {
    $stmt = $pdo->prepare("UPDATE quotations SET status = ? WHERE quotation_id = ?");
            $stmt->execute([$newStatus, $quotationId]);

            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = "Quotation marked as {$newStatus}.";
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Unable to update quotation status.';
        }

        header('Location: cpq_quotations.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$requestsStmt = $pdo->query(
    "SELECT sr.request_id, sr.request_title, sr.request_details, sr.required_skill, sr.status,
            c.client_id, c.company_name
     FROM service_requests sr
     INNER JOIN clients c ON sr.client_id = c.client_id
     WHERE sr.status = 'New'
     ORDER BY sr.created_at DESC"
);
$serviceRequests = $requestsStmt->fetchAll();

$searchTerm  = trim($_GET['search'] ?? '');
$dateFilter  = trim($_GET['date'] ?? '');
$perPage     = 8;
$page        = max(1, (int) ($_GET['page'] ?? 1));
$offset      = ($page - 1) * $perPage;

$statsStmt = $pdo->query("SELECT status, total_amount FROM quotations");
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
$totalQuotations = count($statsRows);

$baseQuery = "FROM quotations q
     INNER JOIN clients c ON q.client_id = c.client_id
     INNER JOIN service_requests sr ON q.request_id = sr.request_id
     LEFT JOIN users u ON q.prepared_by = u.user_id
     WHERE 1=1";
$queryParams = [];

if ($searchTerm !== '') {
    $baseQuery .= " AND (q.quotation_number LIKE ? OR c.company_name LIKE ? OR sr.request_title LIKE ?)";
    $like = '%' . $searchTerm . '%';
    $queryParams[] = $like;
    $queryParams[] = $like;
    $queryParams[] = $like;
}

if ($dateFilter !== '') {
    $baseQuery .= " AND DATE(q.created_at) = ?";
    $queryParams[] = $dateFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) $baseQuery");
$countStmt->execute($queryParams);
$filteredQuotationCount = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($filteredQuotationCount / $perPage));

$listQuery = "SELECT q.quotation_id, q.quotation_number, q.status, q.total_amount, q.valid_until, q.created_at,
            c.company_name, sr.request_title,
            u.firstname AS prepared_by_firstname, u.lastname AS prepared_by_lastname
     $baseQuery
     ORDER BY q.created_at DESC
     LIMIT $perPage OFFSET $offset";
$quotationsStmt = $pdo->prepare($listQuery);
$quotationsStmt->execute($queryParams);
$quotations = $quotationsStmt->fetchAll();

$itemsStmt = $pdo->query('SELECT * FROM quotation_items ORDER BY quotation_id ASC, sort_order ASC');
$itemsByQuotation = [];
foreach ($itemsStmt->fetchAll() as $row) {
    $itemsByQuotation[$row['quotation_id']][] = $row;
}

function buildQuotationPageUrl(int $targetPage, string $searchTerm, string $dateFilter): string
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CPQ and Scope Builder</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  /* Enterprise slate / indigo palette */
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
}

.status-draft { background-color: var(--navy-soft); color: var(--slate); border-color: var(--line); }
.status-approved { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }

/* Status filter tabs */
.status-tabs {
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--line);
  padding: 0 1.15rem;
  background-color: var(--card);
  border-radius: 12px 12px 0 0;
}

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

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }
.btn-close-remove { color: var(--danger-text); background: none; border: none; font-size: .9rem; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.15rem;
}
.stat-card .stat-label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); }
.stat-card .stat-value { font-family: 'Lexend', sans-serif; font-size: 1.5rem; font-weight: 700; color: var(--navy-deep); margin-top: .15rem; }

.pagination .page-link { color: var(--indigo-text); border-color: var(--line); }
.pagination .page-item.active .page-link { background-color: var(--indigo); border-color: var(--indigo); color: #fff; }
.pagination .page-item.disabled .page-link { color: #adb5bd; }
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
          <form method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search quotation #, company, or request" value="<?= htmlspecialchars($searchTerm) ?>">
              </div>
            </div>
            <div class="col-6 col-md-3">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-regular fa-calendar" style="color:var(--ink-soft);"></i></span>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
              </div>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
              <button type="submit" class="btn btn-primary-solid flex-grow-1">
                <i class="fa-solid fa-filter me-1"></i> Filter
              </button>
              <?php if ($searchTerm !== '' || $dateFilter !== ''): ?>
                <a href="cpq_quotations.php" class="btn btn-ghost"><i class="fa-solid fa-xmark"></i></a>
              <?php endif; ?>
            </div>
            <div class="col-12 col-md-2">
              <button type="button" class="btn btn-teal-solid w-100" data-bs-toggle="modal" data-bs-target="#newQuotationModal">
                <i class="fa-solid fa-plus me-1"></i> New Quotation
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="stat-card">
            <div class="stat-label">Total Quotations</div>
            <div class="stat-value"><?= (int) $totalQuotations ?></div>
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
            <div class="stat-value" style="font-size:1.15rem; color:var(--success-text);">&#8369;<?= number_format($totalApprovedValue, 2) ?></div>
          </div>
        </div>
      </div>

      <section class="card overflow-hidden">
        <nav class="status-tabs">
          <button type="button" class="status-tab active" data-filter="all">
            All <span class="count-badge"><?= (int) $totalQuotations ?></span>
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
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Quotation #</th>
                <th scope="col">Client / Request</th>
                <th scope="col" class="d-none d-md-table-cell">Prepared By</th>
                <th scope="col">Total</th>
                <th scope="col" class="d-none d-lg-table-cell">Valid Until</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="quotationsTableBody">
              <?php if (empty($quotations)): ?>
                <tr>
                  <td colspan="7">
                    <div class="empty-state text-center py-5">
                      <i class="fa-regular fa-file-lines fs-3 mb-2 d-block"></i>
                      <p class="small mb-0">No quotations yet. Create one from a service request.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($quotations as $q): ?>
                  <?php
                    $statusClass = 'status-draft';
                    if ($q['status'] === 'Approved') { $statusClass = 'status-approved'; }
                    if ($q['status'] === 'Rejected') { $statusClass = 'status-rejected'; }
                  ?>
                  <tr data-status="<?= htmlspecialchars($q['status']) ?>">
                    <td class="small fw-semibold"><?= htmlspecialchars($q['quotation_number']) ?></td>
                    <td class="small">
                      <div class="fw-semibold"><?= htmlspecialchars($q['company_name']) ?></div>
                      <div style="color:var(--ink-soft); font-size:.75rem;"><?= htmlspecialchars($q['request_title']) ?></div>
                    </td>
                    <td class="small d-none d-md-table-cell"><?= htmlspecialchars(trim(($q['prepared_by_firstname'] ?? '-') . ' ' . ($q['prepared_by_lastname'] ?? ''))) ?></td>
                    <td class="small fw-semibold">&#8369;<?= number_format((float) $q['total_amount'], 2) ?></td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= $q['valid_until'] ? htmlspecialchars(date('M d, Y', strtotime($q['valid_until']))) : '&mdash;' ?></td>
                    <td><span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($q['status']) ?></span></td>
                    <td class="text-end">
                      <div class="d-flex justify-content-end gap-1 flex-wrap">
                        <button type="button" class="btn btn-ghost btn-sm view-quotation-btn" title="View quotation"
                          data-quotation='<?= htmlspecialchars(json_encode([
                            "number" => $q["quotation_number"],
                            "status" => $q["status"],
                            "company" => $q["company_name"],
                            "request" => $q["request_title"],
                            "total" => number_format((float) $q["total_amount"], 2),
                            "valid_until" => $q["valid_until"] ? date('M d, Y', strtotime($q["valid_until"])) : null,
                            "items" => array_map(function ($it) {
                                return [
                                    "description" => $it["description"],
                                    "quantity" => rtrim(rtrim(number_format((float) $it["quantity"], 2), '0'), '.'),
                                    "unit_price" => number_format((float) $it["unit_price"], 2),
                                    "line_total" => number_format((float) $it["line_total"], 2),
                                ];
                            }, $itemsByQuotation[$q["quotation_id"]] ?? []),
                            "quotation_id" => $q["quotation_id"],
                          ]), ENT_QUOTES) ?>'>
                          <i class="fa-solid fa-eye"></i>
                        </button>
                        <?php if ($q['status'] === 'Draft'): ?>
                          <form method="POST" class="d-inline-block m-0">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="quotation_id" value="<?= (int) $q['quotation_id'] ?>">
                            <button type="submit" name="new_status" value="Approved" class="btn btn-approve btn-sm">
                              <i class="fa-solid fa-check me-1"></i>Approve
                            </button>
                          </form>
                          <form method="POST" class="d-inline-block m-0">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="quotation_id" value="<?= (int) $q['quotation_id'] ?>">
                            <button type="submit" name="new_status" value="Rejected" class="btn btn-reject btn-sm">
                              <i class="fa-solid fa-xmark me-1"></i>Reject
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
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
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildQuotationPageUrl($page - 1, $searchTerm, $dateFilter) ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= buildQuotationPageUrl($i, $searchTerm, $dateFilter) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildQuotationPageUrl($page + 1, $searchTerm, $dateFilter) ?>">Next</a>
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
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" id="quotationForm">
        <input type="hidden" name="action" value="create_quotation">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">New Quotation</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">Service Request</label>
            <select class="form-select" name="request_id" required>
              <option value="" selected disabled>Select a service request</option>
              <?php foreach ($serviceRequests as $req): ?>
                <option value="<?= $req['request_id'] ?>">
                  <?= htmlspecialchars($req['company_name']) ?> &mdash; <?= htmlspecialchars($req['request_title']) ?> (<?= htmlspecialchars($req['status']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Project Scope</label>
            <textarea class="form-control" name="project_scope" rows="2" placeholder="Describe the overall project scope and deliverables"></textarea>
          </div>

          <div class="mb-2 d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Scope Items</label>
            <button type="button" class="btn btn-ghost btn-sm" id="addItemBtn"><i class="fa-solid fa-plus me-1"></i> Add Item</button>
          </div>

          <div id="itemsContainer" class="d-flex flex-column gap-2 mb-3"></div>

          <div class="row g-3 mb-3">
            <div class="col-sm-4">
              <label class="form-label">Tax Rate (%)</label>
              <input type="number" class="form-control" name="tax_rate" id="taxRateInput" step="0.01" min="0" value="0">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Valid Until</label>
              <input type="date" class="form-control" name="valid_until">
            </div>
          </div>

          <div class="totals-box mb-3">
            <div class="row-line"><span>Subtotal</span><span id="subtotalDisplay">&#8369;0.00</span></div>
            <div class="row-line"><span>Tax</span><span id="taxDisplay">&#8369;0.00</span></div>
            <div class="row-line grand"><span>Total</span><span id="totalDisplay">&#8369;0.00</span></div>
          </div>

          <div class="mb-1">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes for this quotation"></textarea>
          </div>

        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Save as Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewQuotationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold" id="view_quotation_number">Quotation</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <div class="fw-semibold small" id="view_client_name"></div>
          <div class="small" id="view_request_title" style="color:var(--ink-soft);"></div>
        </div>
        <div class="table-responsive mb-3">
          <table class="table table-sm mb-0">
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
        <div class="d-flex justify-content-end">
          <div class="text-end small">
            <div style="color:var(--ink-soft);">Grand Total</div>
            <div class="fw-bold" style="font-size:1.1rem; color:var(--teal-text);" id="view_total"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer flex-wrap gap-2" id="view_status_actions">
        <form method="POST" id="statusForm" class="d-flex gap-2 flex-wrap">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="quotation_id" id="status_quotation_id">
          <button type="submit" name="new_status" value="Approved" class="btn btn-approve btn-sm px-3">Approve</button>
          <button type="submit" name="new_status" value="Rejected" class="btn btn-reject btn-sm px-3">Reject</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
let itemIndex = 0;
const itemsContainer = document.getElementById('itemsContainer');
const addItemBtn = document.getElementById('addItemBtn');
const taxRateInput = document.getElementById('taxRateInput');

function addItemRow() {
  const row = document.createElement('div');
  row.className = 'item-row';
  const idx = itemIndex++;
  row.innerHTML =
    '<div class="row g-2 align-items-end">' +
      '<div class="col-12 col-sm-5">' +
        '<label class="form-label mb-1" style="font-size:.72rem;">Description</label>' +
        '<input type="text" class="form-control form-control-sm" name="item_description[]" placeholder="e.g. Business process audit" required>' +
      '</div>' +
      '<div class="col-4 col-sm-2">' +
        '<label class="form-label mb-1" style="font-size:.72rem;">Qty</label>' +
        '<input type="number" class="form-control form-control-sm item-qty" name="item_quantity[]" min="0.01" step="0.01" value="1" required>' +
      '</div>' +
      '<div class="col-4 col-sm-2">' +
        '<label class="form-label mb-1" style="font-size:.72rem;">Unit Price</label>' +
        '<input type="number" class="form-control form-control-sm item-price" name="item_unit_price[]" min="0" step="0.01" value="0" required>' +
      '</div>' +
      '<div class="col-3 col-sm-2 text-end">' +
        '<div class="line-total-display item-line-total">&#8369;0.00</div>' +
      '</div>' +
      '<div class="col-1 text-end">' +
        '<button type="button" class="btn-close-remove remove-item-btn"><i class="fa-solid fa-xmark"></i></button>' +
      '</div>' +
    '</div>';

  itemsContainer.appendChild(row);

  const qtyInput = row.querySelector('.item-qty');
  const priceInput = row.querySelector('.item-price');
  qtyInput.addEventListener('input', recalculateTotals);
  priceInput.addEventListener('input', recalculateTotals);
  row.querySelector('.remove-item-btn').addEventListener('click', function () {
    row.remove();
    recalculateTotals();
  });

  recalculateTotals();
}

function recalculateTotals() {
  let subtotal = 0;
  itemsContainer.querySelectorAll('.item-row').forEach(function (row) {
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const lineTotal = qty * price;
    row.querySelector('.item-line-total').textContent = '\u20B1' + lineTotal.toFixed(2);
    subtotal += lineTotal;
  });

  const taxRate = parseFloat(taxRateInput.value) || 0;
  const taxAmount = subtotal * (taxRate / 100);
  const total = subtotal + taxAmount;

  document.getElementById('subtotalDisplay').textContent = '\u20B1' + subtotal.toFixed(2);
  document.getElementById('taxDisplay').textContent = '\u20B1' + taxAmount.toFixed(2);
  document.getElementById('totalDisplay').textContent = '\u20B1' + total.toFixed(2);
}

addItemBtn.addEventListener('click', addItemRow);
taxRateInput.addEventListener('input', recalculateTotals);

document.getElementById('newQuotationModal').addEventListener('show.bs.modal', function () {
  itemsContainer.innerHTML = '';
  itemIndex = 0;
  addItemRow();
});

document.querySelectorAll('.view-quotation-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const data = JSON.parse(this.dataset.quotation);

    document.getElementById('view_quotation_number').textContent = data.number;
    document.getElementById('view_client_name').textContent = data.company;
    document.getElementById('view_request_title').textContent = data.request;
    document.getElementById('view_total').textContent = '\u20B1' + data.total;
    document.getElementById('status_quotation_id').value = data.quotation_id;

    const statusActions = document.getElementById('view_status_actions');
    statusActions.style.display = (data.status === 'Draft') ? '' : 'none';

    const body = document.getElementById('view_items_body');
    body.innerHTML = '';
    data.items.forEach(function (item) {
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="small">' + escapeHtml(item.description) + '</td>' +
        '<td class="small text-end">' + escapeHtml(item.quantity) + '</td>' +
        '<td class="small text-end">\u20B1' + escapeHtml(item.unit_price) + '</td>' +
        '<td class="small text-end fw-semibold">\u20B1' + escapeHtml(item.line_total) + '</td>';
      body.appendChild(tr);
    });

    const modal = new bootstrap.Modal(document.getElementById('viewQuotationModal'));
    modal.show();
  });
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

document.querySelectorAll('.status-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('.status-tab').forEach(function (t) { t.classList.remove('active'); });
    tab.classList.add('active');
    const filter = tab.dataset.filter;
    document.querySelectorAll('#quotationsTableBody tr[data-status]').forEach(function (row) {
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