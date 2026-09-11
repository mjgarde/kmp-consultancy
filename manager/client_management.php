<?php
session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['manager_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add_client') {

        $companyName   = trim($_POST['company_name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $industry      = trim($_POST['industry'] ?? '');

        $errors = [];

        if ($companyName === '') $errors[] = 'Company name is required.';
        if ($contactPerson === '') $errors[] = 'Contact person is required.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if ($contactNumber === '') {
            $errors[] = 'Contact number is required.';
        } elseif (!ctype_digit($contactNumber)) {
            $errors[] = 'Contact number must contain numbers only.';
        }
        if ($address === '') $errors[] = 'Address is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO clients (company_name, contact_person, email, contact_number, address, industry)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$companyName, $contactPerson, $email, $contactNumber, $address, $industry]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Client profile added successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: client_management.php?tab=clients');
        exit;

    } elseif ($action === 'add_request') {

        $clientId       = $_POST['client_id'] ?? '';
        $requestTitle   = trim($_POST['request_title'] ?? '');
        $requestDetails = trim($_POST['request_details'] ?? '');
        $requiredSkill  = trim($_POST['required_skill'] ?? '');

        $errors = [];
        if ($clientId === '') $errors[] = 'Please select a client.';
        if ($requestTitle === '') $errors[] = 'Request title is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO service_requests (client_id, request_title, request_details, required_skill, status) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$clientId, $requestTitle, $requestDetails, $requiredSkill !== '' ? $requiredSkill : null, 'New']);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Service request recorded successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: client_management.php?tab=requests');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$activeTab    = $_GET['tab'] ?? 'clients';
$sortOrder    = $_GET['sort'] ?? 'newest';
$sortSql      = $sortOrder === 'oldest' ? 'ASC' : 'DESC';
$searchTerm   = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$perPage      = 8;
$page         = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($page - 1) * $perPage;

$totalClientsAll  = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$totalRequestsAll = (int) $pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn();

if ($activeTab === 'clients') {

    $clientQuery = 'SELECT * FROM clients WHERE 1=1';
    $clientParams = [];

    if ($searchTerm !== '') {
        $clientQuery .= ' AND (company_name LIKE ? OR contact_person LIKE ?)';
        $like = '%' . $searchTerm . '%';
        $clientParams[] = $like;
        $clientParams[] = $like;
    }

    $countStmt = $pdo->prepare(str_replace('SELECT *', 'SELECT COUNT(*)', $clientQuery));
    $countStmt->execute($clientParams);
    $filteredClientCount = (int) $countStmt->fetchColumn();

    $clientQuery .= " ORDER BY created_at $sortSql LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($clientQuery);
    $stmt->execute($clientParams);
    $clients = $stmt->fetchAll();

    $totalPages = max(1, ceil($filteredClientCount / $perPage));

} else {
    $clients = $pdo->query('SELECT * FROM clients ORDER BY company_name ASC')->fetchAll();
}

if ($activeTab === 'requests') {

    $requestQuery = 'SELECT sr.*, c.company_name, u.firstname, u.lastname
                      FROM service_requests sr
                      JOIN clients c ON c.client_id = sr.client_id
                      LEFT JOIN users u ON u.user_id = sr.assigned_to
                      WHERE 1=1';
    $requestParams = [];

    if ($searchTerm !== '') {
        $requestQuery .= ' AND (sr.request_title LIKE ? OR c.company_name LIKE ?)';
        $like = '%' . $searchTerm . '%';
        $requestParams[] = $like;
        $requestParams[] = $like;
    }

    if (in_array($statusFilter, ['New', 'In Progress', 'Completed', 'Cancelled'])) {
        $requestQuery .= ' AND sr.status = ?';
        $requestParams[] = $statusFilter;
    }

    $countQuery = str_replace('SELECT sr.*, c.company_name, u.firstname, u.lastname', 'SELECT COUNT(*)', $requestQuery);
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($requestParams);
    $filteredRequestCount = (int) $countStmt->fetchColumn();

    $requestQuery .= " ORDER BY sr.created_at $sortSql LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($requestQuery);
    $stmt->execute($requestParams);
    $requests = $stmt->fetchAll();

    $totalPages = max(1, ceil($filteredRequestCount / $perPage));

} else {
    $requests = $pdo->query(
        'SELECT sr.*, c.company_name, u.firstname, u.lastname
         FROM service_requests sr
         JOIN clients c ON c.client_id = sr.client_id
         LEFT JOIN users u ON u.user_id = sr.assigned_to
         ORDER BY sr.created_at DESC'
    )->fetchAll();
}

