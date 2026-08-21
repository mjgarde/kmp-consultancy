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

        // I-validate na ang status ay In Progress o Completed lamang
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

        header('Location: my_assignments.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$sortOrder    = $_GET['sort'] ?? 'newest';
$sortSql      = $sortOrder === 'oldest' ? 'ASC' : 'DESC';
$searchTerm   = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$perPage      = 8;
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$query = 'SELECT sr.*, c.company_name
          FROM service_requests sr
          JOIN clients c ON c.client_id = sr.client_id
          WHERE sr.assigned_to = ?';
$params = [$staffId];

if ($searchTerm !== '') {
    $query .= ' AND (sr.request_title LIKE ? OR c.company_name LIKE ?)';
    $like = '%' . $searchTerm . '%';
    $params[] = $like;
    $params[] = $like;
}

if (in_array($statusFilter, ['New', 'In Progress', 'Completed'])) {
    $query .= ' AND sr.status = ?';
    $params[] = $statusFilter;
}

$countQuery = str_replace('SELECT sr.*, c.company_name', 'SELECT COUNT(*)', $query);
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$filteredCount = (int) $countStmt->fetchColumn();

$query .= " ORDER BY sr.created_at $sortSql LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$totalPages = max(1, ceil($filteredCount / $perPage));

$totalMineStmt  = $pdo->prepare('SELECT COUNT(*) FROM service_requests WHERE assigned_to = ?');
$totalMineStmt->execute([$staffId]);
$totalMine = (int) $totalMineStmt->fetchColumn();

$newMineStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE assigned_to = ? AND status = 'New'");
$newMineStmt->execute([$staffId]);
$newMine = (int) $newMineStmt->fetchColumn();

$inProgressMineStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE assigned_to = ? AND status = 'In Progress'");
$inProgressMineStmt->execute([$staffId]);
$inProgressMine = (int) $inProgressMineStmt->fetchColumn();

$completedMineStmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE assigned_to = ? AND status = 'Completed'");
$completedMineStmt->execute([$staffId]);
$completedMine = (int) $completedMineStmt->fetchColumn();

function statusBadgeColor(string $status): string
{
    return match ($status) {
        'New' => '#2F4858',
        'In Progress' => '#E89C5A',
        'Completed' => '#3AA394',
        'Cancelled' => '#DF6E4F',
        default => '#2F4858',
    };
}

function buildPageUrl(int $targetPage, string $searchTerm, string $sortOrder, string $statusFilter): string
{
    $params = [
        'page' => $targetPage,
        'search' => $searchTerm,
        'sort' => $sortOrder,
    ];
    if ($statusFilter !== '') {
        $params['status'] = $statusFilter;
    }
    return '?' . http_build_query($params);
}
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
<style>
.form-control:focus, .form-select:focus, .form-control:hover, .form-select:hover {
  border-color:#2F4858;
  box-shadow:0 0 0 .2rem rgba(47,72,88,.15);
  outline:none;
}
.my-assignments-pagination .page-link {
  color:#2F4858;
  border-color:#e5e7eb;
}
.my-assignments-pagination .page-item.active .page-link {
  background-color:#2F4858;
  border-color:#2F4858;
  color:#fff;
}
.my-assignments-pagination .page-item.disabled .page-link {
  color:#adb5bd;
}

.my-assignments-stat-body {
  padding:.6rem;
}
.my-assignments-stat-icon {
  width:34px;
  height:34px;
  font-size:.8rem;
}
.my-assignments-stat-label {
  font-size:.62rem;
}
.my-assignments-stat-value {
  font-size:1rem;
}

.my-assignments-toolbar .btn {
  font-size:.75rem;
  padding:.4rem .6rem;
}
.my-assignments-table .btn-sm {
  font-size:.68rem;
  padding:.25rem .45rem;
}
.my-assignments-table td, .my-assignments-table th {
  font-size:.72rem;
}

@media (min-width:576px) {
  .my-assignments-stat-body { padding:.85rem; }
  .my-assignments-stat-icon { width:40px; height:40px; font-size:.95rem; }
  .my-assignments-stat-label { font-size:.72rem; }
  .my-assignments-stat-value { font-size:1.25rem; }
  .my-assignments-toolbar .btn { font-size:.85rem; padding:.45rem .8rem; }
  .my-assignments-table .btn-sm { font-size:.75rem; padding:.3rem .55rem; }
  .my-assignments-table td, .my-assignments-table th { font-size:.8rem; }
}

@media (min-width:768px) {
  .my-assignments-stat-body { padding:1rem; }
  .my-assignments-stat-icon { width:44px; height:44px; font-size:1.05rem; }
  .my-assignments-stat-label { font-size:.8rem; }
  .my-assignments-stat-value { font-size:1.5rem; }
  .my-assignments-toolbar .btn { font-size:.9rem; padding:.5rem 1rem; }
  .my-assignments-table .btn-sm { font-size:.8rem; padding:.35rem .65rem; }
  .my-assignments-table td, .my-assignments-table th { font-size:.85rem; }
}
</style>
</head>
<body class="bg-light">

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/staff/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white border-bottom d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">My Assignments</h1>
          <p class="dashboard-subtitle text-secondary small mb-0 d-none d-sm-block">Service requests assigned to you.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
        <button type="button" class="btn btn-link text-secondary p-0">
          <i class="fa-regular fa-bell fs-5"></i>
        </button>
        <div class="dropdown">
          <button type="button" class="btn btn-link p-0 border-0" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="dashboard-user-icon d-flex align-items-center justify-content-center rounded-circle bg-secondary bg-opacity-10 flex-shrink-0" style="width:36px; height:36px;">
              <i class="fa-solid fa-user text-secondary"></i>
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li>
              <a href="../config/logout.php?role=staff" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="my-assignments-summary row g-2 g-md-3 mb-3">
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body my-assignments-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="my-assignments-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#2F485815;">
                <i class="fa-solid fa-list-check" style="color:#2F4858;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="my-assignments-stat-label text-secondary text-truncate">Total</div>
                <div class="my-assignments-stat-value fw-bold" style="color:#2F4858;"><?= $totalMine ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body my-assignments-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="my-assignments-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#E0C06A15;">
                <i class="fa-solid fa-inbox" style="color:#B8912E;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="my-assignments-stat-label text-secondary text-truncate">New</div>
                <div class="my-assignments-stat-value fw-bold" style="color:#B8912E;"><?= $newMine ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body my-assignments-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="my-assignments-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#E89C5A15;">
                <i class="fa-solid fa-spinner" style="color:#C9762F;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="my-assignments-stat-label text-secondary text-truncate">In Progress</div>
                <div class="my-assignments-stat-value fw-bold" style="color:#C9762F;"><?= $inProgressMine ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body my-assignments-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="my-assignments-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#3AA39415;">
                <i class="fa-solid fa-circle-check" style="color:#2C7C71;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="my-assignments-stat-label text-secondary text-truncate">Completed</div>
                <div class="my-assignments-stat-value fw-bold" style="color:#2C7C71;"><?= $completedMine ?></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="my-assignments-toolbar card border-0 shadow-sm mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="row g-2 align-items-center" method="GET">
            <div class="col-12 col-md-5">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search request or client" value="<?= htmlspecialchars($searchTerm) ?>">
              </div>
            </div>
            <div class="col-6 col-md-3">
              <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="New" <?= $statusFilter === 'New' ? 'selected' : '' ?>>New</option>
                <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
              </select>
            </div>
            <div class="col-6 col-md-3">
              <select name="sort" class="form-select" onchange="this.form.submit()">
                <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
                <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
              </select>
            </div>
            <div class="col-12 col-md-1 text-md-end">
              <button type="submit" class="btn w-100" style="background-color:#E0C06A; color:#2F4858;">
                <i class="fa-solid fa-filter"></i>
              </button>
            </div>
          </form>
        </div>
      </section>

      <section class="my-assignments-table card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" class="small text-uppercase text-secondary">Request</th>
                <th scope="col" class="small text-uppercase text-secondary d-none d-md-table-cell">Client</th>
                <th scope="col" class="small text-uppercase text-secondary">Status</th>
                <th scope="col" class="small text-uppercase text-secondary text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($assignments)): ?>
                <tr>
                  <td colspan="4" class="text-center text-secondary py-5">
                    <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                    No assignments found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold small"><?= htmlspecialchars($assignment['request_title']) ?></div>
                      <div class="text-secondary d-md-none" style="font-size:.72rem;"><?= htmlspecialchars($assignment['company_name']) ?></div>
                      <?php if (!empty($assignment['request_details'])): ?>
                        <div class="text-secondary d-none d-md-block" style="font-size:.72rem;"><?= htmlspecialchars($assignment['request_details']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-secondary small d-none d-md-table-cell"><?= htmlspecialchars($assignment['company_name']) ?></td>
                    <td>
                      <span class="badge rounded-pill" style="background-color:<?= statusBadgeColor($assignment['status']) ?>; color:#fff;"><?= htmlspecialchars($assignment['status']) ?></span>
                    </td>
                    <td class="text-end">
                      <?php if ($assignment['status'] === 'Completed'): ?>
                        <span class="text-secondary small fst-italic">Done</span>
                      <?php else: ?>
                        <button type="button" class="btn btn-sm" style="background-color:#2F4858; color:#fff;" title="Update Status"
                          data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                          data-id="<?= $assignment['request_id'] ?>"
                          data-title="<?= htmlspecialchars($assignment['request_title']) ?>"
                          data-status="<?= htmlspecialchars($assignment['status']) ?>">
                          <i class="fa-regular fa-pen-to-square"></i> Update
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
          <span class="text-secondary small">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredCount ?> total</span>
          <nav aria-label="Assignments pagination">
            <ul class="pagination pagination-sm mb-0 my-assignments-pagination">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPageUrl($page - 1, $searchTerm, $sortOrder, $statusFilter) ?>">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= buildPageUrl($i, $searchTerm, $sortOrder, $statusFilter) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildPageUrl($page + 1, $searchTerm, $sortOrder, $statusFilter) ?>">Next</a>
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
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold" id="update_request_title">Update Status</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" id="update_status_select" class="form-select">
            <!-- Inalis ang "New" option - In Progress at Completed na lang -->
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
          </select>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn" style="background-color:#2F4858; color:#fff;">Save Status</button>
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

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>