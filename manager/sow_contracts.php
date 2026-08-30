<?php

session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['manager_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: login.php');
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
            $quote['project_scope'], $termsConditions, $quote['total_amount'], $startDate, $endDate, $_SESSION['manager_id'],
        ]);

        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = "Contract {$contractNumber} generated as Draft.";
        header('Location: sow_contracts.php');
        exit;
    }

    if ($action === 'update_status') {
        $contractId = $_POST['contract_id'] ?? null;
        $newStatus  = $_POST['new_status'] ?? '';
        $validStatuses = ['Draft', 'Pending Approval', 'Approved', 'Rejected'];

        if ($contractId && in_array($newStatus, $validStatuses, true)) {
            if ($newStatus === 'Approved') {
                $stmt = $pdo->prepare(
                    "UPDATE contracts SET status = ?, approved_by = ?, approved_at = NOW() WHERE contract_id = ?"
                );
                $stmt->execute([$newStatus, $_SESSION['manager_id'], $contractId]);
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
            $pdo->beginTransaction();

            $insertRevision = $pdo->prepare(
                'INSERT INTO contract_revisions (contract_id, revision_note, revised_by) VALUES (?, ?, ?)'
            );
            $insertRevision->execute([$contractId, $revisionNote, $_SESSION['manager_id']]);

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
    "SELECT q.quotation_id, q.quotation_number, q.total_amount, q.project_scope,
            c.company_name, sr.request_title
     FROM quotations q
     INNER JOIN clients c ON q.client_id = c.client_id
     INNER JOIN service_requests sr ON q.request_id = sr.request_id
     LEFT JOIN contracts co ON co.quotation_id = q.quotation_id
     WHERE q.status = 'Approved' AND co.contract_id IS NULL
     ORDER BY q.created_at DESC"
);
$availableQuotations = $availableQuotationsStmt->fetchAll();

$contractsStmt = $pdo->query(
    "SELECT ct.contract_id, ct.contract_number, ct.status, ct.total_amount, ct.start_date, ct.end_date,
            ct.scope_summary, ct.terms_conditions, ct.created_at,
            c.company_name, sr.request_title, q.quotation_number,
            pb.firstname AS prepared_firstname, pb.lastname AS prepared_lastname,
            ab.firstname AS approved_firstname, ab.lastname AS approved_lastname
     FROM contracts ct
     INNER JOIN clients c ON ct.client_id = c.client_id
     INNER JOIN service_requests sr ON ct.request_id = sr.request_id
     INNER JOIN quotations q ON ct.quotation_id = q.quotation_id
     LEFT JOIN users pb ON ct.prepared_by = pb.user_id
     LEFT JOIN users ab ON ct.approved_by = ab.user_id
     ORDER BY ct.created_at DESC"
);
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
        'Draft' => 'status-draft',
        'Pending Approval' => 'status-sent',
        'Approved' => 'status-approved',
        'Rejected' => 'status-rejected',
        default => 'status-draft',
    };
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SOW and Contract Automation</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --navy: #33495C;
  --navy-soft: #EEF2F5;
  --teal: #4CA79A;
  --teal-soft: #E7F5F2;
  --teal-text: #2E6E63;
  --amber: #E0A44E;
  --amber-soft: #FBF1E1;
  --amber-text: #93662A;
  --coral: #DB7A66;
  --coral-soft: #FBECE8;
  --coral-text: #A2452F;
  --ink: #2B3540;
  --ink-soft: #6B7684;
  --line: #E7EAEE;
  --canvas: #F6F8F9;
  --card: #FFFFFF;
}

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-title, h1, h2, h3 {
  font-family: 'Lexend', 'Inter', sans-serif;
}

.dashboard-title { color: var(--ink); letter-spacing: -0.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; }

.card { border-radius: 14px; border: 1px solid var(--line); }

.card-header {
  border-bottom: 1px solid var(--line) !important;
  background-color: var(--card) !important;
  border-radius: 14px 14px 0 0 !important;
  padding: 1rem 1.15rem;
}

.card-header h2 { color: var(--ink); letter-spacing: -0.01em; }
.card-header p { color: var(--ink-soft) !important; }

.form-control:focus, .form-select:focus, .form-control:hover, .form-select:hover {
  border-color: var(--teal);
  box-shadow: 0 0 0 .2rem rgba(76,167,154,.15);
  outline: none;
}

.form-label { font-size: .82rem; font-weight: 600; color: var(--ink); }

