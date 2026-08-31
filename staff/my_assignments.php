<?php
session_name('STAFF_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();
$staffId = $_SESSION['staff_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {

        $requestId = $_POST['request_id'] ?? null;
        $newStatus = $_POST['status'] ?? '';

        if (!in_array($newStatus, ['In Progress', 'Completed'])) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Invalid status. You can only set status to In Progress or Completed.';
            header('Location: my_assignments.php');
            exit;
        }

        $checkStmt = $pdo->prepare('SELECT status, assigned_to FROM service_requests WHERE request_id = ?');
        $checkStmt->execute([$requestId]);
        $currentRequest = $checkStmt->fetch();

        if (!$currentRequest || $currentRequest['assigned_to'] != $staffId) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'You are not authorized to update this request.';
        } elseif ($currentRequest['status'] === 'Completed') {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'This request is already completed and can no longer be updated.';
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
    $query = 'SELECT sr.*, c.company_name
              FROM service_requests sr
              JOIN clients c ON c.client_id = sr.client_id
              WHERE sr.assigned_to = ? AND sr.status = ?';
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

$countQuery = str_replace('SELECT sr.*, c.company_name', 'SELECT COUNT(*)', $query);
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
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

$emptyStateCopy = [
    'new'        => ['title' => "You're all caught up",   'sub' => 'No new assignments waiting for you right now.'],
    'progress'   => ['title' => 'Nothing in progress',    'sub' => 'Requests you start working on will show up here.'],
    'completed'  => ['title' => 'No completed items yet', 'sub' => 'Finished assignments will be listed here.'],
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
                        <?php if ($assignment['status'] === 'Completed'): ?>
                          <span class="request-meta fst-italic">Done</span>
                        <?php else: ?>
                          <button type="button" class="btn btn-sm btn-update" title="Update Status"
                            data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                            data-id="<?= $assignment['request_id'] ?>"
                            data-title="<?= htmlspecialchars($assignment['request_title']) ?>"
                            data-status="<?= htmlspecialchars($assignment['status']) ?>">
                            <i class="fa-regular fa-pen-to-square"></i> <span class="d-none d-sm-inline">Update</span>
                          </button>
                        <?php endif; ?>
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
            </select>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-save">Save Status</button>
          </div>
        </form>
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
  </script>

</body>
</html>