$allClientsForModal = $pdo->query('SELECT client_id, company_name FROM clients ORDER BY company_name ASC')->fetchAll();

$existingSkillsStmt = $pdo->query('SELECT DISTINCT skill_name FROM staff_skills ORDER BY skill_name ASC');
$existingSkills = $existingSkillsStmt->fetchAll(PDO::FETCH_COLUMN);

$newRequests        = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'New'")->fetchColumn();
$inProgressRequests = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'In Progress'")->fetchColumn();
$completedRequests  = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Completed'")->fetchColumn();

$reportClients  = $pdo->query('SELECT * FROM clients ORDER BY company_name ASC')->fetchAll();
$reportRequests = $pdo->query('SELECT client_id, status FROM service_requests')->fetchAll();

function statusBadgeClass(string $status): string
{
    return match ($status) {
        'New' => 'status-draft',
        'In Progress' => 'status-warn',
        'Completed' => 'status-approved',
        'Cancelled' => 'status-rejected',
        default => 'status-draft',
    };
}

function buildPageUrl(int $targetPage, string $activeTab, string $searchTerm, string $sortOrder, string $statusFilter): string
{
    $params = [
        'tab' => $activeTab,
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
<title>Client Management</title>
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

.card { border-radius: 12px; border: 1px solid var(--line) !important; box-shadow: none !important; }

.form-control:focus, .form-select:focus {
  border-color: var(--indigo);
  box-shadow: 0 0 0 .2rem rgba(59,78,138,.13);
  outline: none;
}
.form-control:hover, .form-select:hover { border-color: #C6CCD8; }

.form-label { font-size: .8rem; font-weight: 700; color: var(--slate); text-transform: uppercase; letter-spacing: .02em; }

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

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: .85rem 1rem;
  display: flex;
  align-items: center;
  gap: .7rem;
  height: 100%;
}
.stat-icon {
  width: 38px;
  height: 38px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: .95rem;
}
.stat-label { font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); }
.stat-value { font-family: 'Lexend', sans-serif; font-size: 1.25rem; font-weight: 700; margin-top: .1rem; }

.client-management-tabs {
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--line);
}
.client-management-tabs a {
  border: none;
  background: none;
  padding: .75rem .3rem;
  font-size: .85rem;
  font-weight: 600;
  color: var(--ink-soft);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: .4rem;
}
.client-management-tabs a:hover { color: var(--navy-deep); }
.client-management-tabs a.active { color: var(--indigo-text); border-bottom-color: var(--indigo); }

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
  border: 1px solid transparent;
}
.status-draft { background-color: var(--navy-soft); color: var(--slate); border-color: var(--line); }
.status-warn { background-color: var(--warn-soft); color: var(--warn-text); border-color: var(--warn-border); }
.status-approved { background-color: var(--success-soft); color: var(--success-text); border-color: var(--success-border); }
.status-rejected { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }

.skill-tag-static {
  background-color: var(--indigo-soft);
  color: var(--indigo-text);
  font-size: .68rem;
  font-weight: 700;
  padding: .2rem .55rem;
  border-radius: 999px;
  display: inline-block;
}

.client-management-row { cursor: pointer; }

