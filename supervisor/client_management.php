<?php
session_name('SUPERVISOR_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();

function getClientFilesFolderId(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        "SELECT document_id FROM knowledge_documents
         WHERE item_type = 'folder' AND parent_id IS NULL AND title = 'Client Files'
         ORDER BY document_id ASC LIMIT 1"
    );
    $stmt->execute();
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }

    $ins = $pdo->prepare(
        "INSERT INTO knowledge_documents (item_type, parent_id, title, uploaded_by, uploaded_by_role)
         VALUES ('folder', NULL, 'Client Files', ?, ?)"
    );
    $ins->execute([$_SESSION['user_id'], $_SESSION['role']]);
    return (int) $pdo->lastInsertId();
}

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

        $duplicateFound = false;
        if (empty($errors)) {
            $dupClient = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE company_name = ? AND client_id <> ?');
            $dupClient->execute([$companyName, $action === 'edit_client' ? (int) $clientId : 0]);
            if ((int) $dupClient->fetchColumn() > 0) {
                $duplicateFound = true;
                $errors[] = 'A client named "' . $companyName . '" already exists. Please use a different company name.';
            }
        }

        if (empty($errors)) {
            if ($action === 'add_client') {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'INSERT INTO clients (company_name, contact_person, email, contact_number, address, industry)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$companyName, $contactPerson, $email, $contactNumber, $address, $industry]);

                    $clientFilesId = getClientFilesFolderId($pdo);

                    $dup = $pdo->prepare(
                        "SELECT COUNT(*) FROM knowledge_documents
                         WHERE item_type = 'folder' AND parent_id = ? AND title = ?"
                    );
                    $dup->execute([$clientFilesId, $companyName]);

                    if ((int) $dup->fetchColumn() === 0) {
                        $folderStmt = $pdo->prepare(
                            "INSERT INTO knowledge_documents (item_type, parent_id, title, uploaded_by, uploaded_by_role)
                             VALUES ('folder', ?, ?, ?, ?)"
                        );
                        $folderStmt->execute([$clientFilesId, $companyName, $_SESSION['user_id'], $_SESSION['role']]);
                    }

                    $pdo->commit();
                    $_SESSION['alert_type'] = 'success';
                    $_SESSION['alert_message'] = 'Client profile added and folder created in Repository / Client Files.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('add_client failed: ' . $e->getMessage());
                    $_SESSION['alert_type'] = 'error';
                    $_SESSION['alert_message'] = 'Failed to add client. Please try again.';
                }
            } else {
                try {
                    $pdo->beginTransaction();

                    $oldStmt = $pdo->prepare('SELECT company_name FROM clients WHERE client_id = ?');
                    $oldStmt->execute([$clientId]);
                    $oldName = (string) $oldStmt->fetchColumn();

                    $stmt = $pdo->prepare(
                        'UPDATE clients SET company_name = ?, contact_person = ?, email = ?, contact_number = ?, address = ?, industry = ?, updated_at = NOW() WHERE client_id = ?'
                    );
                    $stmt->execute([$companyName, $contactPerson, $email, $contactNumber, $address, $industry, $clientId]);

                    if ($oldName !== '' && $oldName !== $companyName) {
                        $clientFilesId = getClientFilesFolderId($pdo);

                        $exists = $pdo->prepare(
                            "SELECT COUNT(*) FROM knowledge_documents
                             WHERE item_type = 'folder' AND parent_id = ? AND title = ?"
                        );
                        $exists->execute([$clientFilesId, $companyName]);

                        if ((int) $exists->fetchColumn() === 0) {
                            $ren = $pdo->prepare(
                                "UPDATE knowledge_documents SET title = ?
                                 WHERE item_type = 'folder' AND parent_id = ? AND title = ?
                                 LIMIT 1"
                            );
                            $ren->execute([$companyName, $clientFilesId, $oldName]);
                        }
                    }

                    $pdo->commit();
                    $_SESSION['alert_type'] = 'success';
                    $_SESSION['alert_message'] = 'Client profile updated successfully.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('edit_client failed: ' . $e->getMessage());
                    $_SESSION['alert_type'] = 'error';
                    $_SESSION['alert_message'] = 'Failed to update client. Please try again.';
                }
            }
        } else {
            $_SESSION['alert_type'] = $duplicateFound ? 'warning' : 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: client_management.php?tab=clients');
        exit;

    } elseif ($action === 'add_request') {

        $clientId      = $_POST['client_id'] ?? '';
        $requestTitle  = trim($_POST['request_title'] ?? '');
        $requiredSkill = trim($_POST['required_skill'] ?? '');

        $errors = [];
        if ($clientId === '') $errors[] = 'Please select a client.';
        if ($requestTitle === '') $errors[] = 'Request title is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO service_requests (client_id, request_title, required_skill, status) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$clientId, $requestTitle, $requiredSkill !== '' ? $requiredSkill : null, 'New']);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Service request recorded successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: client_management.php?tab=requests');
        exit;

    } elseif ($action === 'edit_request') {

        $requestId     = $_POST['request_id'] ?? null;
        $clientId      = $_POST['client_id'] ?? '';
        $requestTitle  = trim($_POST['request_title'] ?? '');
        $requiredSkill = trim($_POST['required_skill'] ?? '');

        $errors = [];
        if ($clientId === '') $errors[] = 'Please select a client.';
        if ($requestTitle === '') $errors[] = 'Request title is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'UPDATE service_requests SET client_id = ?, request_title = ?, required_skill = ? WHERE request_id = ?'
            );
            $stmt->execute([$clientId, $requestTitle, $requiredSkill !== '' ? $requiredSkill : null, $requestId]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Service request updated successfully.';
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
$perPage      = 25;
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

$reportTotalPages = max(1, (int) ceil($totalClientsAll / $perPage));
$reportPage       = min($page, $reportTotalPages);
$reportOffset     = ($reportPage - 1) * $perPage;
$reportClients    = $pdo->query("SELECT * FROM clients ORDER BY company_name ASC LIMIT $perPage OFFSET $reportOffset")->fetchAll();
$reportRequests   = $pdo->query('SELECT client_id, status FROM service_requests')->fetchAll();

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

function pageWindow(int $current, int $total): array
{
    if ($total <= 7) {
        return range(1, $total);
    }
    $pages = [1];
    $start = max(2, $current - 1);
    $end = min($total - 1, $current + 1);
    if ($start > 2) {
        $pages[] = '...';
    }
    for ($i = $start; $i <= $end; $i++) {
        $pages[] = $i;
    }
    if ($end < $total - 1) {
        $pages[] = '...';
    }
    $pages[] = $total;
    return $pages;
}

function clientActivityLabel(array $client): string
{
    $createdAt = $client['created_at'] ?? null;
    $updatedAt = $client['updated_at'] ?? null;

    if (!empty($updatedAt) && $updatedAt !== $createdAt) {
        return 'Updated ' . date('M d, Y', strtotime($updatedAt));
    }
    return 'Added ' . date('M d, Y', strtotime($createdAt));
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
  --row-hover: #F5F7FC;
}

body {
  background-color: var(--canvas);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  -webkit-font-smoothing: antialiased;
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

.form-control, .form-select {
  border-color: #D5DAE3;
  border-radius: 8px;
  color: var(--ink);
}
.form-control:focus, .form-select:focus {
  border-color: var(--indigo);
  box-shadow: 0 0 0 .2rem #DDE2F1;
  outline: none;
}
.input-group-text { border-color: #D5DAE3; border-radius: 8px 0 0 8px; }
.input-group .form-control { border-radius: 0 8px 8px 0; }

.form-label { font-size: .85rem; font-weight: 600; color: var(--slate); }

.btn-primary-solid {
  background-color: var(--navy-deep);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
}

.btn-brand {
  background-color: var(--indigo);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-weight: 600;
}
.btn-brand:focus { background-color: var(--indigo-text); color: #fff; }

.btn-icon-neutral {
  background-color: var(--indigo);
  color: #fff;
  border: none;
  border-radius: 7px;
  font-size: .85rem;
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.btn-text-danger {
  background-color: var(--danger);
  color: #fff;
  border: none;
  border-radius: 7px;
  font-weight: 600;
}

.summary-bar {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  background-color: #fff;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .75rem 1.15rem;
}

.summary-item {
  display: flex;
  align-items: baseline;
  gap: .65rem;
  min-width: 0;
}

.summary-label {
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--ink-soft);
  white-space: nowrap;
}

.summary-value {
  font-family: 'Lexend', 'Inter', sans-serif;
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--navy-deep);
  word-break: break-word;
}

.summary-money { color: var(--success-text); }

.summary-divider {
  width: 2px;
  align-self: stretch;
  min-height: 28px;
  background-color: #B8C0CE;
  border-radius: 1px;
  flex-shrink: 0;
}

.client-management-tabs {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
  padding: 4px;
}

.client-management-tabs a {
  --tab-color: #3B4E8A;
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  padding: .45rem .9rem;
  border-radius: 8px;
  border: 2px solid var(--tab-color);
  background-color: var(--tab-color);
  color: #FFFFFF !important;
  font-size: .82rem;
  font-weight: 600;
  text-decoration: none;
}

.client-management-tabs a.tab-clients  { --tab-color: #3B4E8A; }
.client-management-tabs a.tab-requests { --tab-color: #B7791F; }
.client-management-tabs a.tab-reports  { --tab-color: #157A5F; }

.client-management-tabs a.active {
  box-shadow: 0 0 0 3px #fff, 0 0 0 5px var(--tab-color);
}

.client-management-tabs a:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px #fff, 0 0 0 5px var(--tab-color);
}

.client-management-tabs .tab-count {
  font-size: .72rem;
  font-weight: 700;
  color: #FFFFFF;
  opacity: 1;
}

.table thead th {
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .7rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  background-color: #FAFBFD !important;
  padding-top: .8rem;
  padding-bottom: .8rem;
}
.table td { border-bottom: 1px solid var(--line); color: var(--ink); vertical-align: middle; padding-top: .8rem; padding-bottom: .8rem; }
.table tbody tr:last-child td { border-bottom: none; }

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .7rem;
  font-weight: 700;
  padding: .32rem .7rem;
  border-radius: 999px;
  white-space: nowrap;
  border: 1px solid transparent;
}
.status-pill::before {
  content: '';
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: currentColor;
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

.pagination { gap: .25rem; flex-wrap: wrap; }
.pagination .page-link {
  color: var(--indigo-text);
  border: 1px solid var(--line);
  border-radius: 7px !important;
  font-weight: 600;
  min-width: 32px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}
.pagination .page-item.active .page-link { background-color: var(--indigo); border-color: var(--indigo); color: #fff; }
.pagination .page-item.disabled .page-link { color: #adb5bd; background-color: transparent; }

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }

#alertModal .modal-content { text-align: center; }
#alertModal .modal-body { padding: 1.75rem 1.5rem 1rem; }
#alertModal .modal-footer { justify-content: center; border-top: none; padding: .25rem 1.5rem 1.5rem; }
.alert-modal-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  font-size: 1.6rem;
}
.alert-modal-icon.is-success { background-color: var(--success-soft); color: var(--success-text); }
.alert-modal-icon.is-error { background-color: var(--danger-soft); color: var(--danger-text); }
.alert-modal-icon.is-warning { background-color: var(--warn-soft); color: var(--warn-text); }

@media (max-width: 767.98px) {
  .summary-bar {
    gap: .75rem;
    padding: .6rem .8rem;
    flex-wrap: wrap;
  }
  .summary-item {
    flex: 1 1 0;
    flex-direction: column;
    align-items: flex-start;
    gap: .1rem;
    min-width: 45%;
  }
  .summary-label { font-size: .6rem; letter-spacing: .04em; }
  .summary-value { font-size: .98rem; }
  .summary-divider { display: none; }

  .client-management-tabs { gap: .4rem; }
  .client-management-tabs a {
    flex: 1 1 0;
    justify-content: center;
    text-align: center;
    font-size: .72rem;
    padding: .5rem .35rem;
    gap: .3rem;
    min-width: 0;
  }
  .client-management-tabs a i { display: none; }
  .client-management-tabs .tab-count { font-size: .68rem; }

  .btn { font-size: .82rem; padding: .4rem .7rem; }
  .btn-icon-neutral { width: 28px; height: 28px; font-size: .75rem; }

  .form-control, .form-select { font-size: .85rem; padding: .4rem .65rem; }
  .form-label { font-size: .78rem; }

  .table td, .table th { padding: .55rem .6rem; }
  .table thead th { font-size: .62rem; }
  .status-pill { font-size: .62rem; padding: .25rem .55rem; }

  .pagination .page-link { padding: .25rem .5rem; font-size: .75rem; min-width: 28px; }

  .modal-title { font-size: 1rem; }
  .modal-body { padding: .9rem; }
  .modal-header, .modal-footer { padding: .7rem .9rem; }
}

@media (max-width: 575.98px) {
  .summary-value { font-size: .9rem; }
  .dashboard-title { font-size: 1rem; }
}

.modal-wide.modal-dialog {
  width: 62vw;
  max-width: 880px;
  margin: auto;
}
.modal-wide .modal-content {
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  background-color: #FFFFFF;
}
.modal-wide .modal-content > form {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
}
.modal-wide .modal-header {
  flex: 0 0 auto;
  padding: 1.05rem 1.5rem;
  background-color: #FFFFFF;
}
.modal-wide .modal-title {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--navy-deep);
}
.modal-wide .btn-close {
  width: 36px;
  height: 36px;
  padding: 0;
  margin: 0 0 0 auto;
  flex-shrink: 0;
  background-size: 15px;
  background-color: #FFFFFF;
  border: none;
  border-radius: 8px;
  opacity: 1;
}
.modal-wide .btn-close:focus { box-shadow: none; }
.modal-wide .modal-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  padding: 1.4rem 1.5rem;
  background-color: #FAFBFD;
}
.modal-wide .modal-body .row {
  --bs-gutter-x: 1.25rem;
  --bs-gutter-y: 1.1rem;
}
.modal-wide .form-label {
  font-size: .9rem;
  font-weight: 600;
  margin-bottom: .4rem;
  color: var(--slate);
}
.modal-wide .form-control,
.modal-wide .form-select {
  font-size: 1rem;
  padding: .6rem .8rem;
  border-radius: 9px;
  background-color: #FFFFFF;
}
.modal-wide .modal-footer {
  flex: 0 0 auto;
  padding: 1rem 1.5rem;
  gap: .6rem;
  background-color: #FFFFFF;
}
.modal-wide .modal-footer .btn {
  font-size: .95rem;
  padding: .55rem 1.4rem;
  border-radius: 9px;
}
.view-field {
  height: 100%;
  background-color: #FFFFFF;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: .85rem 1.1rem;
}
.view-label {
  font-size: .8rem;
  font-weight: 600;
  color: var(--ink-soft);
  margin-bottom: .3rem;
}
.view-value {
  font-size: 1.1rem;
  font-weight: 600;
  line-height: 1.4;
  color: var(--ink);
  word-break: break-word;
}

@media (max-width: 1199.98px) {
  .modal-wide.modal-dialog { width: 72vw; }
}

@media (max-width: 991.98px) {
  .modal-wide.modal-dialog { width: 84vw; max-width: 84vw; }
  .modal-wide .modal-header { padding: .95rem 1.25rem; }
  .modal-wide .modal-title { font-size: 1.15rem; }
  .modal-wide .modal-body { padding: 1.2rem 1.25rem; }
  .modal-wide .modal-body .row { --bs-gutter-x: 1rem; --bs-gutter-y: 1rem; }
  .modal-wide .form-label { font-size: .85rem; }
  .modal-wide .form-control,
  .modal-wide .form-select { font-size: .95rem; padding: .55rem .75rem; }
  .modal-wide .modal-footer { padding: .9rem 1.25rem; }
  .modal-wide .modal-footer .btn { font-size: .9rem; padding: .5rem 1.25rem; }
  .view-field { padding: .75rem 1rem; }
  .view-label { font-size: .76rem; }
  .view-value { font-size: 1.02rem; }
}

@media (max-width: 767.98px) {
  .modal-wide.modal-dialog { width: 94vw; max-width: 94vw; }
  .modal-wide .modal-content { max-height: 90vh; border-radius: 12px; }
  .modal-wide .modal-header { padding: .8rem 1rem; }
  .modal-wide .modal-title { font-size: 1rem; }
  .modal-wide .btn-close { width: 34px; height: 34px; background-size: 13px; }
  .modal-wide .modal-body { padding: 1rem; }
  .modal-wide .modal-body .row { --bs-gutter-x: .75rem; --bs-gutter-y: .8rem; }
  .modal-wide .form-label { font-size: .78rem; margin-bottom: .25rem; }
  .modal-wide .form-control,
  .modal-wide .form-select { font-size: .88rem; padding: .48rem .65rem; border-radius: 8px; }
  .modal-wide .modal-footer { padding: .75rem 1rem; gap: .5rem; }
  .modal-wide .modal-footer .btn { font-size: .85rem; padding: .48rem 1.1rem; border-radius: 8px; }
  .view-field { padding: .65rem .8rem; border-radius: 9px; }
  .view-label { font-size: .7rem; margin-bottom: .15rem; }
  .view-value { font-size: .92rem; }
}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/supervisor/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Client Management</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Manage client profiles, service requests, and reports.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="summary-bar mb-3">
        <div class="summary-item">
          <span class="summary-label">Total Clients</span>
          <span class="summary-value"><?= (int) $totalClientsAll ?></span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
          <span class="summary-label">Total Requests</span>
          <span class="summary-value"><?= (int) $totalRequestsAll ?></span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
          <span class="summary-label">In Progress</span>
          <span class="summary-value" style="color:var(--warn-text);"><?= (int) $inProgressRequests ?></span>
        </div>
        <div class="summary-divider"></div>
        <div class="summary-item">
          <span class="summary-label">Completed</span>
          <span class="summary-value summary-money"><?= (int) $completedRequests ?></span>
        </div>
      </section>

      <nav class="client-management-tabs mb-3" aria-label="Client management sections">
        <a href="?tab=clients" class="tab-clients <?= $activeTab === 'clients' ? 'active' : '' ?>" <?= $activeTab === 'clients' ? 'aria-current="page"' : '' ?>>
          <i class="fa-solid fa-building"></i>
          Clients
          <span class="tab-count"><?= (int) $totalClientsAll ?></span>
        </a>
        <a href="?tab=requests" class="tab-requests <?= $activeTab === 'requests' ? 'active' : '' ?>" <?= $activeTab === 'requests' ? 'aria-current="page"' : '' ?>>
          <i class="fa-solid fa-clipboard-list"></i>
          Service Requests
          <span class="tab-count"><?= (int) $totalRequestsAll ?></span>
        </a>
        <a href="?tab=reports" class="tab-reports <?= $activeTab === 'reports' ? 'active' : '' ?>" <?= $activeTab === 'reports' ? 'aria-current="page"' : '' ?>>
          <i class="fa-solid fa-chart-simple"></i>
          Reports
          <span class="tab-count"><?= (int) $totalClientsAll ?></span>
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
                <button type="button" class="btn btn-brand w-100" data-bs-toggle="modal" data-bs-target="#addClientModal">
                  <i class="fa-solid fa-plus"></i> Add Client
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="card">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col">Company</th>
                  <th scope="col" class="d-none d-md-table-cell">Contact Person</th>
                  <th scope="col" class="d-none d-lg-table-cell">Email</th>
                  <th scope="col" class="d-none d-lg-table-cell">Contact Number</th>
                  <th scope="col" class="d-none d-md-table-cell">Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($clients)): ?>
                  <tr>
                    <td colspan="5" class="text-center py-5" style="color:var(--ink-soft);">
                      <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                      No clients found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($clients as $client): ?>
                    <tr class="client-management-row"
                      data-bs-toggle="modal" data-bs-target="#viewClientModal"
                      data-id="<?= $client['client_id'] ?>"
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
                      <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= clientActivityLabel($client) ?></td>
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
                <?php foreach (pageWindow($page, (int) $totalPages) as $p): ?>
                  <?php if ($p === '...'): ?>
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                  <?php else: ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildPageUrl($p, 'clients', $searchTerm, $sortOrder, '') ?>"><?= $p ?></a>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
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
                <button type="button" class="btn btn-brand w-100" data-bs-toggle="modal" data-bs-target="#addRequestModal">
                  <i class="fa-solid fa-plus"></i> Add Request
                </button>
              </div>
            </form>
          </div>
        </section>

        <section class="card">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr>
                  <th scope="col">Request</th>
                  <th scope="col" class="d-none d-md-table-cell">Client</th>
                  <th scope="col" class="d-none d-lg-table-cell">Assigned To</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($requests)): ?>
                  <tr>
                    <td colspan="4" class="text-center py-5" style="color:var(--ink-soft);">
                      <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                      No service requests found.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($requests as $request): ?>
                    <tr class="client-management-row"
                      data-bs-toggle="modal" data-bs-target="#manageRequestModal"
                      data-id="<?= $request['request_id'] ?>"
                      data-client-id="<?= $request['client_id'] ?>"
                      data-title="<?= htmlspecialchars($request['request_title']) ?>"
                      data-skill="<?= htmlspecialchars($request['required_skill'] ?? '') ?>"
                      data-status="<?= htmlspecialchars($request['status']) ?>">
                      <td>
                        <div class="fw-semibold small"><?= htmlspecialchars($request['request_title']) ?></div>
                        <div class="d-md-none" style="font-size:.72rem; color:var(--ink-soft);"><?= htmlspecialchars($request['company_name']) ?></div>
                      </td>
                      <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($request['company_name']) ?></td>
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
                <?php foreach (pageWindow($page, (int) $totalPages) as $p): ?>
                  <?php if ($p === '...'): ?>
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                  <?php else: ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildPageUrl($p, 'requests', $searchTerm, $sortOrder, $statusFilter) ?>"><?= $p ?></a>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
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
                <?php if (empty($reportClients)): ?>
                  <tr>
                    <td colspan="3" class="text-center py-5" style="color:var(--ink-soft);">
                      <i class="fa-regular fa-folder-open fs-3 d-block mb-2"></i>
                      No report data available.
                    </td>
                  </tr>
                <?php else: ?>
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
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($reportTotalPages > 1): ?>
          <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3" style="border-top:1px solid var(--line);">
            <span class="small" style="color:var(--ink-soft);">Page <?= $reportPage ?> of <?= $reportTotalPages ?> &middot; <?= $totalClientsAll ?> total</span>
            <nav aria-label="Reports pagination">
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $reportPage <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildPageUrl($reportPage - 1, 'reports', '', 'newest', '') ?>">Previous</a>
                </li>
                <?php foreach (pageWindow($reportPage, $reportTotalPages) as $p): ?>
                  <?php if ($p === '...'): ?>
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                  <?php else: ?>
                    <li class="page-item <?= $p === $reportPage ? 'active' : '' ?>">
                      <a class="page-link" href="<?= buildPageUrl($p, 'reports', '', 'newest', '') ?>"><?= $p ?></a>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item <?= $reportPage >= $reportTotalPages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= buildPageUrl($reportPage + 1, 'reports', '', 'newest', '') ?>">Next</a>
                </li>
              </ul>
            </nav>
          </div>
          <?php endif; ?>
        </section>

      <?php endif; ?>

    </main>

  </div>

</div>

<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-wide">
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
              <input type="text" name="industry" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-brand">Save Client</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-wide">
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
              <label class="form-label">Company Name</label>
              <input type="text" name="company_name" id="edit_company_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Person</label>
              <input type="text" name="contact_person" id="edit_contact_person" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact Number</label>
              <input type="tel" name="contact_number" id="edit_contact_number" class="form-control" inputmode="numeric" pattern="[0-9]*" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Industry</label>
              <input type="text" name="industry" id="edit_industry" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" id="edit_address" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-brand">Update Client</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-wide">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold">Client Details</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Company Name</div>
              <div class="view-value" id="view_company"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Contact Person</div>
              <div class="view-value" id="view_contact"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Email Address</div>
              <div class="view-value" id="view_email"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Contact Number</div>
              <div class="view-value" id="view_number"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Industry</div>
              <div class="view-value" id="view_industry"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="view-field">
              <div class="view-label">Address</div>
              <div class="view-value" id="view_address"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-brand" id="view_edit_btn" data-bs-dismiss="modal">
          <i class="fa-regular fa-pen-to-square"></i> Edit
        </button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
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
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-brand">Save Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="manageRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="edit_request">
        <input type="hidden" name="request_id" id="manage_request_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Service Request Details</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Client</label>
              <select name="client_id" id="manage_client_id" class="form-select" required>
                <?php foreach ($allClientsForModal as $client): ?>
                  <option value="<?= $client['client_id'] ?>"><?= htmlspecialchars($client['company_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Request Title</label>
              <input type="text" name="request_title" id="manage_request_title" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Required Skill</label>
              <select name="required_skill" id="manage_required_skill" class="form-select">
                <option value="">Not specified / any skill</option>
                <?php foreach ($existingSkills as $existingSkill): ?>
                  <option value="<?= htmlspecialchars($existingSkill) ?>"><?= htmlspecialchars($existingSkill) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">This determines which staff will be recommended in Resource Matching.</div>
            </div>
            <div class="col-12">
              <div class="small mb-1" style="color:var(--ink-soft);">Status</div>
              <span class="status-pill" id="manage_status_pill"></span>
              <div class="form-text mt-2">Status is updated once the request has an approved contract, in Resource Matching.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-brand">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
    <div class="modal-content">
      <div class="modal-body">
        <div class="alert-modal-icon" id="alertModalIcon"><i class="fa-solid"></i></div>
        <h2 class="h5 fw-bold mb-2" id="alertModalTitle"></h2>
        <p class="small mb-0" id="alertModalMessage" style="color:var(--ink-soft);"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-brand px-5" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('viewClientModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  const data = btn.dataset;
  document.getElementById('view_company').textContent = data.company;
  document.getElementById('view_contact').textContent = data.contact;
  document.getElementById('view_email').textContent = data.email;
  document.getElementById('view_number').textContent = data.number;
  document.getElementById('view_industry').textContent = data.industry || '-';
  document.getElementById('view_address').textContent = data.address;

  const editBtn = document.getElementById('view_edit_btn');
  editBtn.onclick = function () {
    document.getElementById('edit_client_id').value = data.id;
    document.getElementById('edit_company_name').value = data.company;
    document.getElementById('edit_contact_person').value = data.contact;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_contact_number').value = data.number;
    document.getElementById('edit_industry').value = data.industry;
    document.getElementById('edit_address').value = data.address;
    const editModal = new bootstrap.Modal(document.getElementById('editClientModal'));
    editModal.show();
  };
});

document.getElementById('manageRequestModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  const data = btn.dataset;
  document.getElementById('manage_request_id').value = data.id;
  document.getElementById('manage_client_id').value = data.clientId;
  document.getElementById('manage_request_title').value = data.title;
  document.getElementById('manage_required_skill').value = data.skill;
  const statusPill = document.getElementById('manage_status_pill');
  statusPill.textContent = data.status;
  statusPill.className = 'status-pill ' + ({
    'New': 'status-draft',
    'In Progress': 'status-warn',
    'Completed': 'status-approved',
    'Cancelled': 'status-rejected'
  }[data.status] || 'status-draft');
});

function showAlertModal(type, message) {
  const config = {
    success: { title: 'Success', icon: 'fa-circle-check', cls: 'is-success' },
    warning: { title: 'Already Exists', icon: 'fa-triangle-exclamation', cls: 'is-warning' },
    error:   { title: 'Something Went Wrong', icon: 'fa-circle-xmark', cls: 'is-error' }
  }[type] || { title: 'Notice', icon: 'fa-circle-info', cls: 'is-warning' };

  document.getElementById('alertModalIcon').className = 'alert-modal-icon ' + config.cls;
  document.getElementById('alertModalIcon').innerHTML = '<i class="fa-solid ' + config.icon + '"></i>';
  document.getElementById('alertModalTitle').textContent = config.title;
  document.getElementById('alertModalMessage').textContent = message;
  new bootstrap.Modal(document.getElementById('alertModal')).show();
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  showAlertModal(<?= json_encode($alertType) ?>, <?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>