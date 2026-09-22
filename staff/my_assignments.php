<?php
session_name('STAFF_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();
$staffId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {

        $requestId = $_POST['request_id'] ?? null;
        $newStatus = $_POST['status'] ?? '';

        if (!in_array($newStatus, ['In Progress', 'Completed', 'Cancelled'])) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Invalid status. You can only set status to In Progress, Completed, or Cancelled.';
            header('Location: my_assignments.php');
            exit;
        }

        $checkStmt = $pdo->prepare('SELECT status, assigned_to FROM service_requests WHERE request_id = ?');
        $checkStmt->execute([$requestId]);
        $currentRequest = $checkStmt->fetch();

        if (!$currentRequest || $currentRequest['assigned_to'] != $staffId) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'You are not authorized to update this request.';
        } elseif (in_array($currentRequest['status'], ['Completed', 'Cancelled'])) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'This request is already ' . strtolower($currentRequest['status']) . ' and can no longer be updated.';
        } else {
            $stmt = $pdo->prepare('UPDATE service_requests SET status = ? WHERE request_id = ?');
            $stmt->execute([$newStatus, $requestId]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Assignment status updated successfully.';
        }

        header('Location: my_assignments.php?tab=' . urlencode($_POST['return_tab'] ?? 'new'));
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$tabStatusMap = [
    'new' => 'New',
    'progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

$activeTab = $_GET['tab'] ?? 'new';
if (!array_key_exists($activeTab, $tabStatusMap)) {
    $activeTab = 'new';
}
$activeStatus = $tabStatusMap[$activeTab];

$sortOrder  = $_GET['sort'] ?? 'newest';
$sortSql    = $sortOrder === 'oldest' ? 'ASC' : 'DESC';
$searchTerm = trim($_GET['search'] ?? '');
$perPage    = 10;
$page       = max(1, (int) ($_GET['page'] ?? 1));
$offset     = ($page - 1) * $perPage;

function buildAssignmentQuery(string $status, string $searchTerm): array
{
    $query = "SELECT sr.*, c.company_name, c.contact_person, c.email, c.contact_number, c.address, c.industry,
                     ct.contract_number, ct.quotation_id, ct.total_amount, ct.scope_summary, ct.terms_conditions,
                     ct.start_date, ct.end_date, ct.approved_at,
                     q.quotation_number, q.subtotal, q.tax_rate, q.tax_amount,
                     q.valid_until AS quotation_valid_until, q.notes AS quotation_notes,
                     ab.firstname AS assigned_by_firstname, ab.lastname AS assigned_by_lastname
              FROM service_requests sr
              JOIN clients c ON c.client_id = sr.client_id
              LEFT JOIN contracts ct ON ct.request_id = sr.request_id AND ct.status = 'Approved'
              LEFT JOIN quotations q ON ct.quotation_id = q.quotation_id
              LEFT JOIN users ab ON sr.assigned_by = ab.user_id
              WHERE sr.assigned_to = ? AND sr.status = ?";
    $params = [$GLOBALS['staffId'], $status];

    if ($searchTerm !== '') {
        $query .= ' AND (sr.request_title LIKE ? OR c.company_name LIKE ?)';
        $like = '%' . $searchTerm . '%';
        $params[] = $like;
        $params[] = $like;
    }

    return [$query, $params];
}

[$query, $params] = buildAssignmentQuery($activeStatus, $searchTerm);

$countQuery = 'SELECT COUNT(*) FROM service_requests sr JOIN clients c ON c.client_id = sr.client_id WHERE sr.assigned_to = ? AND sr.status = ?';
$countParams = [$staffId, $activeStatus];
if ($searchTerm !== '') {
    $countQuery .= ' AND (sr.request_title LIKE ? OR c.company_name LIKE ?)';
    $like = '%' . $searchTerm . '%';
    $countParams[] = $like;
    $countParams[] = $like;
}
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$filteredCount = (int) $countStmt->fetchColumn();

$query .= " ORDER BY sr.created_at $sortSql LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$totalPages = max(1, (int) ceil($filteredCount / $perPage));

$tabCounts = [];
foreach ($tabStatusMap as $tabKey => $statusValue) {
    $tabCountStmt = $pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE assigned_to = ? AND status = ?');
    $tabCountStmt->execute([$staffId, $statusValue]);
    $tabCounts[$tabKey] = (int) $tabCountStmt->fetchColumn();
}
$totalMine = array_sum($tabCounts);

function statusPillClass(string $status): string
{
    return match ($status) {
        'New' => 'status-new',
        'In Progress' => 'status-progress',
        'Completed' => 'status-approved',
        'Cancelled' => 'status-rejected',
        default => 'status-new',
    };
}

function statusIcon(string $status): string
{
    return match ($status) {
        'New' => 'fa-inbox',
        'In Progress' => 'fa-spinner',
        'Completed' => 'fa-circle-check',
        'Cancelled' => 'fa-circle-xmark',
        default => 'fa-inbox',
    };
}

function buildPageUrl(int $targetPage, string $tab, string $searchTerm, string $sortOrder): string
{
    $params = [
        'tab' => $tab,
        'page' => $targetPage,
        'search' => $searchTerm,
        'sort' => $sortOrder,
    ];
    return '?' . http_build_query($params);
}

function buildTabUrl(string $tab, string $searchTerm, string $sortOrder): string
{
    $params = [
        'tab' => $tab,
        'search' => $searchTerm,
        'sort' => $sortOrder,
    ];
    return '?' . http_build_query($params);
}

function formatDisplayDate(?string $date): ?string
{
    return $date ? date('M d, Y', strtotime($date)) : null;
}

function formatQuantity($quantity): string
{
    return rtrim(rtrim(number_format((float) $quantity, 2), '0'), '.');
}

function buildAssignmentPayload(array $row, array $itemsByQuotation): array
{
    $items = array_map(function ($item) {
        return [
            'description' => $item['description'],
            'quantity'    => formatQuantity($item['quantity']),
            'unit_price'  => number_format((float) $item['unit_price'], 2),
            'line_total'  => number_format((float) $item['line_total'], 2),
        ];
    }, $itemsByQuotation[$row['quotation_id'] ?? 0] ?? []);

    $assignedBy = trim(($row['assigned_by_firstname'] ?? '') . ' ' . ($row['assigned_by_lastname'] ?? ''));

    return [
        'request_id'       => (int) $row['request_id'],
        'request_title'    => $row['request_title'],
        'request_details'  => $row['request_details'],
        'required_skill'   => $row['required_skill'] ?? null,
        'status'           => $row['status'],
        'received_at'      => formatDisplayDate($row['created_at']),
        'company'          => $row['company_name'],
        'contact_person'   => $row['contact_person'],
        'email'            => $row['email'],
        'contact_number'   => $row['contact_number'],
        'address'          => $row['address'],
        'industry'         => $row['industry'],
        'contract_number'  => $row['contract_number'] ?? null,
        'contract_total'   => isset($row['total_amount']) ? number_format((float) $row['total_amount'], 2) : null,
        'start_date'       => formatDisplayDate($row['start_date'] ?? null),
        'end_date'         => formatDisplayDate($row['end_date'] ?? null),
        'scope_summary'    => $row['scope_summary'] ?? null,
        'terms_conditions' => $row['terms_conditions'] ?? null,
        'assigned_by'      => $assignedBy !== '' ? $assignedBy : null,
        'quotation_number' => $row['quotation_number'] ?? null,
        'subtotal'         => isset($row['subtotal']) ? number_format((float) $row['subtotal'], 2) : null,
        'tax_rate'         => isset($row['tax_rate']) ? formatQuantity($row['tax_rate']) : null,
        'tax_amount'       => isset($row['tax_amount']) ? number_format((float) $row['tax_amount'], 2) : null,
        'valid_until'      => formatDisplayDate($row['quotation_valid_until'] ?? null),
        'quotation_notes'  => $row['quotation_notes'] ?? null,
        'items'            => $items,
    ];
}

$quotationIds = array_filter(array_column($assignments, 'quotation_id'));
$itemsByQuotation = [];
if (!empty($quotationIds)) {
    $placeholders = implode(',', array_fill(0, count($quotationIds), '?'));
    $itemsStmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id IN ($placeholders) ORDER BY quotation_id ASC, sort_order ASC");
    $itemsStmt->execute(array_values($quotationIds));
    foreach ($itemsStmt->fetchAll() as $item) {
        $itemsByQuotation[$item['quotation_id']][] = $item;
    }
}

$emptyStateCopy = [
    'new'        => ['title' => "You're all caught up",   'sub' => 'No new assignments waiting for you right now.'],
    'progress'   => ['title' => 'Nothing in progress',    'sub' => 'Requests you start working on will show up here.'],
    'completed'  => ['title' => 'No completed items yet', 'sub' => 'Finished assignments will be listed here.'],
    'cancelled'  => ['title' => 'No cancelled items',     'sub' => 'Cancelled assignments will be listed here.'],
];
$emptyCopy = $emptyStateCopy[$activeTab] ?? $emptyStateCopy['new'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Assignments</title>
  <link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <link rel="stylesheet" href="../assets/css/staff/my_assignments.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Full Details Modal (matches admin resource matching style) */
    #requestDetailsModal .modal-content { border-radius: 0; }
    #requestDetailsModal .modal-header {
      padding: .9rem 1.25rem;
      background-color: #fff;
      border-bottom: 1px solid var(--line, #e6e2da);
    }
    #requestDetailsModal .modal-header-col { flex: 1 1 0; min-width: 0; }
    #requestDetailsModal .modal-eyebrow {
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--ink-soft, #6e7275);
    }
    #requestDetailsModal .modal-main-title {
      font-family: 'Lexend', 'Inter', sans-serif;
      font-size: .95rem;
      font-weight: 700;
      color: var(--charcoal, #2b3134);
      margin: 0;
    }
    #requestDetailsModal .modal-body {
      background-color: var(--surface, #f6f4ef);
      padding: 1.25rem;
    }
    #requestDetailsModal .quote-shell { max-width: 1400px; margin: 0 auto; }
    #requestDetailsModal .quote-panel {
      background-color: #fff;
      border: 1px solid var(--line, #e6e2da);
      border-radius: 12px;
      padding: 1.1rem 1.25rem;
      box-shadow: 0 1px 2px rgba(42, 45, 47, .04);
    }
    #requestDetailsModal .quote-sticky { position: sticky; top: 0; }
    #requestDetailsModal .section-label {
      font-size: .7rem;
      font-weight: 700;
      letter-spacing: .05em;
      text-transform: uppercase;
      color: var(--ink-soft, #6e7275);
      margin-bottom: .3rem;
    }
    #requestDetailsModal .details-heading {
      font-family: 'Lexend', 'Inter', sans-serif;
      font-size: .85rem;
      font-weight: 600;
      color: var(--charcoal, #2b3134);
      margin-bottom: .75rem;
      display: flex;
      align-items: center;
      gap: .55rem;
    }
    #requestDetailsModal .details-heading .step-icon {
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
    #requestDetailsModal .details-value { font-size: .875rem; color: var(--ink, #2a2d2f); word-break: break-word; }
    #requestDetailsModal .clamp-text {
      white-space: pre-line;
      word-break: break-word;
      font-size: .875rem;
      color: var(--ink, #2a2d2f);
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 4;
      overflow: hidden;
    }
    #requestDetailsModal .clamp-text.is-expanded { display: block; -webkit-line-clamp: unset; }
    #requestDetailsModal .clamp-toggle {
      background: none;
      border: none;
      padding: 0;
      margin-top: .35rem;
      font-size: .78rem;
      font-weight: 600;
      color: #245853;
    }
    #requestDetailsModal .clamp-toggle:hover { color: #1c433f; text-decoration: underline; }
    #requestDetailsModal .items-table th {
      background-color: var(--surface, #f6f4ef) !important;
      color: var(--ink-soft, #6e7275);
      font-size: .68rem;
      font-weight: 700;
      letter-spacing: .05em;
      text-transform: uppercase;
      border-bottom: 1px solid var(--line, #e6e2da) !important;
    }
    #requestDetailsModal .items-table td { font-size: .82rem; border-bottom: 1px solid var(--line, #e6e2da); }
    #requestDetailsModal .totals-line { display: flex; justify-content: space-between; font-size: .85rem; color: #55595c; }
    #requestDetailsModal .totals-line.grand {
      margin-top: .45rem;
      padding-top: .45rem;
      border-top: 1px solid var(--line, #e6e2da);
      font-weight: 700;
      font-size: 1rem;
      color: #245853;
    }
    #requestDetailsModal .skill-badge {
      display: inline-block;
      background-color: #E3EFEC;
      color: #245853;
      font-size: .7rem;
      font-weight: 600;
      padding: .28rem .65rem;
      border-radius: 999px;
      border: 1px solid #C5DDCF;
    }
    .btn-view-details {
      background-color: #fff;
      color: #245853;
      border: 1px solid #C5DDCF;
      border-radius: 8px;
      font-weight: 600;
      font-size: .78rem;
      padding: .35rem .75rem;
    }
    .btn-view-details:hover { background-color: #E3EFEC; color: #245853; }

    @media (max-width: 991.98px) {
      #requestDetailsModal .quote-sticky { position: static; }
    }
    @media (max-width: 767.98px) {
      #requestDetailsModal .modal-body { padding: .75rem; }
      #requestDetailsModal .quote-panel { padding: .9rem; }
      #requestDetailsModal .modal-header { padding: .65rem .85rem; }
      #requestDetailsModal .modal-eyebrow { font-size: .6rem; }
      #requestDetailsModal .modal-main-title { font-size: .8rem; }
      #requestDetailsModal .btn-close { transform: scale(.85); }
    }
  </style>
</head>
<body class="bg-light">

  <div class="dashboard-layout d-flex">

    <?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

    <div class="dashboard-main flex-grow-1" style="min-width:0;">

      <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
        <div class="d-flex align-items-center gap-3">
          <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
            <i class="fa-solid fa-bars fs-5"></i>
          </button>
          <div>
            <h1 class="dashboard-title h6 h5-md fw-bold mb-0">My Assignments</h1>
            <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Service requests assigned to you.</p>
          </div>
        </div>
      </header>

      <main class="dashboard-content p-3 p-md-4">

        <section class="row g-2 g-md-3 mb-3">
          <div class="col-6 col-md-3">
            <div class="card border-0 stat-card d-flex flex-row align-items-center gap-2 gap-md-3 h-100">
              <span class="stat-icon" style="background-color:var(--navy-soft);">
                <i class="fa-solid fa-list-check" style="color:var(--navy);"></i>
              </span>
              <div class="overflow-hidden">
                <div class="stat-label text-truncate">Total</div>
                <div class="stat-value" style="color:var(--navy);"><?= $totalMine ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 stat-card d-flex flex-row align-items-center gap-2 gap-md-3 h-100">
              <span class="stat-icon" style="background-color:var(--navy-soft);">
                <i class="fa-solid fa-inbox" style="color:var(--navy);"></i>
              </span>
              <div class="overflow-hidden">
                <div class="stat-label text-truncate">New</div>
                <div class="stat-value" style="color:var(--navy);"><?= $tabCounts['new'] ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 stat-card d-flex flex-row align-items-center gap-2 gap-md-3 h-100">
              <span class="stat-icon" style="background-color:var(--amber-soft);">
                <i class="fa-solid fa-spinner" style="color:var(--amber-text);"></i>
              </span>
              <div class="overflow-hidden">
                <div class="stat-label text-truncate">In Progress</div>
                <div class="stat-value" style="color:var(--amber-text);"><?= $tabCounts['progress'] ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 stat-card d-flex flex-row align-items-center gap-2 gap-md-3 h-100">
              <span class="stat-icon" style="background-color:var(--teal-soft);">
                <i class="fa-solid fa-circle-check" style="color:var(--teal-text);"></i>
              </span>
              <div class="overflow-hidden">
                <div class="stat-label text-truncate">Completed</div>
                <div class="stat-value" style="color:var(--teal-text);"><?= $tabCounts['completed'] ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 stat-card d-flex flex-row align-items-center gap-2 gap-md-3 h-100">
              <span class="stat-icon" style="background-color:#F8E9E5;">
                <i class="fa-solid fa-circle-xmark" style="color:#8C3D2E;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="stat-label text-truncate">Cancelled</div>
                <div class="stat-value" style="color:#8C3D2E;"><?= $tabCounts['cancelled'] ?></div>
              </div>
            </div>
          </div>
        </section>

        <section class="card border-0 mb-3">
          <div class="card-body p-2 p-md-3">
            <div class="status-tabs mb-3">
              <a href="<?= buildTabUrl('new', $searchTerm, $sortOrder) ?>" class="status-tab <?= $activeTab === 'new' ? 'active' : '' ?>">
                New <span class="tab-count">(<?= $tabCounts['new'] ?>)</span>
              </a>
              <a href="<?= buildTabUrl('progress', $searchTerm, $sortOrder) ?>" class="status-tab <?= $activeTab === 'progress' ? 'active' : '' ?>">
                In Progress <span class="tab-count">(<?= $tabCounts['progress'] ?>)</span>
              </a>
              <a href="<?= buildTabUrl('completed', $searchTerm, $sortOrder) ?>" class="status-tab <?= $activeTab === 'completed' ? 'active' : '' ?>">
                Completed <span class="tab-count">(<?= $tabCounts['completed'] ?>)</span>
              </a>
              <a href="<?= buildTabUrl('cancelled', $searchTerm, $sortOrder) ?>" class="status-tab <?= $activeTab === 'cancelled' ? 'active' : '' ?>">
                Cancelled <span class="tab-count">(<?= $tabCounts['cancelled'] ?>)</span>
              </a>
            </div>

            <form class="row g-2 align-items-center toolbar-form" method="GET">
              <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
              <div class="col-12 col-md-7">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                  <input type="text" name="search" class="form-control" placeholder="Search request or client" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
              </div>
              <div class="col-8 col-md-3">
                <select name="sort" class="form-select" onchange="this.form.submit()">
                  <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
                  <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
                </select>
              </div>
              <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-filter w-100">
                  <i class="fa-solid fa-filter"></i> <span class="d-none d-sm-inline">Filter</span>
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="card border-0">
          <?php if (empty($assignments)): ?>
            <div class="empty-state-wrap">
              <div class="empty-state-icon">
                <i class="fa-regular fa-folder-open"></i>
              </div>
              <div class="empty-state-title"><?= htmlspecialchars($emptyCopy['title']) ?></div>
              <div class="empty-state-sub"><?= htmlspecialchars($emptyCopy['sub']) ?></div>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Request</th>
                    <th scope="col" class="d-none d-md-table-cell">Client</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($assignments as $assignment): ?>
                    <?php $payload = buildAssignmentPayload($assignment, $itemsByQuotation); ?>
                    <tr>
                      <td>
                        <div class="request-title"><?= htmlspecialchars($assignment['request_title']) ?></div>
                        <div class="request-meta d-md-none"><?= htmlspecialchars($assignment['company_name']) ?></div>
                        <?php if (!empty($assignment['request_details'])): ?>
                          <div class="request-meta d-none d-md-block"><?= htmlspecialchars($assignment['request_details']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="d-none d-md-table-cell" style="color:var(--ink-soft); font-size:.82rem;"><?= htmlspecialchars($assignment['company_name']) ?></td>
                      <td>
                        <span class="status-pill <?= statusPillClass($assignment['status']) ?>">
                          <i class="fa-solid <?= statusIcon($assignment['status']) ?>"></i>
                          <?= htmlspecialchars($assignment['status']) ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                          <button type="button" class="btn btn-sm btn-view-details view-details-btn" title="View Full Details"
                            data-request='<?= htmlspecialchars(json_encode($payload), ENT_QUOTES) ?>'>
                            <i class="fa-regular fa-eye"></i> <span class="d-none d-sm-inline">Full Details</span>
                          </button>
                          <?php if (!in_array($assignment['status'], ['Completed', 'Cancelled'])): ?>
                            <button type="button" class="btn btn-sm btn-update" title="Update Status"
                              data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                              data-id="<?= $assignment['request_id'] ?>"
                              data-title="<?= htmlspecialchars($assignment['request_title']) ?>"
                              data-status="<?= htmlspecialchars($assignment['status']) ?>">
                              <i class="fa-regular fa-pen-to-square"></i> <span class="d-none d-sm-inline">Update</span>
                            </button>
                          <?php else: ?>
                            <span class="request-meta fst-italic align-self-center"><?= $assignment['status'] === 'Cancelled' ? 'Cancelled' : 'Done' ?></span>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
              <span class="request-meta">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredCount ?> total</span>
              <nav aria-label="Assignments pagination">
                <ul class="pagination pagination-sm mb-0">
                  <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildPageUrl($page - 1, $activeTab, $searchTerm, $sortOrder) ?>">Previous</a>
                  </li>
                  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildPageUrl($i, $activeTab, $searchTerm, $sortOrder) ?>"><?= $i ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= buildPageUrl($page + 1, $activeTab, $searchTerm, $sortOrder) ?>">Next</a>
                  </li>
                </ul>
              </nav>
            </div>
          <?php endif; ?>
        </section>

      </main>

    </div>

  </div>

  <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" novalidate>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="request_id" id="update_request_id">
          <input type="hidden" name="return_tab" value="<?= htmlspecialchars($activeTab) ?>">
          <div class="modal-header">
            <h2 class="modal-title h5 fw-bold" id="update_request_title">Update Status</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" id="update_status_select" class="form-select">
              <option value="In Progress">In Progress</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-save">Save Status</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Full Details Modal (same style as admin Resource Matching) -->
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
                        <div class="section-label">Assigned By</div>
                        <div class="details-value" id="detail_assigned_by"></div>
                      </div>
                      <div class="col-sm-6">
                        <div class="section-label">Required Skill</div>
                        <div id="detail_required_skill_display"></div>
                      </div>
                      <div class="col-sm-6">
                        <div class="section-label">Current Status</div>
                        <div class="details-value" id="detail_status"></div>
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
    document.getElementById('updateStatusModal').addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      document.getElementById('update_request_id').value = btn.dataset.id;
      document.getElementById('update_request_title').textContent = btn.dataset.title;
      document.getElementById('update_status_select').value = btn.dataset.status;
    });

    function showAppToast(message, type) {
      const toast = document.createElement('div');
      toast.className = 'app-toast ' + (type === 'error' ? 'error' : 'success');
      toast.innerHTML = `
        <i class="fa-solid ${type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'} toast-icon"></i>
        <span>${message}</span>
        <button type="button" class="toast-close" aria-label="Close">&times;</button>
      `;
      document.body.appendChild(toast);
      requestAnimationFrame(() => toast.classList.add('show'));

      const remove = () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 250);
      };
      toast.querySelector('.toast-close').addEventListener('click', remove);
      setTimeout(remove, 4000);
    }

    <?php if ($alertType && $alertMessage): ?>
    window.addEventListener('DOMContentLoaded', function () {
      showAppToast(<?= json_encode($alertMessage) ?>, <?= json_encode($alertType) ?>);
    });
    <?php endif; ?>

    /* ---------- Full Details Modal logic (same pattern as admin resource_matching.php) ---------- */
    const EMPTY_VALUE = '\u2014';
    const CLAMP_CHARACTER_LIMIT = 220;
    const CLAMP_LINE_LIMIT = 3;

    function hasText(value) {
      return value !== null && value !== undefined && String(value).trim() !== '';
    }

    function setText(elementId, value, fallback) {
      document.getElementById(elementId).textContent = hasText(value) ? value : (fallback !== undefined ? fallback : EMPTY_VALUE);
    }

    function escapeHtml(value) {
      const element = document.createElement('div');
      element.textContent = (value === null || value === undefined) ? '' : String(value);
      return element.innerHTML;
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
        el.innerHTML = '<span class="skill-badge mb-0">' + escapeHtml(requiredSkill) + '</span>';
      } else {
        el.innerHTML = '<span class="details-value">Not specified</span>';
      }
    }

    function renderQuotationItems(items) {
      const itemsBody = document.getElementById('detail_items_body');
      itemsBody.innerHTML = '';

      if (!items || items.length === 0) {
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

    function renderAssignmentDetails(request) {
      document.getElementById('details_eyebrow').textContent = request.contract_number || 'Service Request';
      document.getElementById('details_modal_title').textContent = request.company || 'Full Details';

      setText('detail_company', request.company);
      setText('detail_contact_person', request.contact_person);
      setText('detail_industry', request.industry);
      setText('detail_email', request.email);
      setText('detail_contact_number', request.contact_number);
      setText('detail_address', request.address);

      setText('detail_received_at', request.received_at);
      setText('detail_assigned_by', request.assigned_by, 'Not specified');
      setText('detail_status', request.status);
      renderRequiredSkillInto('detail_required_skill_display', request.required_skill);
      setClampText('detail_request_details', request.request_details, 'No additional details were provided for this request.');
      setClampText('detail_scope', request.scope_summary, 'No project scope provided.');
      setClampText('detail_terms', formatTerms(request.terms_conditions), 'No terms and conditions provided.');
      setClampText('detail_quotation_notes', request.quotation_notes, 'No notes provided.');

      setText('detail_quotation_number', request.quotation_number ? '\u00B7 ' + request.quotation_number : '', '');
      renderQuotationItems(request.items);
      document.getElementById('detail_subtotal').textContent = hasText(request.subtotal) ? '\u20B1' + request.subtotal : EMPTY_VALUE;
      document.getElementById('detail_tax').textContent = hasText(request.tax_amount) ? '\u20B1' + request.tax_amount + ' (' + request.tax_rate + '%)' : EMPTY_VALUE;
      document.getElementById('detail_total').textContent = hasText(request.contract_total) ? '\u20B1' + request.contract_total : EMPTY_VALUE;
      setText('detail_valid_until', request.valid_until, 'No expiry set');
    }

    document.querySelectorAll('.view-details-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const request = JSON.parse(btn.dataset.request);
        renderAssignmentDetails(request);
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('requestDetailsModal'));
        modal.show();
      });
    });
  </script>

</body>
</html>