<?php

session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

$adminId = $_SESSION['admin_id'];
$adminStmt = $pdo->prepare('SELECT firstname, lastname FROM users WHERE user_id = ?');
$adminStmt->execute([$adminId]);
$adminRow = $adminStmt->fetch();
$adminFullname = $adminRow ? trim($adminRow['firstname'] . ' ' . $adminRow['lastname']) : 'Admin';

$roleFilter   = $_GET['role'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$searchTerm   = trim($_GET['search'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$unionQuery = "
    SELECT u.user_id AS actor_id, u.firstname, u.lastname, u.role,
           'Assignment' AS action_type,
           CONCAT('Assigned service request to ', s.firstname, ' ', s.lastname) AS action_label,
           sr.request_title AS target_label, sr.updated_at AS occurred_at,
           CONCAT(s.firstname, ' ', s.lastname) AS related_staff
    FROM service_requests sr
    INNER JOIN users u ON u.user_id = sr.assigned_by
    INNER JOIN users s ON s.user_id = sr.assigned_to
    WHERE sr.assigned_to IS NOT NULL

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Progress Update', CONCAT('Marked request as ', sr.status),
           sr.request_title, sr.updated_at, NULL
    FROM service_requests sr
    INNER JOIN users u ON u.user_id = sr.assigned_to
    WHERE sr.status IN ('In Progress', 'Completed', 'Cancelled')

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Quotation', 'Created quotation',
           q.quotation_number, q.created_at, NULL
    FROM quotations q
    INNER JOIN users u ON u.user_id = q.prepared_by

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Contract', 'Generated contract',
           ct.contract_number, ct.created_at, NULL
    FROM contracts ct
    INNER JOIN users u ON u.user_id = ct.prepared_by

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Contract', 'Approved contract',
           ct.contract_number, ct.approved_at, NULL
    FROM contracts ct
    INNER JOIN users u ON u.user_id = ct.approved_by
    WHERE ct.approved_at IS NOT NULL

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Revision', 'Recorded contract revision',
           (SELECT contract_number FROM contracts WHERE contract_id = cr.contract_id), cr.created_at, NULL
    FROM contract_revisions cr
    INNER JOIN users u ON u.user_id = cr.revised_by

    UNION ALL

    SELECT a.admin_id, a.fullname, '', 'Admin',
           'Repository', 'Uploaded document',
           CONCAT(kd.title, ' (', kd.category, ')'), kd.created_at, NULL
    FROM knowledge_documents kd
    INNER JOIN administrator a ON a.admin_id = kd.uploaded_by
    WHERE kd.uploaded_by_role = 'admin'

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Repository', 'Uploaded document',
           CONCAT(kd.title, ' (', kd.category, ')'), kd.created_at, NULL
    FROM knowledge_documents kd
    INNER JOIN users u ON u.user_id = kd.uploaded_by
    WHERE kd.uploaded_by_role IN ('manager', 'supervisor')

    UNION ALL

    SELECT a.admin_id, a.fullname, '', 'Admin',
           'Repository', 'Updated document',
           CONCAT(kd.title, ' (', kd.category, ')'), kd.updated_at, NULL
    FROM knowledge_documents kd
    INNER JOIN administrator a ON a.admin_id = kd.uploaded_by
    WHERE kd.uploaded_by_role = 'admin' AND kd.updated_at != kd.created_at

    UNION ALL

    SELECT u.user_id, u.firstname, u.lastname, u.role,
           'Repository', 'Updated document',
           CONCAT(kd.title, ' (', kd.category, ')'), kd.updated_at, NULL
    FROM knowledge_documents kd
    INNER JOIN users u ON u.user_id = kd.uploaded_by
    WHERE kd.uploaded_by_role IN ('manager', 'supervisor') AND kd.updated_at != kd.created_at
";

$conditions = [];
$params = [];

if (in_array($roleFilter, ['Manager', 'Supervisor', 'Staff', 'Admin'], true)) {
    $conditions[] = 'role = ?';
    $params[] = $roleFilter;
}

if ($actionFilter !== '') {
    $conditions[] = 'action_type = ?';
    $params[] = $actionFilter;
}

if ($searchTerm !== '') {
    $conditions[] = '(target_label LIKE ? OR CONCAT(firstname, " ", lastname) LIKE ?)';
    $like = '%' . $searchTerm . '%';
    $params[] = $like;
    $params[] = $like;
}

$whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

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

$roleCountsStmt = $pdo->query(
    "SELECT role, COUNT(*) AS cnt FROM ({$unionQuery}) AS activity_feed GROUP BY role"
);
$roleCounts = ['Manager' => 0, 'Supervisor' => 0, 'Staff' => 0, 'Admin' => 0];
foreach ($roleCountsStmt->fetchAll() as $row) {
    $roleCounts[$row['role']] = (int) $row['cnt'];
}

function actionTypeClass(string $type): string
{
    return match ($type) {
        'Assignment' => 'status-progress',
        'Progress Update' => 'status-approved',
        'Quotation' => 'status-new',
        'Contract' => 'status-rejected',
        'Revision' => 'status-progress',
        'Repository' => 'status-new',
        default => 'status-new',
    };
}

function roleBadgeClass(string $role): string
{
    return match ($role) {
        'Manager' => 'status-new',
        'Supervisor' => 'status-progress',
        'Staff' => 'status-approved',
        'Admin' => 'status-rejected',
        default => 'status-new',
    };
}

function buildLogPageUrl(int $targetPage, string $role, string $action, string $search): string
{
    return '?' . http_build_query([
        'page' => $targetPage,
        'role' => $role,
        'action' => $action,
        'search' => $search,
    ]);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Logs</title>
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

.metric-card {
  border-radius: 14px;
  border: 1px solid var(--line);
  background-color: var(--card);
  padding: 1.1rem 1.2rem;
  height: 100%;
}
.metric-icon {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.05rem;
  flex-shrink: 0;
}
.metric-label { font-size: .75rem; color: var(--ink-soft); font-weight: 600; }
.metric-value { font-size: 1.5rem; font-weight: 700; font-family: 'Lexend', sans-serif; color: var(--ink); }

.status-pill {
  font-size: .68rem;
  font-weight: 600;
  padding: .28rem .6rem;
  border-radius: 999px;
  white-space: nowrap;
}
.status-new { background-color: var(--navy-soft); color: var(--navy); }
.status-progress { background-color: var(--amber-soft); color: var(--amber-text); }
.status-approved { background-color: var(--teal-soft); color: var(--teal-text); }
.status-rejected { background-color: var(--coral-soft); color: var(--coral-text); }

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 600;
  font-size: .68rem;
  letter-spacing: .04em;
  text-transform: uppercase;
  background-color: var(--canvas) !important;
}
.table td { border-bottom: 1px solid var(--line); vertical-align: middle; font-size: .82rem; }
.table-hover tbody tr:hover { background-color: var(--navy-soft); }

.actor-avatar {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .72rem;
  font-weight: 700;
  flex-shrink: 0;
  background-color: var(--navy-soft);
  color: var(--navy);
}

.filter-toolbar .form-select,
.filter-toolbar .form-control {
  font-size: .82rem;
}

.log-pagination .page-link { color: var(--navy); border-color: var(--line); }
.log-pagination .page-item.active .page-link { background-color: var(--navy); border-color: var(--navy); color: #fff; }
.log-pagination .page-item.disabled .page-link { color: #adb5bd; }

.log-row { cursor: pointer; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }
.detail-row { padding: .6rem 0; border-bottom: 1px solid var(--line); }
.detail-row:last-child { border-bottom: none; }
.detail-label { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: var(--ink-soft); }
.detail-value { font-size: .9rem; font-weight: 600; color: var(--ink); }
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Activity Logs</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Audit trail of actions performed across the system.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--navy-soft);">
              <i class="fa-solid fa-list-check" style="color:var(--navy);"></i>
            </span>
            <div>
              <div class="metric-label">Total Logged Actions</div>
              <div class="metric-value"><?= $totalLogs ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--navy-soft);">
              <i class="fa-solid fa-user-tie" style="color:var(--navy);"></i>
            </span>
            <div>
              <div class="metric-label">Manager Actions</div>
              <div class="metric-value"><?= $roleCounts['Manager'] ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--amber-soft);">
              <i class="fa-solid fa-user-shield" style="color:var(--amber-text);"></i>
            </span>
            <div>
              <div class="metric-label">Supervisor Actions</div>
              <div class="metric-value"><?= $roleCounts['Supervisor'] ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="metric-card d-flex align-items-center gap-3">
            <span class="metric-icon" style="background-color:var(--teal-soft);">
              <i class="fa-solid fa-user" style="color:var(--teal-text);"></i>
            </span>
            <div>
              <div class="metric-label">Staff Actions</div>
              <div class="metric-value"><?= $roleCounts['Staff'] ?></div>
            </div>
          </div>
        </div>
      </div>

      <section class="filter-toolbar card border-0 shadow-sm mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="row g-2 align-items-center" method="GET">
            <div class="col-12 col-md-4">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by staff name or record" value="<?= htmlspecialchars($searchTerm) ?>">
              </div>
            </div>
            <div class="col-6 col-md-3">
              <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="Manager" <?= $roleFilter === 'Manager' ? 'selected' : '' ?>>Manager</option>
                <option value="Supervisor" <?= $roleFilter === 'Supervisor' ? 'selected' : '' ?>>Supervisor</option>
                <option value="Staff" <?= $roleFilter === 'Staff' ? 'selected' : '' ?>>Staff</option>
                <option value="Admin" <?= $roleFilter === 'Admin' ? 'selected' : '' ?>>Admin</option>
              </select>
            </div>
            <div class="col-6 col-md-3">
              <select name="action" class="form-select" onchange="this.form.submit()">
                <option value="">All Actions</option>
                <option value="Assignment" <?= $actionFilter === 'Assignment' ? 'selected' : '' ?>>Assignment</option>
                <option value="Progress Update" <?= $actionFilter === 'Progress Update' ? 'selected' : '' ?>>Progress Update</option>
                <option value="Quotation" <?= $actionFilter === 'Quotation' ? 'selected' : '' ?>>Quotation</option>
                <option value="Contract" <?= $actionFilter === 'Contract' ? 'selected' : '' ?>>Contract</option>
                <option value="Revision" <?= $actionFilter === 'Revision' ? 'selected' : '' ?>>Revision</option>
                <option value="Repository" <?= $actionFilter === 'Repository' ? 'selected' : '' ?>>Repository</option>
              </select>
            </div>
            <div class="col-12 col-md-2">
              <button type="submit" class="btn w-100" style="background-color:var(--navy); color:#fff; font-weight:600;">Filter</button>
            </div>
          </form>
        </div>
      </section>

      <section class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Staff</th>
                <th scope="col" class="d-none d-md-table-cell">Role</th>
                <th scope="col">Action</th>
                <th scope="col" class="d-none d-lg-table-cell">Record</th>
                <th scope="col">Date &amp; Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($logs)): ?>
                <tr>
                  <td colspan="5">
                    <div class="empty-state text-center py-5">
                      <i class="fa-regular fa-clock fs-3 mb-2 d-block"></i>
                      <p class="small mb-0">No activity found for the selected filters.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <?php $initials = strtoupper(substr($log['firstname'], 0, 1)); ?>
                  <tr class="log-row" role="button" data-bs-toggle="modal" data-bs-target="#logDetailModal"
                    data-name="<?= htmlspecialchars(trim($log['firstname'] . ' ' . $log['lastname'])) ?>"
                    data-role="<?= htmlspecialchars($log['role']) ?>"
                    data-action-type="<?= htmlspecialchars($log['action_type']) ?>"
                    data-action-label="<?= htmlspecialchars($log['action_label']) ?>"
                    data-target="<?= htmlspecialchars($log['target_label'] ?? '—') ?>"
                    data-related="<?= htmlspecialchars($log['related_staff'] ?? '') ?>"
                    data-occurred="<?= htmlspecialchars(date('F d, Y g:i A', strtotime($log['occurred_at']))) ?>">
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <span class="actor-avatar"><?= htmlspecialchars($initials) ?></span>
                        <div>
                          <div class="fw-semibold small"><?= htmlspecialchars(trim($log['firstname'] . ' ' . $log['lastname'])) ?></div>
                          <div class="d-md-none"><span class="status-pill <?= roleBadgeClass($log['role']) ?>"><?= htmlspecialchars($log['role']) ?></span></div>
                        </div>
                      </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                      <span class="status-pill <?= roleBadgeClass($log['role']) ?>"><?= htmlspecialchars($log['role']) ?></span>
                    </td>
                    <td>
                      <span class="status-pill <?= actionTypeClass($log['action_type']) ?>"><?= htmlspecialchars($log['action_type']) ?></span>
                      <div class="small mt-1" style="color:var(--ink-soft);"><?= htmlspecialchars($log['action_label']) ?></div>
                    </td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($log['target_label'] ?? '—') ?></td>
                    <td class="small" style="color:var(--ink-soft);"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($log['occurred_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
          <span class="small" style="color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $totalLogs ?> total</span>
          <nav aria-label="Activity log pagination">
            <ul class="pagination pagination-sm mb-0 log-pagination">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildLogPageUrl($page - 1, $roleFilter, $actionFilter, $searchTerm) ?>">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= buildLogPageUrl($i, $roleFilter, $actionFilter, $searchTerm) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildLogPageUrl($page + 1, $roleFilter, $actionFilter, $searchTerm) ?>">Next</a>
              </li>
            </ul>
          </nav>
        </div>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<div class="modal fade" id="logDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold">Activity Detail</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="detail-row">
          <div class="detail-label">Staff</div>
          <div class="detail-value" id="detail_name"></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Role</div>
          <div class="detail-value" id="detail_role"></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Action</div>
          <div class="detail-value" id="detail_action"></div>
        </div>
        <div class="detail-row" id="detail_related_wrap">
          <div class="detail-label">Assigned To</div>
          <div class="detail-value" id="detail_related"></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Record</div>
          <div class="detail-value" id="detail_target"></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Date &amp; Time</div>
          <div class="detail-value" id="detail_occurred"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.log-row').forEach(function (row) {
  row.addEventListener('click', function () {
    document.getElementById('detail_name').textContent = row.dataset.name;
    document.getElementById('detail_role').textContent = row.dataset.role;
    document.getElementById('detail_action').textContent = row.dataset.actionType + ' — ' + row.dataset.actionLabel;
    document.getElementById('detail_target').textContent = row.dataset.target;
    document.getElementById('detail_occurred').textContent = row.dataset.occurred;

    const relatedWrap = document.getElementById('detail_related_wrap');
    if (row.dataset.related) {
      relatedWrap.classList.remove('d-none');
      document.getElementById('detail_related').textContent = row.dataset.related;
    } else {
      relatedWrap.classList.add('d-none');
    }
  });
});
</script>

</body>
</html>