.pagination .page-link { color: var(--indigo-text); border-color: var(--line); }
.pagination .page-item.active .page-link { background-color: var(--indigo); border-color: var(--indigo); color: #fff; }
.pagination .page-item.disabled .page-link { color: #adb5bd; }

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Client Management</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Manage client profiles and record service requests.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--indigo-soft);">
              <i class="fa-solid fa-building" style="color:var(--indigo-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Total Clients</div>
              <div class="stat-value" style="color:var(--indigo-text);"><?= $totalClientsAll ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--warn-soft);">
              <i class="fa-solid fa-clipboard-list" style="color:var(--warn-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Total Requests</div>
              <div class="stat-value" style="color:var(--warn-text);"><?= $totalRequestsAll ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--navy-soft);">
              <i class="fa-solid fa-spinner" style="color:var(--slate);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">In Progress</div>
              <div class="stat-value" style="color:var(--slate);"><?= $inProgressRequests ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--success-soft);">
              <i class="fa-solid fa-circle-check" style="color:var(--success-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Completed</div>
              <div class="stat-value" style="color:var(--success-text);"><?= $completedRequests ?></div>
            </div>
          </div>
        </div>
      </section>

      <nav class="client-management-tabs mb-3">
        <a href="?tab=clients" class="<?= $activeTab === 'clients' ? 'active' : '' ?>">
          <i class="fa-solid fa-building"></i> Clients
        </a>
        <a href="?tab=requests" class="<?= $activeTab === 'requests' ? 'active' : '' ?>">
          <i class="fa-solid fa-clipboard-list"></i> Service Requests
        </a>
        <a href="?tab=reports" class="<?= $activeTab === 'reports' ? 'active' : '' ?>">
          <i class="fa-solid fa-chart-simple"></i> Reports
        </a>
      </nav>

      <?php if ($activeTab === 'clients'): ?>

        <section class="card mb-3">
          <div class="card-body p-2 p-md-3">
            <form class="row g-2 align-items-center" method="GET">
              <input type="hidden" name="tab" value="clients">
              <div class="col-12 col-md-6">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
                  <input type="text" name="search" class="form-control" placeholder="Search by company or contact person" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
              </div>
              <div class="col-6 col-md-3">
                <select name="sort" class="form-select" onchange="this.form.submit()">
                  <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
                  <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
                </select>
              </div>
              <div class="col-6 col-md-3 text-md-end">
                <button type="button" class="btn btn-teal-solid w-100" data-bs-toggle="modal" data-bs-target="#addClientModal">
                  <i class="fa-solid fa-plus"></i> Add Client
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="card">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col">Company</th>
                  <th scope="col" class="d-none d-md-table-cell">Contact Person</th>
                  <th scope="col" class="d-none d-lg-table-cell">Email</th>
                  <th scope="col" class="d-none d-lg-table-cell">Contact Number</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($clients)): ?>
                  <tr>
                    <td colspan="4" class="text-center py-5" style="color:var(--ink-soft);">
                      <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                      No clients found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($clients as $client): ?>
                    <tr class="client-management-row"
                      data-bs-toggle="modal" data-bs-target="#viewClientModal"
                      data-company="<?= htmlspecialchars($client['company_name']) ?>"
                      data-contact="<?= htmlspecialchars($client['contact_person']) ?>"
                      data-email="<?= htmlspecialchars($client['email']) ?>"
                      data-number="<?= htmlspecialchars($client['contact_number']) ?>"
                      data-address="<?= htmlspecialchars($client['address']) ?>"
                      data-industry="<?= htmlspecialchars($client['industry'] ?? '') ?>">
                      <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($client['company_name']) ?></div>
                        <div class="d-md-none" style="font-size:.72rem; color:var(--ink-soft);"><?= htmlspecialchars($client['contact_person']) ?></div>
                      </td>
                      <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($client['contact_person']) ?></td>
                      <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($client['email']) ?></td>
                      <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($client['contact_number']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
          <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3" style="border-top:1px solid var(--line);">
            <span class="small" style="color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredClientCount ?> total</span>
            <nav aria-label="Clients pagination">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildPageUrl($page - 1, 'clients', $searchTerm, $sortOrder, '') ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= buildPageUrl($i, 'clients', $searchTerm, $sortOrder, '') ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildPageUrl($page + 1, 'clients', $searchTerm, $sortOrder, '') ?>">Next</a>
                </li>
              </ul>
            </nav>
          </div>
          <?php endif; ?>
        </section>

      <?php elseif ($activeTab === 'requests'): ?>

        <section class="card mb-3">
          <div class="card-body p-2 p-md-3">
            <form class="row g-2 align-items-center" method="GET">
              <input type="hidden" name="tab" value="requests">
              <div class="col-12 col-md-4">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass" style="color:var(--ink-soft);"></i></span>
                  <input type="text" name="search" class="form-control" placeholder="Search request or client" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
              </div>
              <div class="col-6 col-md-3">
                <select name="status" class="form-select" onchange="this.form.submit()">
                  <option value="">All Status</option>
                  <option value="New" <?= $statusFilter === 'New' ? 'selected' : '' ?>>New</option>
                  <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                  <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                  <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
              </div>
              <div class="col-6 col-md-3">
                <select name="sort" class="form-select" onchange="this.form.submit()">
                  <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
                  <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
                </select>
              </div>
              <div class="col-12 col-md-2 text-md-end">
                <button type="button" class="btn btn-teal-solid w-100" data-bs-toggle="modal" data-bs-target="#addRequestModal">
                  <i class="fa-solid fa-plus"></i> Add Request
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="card">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col">Request</th>
                  <th scope="col" class="d-none d-md-table-cell">Client</th>
                  <th scope="col" class="d-none d-lg-table-cell">Required Skill</th>
                  <th scope="col" class="d-none d-lg-table-cell">Assigned To</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($requests)): ?>
                  <tr>
                    <td colspan="5" class="text-center py-5" style="color:var(--ink-soft);">
                      <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                      No service requests found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($requests as $request): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($request['request_title']) ?></div>
                        <div class="d-md-none" style="font-size:.72rem; color:var(--ink-soft);"><?= htmlspecialchars($request['company_name']) ?></div>
                      </td>
                      <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($request['company_name']) ?></td>
                      <td class="d-none d-lg-table-cell">
                        <?php if (!empty($request['required_skill'])): ?>
                          <span class="skill-tag-static"><?= htmlspecialchars($request['required_skill']) ?></span>
                        <?php else: ?>
                          <span class="small" style="color:var(--ink-soft);">&mdash;</span>
                        <?php endif; ?>
                      </td>
                      <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);">
                        <?= $request['firstname'] ? htmlspecialchars($request['firstname'] . ' ' . $request['lastname']) : '&mdash;' ?>
                      </td>
                      <td>
                        <span class="status-pill <?= statusBadgeClass($request['status']) ?>"><?= htmlspecialchars($request['status']) ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
          <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3" style="border-top:1px solid var(--line);">
            <span class="small" style="color:var(--ink-soft);">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredRequestCount ?> total</span>
            <nav aria-label="Requests pagination">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildPageUrl($page - 1, 'requests', $searchTerm, $sortOrder, $statusFilter) ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= buildPageUrl($i, 'requests', $searchTerm, $sortOrder, $statusFilter) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildPageUrl($page + 1, 'requests', $searchTerm, $sortOrder, $statusFilter) ?>">Next</a>
                </li>
              </ul>
            </nav>
          </div>
          <?php endif; ?>
        </section>

      <?php else: ?>

        <section class="card">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col">Client</th>
                  <th scope="col">Total Requests</th>
                  <th scope="col">Completed</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($reportClients as $client): ?>
                  <?php
                    $clientRequests = array_filter($reportRequests, fn($r) => $r['client_id'] == $client['client_id']);
                    $clientCompleted = count(array_filter($clientRequests, fn($r) => $r['status'] === 'Completed'));
                  ?>
                  <tr>
                    <td class="fw-semibold small"><?= htmlspecialchars($client['company_name']) ?></td>
                    <td class="small" style="color:var(--ink-soft);"><?= count($clientRequests) ?></td>
                    <td class="small" style="color:var(--ink-soft);"><?= $clientCompleted ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

      <?php endif; ?>

    </main>

  </div>