.btn-primary-solid {
  background-color: var(--navy);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-weight: 600;
}
.btn-primary-solid:hover { background-color: #263A4A; color: #fff; }

.btn-teal-solid {
  background-color: var(--teal);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-weight: 600;
}
.btn-teal-solid:hover { background-color: var(--teal-text); color: #fff; }

.btn-ghost {
  background-color: var(--navy-soft);
  color: var(--navy);
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: .82rem;
}
.btn-ghost:hover { background-color: #E0E7EB; color: var(--navy); }

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 600;
  font-size: .72rem;
  letter-spacing: .04em;
  background-color: var(--canvas) !important;
}

.table td { border-bottom: 1px solid var(--line); color: var(--ink); vertical-align: middle; }
.table-hover tbody tr:hover { background-color: var(--navy-soft); }

.status-pill {
  font-size: .72rem;
  font-weight: 600;
  padding: .3rem .65rem;
  border-radius: 999px;
  white-space: nowrap;
}
.status-draft { background-color: var(--navy-soft); color: var(--navy); }
.status-sent { background-color: var(--amber-soft); color: var(--amber-text); }
.status-approved { background-color: var(--teal-soft); color: var(--teal-text); }
.status-rejected { background-color: var(--coral-soft); color: var(--coral-text); }

.quotation-pick-card {
  border: 1px solid var(--line);
  border-radius: 12px;
  cursor: pointer;
  transition: border-color .15s ease, background-color .15s ease;
}
.quotation-pick-card:hover { border-color: var(--teal); }
.quotation-pick-card.active { border-color: var(--teal); background-color: var(--teal-soft); }

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
  background-color: var(--amber);
}

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">SOW and Contract Automation</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Generate, approve, and manage Statements of Work and contracts.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <button type="button" class="btn btn-link text-secondary p-0">
          <i class="fa-regular fa-bell fs-5"></i>
        </button>
        <div class="dropdown">
          <button type="button" class="btn btn-link p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="d-flex align-items-center justify-content-center rounded-circle flex-shrink-0" style="width:36px; height:36px; background-color:var(--navy-soft);">
              <i class="fa-solid fa-user" style="color:var(--navy);"></i>
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
              <a href="../config/logout.php?role=manager" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="h6 fw-bold mb-0">Contracts</h2>
          <p class="small mb-0" style="color:var(--ink-soft);">Generated from approved quotations.</p>
        </div>
        <button type="button" class="btn btn-teal-solid px-3" data-bs-toggle="modal" data-bs-target="#generateContractModal" <?= empty($availableQuotations) ? 'disabled' : '' ?>>
          <i class="fa-solid fa-plus me-1"></i> Generate Contract
        </button>
      </div>

      <?php if (empty($availableQuotations)): ?>
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body small py-3" style="color:var(--ink-soft);">
            <i class="fa-regular fa-circle-question me-1"></i> No approved quotations are waiting for a contract. Approve a quotation in CPQ and Scope Builder first.
          </div>
        </div>
      <?php endif; ?>

      <section class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
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
            <tbody>
              <?php if (empty($contracts)): ?>
                <tr>
                  <td colspan="7">
                    <div class="empty-state text-center py-5">
                      <i class="fa-regular fa-file-signature fs-3 mb-2 d-block"></i>
                      <p class="small mb-0">No contracts yet.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($contracts as $ct): ?>
                  <tr>
                    <td class="small fw-semibold"><?= htmlspecialchars($ct['contract_number']) ?></td>
                    <td class="small">
                      <div class="fw-semibold"><?= htmlspecialchars($ct['company_name']) ?></div>
                      <div style="color:var(--ink-soft); font-size:.75rem;"><?= htmlspecialchars($ct['request_title']) ?></div>
                    </td>
                    <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($ct['quotation_number']) ?></td>
                    <td class="small fw-semibold">&#8369;<?= number_format((float) $ct['total_amount'], 2) ?></td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);">
                      <?= $ct['start_date'] ? htmlspecialchars(date('M d, Y', strtotime($ct['start_date']))) : '&mdash;' ?>
                      &ndash;
                      <?= $ct['end_date'] ? htmlspecialchars(date('M d, Y', strtotime($ct['end_date']))) : '&mdash;' ?>
                    </td>
                    <td><span class="status-pill <?= contractStatusClass($ct['status']) ?>"><?= htmlspecialchars($ct['status']) ?></span></td>
                    <td class="text-end">
                      <button type="button" class="btn btn-ghost btn-sm view-contract-btn"
                        data-contract='<?= htmlspecialchars(json_encode([
                          "contract_id" => $ct["contract_id"],
                          "number" => $ct["contract_number"],
                          "status" => $ct["status"],
                          "company" => $ct["company_name"],
                          "request" => $ct["request_title"],
                          "quotation_number" => $ct["quotation_number"],
                          "total" => number_format((float) $ct["total_amount"], 2),
                          "start_date" => $ct["start_date"] ? date('M d, Y', strtotime($ct["start_date"])) : null,
                          "end_date" => $ct["end_date"] ? date('M d, Y', strtotime($ct["end_date"])) : null,
                          "scope_summary" => $ct["scope_summary"],
                          "terms_conditions" => $ct["terms_conditions"],
                          "prepared_by" => trim(($ct["prepared_firstname"] ?? '') . ' ' . ($ct["prepared_lastname"] ?? '')),
                          "approved_by" => $ct["approved_firstname"] ? trim($ct["approved_firstname"] . ' ' . $ct["approved_lastname"]) : null,
                          "revisions" => array_map(function ($r) {
                              return [
                                  "note" => $r["revision_note"],
                                  "by" => trim(($r["firstname"] ?? '') . ' ' . ($r["lastname"] ?? '')),
                                  "date" => $r["created_at"],
                              ];
                          }, $revisionsByContract[$ct["contract_id"]] ?? []),
                        ]), ENT_QUOTES) ?>'>
                        View
                      </button>
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

