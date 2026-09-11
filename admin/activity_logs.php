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
$dateFilter   = trim($_GET['date'] ?? '');
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

if ($dateFilter !== '') {
    $conditions[] = 'DATE(occurred_at) = ?';
    $params[] = $dateFilter;
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

$roleCountConditions = array_filter($conditions, fn($c) => $c !== 'role = ?');
$roleCountParams = [];
foreach ($conditions as $i => $c) {
    if ($c !== 'role = ?') {
        $roleCountParams[] = $params[$i];
    }
}
$roleCountWhere = empty($roleCountConditions) ? '' : 'WHERE ' . implode(' AND ', $roleCountConditions);

$roleCountsStmt = $pdo->prepare(
    "SELECT role, COUNT(*) AS cnt FROM ({$unionQuery}) AS activity_feed {$roleCountWhere} GROUP BY role"
);
$roleCountsStmt->execute($roleCountParams);
$roleCounts = ['Manager' => 0, 'Supervisor' => 0, 'Staff' => 0, 'Admin' => 0];
foreach ($roleCountsStmt->fetchAll() as $row) {
    if (isset($roleCounts[$row['role']])) {
        $roleCounts[$row['role']] = (int) $row['cnt'];
    }
}

function actionTypeClass(string $type): string
{
    return match ($type) {
        'Assignment' => 'chip-indigo',
        'Progress Update' => 'chip-success',
        'Quotation' => 'chip-navy',
        'Contract' => 'chip-danger',
        'Revision' => 'chip-warn',
        'Repository' => 'chip-slate',
        default => 'chip-slate',
    };
}

function buildLogPageUrl(int $targetPage, string $role, string $action, string $search, string $date): string
{
    return '?' . http_build_query([
        'page' => $targetPage,
        'role' => $role,
        'action' => $action,
        'search' => $search,
        'date' => $date,
    ]);
}

$actionTypes = ['Assignment', 'Progress Update', 'Quotation', 'Contract', 'Revision', 'Repository'];
$roleTypes = ['Manager', 'Supervisor', 'Staff', 'Admin'];
$hasActiveFilters = $searchTerm !== '' || $roleFilter !== '' || $actionFilter !== '' || $dateFilter !== '';

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

/* --- Stat strip --- */
.stat-strip {
  display: flex;
  border: 1px solid var(--line);
  border-radius: 10px;
  overflow: hidden;
  background-color: #fff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
}
.stat-strip-item {
  flex: 1;
  padding: 1.1rem 1.2rem;
  border-right: 1px solid var(--line);
  text-align: center;
}
.stat-strip-item:last-child { border-right: none; }
.stat-strip-label { font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ink-soft); }
.stat-strip-value { font-size: 1.6rem; font-weight: 700; font-family: 'Lexend', sans-serif; color: var(--navy-deep); margin-top: .3rem; line-height: 1; }

/* --- Filter toolbar --- */
.filter-toolbar {
  border: 1px solid var(--line);
  border-radius: 8px;
  background-color: #FAFBFC;
  padding: .9rem 1rem;
}
.filter-toolbar .form-label {
  font-size: .68rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin-bottom: .3rem;
}
.filter-toolbar .form-control,
.filter-toolbar .form-select {
  border: 1px solid var(--line);
  border-radius: 6px;
  font-size: .82rem;
  color: var(--ink);
}
.filter-toolbar .form-control:focus,
.filter-toolbar .form-select:focus {
  border-color: var(--indigo);
  box-shadow: 0 0 0 .15rem rgba(59,78,138,.12);
}
.filter-toolbar .input-group-text {
  background-color: #fff;
  border: 1px solid var(--line);
  border-right: none;
  color: var(--ink-soft);
}

.btn-filter-clear {
  background-color: #fff;
  color: var(--ink-soft);
  border: 1px solid var(--line);
  border-radius: 6px;
  font-weight: 600;
  font-size: .82rem;
}
.btn-filter-clear:hover { background-color: var(--navy-soft); color: var(--ink); }

