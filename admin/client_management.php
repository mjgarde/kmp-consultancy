<?php
session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add_client' || $action === 'edit_client') {

        $clientId      = $_POST['client_id'] ?? null;
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
            if ($action === 'add_client') {
                $stmt = $pdo->prepare(
                    'INSERT INTO clients (company_name, contact_person, email, contact_number, address, industry)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$companyName, $contactPerson, $email, $contactNumber, $address, $industry]);
                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'Client profile added successfully.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE clients SET company_name = ?, contact_person = ?, email = ?, contact_number = ?, address = ?, industry = ? WHERE client_id = ?'
                );
                $stmt->execute([$companyName, $contactPerson, $email, $contactNumber, $address, $industry, $clientId]);
                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'Client profile updated successfully.';
            }
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: client_management.php?tab=clients');
        exit;

    } elseif ($action === 'delete_client') {

        $clientId = $_POST['client_id'] ?? null;
        $stmt = $pdo->prepare('DELETE FROM clients WHERE client_id = ?');
        $stmt->execute([$clientId]);
        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'Client profile deleted successfully.';
        header('Location: client_management.php?tab=clients');
        exit;

    } elseif ($action === 'add_request') {

        $clientId       = $_POST['client_id'] ?? '';
        $requestTitle   = trim($_POST['request_title'] ?? '');
        $requestDetails = trim($_POST['request_details'] ?? '');

        $errors = [];
        if ($clientId === '') $errors[] = 'Please select a client.';
        if ($requestTitle === '') $errors[] = 'Request title is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO service_requests (client_id, request_title, request_details, status) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$clientId, $requestTitle, $requestDetails, 'New']);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Service request recorded successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: client_management.php?tab=requests');
        exit;

    } elseif ($action === 'update_request') {

        $requestId  = $_POST['request_id'] ?? null;
        $status     = $_POST['status'] ?? 'New';
        $assignedTo = $_POST['assigned_to'] ?? null;
        $assignedTo = $assignedTo === '' ? null : $assignedTo;

        $stmt = $pdo->prepare('UPDATE service_requests SET status = ?, assigned_to = ? WHERE request_id = ?');
        $stmt->execute([$status, $assignedTo, $requestId]);

        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'Service request updated successfully.';
        header('Location: client_management.php?tab=requests');
        exit;

    } elseif ($action === 'delete_request') {

        $requestId = $_POST['request_id'] ?? null;
        $stmt = $pdo->prepare('DELETE FROM service_requests WHERE request_id = ?');
        $stmt->execute([$requestId]);
        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'Service request deleted successfully.';
        header('Location: client_management.php?tab=requests');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$activeTab   = $_GET['tab'] ?? 'clients';
$sortOrder   = $_GET['sort'] ?? 'newest';
$sortSql     = $sortOrder === 'oldest' ? 'ASC' : 'DESC';
$searchTerm  = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$perPage     = 8;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $perPage;

$totalClientsAll = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
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

$staffList = $pdo->query("SELECT user_id, firstname, lastname, role FROM users WHERE status = 'Active' ORDER BY firstname")->fetchAll();
$allClientsForModal = $pdo->query('SELECT client_id, company_name FROM clients ORDER BY company_name ASC')->fetchAll();

$newRequests        = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'New'")->fetchColumn();
$inProgressRequests = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'In Progress'")->fetchColumn();
$completedRequests  = (int) $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'Completed'")->fetchColumn();

$reportClients  = $pdo->query('SELECT * FROM clients ORDER BY company_name ASC')->fetchAll();
$reportRequests = $pdo->query('SELECT client_id, status FROM service_requests')->fetchAll();

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

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-title, h1, h2, h3 {
  font-family: 'Lexend', 'Inter', sans-serif;
}
.form-control:focus, .form-select:focus, .form-control:hover, .form-select:hover {
  border-color:#2F4858;
  box-shadow:0 0 0 .2rem rgba(47,72,88,.15);
  outline:none;
}
.client-management-pagination .page-link {
  color:#2F4858;
  border-color:#e5e7eb;
}
.client-management-pagination .page-item.active .page-link {
  background-color:#2F4858;
  border-color:#2F4858;
  color:#fff;
}
.client-management-pagination .page-item.disabled .page-link {
  color:#adb5bd;
}

.client-stat-body {
  padding:.6rem;
}
.client-stat-icon {
  width:34px;
  height:34px;
  font-size:.8rem;
}
.client-stat-label {
  font-size:.62rem;
}
.client-stat-value {
  font-size:1rem;
}

.client-management-tabs .nav-link {
  font-size:.72rem;
  padding:.4rem .55rem;
}

@media (min-width:576px) {
  .client-stat-body { padding:.85rem; }
  .client-stat-icon { width:40px; height:40px; font-size:.95rem; }
  .client-stat-label { font-size:.72rem; }
  .client-stat-value { font-size:1.25rem; }
  .client-management-tabs .nav-link { font-size:.85rem; padding:.5rem .9rem; }
}

.client-management-toolbar .btn {
  font-size:.75rem;
  padding:.4rem .6rem;
}
.client-management-table .btn-sm {
  font-size:.68rem;
  padding:.25rem .45rem;
}
.client-management-table td, .client-management-table th {
  font-size:.72rem;
}

@media (min-width:576px) {
  .client-management-toolbar .btn { font-size:.85rem; padding:.45rem .8rem; }
  .client-management-table .btn-sm { font-size:.75rem; padding:.3rem .55rem; }
  .client-management-table td, .client-management-table th { font-size:.8rem; }
}

@media (min-width:768px) {
  .client-management-toolbar .btn { font-size:.9rem; padding:.5rem 1rem; }
  .client-management-table .btn-sm { font-size:.8rem; padding:.35rem .65rem; }
  .client-management-table td, .client-management-table th { font-size:.85rem; }
}
</style>
</head>
<body class="bg-light">

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white border-bottom d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Client Management</h1>
          <p class="dashboard-subtitle text-secondary small mb-0 d-none d-sm-block">Manage client profiles, service requests, and reports.</p>
        </div>
      </div>
      <div class="dashboard-topbar-actions d-flex align-items-center gap-3 gap-md-4">
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="client-management-summary row g-2 g-md-3 mb-3">
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body client-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="client-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#2F485815;">
                <i class="fa-solid fa-building" style="color:#2F4858;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="client-stat-label text-secondary text-truncate">Total Clients</div>
                <div class="client-stat-value fw-bold" style="color:#2F4858;"><?= $totalClientsAll ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body client-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="client-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#E0C06A15;">
                <i class="fa-solid fa-clipboard-list" style="color:#B8912E;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="client-stat-label text-secondary text-truncate">Total Requests</div>
                <div class="client-stat-value fw-bold" style="color:#B8912E;"><?= $totalRequestsAll ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body client-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="client-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#E89C5A15;">
                <i class="fa-solid fa-spinner" style="color:#C9762F;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="client-stat-label text-secondary text-truncate">In Progress</div>
                <div class="client-stat-value fw-bold" style="color:#C9762F;"><?= $inProgressRequests ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-3">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body client-stat-body d-flex align-items-center gap-2 gap-md-3">
              <span class="client-stat-icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="background-color:#3AA39415;">
                <i class="fa-solid fa-circle-check" style="color:#2C7C71;"></i>
              </span>
              <div class="overflow-hidden">
                <div class="client-stat-label text-secondary text-truncate">Completed</div>
                <div class="client-stat-value fw-bold" style="color:#2C7C71;"><?= $completedRequests ?></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <nav class="client-management-tabs mb-3">
        <ul class="nav gap-2">
          <li class="nav-item">
            <a href="?tab=clients" class="nav-link rounded-3 fw-semibold" style="background-color:#2F4858; color:#fff; <?= $activeTab === 'clients' ? 'box-shadow:0 2px 6px rgba(47,72,88,.4);' : '' ?>">
              <i class="fa-solid fa-building me-1"></i> Clients
            </a>
          </li>
          <li class="nav-item">
            <a href="?tab=requests" class="nav-link rounded-3 fw-semibold" style="background-color:#E89C5A; color:#fff; <?= $activeTab === 'requests' ? 'box-shadow:0 2px 6px rgba(232,156,90,.5);' : '' ?>">
              <i class="fa-solid fa-clipboard-list me-1"></i> Service Requests
            </a>
          </li>
          <li class="nav-item">
            <a href="?tab=reports" class="nav-link rounded-3 fw-semibold" style="background-color:#3AA394; color:#fff; <?= $activeTab === 'reports' ? 'box-shadow:0 2px 6px rgba(58,163,148,.5);' : '' ?>">
              <i class="fa-solid fa-chart-simple me-1"></i> Reports
            </a>
          </li>
        </ul>
      </nav>

      <?php if ($activeTab === 'clients'): ?>

        <section class="client-management-toolbar card border-0 shadow-sm mb-3">
          <div class="card-body p-2 p-md-3">
            <form class="row g-2 align-items-center" method="GET">
              <input type="hidden" name="tab" value="clients">
              <div class="col-12 col-md-5">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                  <input type="text" name="search" class="form-control" placeholder="Search by company or contact person" value="<?= htmlspecialchars($searchTerm) ?>">
                </div>
              </div>
              <div class="col-6 col-md-3">
                <select name="sort" class="form-select" onchange="this.form.submit()">
                  <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest to Oldest</option>
                  <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest to Newest</option>
                </select>
              </div>
              <div class="col-6 col-md-2">
                <button type="submit" class="btn w-100" style="background-color:#E0C06A; color:#2F4858;">
                  <i class="fa-solid fa-filter"></i> <span class="d-none d-sm-inline">Filter</span>
                </button>
              </div>
              <div class="col-12 col-md-2 text-md-end">
                <button type="button" class="btn w-100" style="background-color:#2F4858; color:#fff;" data-bs-toggle="modal" data-bs-target="#addClientModal">
                  <i class="fa-solid fa-plus"></i> Add Client
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="client-management-table card border-0 shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col" class="small text-uppercase text-secondary">Company</th>
                  <th scope="col" class="small text-uppercase text-secondary d-none d-md-table-cell">Contact Person</th>
                  <th scope="col" class="small text-uppercase text-secondary d-none d-lg-table-cell">Email</th>
                  <th scope="col" class="small text-uppercase text-secondary d-none d-lg-table-cell">Contact Number</th>
                  <th scope="col" class="small text-uppercase text-secondary text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($clients)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-secondary py-5">
                      <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                      No clients found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($clients as $client): ?>
                    <tr class="client-management-row" role="button" style="cursor:pointer;"
                      data-bs-toggle="modal" data-bs-target="#viewClientModal"
                      data-company="<?= htmlspecialchars($client['company_name']) ?>"
                      data-contact="<?= htmlspecialchars($client['contact_person']) ?>"
                      data-email="<?= htmlspecialchars($client['email']) ?>"
                      data-number="<?= htmlspecialchars($client['contact_number']) ?>"
                      data-address="<?= htmlspecialchars($client['address']) ?>"
                      data-industry="<?= htmlspecialchars($client['industry'] ?? '') ?>">
                      <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($client['company_name']) ?></div>
                        <div class="text-secondary d-md-none" style="font-size:.72rem;"><?= htmlspecialchars($client['contact_person']) ?></div>
                      </td>
                      <td class="text-secondary small d-none d-md-table-cell"><?= htmlspecialchars($client['contact_person']) ?></td>
                      <td class="text-secondary small d-none d-lg-table-cell"><?= htmlspecialchars($client['email']) ?></td>
                      <td class="text-secondary small d-none d-lg-table-cell"><?= htmlspecialchars($client['contact_number']) ?></td>
                      <td class="text-end" onclick="event.stopPropagation();">
                        <button type="button" class="btn btn-sm" style="background-color:#2F4858; color:#fff;" title="Edit"
                          data-bs-toggle="modal" data-bs-target="#editClientModal"
                          data-id="<?= $client['client_id'] ?>"
                          data-company="<?= htmlspecialchars($client['company_name']) ?>"
                          data-contact="<?= htmlspecialchars($client['contact_person']) ?>"
                          data-email="<?= htmlspecialchars($client['email']) ?>"
                          data-number="<?= htmlspecialchars($client['contact_number']) ?>"
                          data-address="<?= htmlspecialchars($client['address']) ?>"
                          data-industry="<?= htmlspecialchars($client['industry'] ?? '') ?>">
                          <i class="fa-regular fa-pen-to-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm" style="background-color:#DF6E4F; color:#fff;" title="Delete"
                          data-bs-toggle="modal" data-bs-target="#deleteClientModal"
                          data-id="<?= $client['client_id'] ?>"
                          data-name="<?= htmlspecialchars($client['company_name']) ?>">
                          <i class="fa-regular fa-trash-can"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
          <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
            <span class="text-secondary small">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredClientCount ?> total</span>
            <nav aria-label="Clients pagination">
              <ul class="pagination pagination-sm mb-0 client-management-pagination">
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

        <section class="client-management-toolbar card border-0 shadow-sm mb-3">
          <div class="card-body p-2 p-md-3">
            <form class="row g-2 align-items-center" method="GET">
              <input type="hidden" name="tab" value="requests">
              <div class="col-12 col-md-4">
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
                <button type="button" class="btn w-100" style="background-color:#2F4858; color:#fff;" data-bs-toggle="modal" data-bs-target="#addRequestModal">
                  <i class="fa-solid fa-plus"></i> Add Request
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="client-management-table card border-0 shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col" class="small text-uppercase text-secondary">Request</th>
                  <th scope="col" class="small text-uppercase text-secondary d-none d-md-table-cell">Client</th>
                  <th scope="col" class="small text-uppercase text-secondary d-none d-lg-table-cell">Assigned To</th>
                  <th scope="col" class="small text-uppercase text-secondary">Status</th>
                  <th scope="col" class="small text-uppercase text-secondary text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($requests)): ?>
                  <tr>
                    <td colspan="5" class="text-center text-secondary py-5">
                      <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                      No service requests found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($requests as $request): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($request['request_title']) ?></div>
                        <div class="text-secondary d-md-none" style="font-size:.72rem;"><?= htmlspecialchars($request['company_name']) ?></div>
                      </td>
                      <td class="text-secondary small d-none d-md-table-cell"><?= htmlspecialchars($request['company_name']) ?></td>
                      <td class="text-secondary small d-none d-lg-table-cell">
                        <?= $request['firstname'] ? htmlspecialchars($request['firstname'] . ' ' . $request['lastname']) : '—' ?>
                      </td>
                      <td>
                        <span class="badge rounded-pill" style="background-color:<?= statusBadgeColor($request['status']) ?>; color:#fff;"><?= htmlspecialchars($request['status']) ?></span>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn btn-sm" style="background-color:#2F4858; color:#fff;" title="Manage"
                          data-bs-toggle="modal" data-bs-target="#manageRequestModal"
                          data-id="<?= $request['request_id'] ?>"
                          data-title="<?= htmlspecialchars($request['request_title']) ?>"
                          data-details="<?= htmlspecialchars($request['request_details'] ?? '') ?>"
                          data-status="<?= htmlspecialchars($request['status']) ?>"
                          data-assigned="<?= $request['assigned_to'] ?? '' ?>">
                          <i class="fa-regular fa-pen-to-square"></i>
                        </button>
                        <button type="button" class="btn btn-sm" style="background-color:#DF6E4F; color:#fff;" title="Delete"
                          data-bs-toggle="modal" data-bs-target="#deleteRequestModal"
                          data-id="<?= $request['request_id'] ?>"
                          data-title="<?= htmlspecialchars($request['request_title']) ?>">
                          <i class="fa-regular fa-trash-can"></i>
                        </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($totalPages > 1): ?>
          <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
            <span class="text-secondary small">Page <?= $page ?> of <?= $totalPages ?> &middot; <?= $filteredRequestCount ?> total</span>
            <nav aria-label="Requests pagination">
              <ul class="pagination pagination-sm mb-0 client-management-pagination">
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

        <section class="client-management-reports row g-3 mb-3">
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="text-secondary small mb-1">Total Clients</div>
                <div class="fs-4 fw-bold"><?= $totalClientsAll ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="text-secondary small mb-1">Total Requests</div>
                <div class="fs-4 fw-bold"><?= $totalRequestsAll ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="text-secondary small mb-1">In Progress</div>
                <div class="fs-4 fw-bold"><?= $inProgressRequests ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-3">
                <div class="text-secondary small mb-1">Completed</div>
                <div class="fs-4 fw-bold"><?= $completedRequests ?></div>
              </div>
            </div>
          </div>
        </section>

        <section class="client-management-table card border-0 shadow-sm">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th scope="col" class="small text-uppercase text-secondary">Client</th>
                  <th scope="col" class="small text-uppercase text-secondary">Total Requests</th>
                  <th scope="col" class="small text-uppercase text-secondary">Completed</th>
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
                    <td class="small text-secondary"><?= count($clientRequests) ?></td>
                    <td class="small text-secondary"><?= $clientCompleted ?></td>
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
              <label class="form-label fw-semibold">Company Name</label>
              <input type="text" name="company_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact Person</label>
              <input type="text" name="contact_person" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact Number</label>
              <input type="tel" name="contact_number" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Industry</label>
              <input type="text" name="industry" class="form-control" placeholder="Optional">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Address</label>
              <input type="text" name="address" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn" style="background-color:#2F4858; color:#fff;">Save Client</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="edit_client">
        <input type="hidden" name="client_id" id="edit_client_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Edit Client</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Company Name</label>
              <input type="text" name="company_name" id="edit_company_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact Person</label>
              <input type="text" name="contact_person" id="edit_contact_person" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact Number</label>
              <input type="tel" name="contact_number" id="edit_contact_number" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Industry</label>
              <input type="text" name="industry" id="edit_industry" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Address</label>
              <input type="text" name="address" id="edit_address" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn" style="background-color:#2F4858; color:#fff;">Update Client</button>
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
            <div class="text-secondary small">Company Name</div>
            <div class="fw-semibold" id="view_company"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Contact Person</div>
            <div class="fw-semibold" id="view_contact"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Email Address</div>
            <div class="fw-semibold" id="view_email"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Contact Number</div>
            <div class="fw-semibold" id="view_number"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Industry</div>
            <div class="fw-semibold" id="view_industry"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Address</div>
            <div class="fw-semibold" id="view_address"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete_client">
        <input type="hidden" name="client_id" id="delete_client_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Delete Client</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="delete_client_name"></strong>? This will also remove all related service requests.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Client</button>
        </div>
      </form>
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
              <label class="form-label fw-semibold">Client</label>
              <select name="client_id" class="form-select" required>
                <option value="" selected disabled>Select client</option>
                <?php foreach ($allClientsForModal as $client): ?>
                  <option value="<?= $client['client_id'] ?>"><?= htmlspecialchars($client['company_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Request Title</label>
              <input type="text" name="request_title" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Details</label>
              <textarea name="request_details" class="form-control" rows="4"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn" style="background-color:#2F4858; color:#fff;">Save Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="manageRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="update_request">
        <input type="hidden" name="request_id" id="manage_request_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold" id="manage_request_title">Manage Request</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary small" id="manage_request_details"></p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Status</label>
              <select name="status" id="manage_status" class="form-select">
                <option value="New">New</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Assign To</label>
              <select name="assigned_to" id="manage_assigned" class="form-select">
                <option value="">Unassigned</option>
                <?php foreach ($staffList as $staffMember): ?>
                  <option value="<?= $staffMember['user_id'] ?>"><?= htmlspecialchars($staffMember['firstname'] . ' ' . $staffMember['lastname'] . ' (' . $staffMember['role'] . ')') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn" style="background-color:#2F4858; color:#fff;">Update Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete_request">
        <input type="hidden" name="request_id" id="delete_request_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Delete Request</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="delete_request_title"></strong>?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editClientModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('edit_client_id').value = btn.dataset.id;
  document.getElementById('edit_company_name').value = btn.dataset.company;
  document.getElementById('edit_contact_person').value = btn.dataset.contact;
  document.getElementById('edit_email').value = btn.dataset.email;
  document.getElementById('edit_contact_number').value = btn.dataset.number;
  document.getElementById('edit_industry').value = btn.dataset.industry;
  document.getElementById('edit_address').value = btn.dataset.address;
});

document.getElementById('viewClientModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('view_company').textContent = btn.dataset.company;
  document.getElementById('view_contact').textContent = btn.dataset.contact;
  document.getElementById('view_email').textContent = btn.dataset.email;
  document.getElementById('view_number').textContent = btn.dataset.number;
  document.getElementById('view_industry').textContent = btn.dataset.industry || '-';
  document.getElementById('view_address').textContent = btn.dataset.address;
});

document.getElementById('deleteClientModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('delete_client_id').value = btn.dataset.id;
  document.getElementById('delete_client_name').textContent = btn.dataset.name;
});

document.getElementById('manageRequestModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('manage_request_id').value = btn.dataset.id;
  document.getElementById('manage_request_title').textContent = btn.dataset.title;
  document.getElementById('manage_request_details').textContent = btn.dataset.details;
  document.getElementById('manage_status').value = btn.dataset.status;
  document.getElementById('manage_assigned').value = btn.dataset.assigned;
});

document.getElementById('deleteRequestModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('delete_request_id').value = btn.dataset.id;
  document.getElementById('delete_request_title').textContent = btn.dataset.title;
});

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>