<div class="modal fade" id="generateContractModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" id="generateContractForm">
        <input type="hidden" name="action" value="generate_contract">
        <input type="hidden" name="quotation_id" id="generate_quotation_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Generate Contract</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

          <div class="mb-3">
            <label class="form-label">Approved Quotation</label>
            <div class="d-flex flex-column gap-2" id="quotationPickList">
              <?php foreach ($availableQuotations as $q): ?>
                <div class="quotation-pick-card p-3" data-quotation-id="<?= $q['quotation_id'] ?>">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <div class="fw-semibold small"><?= htmlspecialchars($q['quotation_number']) ?></div>
                      <div class="small" style="color:var(--ink-soft);"><?= htmlspecialchars($q['company_name']) ?> &mdash; <?= htmlspecialchars($q['request_title']) ?></div>
                    </div>
                    <div class="fw-semibold small" style="color:var(--teal-text);">&#8369;<?= number_format((float) $q['total_amount'], 2) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label">Start Date</label>
              <input type="date" class="form-control" name="start_date">
            </div>
            <div class="col-sm-6">
              <label class="form-label">End Date</label>
              <input type="date" class="form-control" name="end_date">
            </div>
          </div>

          <div class="mb-1">
            <label class="form-label">Terms and Conditions</label>
            <textarea class="form-control" name="terms_conditions" rows="4" placeholder="Payment terms, confidentiality, termination clause, and other standard agreement terms"></textarea>
            <div class="form-text">The project scope is pulled automatically from the selected quotation.</div>
          </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal-solid" id="generateContractSubmitBtn" disabled>Generate as Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewContractModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold" id="view_contract_number">Contract</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="mb-3">
          <div class="fw-semibold small" id="view_contract_company"></div>
          <div class="small" id="view_contract_request" style="color:var(--ink-soft);"></div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-4">
            <div class="small" style="color:var(--ink-soft);">Quotation</div>
            <div class="fw-semibold small" id="view_contract_quotation"></div>
          </div>
          <div class="col-sm-4">
            <div class="small" style="color:var(--ink-soft);">Total</div>
            <div class="fw-semibold small" id="view_contract_total"></div>
          </div>
          <div class="col-sm-4">
            <div class="small" style="color:var(--ink-soft);">Duration</div>
            <div class="fw-semibold small" id="view_contract_duration"></div>
          </div>
        </div>

        <div class="mb-3">
          <div class="small fw-semibold mb-1">Project Scope</div>
          <p class="small mb-0" id="view_contract_scope" style="color:var(--ink-soft);"></p>
        </div>

        <div class="mb-3">
          <div class="small fw-semibold mb-1">Terms and Conditions</div>
          <p class="small mb-0" id="view_contract_terms" style="color:var(--ink-soft);"></p>
        </div>

        <div class="mb-3">
          <div class="small" style="color:var(--ink-soft);">Prepared by <span id="view_contract_prepared" class="fw-semibold" style="color:var(--ink);"></span></div>
          <div class="small" id="view_contract_approved_wrap" style="color:var(--ink-soft);">Approved by <span id="view_contract_approved" class="fw-semibold" style="color:var(--ink);"></span></div>
        </div>

        <div id="view_revisions_wrap" class="mb-1">
          <div class="small fw-semibold mb-2">Revision History</div>
          <div id="view_revisions_list" class="d-flex flex-column gap-2" style="max-height:150px; overflow-y:auto;"></div>
        </div>

      </div>
      <div class="modal-footer flex-wrap gap-2">
        <form method="POST" id="contractStatusForm" class="d-flex gap-2 flex-wrap">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="contract_id" id="status_contract_id">
          <button type="submit" name="new_status" value="Pending Approval" class="btn btn-ghost btn-sm">Send for Approval</button>
          <button type="submit" name="new_status" value="Approved" class="btn btn-teal-solid btn-sm">Approve</button>
          <button type="submit" name="new_status" value="Rejected" class="btn btn-sm" style="background-color:var(--coral-soft); color:var(--coral-text); border:none;">Reject</button>
        </form>
        <button type="button" class="btn btn-sm" style="background-color:var(--amber-soft); color:var(--amber-text); border:none;" data-bs-toggle="modal" data-bs-target="#addRevisionModal">
          <i class="fa-solid fa-pen me-1"></i> Add Revision
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addRevisionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
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
          <div class="form-text">This will revert the contract status back to Draft.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-teal-solid">Save Revision</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.quotation-pick-card').forEach(function (cardEl) {
  cardEl.addEventListener('click', function () {
    document.querySelectorAll('.quotation-pick-card').forEach(function (c) { c.classList.remove('active'); });
    cardEl.classList.add('active');
    document.getElementById('generate_quotation_id').value = cardEl.dataset.quotationId;
    document.getElementById('generateContractSubmitBtn').disabled = false;
  });
});

