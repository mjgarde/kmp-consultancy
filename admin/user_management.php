<?php
session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();
$roles = ['Manager', 'Supervisor', 'Staff'];

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function initials(string $first, string $last): string
{
    return strtoupper(mb_substr(trim($first), 0, 1) . mb_substr(trim($last), 0, 1));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $errors = [];

    if ($action === 'add' || $action === 'edit') {

        $userId        = (int) ($_POST['user_id'] ?? 0);
        $firstname     = trim($_POST['firstname'] ?? '');
        $middlename    = trim($_POST['middlename'] ?? '');
        $lastname      = trim($_POST['lastname'] ?? '');
        $birthday      = $_POST['birthday'] ?? '';
        $gender        = $_POST['gender'] ?? '';
        $address       = trim($_POST['address'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $role          = ucfirst(strtolower(trim($_POST['role'] ?? '')));
        $password      = $_POST['password'] ?? '';
        $skillsRaw     = $_POST['skills'] ?? '';

        $skills = [];
        if ($role === 'Staff' && $skillsRaw !== '') {
            $decoded = json_decode($skillsRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $s) {
                    $s = trim((string) $s);
                    if ($s !== '' && !in_array(strtolower($s), array_map('strtolower', $skills), true)) $skills[] = $s;
                }
            }
        }

        if ($firstname === '') $errors[] = 'First name is required.';
        if ($lastname === '') $errors[] = 'Last name is required.';
        if ($birthday === '') $errors[] = 'Birthday is required.';
        if (!in_array($gender, ['Male', 'Female'], true)) $errors[] = 'Please select a gender.';
        if ($address === '') $errors[] = 'Address is required.';
        if ($contactNumber === '') {
            $errors[] = 'Contact number is required.';
        } elseif (!preg_match('/^[0-9+\s\-()]+$/', $contactNumber)) {
            $errors[] = 'Contact number contains invalid characters.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (!in_array($role, $roles, true)) $errors[] = 'Please select a role.';

        if ($action === 'add' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if ($action === 'edit' && $password !== '' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if (empty($errors)) {
            $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
            $checkStmt->execute([$email, $userId]);

            if ($checkStmt->fetch()) {
                $errors[] = 'An account with that email already exists.';
            } elseif ($action === 'add') {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (firstname, middlename, lastname, birthday, gender, address, contact_number, email, password, role)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$firstname, $middlename, $lastname, $birthday, $gender, $address, $contactNumber, $email, password_hash($password, PASSWORD_DEFAULT), strtolower($role)]);
                $newUserId = (int) $pdo->lastInsertId();

                if ($role === 'Staff' && $skills) {
                    $skillStmt = $pdo->prepare('INSERT INTO staff_skills (user_id, skill_name) VALUES (?, ?)');
                    foreach ($skills as $skillName) $skillStmt->execute([$newUserId, $skillName]);
                }

                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'User account created successfully.';
                header('Location: user_management.php');
                exit;
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users SET firstname = ?, middlename = ?, lastname = ?, birthday = ?, gender = ?, address = ?, contact_number = ?, email = ?, role = ? WHERE user_id = ?'
                );
                $stmt->execute([$firstname, $middlename, $lastname, $birthday, $gender, $address, $contactNumber, $email, strtolower($role), $userId]);

                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
                }

                $pdo->prepare('DELETE FROM staff_skills WHERE user_id = ?')->execute([$userId]);
                if ($role === 'Staff' && $skills) {
                    $skillStmt = $pdo->prepare('INSERT INTO staff_skills (user_id, skill_name) VALUES (?, ?)');
                    foreach ($skills as $skillName) $skillStmt->execute([$userId, $skillName]);
                }

                $_SESSION['alert_type'] = 'success';
                $_SESSION['alert_message'] = 'User account updated successfully.';
                header('Location: user_management.php');
                exit;
            }
        }

        $_SESSION['alert_type'] = 'error';
        $_SESSION['alert_message'] = implode(' ', $errors);
        header('Location: user_management.php');
        exit;

    } elseif ($action === 'delete') {

        $userId = (int) ($_POST['user_id'] ?? 0);
        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);

        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'User account deleted successfully.';
        header('Location: user_management.php');
        exit;

    } elseif ($action === 'toggle_status') {

        $userId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = in_array($_POST['new_status'] ?? '', ['Active', 'Inactive'], true) ? $_POST['new_status'] : 'Active';
        $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?')->execute([$newStatus, $userId]);

        header('Location: user_management.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$searchTerm = trim($_GET['search'] ?? '');
$roleFilter = ucfirst(strtolower(trim($_GET['role'] ?? '')));
if (!in_array($roleFilter, $roles, true)) $roleFilter = '';

$query = 'SELECT * FROM users WHERE 1=1';
$params = [];

if ($searchTerm !== '') {
    $query .= ' AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)';
    $like = '%' . $searchTerm . '%';
    array_push($params, $like, $like, $like);
}
if ($roleFilter !== '') {
    $query .= ' AND role = ?';
    $params[] = strtolower($roleFilter);
}
$query .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$skillsByUser = [];
foreach ($pdo->query('SELECT user_id, skill_name FROM staff_skills ORDER BY skill_id ASC')->fetchAll() as $row) {
    $skillsByUser[$row['user_id']][] = $row['skill_name'];
}
$existingSkills = $pdo->query('SELECT DISTINCT skill_name FROM staff_skills ORDER BY skill_name ASC')->fetchAll(PDO::FETCH_COLUMN);

$roleCounts = ['Manager' => ['total' => 0, 'active' => 0], 'Supervisor' => ['total' => 0, 'active' => 0], 'Staff' => ['total' => 0, 'active' => 0]];
foreach ($pdo->query('SELECT role, status, COUNT(*) AS cnt FROM users GROUP BY role, status')->fetchAll() as $row) {
    $r = ucfirst(strtolower(trim($row['role'] ?? '')));
    if (!isset($roleCounts[$r])) continue;
    $roleCounts[$r]['total'] += (int) $row['cnt'];
    if ($row['status'] === 'Active') $roleCounts[$r]['active'] += (int) $row['cnt'];
}
$totalUsers = array_sum(array_column($roleCounts, 'total'));
$totalActive = array_sum(array_column($roleCounts, 'active'));

$roleStyle = [
    'Manager'    => ['tg-pink', '#3A5A8C'],
    'Supervisor' => ['tg-olive', '#3A5A8C'],
    'Staff'      => ['tg-teal', '#3A5A8C'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>KMP ConsultHub - User Management</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --canvas: #FAF9F6;
  --card: #FFFEFC;
  --surface: #F6F4EF;
  --line: #E6E2DA;
  --ink: #2A2D2F;
  --ink-soft: #6E7275;
  --charcoal: #2B3134;

  --navy: #1E293B;
  --navy-deep: #0F172A;
  --navy-soft: #EEF1F6;
  --indigo: #3B4E8A;
  --indigo-hover: #2E3E70;
  --success: #157A5F;
  --success-hover: #0F5F49;
  --danger: #B4432F;
  --danger-hover: #9C3A29;
}

* { -webkit-tap-highlight-color: transparent; }
body { background: var(--canvas); color: var(--ink); font-family: 'Inter', -apple-system, sans-serif; overflow-x: hidden; }
.dashboard-layout, .dashboard-main, .dashboard-content { background: var(--canvas) !important; }
.dashboard-main { min-width: 0; max-width: 100%; }
.dashboard-title, h1, h2, h3, .num { font-family: 'Lexend', 'Inter', sans-serif; }
.dashboard-title { color: var(--charcoal); letter-spacing: -.01em; }
.dashboard-subtitle { color: var(--ink-soft) !important; }
.dashboard-topbar { border-bottom: 1px solid var(--line) !important; background: var(--card); }

.card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 1px 2px rgba(42,45,47,.04); }
.form-control, .form-select { border-color: var(--line); background-color: var(--card); font-size: .875rem; }
.form-control:hover, .form-select:hover { border-color: #CFCAC0; }
.form-control:focus, .form-select:focus { border-color: var(--indigo); box-shadow: 0 0 0 .2rem rgba(59,78,138,.14); outline: none; }
.input-group-text { background: var(--card); border-color: var(--line); color: var(--ink-soft); }
.form-label { font-size: .8rem; color: var(--charcoal); }

.btn-main { background: var(--indigo); color: #fff; border: 0; border-radius: 8px; font-weight: 600; font-size: .85rem; padding: .5rem 1.1rem; }
.btn-main:hover { background: var(--indigo-hover); color: #fff; }

.btn-danger-solid { background: var(--danger); color: #fff; border: 0; border-radius: 8px; font-weight: 600; font-size: .85rem; padding: .5rem 1.1rem; }
.btn-danger-solid:hover { background: var(--danger-hover); color: #fff; }

.btn-ok { background: var(--success); color: #fff; border: 0; border-radius: 8px; font-weight: 600; font-size: .85rem; padding: .5rem 1.1rem; }
.btn-ok:hover { background: var(--success-hover); color: #fff; }

.btn-ghost { background: var(--navy-soft); color: var(--navy); border: 1px solid var(--line); border-radius: 8px; font-weight: 600; font-size: .85rem; padding: .5rem 1.1rem; }
.btn-ghost:hover { background: #E4E8F0; color: var(--navy); }

.btn-soft { background: var(--surface); color: var(--charcoal); border: 1px solid var(--line); border-radius: 8px; font-weight: 600; font-size: .8rem; }
.btn-soft:hover { background: #EAE7E0; color: var(--charcoal); }

.stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: .2rem;
  padding: 1rem .85rem;
  border-radius: 12px;
  color: #fff;
  height: 100%;
  box-shadow: 0 2px 6px rgba(20,24,32,.18);
}

.st-navy   { background: #1E293B; }
.st-indigo { background: #3B4E8A; }
.st-slate  { background: #475569; }
.st-forest { background: #157A5F; }

.stat-label {
  font-size: .7rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: #fff;
  opacity: .82;
  margin-bottom: .1rem;
}

.stat-value {
  font-size: 2.15rem;
  font-weight: 700;
  line-height: 1;
  color: #fff;
  letter-spacing: -.02em;
}

.stat-sub {
  font-size: .74rem;
  font-weight: 500;
  color: #fff;
  opacity: .68;
  white-space: nowrap;
}

.tag {
  display: inline-block;
  font-size: .78rem;
  font-weight: 600;
  padding: .35rem .85rem;
  border-radius: 4px;
  background: #475569;
  color: #fff;
  white-space: nowrap;
  line-height: 1.2;
}
.tg-pink,
.tg-olive,
.tg-teal,
.tg-green,
.tg-gray { background: #475569; }

.flash { display: flex; align-items: center; gap: .75rem; border-radius: 10px; padding: .8rem 1rem; font-size: .88rem; font-weight: 600; margin-bottom: 1rem; color: #fff; }
.flash i { font-size: 1rem; }
.flash.success { background: var(--success); }
.flash.error { background: var(--danger); }
.flash .btn-close { margin-left: auto; font-size: .7rem; filter: invert(1); opacity: 1; }

.table thead th {
  background: var(--surface) !important;
  border-bottom: 1px solid var(--line) !important;
  color: var(--ink-soft);
  font-weight: 700;
  font-size: .8rem;
  letter-spacing: .05em;
  text-transform: uppercase;
  white-space: nowrap;
  padding: .85rem 1rem;
}
.table td {
  border-bottom: 1px solid var(--line);
  vertical-align: middle;
  font-size: .92rem;
  color: var(--ink);
  padding: .85rem 1rem;
}
.table tbody tr:last-child td { border-bottom: 0; }
.table-hover tbody tr:hover { background: var(--surface); }
.user-row { cursor: pointer; }
.u-name { display: flex; align-items: center; gap: .75rem; }
.u-avatar { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; flex-shrink: 0; }
.u-title { font-weight: 600; color: var(--charcoal); font-size: .92rem; }
.u-sub { font-size: .78rem; color: var(--ink-soft); }

.icon-btn { width: 40px; height: 40px; border: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff; transition: background .15s, transform .1s; }
.icon-btn:active { transform: scale(.96); }
.ib-edit { background: var(--indigo); }
.ib-edit:hover { background: var(--indigo-hover); color: #fff; }
.ib-delete { background: var(--danger); }
.ib-delete:hover { background: var(--danger-hover); color: #fff; }

.skill-tag { display: inline-flex; align-items: center; gap: .4rem; background: #475569; color: #fff; padding: .3rem .65rem; border-radius: 4px; font-size: .78rem; font-weight: 500; }
.skill-tag button { background: none; border: 0; color: #fff; line-height: 1; padding: 0; font-size: 1rem; }
.skill-tag button:hover { color: #E5E7EB; }
.skill-badge { background: #475569; color: #fff; font-size: .72rem; font-weight: 600; padding: .28rem .65rem; border-radius: 4px; margin: 0 .3rem .3rem 0; display: inline-block; }

.modal-content { background: var(--card); border-radius: 14px; border: 0; }
.modal-header { border-bottom: 1px solid var(--line); padding: 1rem 1.25rem; }
.modal-footer { border-top: 1px solid var(--line); padding: .85rem 1.25rem; }
.modal-title { font-family: 'Lexend', 'Inter', sans-serif; color: var(--charcoal); }

#addUserModal .modal-title,
#editUserModal .modal-title { font-size: 1.9rem; }
#addUserModal .modal-body,
#editUserModal .modal-body { padding: 1.75rem 2rem; }
#addUserModal .form-label,
#editUserModal .form-label { font-size: 1.1rem; font-weight: 600; margin-bottom: .45rem; }
#addUserModal .form-control,
#addUserModal .form-select,
#editUserModal .form-control,
#editUserModal .form-select { font-size: 1.15rem; padding: .7rem 1rem; border-radius: 8px; }
#addUserModal .btn-soft,
#editUserModal .btn-soft { font-size: 1.05rem; padding: .65rem 1.2rem; }
#addUserModal .modal-footer .btn,
#editUserModal .modal-footer .btn { font-size: 1.15rem; padding: .75rem 1.75rem; }
#addUserModal .skill-tag,
#editUserModal .skill-tag { font-size: 1rem; padding: .45rem .85rem; }

#viewUserModal .modal-title { font-size: 1.9rem; }
#viewUserModal .modal-body { padding: 2rem 2.25rem; }
#viewUserModal .detail-label {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--ink-soft);
  margin-bottom: .4rem;
}
#viewUserModal .detail-value {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ink);
  word-break: break-word;
  line-height: 1.35;
}
#viewUserModal .skill-badge {
  font-size: 1.1rem;
  padding: .45rem .95rem;
  margin: 0 .4rem .4rem 0;
}
#viewUserModal .modal-header { padding: 1.25rem 1.75rem; }
#viewUserModal .btn-close { transform: scale(1.2); }

.confirm-dialog { max-width: 620px; }
.modal-wide { max-width: 85vw; width: 85vw; }
@media (max-width: 767.98px) { .modal-wide { max-width: 94vw; width: 94vw; } }
.confirm-body { text-align: center; padding: 3.25rem 2.75rem 1.5rem; }
.confirm-icon { width: 112px; height: 112px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.9rem; margin: 0 auto 1.6rem; color: #fff; }
.ci-danger { background: var(--danger); }
.ci-success { background: var(--success); }
.confirm-title { font-family: 'Lexend', sans-serif; font-size: 1.85rem; font-weight: 700; color: var(--charcoal); margin-bottom: .75rem; }
.confirm-text { font-size: 1.15rem; color: var(--ink-soft); margin: 0; line-height: 1.6; }
.confirm-text strong { color: var(--charcoal); }
.confirm-actions { display: flex; gap: .9rem; padding: 1.5rem 2.75rem 2.75rem; }
.confirm-actions .btn { flex: 1 1 0; font-size: 1.1rem; padding: 1rem 1.2rem; }

.mobile-row-label { display: none; }

.modal-dialog-scrollable { max-height: calc(100vh - 2rem); }
.modal-dialog-scrollable .modal-content { max-height: calc(100vh - 2rem); display: flex; flex-direction: column; }
.modal-dialog-scrollable .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; flex: 1 1 auto; min-height: 0; }

@media (max-width: 1199.98px) {
  #addUserModal .modal-title,
  #editUserModal .modal-title,
  #viewUserModal .modal-title { font-size: 1.5rem; }
  #addUserModal .form-label,
  #editUserModal .form-label { font-size: .95rem; }
  #addUserModal .form-control,
  #addUserModal .form-select,
  #editUserModal .form-control,
  #editUserModal .form-select { font-size: 1rem; padding: .6rem .85rem; }
  #addUserModal .modal-footer .btn,
  #editUserModal .modal-footer .btn { font-size: 1rem; padding: .65rem 1.4rem; }
  #viewUserModal .detail-label { font-size: .85rem; }
  #viewUserModal .detail-value { font-size: 1.2rem; }
  #viewUserModal .skill-badge { font-size: .95rem; }
}

@media (max-width: 767.98px) {
  .dashboard-content { padding: .65rem !important; }
  .dashboard-topbar { padding-left: .65rem !important; padding-right: .65rem !important; }
  .dashboard-title { font-size: .92rem !important; }

  .stat { padding: .8rem .6rem; gap: .15rem; }
  .stat-label { font-size: .62rem; }
  .stat-value { font-size: 1.7rem; }
  .stat-sub { font-size: .68rem; }

  .form-control, .form-select, .input-group-text { font-size: .8rem; }
  .confirm-body { padding: 2.5rem 1.5rem 1rem; }
  .confirm-actions { padding: 1.25rem 1.5rem 2rem; }

  .modal-dialog-scrollable { max-height: calc(100vh - 1rem); margin: .5rem auto; }
  .modal-dialog-scrollable .modal-content { max-height: calc(100vh - 1rem); }

  #addUserModal .modal-title,
  #editUserModal .modal-title,
  #viewUserModal .modal-title { font-size: 1.15rem; }
  #addUserModal .modal-body,
  #editUserModal .modal-body { padding: 1rem; }
  #viewUserModal .modal-body { padding: 1rem; }
  #addUserModal .form-label,
  #editUserModal .form-label { font-size: .8rem; margin-bottom: .3rem; }
  #addUserModal .form-control,
  #addUserModal .form-select,
  #editUserModal .form-control,
  #editUserModal .form-select { font-size: .85rem; padding: .5rem .7rem; }
  #addUserModal .btn-soft,
  #editUserModal .btn-soft { font-size: .78rem; padding: .4rem .8rem; }
  #addUserModal .modal-footer .btn,
  #editUserModal .modal-footer .btn { font-size: .85rem; padding: .5rem 1rem; }
  #addUserModal .skill-tag,
  #editUserModal .skill-tag { font-size: .78rem; padding: .3rem .6rem; }

  #viewUserModal .detail-label { font-size: .68rem; margin-bottom: .15rem; }
  #viewUserModal .detail-value { font-size: .95rem; }
  #viewUserModal .skill-badge { font-size: .8rem; padding: .3rem .65rem; margin: 0 .3rem .3rem 0; }
  #viewUserModal .modal-header { padding: .8rem 1rem; }
  #viewUserModal .btn-close { transform: scale(1); }

  .table-responsive { overflow: visible; }
  #usersTable thead { display: none; }
  #usersTable, #usersTable tbody, #usersTable tr, #usersTable td { display: block; width: 100%; }
  #usersTable tbody tr.user-row { border: 1px solid var(--line); border-radius: 10px; margin: .6rem .65rem; padding: .5rem .2rem; background: var(--card); width: auto; }
  #usersTable tbody tr.user-row td { display: flex !important; justify-content: space-between; align-items: center; gap: .75rem; border: 0 !important; padding: .3rem .7rem; font-size: .78rem; text-align: right; }
  #usersTable tbody tr.user-row td .mobile-row-label { display: block; font-size: .62rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); text-align: left; flex: 0 0 auto; }
  #usersTable tbody tr.user-row td .cell-body { flex: 1 1 auto; min-width: 0; word-break: break-word; text-align: right; }
  #usersTable .u-avatar { display: none; }
  #usersTable .u-name { justify-content: flex-end; }
  #usersTable tbody tr.user-row td.cell-actions { border-top: 1px solid var(--line) !important; margin-top: .3rem; padding-top: .5rem; justify-content: flex-end; }
  #usersTable tbody tr.user-row td.cell-actions .mobile-row-label { display: none; }
  #usersTable tbody tr.user-row td.cell-actions .cell-body { display: flex; justify-content: flex-end; gap: .4rem; }
}
</style>
</head>
<body>

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/admin/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1">

    <header class="dashboard-topbar d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">User Management</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Manage manager, supervisor, and staff accounts.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <?php if ($alertType && $alertMessage): ?>
        <div class="flash <?= $alertType === 'success' ? 'success' : 'error' ?>" role="alert">
          <i class="fa-solid <?= $alertType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
          <span><?= h($alertMessage) ?></span>
          <button type="button" class="btn-close" aria-label="Close" onclick="this.parentElement.remove()"></button>
        </div>
      <?php endif; ?>

      <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-xl-3">
          <div class="stat st-navy">
            <div class="stat-label">Total Users</div>
            <div class="stat-value num"><?= $totalUsers ?></div>
            <div class="stat-sub"><?= $totalActive ?> active</div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat st-indigo">
            <div class="stat-label">Managers</div>
            <div class="stat-value num"><?= $roleCounts['Manager']['total'] ?></div>
            <div class="stat-sub"><?= $roleCounts['Manager']['active'] ?> active</div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat st-slate">
            <div class="stat-label">Supervisors</div>
            <div class="stat-value num"><?= $roleCounts['Supervisor']['total'] ?></div>
            <div class="stat-sub"><?= $roleCounts['Supervisor']['active'] ?> active</div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="stat st-forest">
            <div class="stat-label">Staff</div>
            <div class="stat-value num"><?= $roleCounts['Staff']['total'] ?></div>
            <div class="stat-sub"><?= $roleCounts['Staff']['active'] ?> active</div>
          </div>
        </div>
      </div>

      <section class="card mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="row g-2 align-items-center" method="GET" id="filterForm">
            <div class="col-12 col-md-7">
              <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" name="search" id="searchInput" class="form-control" placeholder="Search by name or email" value="<?= h($searchTerm) ?>">
              </div>
            </div>
            <div class="col-6 col-md-3">
              <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <button type="button" class="btn btn-main w-100" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fa-solid fa-plus me-1"></i> Add User
              </button>
            </div>
          </form>
        </div>
      </section>

      <section class="card overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="usersTable">
            <thead>
              <tr>
                <th scope="col">Name</th>
                <th scope="col" class="d-none d-md-table-cell">Email</th>
                <th scope="col" class="d-none d-lg-table-cell">Contact Number</th>
                <th scope="col">Role</th>
                <th scope="col" class="d-none d-sm-table-cell">Status</th>
                <th scope="col" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center py-5" style="color:var(--ink-soft);"><i class="fa-regular fa-user d-block fs-4 mb-2" style="color:#CFCAC0;"></i>No users found.</td></tr>
              <?php else: ?>
                <?php foreach ($users as $user):
                    $userSkills = $skillsByUser[$user['user_id']] ?? [];
                    $roleLabel = ucfirst(strtolower($user['role']));
                    $rs = $roleStyle[$roleLabel] ?? ['tg-gray', '#3A5A8C'];
                    $fullName = $user['firstname'] . ' ' . $user['lastname'];
                ?>
                  <tr class="user-row" role="button"
                    data-bs-toggle="modal" data-bs-target="#viewUserModal"
                    data-firstname="<?= h($user['firstname']) ?>"
                    data-middlename="<?= h($user['middlename'] ?? '') ?>"
                    data-lastname="<?= h($user['lastname']) ?>"
                    data-birthday="<?= h($user['birthday']) ?>"
                    data-gender="<?= h($user['gender']) ?>"
                    data-address="<?= h($user['address']) ?>"
                    data-contact="<?= h($user['contact_number']) ?>"
                    data-email="<?= h($user['email']) ?>"
                    data-role="<?= h($roleLabel) ?>"
                    data-status="<?= h($user['status']) ?>"
                    data-skills="<?= h(json_encode($userSkills)) ?>">
                    <td>
                      <span class="mobile-row-label">Name</span>
                      <span class="cell-body">
                        <span class="u-name">
                          <span class="u-avatar" style="background:<?= $rs[1] ?>;"><?= h(initials($user['firstname'], $user['lastname'])) ?></span>
                          <span>
                            <span class="u-title d-block"><?= h($fullName) ?></span>
                            <span class="u-sub d-md-none d-block"><?= h($user['email']) ?></span>
                          </span>
                        </span>
                      </span>
                    </td>
                    <td class="d-none d-md-table-cell" style="color:var(--ink-soft);">
                      <span class="mobile-row-label">Email</span>
                      <span class="cell-body"><?= h($user['email']) ?></span>
                    </td>
                    <td class="d-none d-lg-table-cell" style="color:var(--ink-soft);">
                      <span class="mobile-row-label">Contact</span>
                      <span class="cell-body"><?= h($user['contact_number']) ?></span>
                    </td>
                    <td>
                      <span class="mobile-row-label">Role</span>
                      <span class="cell-body"><span class="tag <?= $rs[0] ?>"><?= h($roleLabel) ?></span></span>
                    </td>
                    <td class="d-none d-sm-table-cell">
                      <span class="mobile-row-label">Status</span>
                      <span class="cell-body"><span class="tag <?= $user['status'] === 'Active' ? 'tg-green' : 'tg-gray' ?>"><?= h($user['status']) ?></span></span>
                    </td>
                    <td class="text-end cell-actions" onclick="event.stopPropagation();">
                      <span class="mobile-row-label">Actions</span>
                      <span class="cell-body">
                        <button type="button" class="icon-btn ib-edit" title="Edit"
                          data-bs-toggle="modal" data-bs-target="#editUserModal"
                          data-id="<?= (int) $user['user_id'] ?>"
                          data-firstname="<?= h($user['firstname']) ?>"
                          data-middlename="<?= h($user['middlename'] ?? '') ?>"
                          data-lastname="<?= h($user['lastname']) ?>"
                          data-birthday="<?= h($user['birthday']) ?>"
                          data-gender="<?= h($user['gender']) ?>"
                          data-address="<?= h($user['address']) ?>"
                          data-contact="<?= h($user['contact_number']) ?>"
                          data-email="<?= h($user['email']) ?>"
                          data-role="<?= h($roleLabel) ?>"
                          data-skills="<?= h(json_encode($userSkills)) ?>">
                          <i class="fa-regular fa-pen-to-square"></i>
                        </button>
                        <button type="button" class="icon-btn ib-delete" title="Delete"
                          data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                          data-id="<?= (int) $user['user_id'] ?>"
                          data-name="<?= h($fullName) ?>">
                          <i class="fa-regular fa-trash-can"></i>
                        </button>
                      </span>
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

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" id="addUserForm">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="skills" id="add_skills_input" value="[]">
        <div class="modal-header">
          <h2 class="modal-title fw-bold">Add User</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12 col-md-4"><label class="form-label fw-semibold">First Name</label><input type="text" name="firstname" class="form-control" required></div>
            <div class="col-12 col-md-4"><label class="form-label fw-semibold">Middle Name</label><input type="text" name="middlename" class="form-control"></div>
            <div class="col-12 col-md-4"><label class="form-label fw-semibold">Last Name</label><input type="text" name="lastname" class="form-control" required></div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">Birthday</label><input type="date" name="birthday" class="form-control" required></div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Gender</label>
              <select name="gender" class="form-select" required>
                <option value="" selected disabled>Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label fw-semibold">Address</label><input type="text" name="address" class="form-control" required></div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">Contact Number</label><input type="tel" name="contact_number" class="form-control" placeholder="e.g. +639171234567 or 09171234567" required></div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Role</label>
              <select name="role" class="form-select" id="add_role" required>
                <option value="" selected disabled>Select role</option>
                <?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">Email Address</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">Password</label><input type="password" name="password" class="form-control" placeholder="At least 8 characters" minlength="8" required></div>
            <div class="col-12 d-none" id="add_skills_wrapper">
              <label class="form-label fw-semibold">Skills</label>
              <div class="row g-2 mb-2">
                <div class="col-12 col-md-6">
                  <div class="input-group">
                    <select class="form-select" id="add_skill_select">
                      <option value="" selected disabled>Select existing skill</option>
                      <?php foreach ($existingSkills as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-soft" id="add_skill_select_btn">Add</button>
                  </div>
                </div>
                <div class="col-12 col-md-6">
                  <div class="input-group">
                    <input type="text" class="form-control" id="add_skill_input" placeholder="Add new skill">
                    <button type="button" class="btn btn-soft" id="add_skill_btn">Add</button>
                  </div>
                </div>
              </div>
              <div id="add_skills_list" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-main">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-wide modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" id="editUserForm" style="display:contents;">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="user_id" id="edit_user_id">
        <input type="hidden" name="skills" id="edit_skills_input" value="[]">
        <div class="modal-header">
          <h2 class="modal-title fw-bold">Edit User</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12 col-md-4"><label class="form-label fw-semibold">First Name</label><input type="text" name="firstname" id="edit_firstname" class="form-control" required></div>
            <div class="col-12 col-md-4"><label class="form-label fw-semibold">Middle Name</label><input type="text" name="middlename" id="edit_middlename" class="form-control"></div>
            <div class="col-12 col-md-4"><label class="form-label fw-semibold">Last Name</label><input type="text" name="lastname" id="edit_lastname" class="form-control" required></div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">Birthday</label><input type="date" name="birthday" id="edit_birthday" class="form-control" required></div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Gender</label>
              <select name="gender" id="edit_gender" class="form-select" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="col-12"><label class="form-label fw-semibold">Address</label><input type="text" name="address" id="edit_address" class="form-control" required></div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">Contact Number</label><input type="tel" name="contact_number" id="edit_contact_number" class="form-control" required></div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold">Role</label>
              <select name="role" id="edit_role" class="form-select" required>
                <?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">Email Address</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
            <div class="col-12 col-md-6"><label class="form-label fw-semibold">New Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password"></div>
            <div class="col-12 d-none" id="edit_skills_wrapper">
              <label class="form-label fw-semibold">Skills</label>
              <div class="row g-2 mb-2">
                <div class="col-12 col-md-6">
                  <div class="input-group">
                    <select class="form-select" id="edit_skill_select">
                      <option value="" selected disabled>Select existing skill</option>
                      <?php foreach ($existingSkills as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-soft" id="edit_skill_select_btn">Add</button>
                  </div>
                </div>
                <div class="col-12 col-md-6">
                  <div class="input-group">
                    <input type="text" class="form-control" id="edit_skill_input" placeholder="Add new skill">
                    <button type="button" class="btn btn-soft" id="edit_skill_btn">Add</button>
                  </div>
                </div>
              </div>
              <div id="edit_skills_list" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-main" id="editSaveBtn">Update User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered confirm-dialog">
    <div class="modal-content">
      <div class="confirm-body">
        <div class="confirm-icon ci-success"><i class="fa-solid fa-check"></i></div>
        <div class="confirm-title">Save Changes?</div>
        <p class="confirm-text">Do you want to save the changes to <strong id="confirm_edit_name"></strong>?</p>
      </div>
      <div class="confirm-actions">
        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-ok" id="confirmEditBtn">Yes, Save</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered confirm-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="delete_user_id">
        <div class="confirm-body">
          <div class="confirm-icon ci-danger"><i class="fa-solid fa-xmark"></i></div>
          <div class="confirm-title">Delete User?</div>
          <p class="confirm-text">Do you want to delete <strong id="delete_user_name"></strong>? This action cannot be undone.</p>
        </div>
        <div class="confirm-actions">
          <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger-solid">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-wide modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fw-bold">User Details</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12 col-md-4"><div class="detail-label">First Name</div><div class="detail-value" id="view_firstname"></div></div>
          <div class="col-12 col-md-4"><div class="detail-label">Middle Name</div><div class="detail-value" id="view_middlename"></div></div>
          <div class="col-12 col-md-4"><div class="detail-label">Last Name</div><div class="detail-value" id="view_lastname"></div></div>
          <div class="col-6"><div class="detail-label">Birthday</div><div class="detail-value" id="view_birthday"></div></div>
          <div class="col-6"><div class="detail-label">Gender</div><div class="detail-value" id="view_gender"></div></div>
          <div class="col-12"><div class="detail-label">Address</div><div class="detail-value" id="view_address"></div></div>
          <div class="col-12 col-md-6"><div class="detail-label">Contact Number</div><div class="detail-value" id="view_contact"></div></div>
          <div class="col-12 col-md-6"><div class="detail-label">Email Address</div><div class="detail-value" id="view_email"></div></div>
          <div class="col-6"><div class="detail-label">Role</div><div class="detail-value" id="view_role"></div></div>
          <div class="col-6"><div class="detail-label">Status</div><div class="detail-value" id="view_status"></div></div>
          <div class="col-12 d-none" id="view_skills_wrapper">
            <div class="detail-label mb-1">Skills</div>
            <div id="view_skills_list"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
let addSkills = [];
let editSkills = [];

function renderSkillTags(container, skillsArray, hiddenInput, onRemove) {
  container.innerHTML = '';
  skillsArray.forEach(function (skill, index) {
    const tag = document.createElement('span');
    tag.className = 'skill-tag';
    tag.appendChild(document.createTextNode(skill + ' '));
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.innerHTML = '&times;';
    btn.addEventListener('click', function () { onRemove(index); });
    tag.appendChild(btn);
    container.appendChild(tag);
  });
  hiddenInput.value = JSON.stringify(skillsArray);
}

function toggleSkillsWrapper(roleValue, wrapperEl) {
  wrapperEl.classList.toggle('d-none', roleValue !== 'Staff');
}

function parseSkills(raw) {
  try {
    const parsed = JSON.parse(raw || '[]');
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return [];
  }
}

const addRoleSelect = document.getElementById('add_role');
const addSkillsWrapper = document.getElementById('add_skills_wrapper');
const addSkillsList = document.getElementById('add_skills_list');
const addSkillInput = document.getElementById('add_skill_input');
const addSkillSelect = document.getElementById('add_skill_select');
const addSkillsHidden = document.getElementById('add_skills_input');

function removeAddSkill(idx) {
  addSkills.splice(idx, 1);
  renderSkillTags(addSkillsList, addSkills, addSkillsHidden, removeAddSkill);
}

function addSkillToAddList(val) {
  val = val.trim();
  if (val === '' || addSkills.some(function (s) { return s.toLowerCase() === val.toLowerCase(); })) return;
  addSkills.push(val);
  renderSkillTags(addSkillsList, addSkills, addSkillsHidden, removeAddSkill);
}

addRoleSelect.addEventListener('change', function () { toggleSkillsWrapper(this.value, addSkillsWrapper); });
document.getElementById('add_skill_btn').addEventListener('click', function () {
  addSkillToAddList(addSkillInput.value);
  addSkillInput.value = '';
});
addSkillInput.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('add_skill_btn').click(); }
});
document.getElementById('add_skill_select_btn').addEventListener('click', function () {
  if (addSkillSelect.value === '') return;
  addSkillToAddList(addSkillSelect.value);
  addSkillSelect.value = '';
});

document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
  addSkills = [];
  addSkillsList.innerHTML = '';
  addSkillsHidden.value = '[]';
  addSkillsWrapper.classList.add('d-none');
  document.getElementById('addUserForm').reset();
});

const editForm = document.getElementById('editUserForm');
const editModalEl = document.getElementById('editUserModal');
const confirmEditEl = document.getElementById('confirmEditModal');
const editSkillsWrapper = document.getElementById('edit_skills_wrapper');
const editSkillsList = document.getElementById('edit_skills_list');
const editSkillInput = document.getElementById('edit_skill_input');
const editSkillSelect = document.getElementById('edit_skill_select');
const editSkillsHidden = document.getElementById('edit_skills_input');
const editPassword = editForm.elements['password'];
let editConfirmed = false;

function removeEditSkill(idx) {
  editSkills.splice(idx, 1);
  renderSkillTags(editSkillsList, editSkills, editSkillsHidden, removeEditSkill);
}

function addSkillToEditList(val) {
  val = val.trim();
  if (val === '' || editSkills.some(function (s) { return s.toLowerCase() === val.toLowerCase(); })) return;
  editSkills.push(val);
  renderSkillTags(editSkillsList, editSkills, editSkillsHidden, removeEditSkill);
}

document.getElementById('edit_role').addEventListener('change', function () { toggleSkillsWrapper(this.value, editSkillsWrapper); });
document.getElementById('edit_skill_btn').addEventListener('click', function () {
  addSkillToEditList(editSkillInput.value);
  editSkillInput.value = '';
});
editSkillInput.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') { e.preventDefault(); document.getElementById('edit_skill_btn').click(); }
});
document.getElementById('edit_skill_select_btn').addEventListener('click', function () {
  if (editSkillSelect.value === '') return;
  addSkillToEditList(editSkillSelect.value);
  editSkillSelect.value = '';
});
editPassword.addEventListener('input', function () { editPassword.setCustomValidity(''); });

editModalEl.addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  document.getElementById('edit_user_id').value = btn.dataset.id;
  document.getElementById('edit_firstname').value = btn.dataset.firstname;
  document.getElementById('edit_middlename').value = btn.dataset.middlename;
  document.getElementById('edit_lastname').value = btn.dataset.lastname;
  document.getElementById('edit_birthday').value = btn.dataset.birthday;
  document.getElementById('edit_gender').value = btn.dataset.gender;
  document.getElementById('edit_address').value = btn.dataset.address;
  document.getElementById('edit_contact_number').value = btn.dataset.contact;
  document.getElementById('edit_role').value = btn.dataset.role;
  document.getElementById('edit_email').value = btn.dataset.email;
  editPassword.value = '';
  editPassword.setCustomValidity('');

  editSkills = parseSkills(btn.dataset.skills);
  renderSkillTags(editSkillsList, editSkills, editSkillsHidden, removeEditSkill);
  toggleSkillsWrapper(btn.dataset.role, editSkillsWrapper);
});

document.getElementById('editSaveBtn').addEventListener('click', function () {
  editPassword.setCustomValidity(editPassword.value !== '' && editPassword.value.length < 8 ? 'Password must be at least 8 characters long.' : '');
  if (!editForm.reportValidity()) return;

  const name = (document.getElementById('edit_firstname').value + ' ' + document.getElementById('edit_lastname').value).trim();
  document.getElementById('confirm_edit_name').textContent = name;
  editConfirmed = false;

  editModalEl.addEventListener('hidden.bs.modal', function () {
    bootstrap.Modal.getOrCreateInstance(confirmEditEl).show();
  }, { once: true });
  bootstrap.Modal.getOrCreateInstance(editModalEl).hide();
});

document.getElementById('confirmEditBtn').addEventListener('click', function () {
  editConfirmed = true;
  editForm.submit();
});

confirmEditEl.addEventListener('hidden.bs.modal', function () {
  if (!editConfirmed) bootstrap.Modal.getOrCreateInstance(editModalEl).show();
});

const ROLE_TAGS = { Manager: 'tg-pink', Supervisor: 'tg-olive', Staff: 'tg-teal' };

function setTag(id, text, cls) {
  const el = document.getElementById(id);
  el.innerHTML = '';
  const span = document.createElement('span');
  span.className = 'tag ' + cls;
  span.textContent = text;
  el.appendChild(span);
}

document.getElementById('viewUserModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  document.getElementById('view_firstname').textContent = btn.dataset.firstname;
  document.getElementById('view_middlename').textContent = btn.dataset.middlename || '-';
  document.getElementById('view_lastname').textContent = btn.dataset.lastname;
  document.getElementById('view_birthday').textContent = btn.dataset.birthday;
  document.getElementById('view_gender').textContent = btn.dataset.gender;
  document.getElementById('view_address').textContent = btn.dataset.address;
  document.getElementById('view_contact').textContent = btn.dataset.contact;
  setTag('view_role', btn.dataset.role, ROLE_TAGS[btn.dataset.role] || 'tg-gray');
  document.getElementById('view_email').textContent = btn.dataset.email;
  setTag('view_status', btn.dataset.status, btn.dataset.status === 'Active' ? 'tg-green' : 'tg-gray');

  const wrapper = document.getElementById('view_skills_wrapper');
  const list = document.getElementById('view_skills_list');
  list.innerHTML = '';
  const skills = parseSkills(btn.dataset.skills);

  if (btn.dataset.role === 'Staff' && skills.length > 0) {
    wrapper.classList.remove('d-none');
    skills.forEach(function (skill) {
      const span = document.createElement('span');
      span.className = 'skill-badge';
      span.textContent = skill;
      list.appendChild(span);
    });
  } else {
    wrapper.classList.add('d-none');
  }
});

document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  if (!btn) return;
  document.getElementById('delete_user_id').value = btn.dataset.id;
  document.getElementById('delete_user_name').textContent = btn.dataset.name;
});

let userSearchDebounce;
document.getElementById('searchInput').addEventListener('input', function () {
  clearTimeout(userSearchDebounce);
  userSearchDebounce = setTimeout(function () { document.getElementById('filterForm').submit(); }, 500);
});
</script>

</body>
</html>