/* --- Table --- */
.log-table-wrap { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background-color: #fff; }
.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .68rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  background-color: #FAFBFC !important;
  padding: .7rem 1rem;
}
.table td { border-bottom: 1px solid var(--line); vertical-align: middle; font-size: .82rem; color: var(--ink); padding: .65rem 1rem; }
.table tbody tr:last-child td { border-bottom: none; }
.table-hover tbody tr:hover { background-color: #F8F9FB; }
.log-row { cursor: pointer; }

/* --- Avatar --- */
.actor-avatar {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: .68rem;
  font-weight: 700;
  flex-shrink: 0;
  background-color: var(--navy-soft);
  color: var(--slate);
}

.role-label {
  font-size: .78rem;
  font-weight: 600;
  color: var(--ink);
}

/* --- Action chip: text color only, no background --- */
.chip {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-size: .72rem;
  font-weight: 700;
  white-space: nowrap;
}
.chip::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: currentColor;
  flex-shrink: 0;
}
.chip-indigo  { color: #3B4E8A; }
.chip-navy    { color: #33495C; }
.chip-warn    { color: #B7791F; }
.chip-success { color: #157A5F; }
.chip-danger  { color: #B4432F; }
.chip-slate   { color: #64748B; }

.log-pagination .page-link { color: var(--indigo-text); border-color: var(--line); font-size: .8rem; border-radius: 6px; }
.log-pagination .page-item { margin-right: 2px; }
.log-pagination .page-item.active .page-link { background-color: var(--indigo); border-color: var(--indigo); color: #fff; }
.log-pagination .page-item.disabled .page-link { color: #adb5bd; }

.empty-state { color: var(--ink-soft); }
.empty-state i { color: #C7D0D6; }

.modal-content { border-radius: 10px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }
.detail-row { padding: .6rem 0; border-bottom: 1px solid var(--line); }
.detail-row:last-child { border-bottom: none; }
.detail-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--ink-soft); }
.detail-value { font-size: .88rem; font-weight: 600; color: var(--ink); margin-top: .15rem; }
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
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="stat-strip mb-3">
        <div class="stat-strip-item">
          <div class="stat-strip-label">Total Logged Actions</div>
          <div class="stat-strip-value"><?= $totalLogs ?></div>
        </div>
        <div class="stat-strip-item">
          <div class="stat-strip-label">Manager</div>
          <div class="stat-strip-value"><?= $roleCounts['Manager'] ?></div>
        </div>
        <div class="stat-strip-item">
          <div class="stat-strip-label">Supervisor</div>
          <div class="stat-strip-value"><?= $roleCounts['Supervisor'] ?></div>
        </div>
        <div class="stat-strip-item">
          <div class="stat-strip-label">Staff</div>
          <div class="stat-strip-value"><?= $roleCounts['Staff'] ?></div>
        </div>
        <div class="stat-strip-item">
          <div class="stat-strip-label">Admin</div>
          <div class="stat-strip-value"><?= $roleCounts['Admin'] ?></div>
        </div>
      </div>

      <form method="GET" id="filterForm" class="filter-toolbar mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label">Search</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
              <input type="text" name="search" id="searchInput" class="form-control" placeholder="Name or record" value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" onchange="this.form.submit()">
              <option value="">All Roles</option>
              <?php foreach ($roleTypes as $rt): ?>
                <option value="<?= $rt ?>" <?= $roleFilter === $rt ? 'selected' : '' ?>><?= $rt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Action</label>
            <select name="action" class="form-select" onchange="this.form.submit()">
              <option value="">All Actions</option>
              <?php foreach ($actionTypes as $at): ?>
                <option value="<?= $at ?>" <?= $actionFilter === $at ? 'selected' : '' ?>><?= $at ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>" onchange="this.form.submit()">
          </div>
        </div>
        <?php if ($hasActiveFilters): ?>
          <div class="mt-2">
            <a href="activity_logs.php" class="btn btn-filter-clear btn-sm px-3"><i class="fa-solid fa-xmark me-1"></i>Clear Filters</a>
          </div>
        <?php endif; ?>
      </form>

      <section class="log-table-wrap">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">User</th>
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
                          <div class="d-md-none role-label"><?= htmlspecialchars($log['role']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                      <span class="role-label"><?= htmlspecialchars($log['role']) ?></span>
                    </td>
                    <td>
                      <span class="chip <?= actionTypeClass($log['action_type']) ?>"><?= htmlspecialchars($log['action_type']) ?></span>
                      <div class="small mt-1" style="color:var(--ink-soft);"><?= htmlspecialchars($log['action_label']) ?></div>
                    </td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($log['target_label'] ?? '—') ?></td>
                    <td class="small" style="color:var(--ink-soft); white-space:nowrap;"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($log['occurred_at']))) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-3" style="border-top:1px solid var(--line);">
          <span class="small" style="color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $totalLogs ?> total</span>
          <nav aria-label="Activity log pagination">
            <ul class="pagination pagination-sm mb-0 log-pagination">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildLogPageUrl($page - 1, $roleFilter, $actionFilter, $searchTerm, $dateFilter) ?>">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= buildLogPageUrl($i, $roleFilter, $actionFilter, $searchTerm, $dateFilter) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= buildLogPageUrl($page + 1, $roleFilter, $actionFilter, $searchTerm, $dateFilter) ?>">Next</a>
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
          <div class="detail-label">User</div>
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

let searchDebounce;
document.getElementById('searchInput').addEventListener('input', function () {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => document.getElementById('filterForm').submit(), 500);
});
</script>

</body>
</html>