document.getElementById('generateContractModal').addEventListener('hidden.bs.modal', function () {
  document.querySelectorAll('.quotation-pick-card').forEach(function (c) { c.classList.remove('active'); });
  document.getElementById('generate_quotation_id').value = '';
  document.getElementById('generateContractSubmitBtn').disabled = true;
});

document.querySelectorAll('.view-contract-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const data = JSON.parse(this.dataset.contract);

    document.getElementById('view_contract_number').textContent = data.number;
    document.getElementById('view_contract_company').textContent = data.company;
    document.getElementById('view_contract_request').textContent = data.request;
    document.getElementById('view_contract_quotation').textContent = data.quotation_number;
    document.getElementById('view_contract_total').textContent = '\u20B1' + data.total;
    document.getElementById('view_contract_duration').textContent =
      (data.start_date || '\u2014') + ' \u2013 ' + (data.end_date || '\u2014');
    document.getElementById('view_contract_scope').textContent = data.scope_summary || 'No scope summary provided.';
    document.getElementById('view_contract_terms').textContent = data.terms_conditions || 'No terms and conditions provided.';
    document.getElementById('view_contract_prepared').textContent = data.prepared_by || '\u2014';

    const approvedWrap = document.getElementById('view_contract_approved_wrap');
    if (data.approved_by) {
      approvedWrap.classList.remove('d-none');
      document.getElementById('view_contract_approved').textContent = data.approved_by;
    } else {
      approvedWrap.classList.add('d-none');
    }

    document.getElementById('status_contract_id').value = data.contract_id;
    document.getElementById('revision_contract_id').value = data.contract_id;

    const revisionsList = document.getElementById('view_revisions_list');
    revisionsList.innerHTML = '';
    if (data.revisions.length === 0) {
      revisionsList.innerHTML = '<p class="small mb-0" style="color:var(--ink-soft);">No revisions recorded.</p>';
    } else {
      data.revisions.forEach(function (rev) {
        const div = document.createElement('div');
        div.className = 'revision-item';
        div.innerHTML =
          '<div class="small">' + escapeHtml(rev.note) + '</div>' +
          '<div class="small" style="color:var(--ink-soft); font-size:.7rem;">' + escapeHtml(rev.by || 'Unknown') + ' &middot; ' + escapeHtml(rev.date) + '</div>';
        revisionsList.appendChild(div);
      });
    }

    const modal = new bootstrap.Modal(document.getElementById('viewContractModal'));
    modal.show();
  });
});

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>