</div>

<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="add_client">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Add Client</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Company Name</label>
              <input type="text" name="company_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Person</label>
              <input type="text" name="contact_person" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Number</label>
              <input type="tel" name="contact_number" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Industry</label>
              <input type="text" name="industry" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Save Client</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold">Client Details</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="small" style="color:var(--ink-soft);">Company Name</div>
            <div class="fw-semibold" id="view_company"></div>
          </div>
          <div class="col-md-6">
            <div class="small" style="color:var(--ink-soft);">Contact Person</div>
            <div class="fw-semibold" id="view_contact"></div>
          </div>
          <div class="col-md-6">
            <div class="small" style="color:var(--ink-soft);">Email Address</div>
            <div class="fw-semibold" id="view_email"></div>
          </div>
          <div class="col-md-6">
            <div class="small" style="color:var(--ink-soft);">Contact Number</div>
            <div class="fw-semibold" id="view_number"></div>
          </div>
          <div class="col-md-6">
            <div class="small" style="color:var(--ink-soft);">Industry</div>
            <div class="fw-semibold" id="view_industry"></div>
          </div>
          <div class="col-md-6">
            <div class="small" style="color:var(--ink-soft);">Address</div>
            <div class="fw-semibold" id="view_address"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="add_request">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Add Service Request</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Client</label>
              <select name="client_id" class="form-select" required>
                <option value="" selected disabled>Select client</option>
                <?php foreach ($allClientsForModal as $client): ?>
                  <option value="<?= $client['client_id'] ?>"><?= htmlspecialchars($client['company_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Request Title</label>
              <input type="text" name="request_title" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Required Skill</label>
              <select name="required_skill" class="form-select">
                <option value="">Not specified / any skill</option>
                <?php foreach ($existingSkills as $existingSkill): ?>
                  <option value="<?= htmlspecialchars($existingSkill) ?>"><?= htmlspecialchars($existingSkill) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">This determines which staff will be recommended in Resource Matching.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Details</label>
              <textarea name="request_details" class="form-control" rows="4"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-teal-solid">Save Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('viewClientModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('view_company').textContent = btn.dataset.company;
  document.getElementById('view_contact').textContent = btn.dataset.contact;
  document.getElementById('view_email').textContent = btn.dataset.email;
  document.getElementById('view_number').textContent = btn.dataset.number;
  document.getElementById('view_industry').textContent = btn.dataset.industry || '-';
  document.getElementById('view_address').textContent = btn.dataset.address;
});

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>