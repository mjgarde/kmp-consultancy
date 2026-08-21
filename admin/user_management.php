<?php
session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {

        $userId         = $_POST['user_id'] ?? null;
        $firstname      = trim($_POST['firstname'] ?? '');
        $middlename     = trim($_POST['middlename'] ?? '');
        $lastname       = trim($_POST['lastname'] ?? '');
        $birthday       = $_POST['birthday'] ?? '';
        $gender         = $_POST['gender'] ?? '';
        $address        = trim($_POST['address'] ?? '');
        $contactNumber  = trim($_POST['contact_number'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $role           = $_POST['role'] ?? '';
        $password       = $_POST['password'] ?? '';
        $skillsRaw      = $_POST['skills'] ?? '';

        $skills = [];
        if ($role === 'Staff' && $skillsRaw !== '') {
            $decoded = json_decode($skillsRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $s) {
                    $s = trim($s);
                    if ($s !== '') $skills[] = $s;
                }
            }
        }

        if ($firstname === '') $errors[] = 'First name is required.';
        if ($lastname === '') $errors[] = 'Last name is required.';
        if ($birthday === '') $errors[] = 'Birthday is required.';
        if (!in_array($gender, ['Male', 'Female'])) $errors[] = 'Please select a gender.';
        if ($address === '') $errors[] = 'Address is required.';
        if ($contactNumber === '') {
            $errors[] = 'Contact number is required.';
        } elseif (!preg_match('/^[0-9+\s\-()]+$/', $contactNumber)) {
            $errors[] = 'Contact number contains invalid characters.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (!in_array($role, ['Manager', 'Supervisor', 'Staff'])) $errors[] = 'Please select a role.';

        if ($action === 'add' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if (empty($errors)) {
            $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
            $checkStmt->execute([$email, $userId ?? 0]);

            if ($checkStmt->fetch()) {
                $errors[] = 'An account with that email already exists.';
            } else {
                if ($action === 'add') {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO users (firstname, middlename, lastname, birthday, gender, address, contact_number, email, password, role)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([$firstname, $middlename, $lastname, $birthday, $gender, $address, $contactNumber, $email, $hashedPassword, $role]);
                    $newUserId = $pdo->lastInsertId();

                    if ($role === 'Staff' && !empty($skills)) {
                        $skillStmt = $pdo->prepare('INSERT INTO staff_skills (user_id, skill_name) VALUES (?, ?)');
                        foreach ($skills as $skillName) {
                            $skillStmt->execute([$newUserId, $skillName]);
                        }
                    }

                    $_SESSION['alert_type'] = 'success';
                    $_SESSION['alert_message'] = 'User account created successfully.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE users SET firstname = ?, middlename = ?, lastname = ?, birthday = ?, gender = ?, address = ?, contact_number = ?, email = ?, role = ? WHERE user_id = ?'
                    );
                    $stmt->execute([$firstname, $middlename, $lastname, $birthday, $gender, $address, $contactNumber, $email, $role, $userId]);

                    if (!empty($password)) {
                        if (strlen($password) < 8) {
                            $errors[] = 'Password must be at least 8 characters long.';
                        } else {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $pwStmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                            $pwStmt->execute([$hashedPassword, $userId]);
                        }
                    }

                    $delSkillsStmt = $pdo->prepare('DELETE FROM staff_skills WHERE user_id = ?');
                    $delSkillsStmt->execute([$userId]);

                    if ($role === 'Staff' && !empty($skills)) {
                        $skillStmt = $pdo->prepare('INSERT INTO staff_skills (user_id, skill_name) VALUES (?, ?)');
                        foreach ($skills as $skillName) {
                            $skillStmt->execute([$userId, $skillName]);
                        }
                    }

                    $_SESSION['alert_type'] = 'success';
                    $_SESSION['alert_message'] = 'User account updated successfully.';
                }

                header('Location: user_management.php');
                exit;
            }
        }

        if (!empty($errors)) {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
            header('Location: user_management.php');
            exit;
        }

    } elseif ($action === 'delete') {

        $userId = $_POST['user_id'] ?? null;
        $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);

        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'User account deleted successfully.';
        header('Location: user_management.php');
        exit;

    } elseif ($action === 'toggle_status') {

        $userId = $_POST['user_id'] ?? null;
        $newStatus = $_POST['new_status'] ?? 'Active';
        $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
        $stmt->execute([$newStatus, $userId]);

        header('Location: user_management.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$searchTerm = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$query = 'SELECT * FROM users WHERE 1=1';
$params = [];

if ($searchTerm !== '') {
    $query .= ' AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)';
    $like = '%' . $searchTerm . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (in_array($roleFilter, ['Manager', 'Supervisor', 'Staff'])) {
    $query .= ' AND role = ?';
    $params[] = $roleFilter;
}

$query .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$skillsStmt = $pdo->query('SELECT user_id, skill_name FROM staff_skills ORDER BY skill_id ASC');
$skillsByUser = [];
foreach ($skillsStmt->fetchAll() as $row) {
    $skillsByUser[$row['user_id']][] = $row['skill_name'];
}

$existingSkillsStmt = $pdo->query('SELECT DISTINCT skill_name FROM staff_skills ORDER BY skill_name ASC');
$existingSkills = $existingSkillsStmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KMP ConsultHub - User Management</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<style>
.form-control:focus, .form-select:focus, .form-control:hover, .form-select:hover {
  border-color:#2F4858;
  box-shadow:0 0 0 .2rem rgba(47,72,88,.15);
  outline:none;
}
.skill-tag {
  display:inline-flex;
  align-items:center;
  gap:.4rem;
  background-color:#2F4858;
  color:#fff;
  padding:.3rem .6rem;
  border-radius:999px;
  font-size:.8rem;
}
.skill-tag button {
  background:none;
  border:none;
  color:#fff;
  line-height:1;
  padding:0;
  opacity:.8;
}
.skill-tag button:hover {
  opacity:1;
}
.skill-badge {
  background-color:#3AA394;
  color:#fff;
  font-size:.7rem;
  padding:.25rem .5rem;
  border-radius:999px;
  margin:0 .25rem .25rem 0;
  display:inline-block;
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">User Management</h1>
          <p class="dashboard-subtitle text-secondary small mb-0 d-none d-sm-block">Manage manager, supervisor, and staff accounts.</p>
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
              <a href="../config/logout.php" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="user-management-toolbar card border-0 shadow-sm mb-3">
        <div class="card-body p-2 p-md-3">
          <form class="user-management-filter-form row g-2 align-items-center" method="GET">
            <div class="col-12 col-md-5">
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by name or email" value="<?= htmlspecialchars($searchTerm) ?>">
              </div>
            </div>
            <div class="col-6 col-md-3">
              <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="Manager" <?= $roleFilter === 'Manager' ? 'selected' : '' ?>>Manager</option>
                <option value="Supervisor" <?= $roleFilter === 'Supervisor' ? 'selected' : '' ?>>Supervisor</option>
                <option value="Staff" <?= $roleFilter === 'Staff' ? 'selected' : '' ?>>Staff</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <button type="submit" class="btn w-100" style="background-color:#3AA394; color:#fff;">
                <i class="fa-solid fa-filter"></i> <span class="d-none d-sm-inline">Filter</span>
              </button>
            </div>
            <div class="col-12 col-md-2 text-md-end">
              <button type="button" class="btn w-100" style="background-color:#2F4858; color:#fff;" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fa-solid fa-plus"></i> Add User
              </button>
            </div>
          </form>
        </div>
      </section>

      <section class="user-management-table card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" class="small text-uppercase text-secondary">Name</th>
                <th scope="col" class="small text-uppercase text-secondary d-none d-md-table-cell">Email</th>
                <th scope="col" class="small text-uppercase text-secondary d-none d-lg-table-cell">Contact Number</th>
                <th scope="col" class="small text-uppercase text-secondary">Role</th>
                <th scope="col" class="small text-uppercase text-secondary d-none d-sm-table-cell">Status</th>
                <th scope="col" class="small text-uppercase text-secondary text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($users)): ?>
                <tr>
                  <td colspan="6" class="text-center text-secondary py-4">No users found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($users as $user): ?>
                  <?php $userSkills = $skillsByUser[$user['user_id']] ?? []; ?>
                  <tr class="user-management-row" role="button" style="cursor:pointer;"
                    data-bs-toggle="modal" data-bs-target="#viewUserModal"
                    data-firstname="<?= htmlspecialchars($user['firstname']) ?>"
                    data-middlename="<?= htmlspecialchars($user['middlename'] ?? '') ?>"
                    data-lastname="<?= htmlspecialchars($user['lastname']) ?>"
                    data-birthday="<?= htmlspecialchars($user['birthday']) ?>"
                    data-gender="<?= htmlspecialchars($user['gender']) ?>"
                    data-address="<?= htmlspecialchars($user['address']) ?>"
                    data-contact="<?= htmlspecialchars($user['contact_number']) ?>"
                    data-email="<?= htmlspecialchars($user['email']) ?>"
                    data-role="<?= htmlspecialchars($user['role']) ?>"
                    data-status="<?= htmlspecialchars($user['status']) ?>"
                    data-skills="<?= htmlspecialchars(json_encode($userSkills)) ?>">
                    <td>
                      <div class="fw-semibold small"><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></div>
                      <div class="text-secondary d-md-none" style="font-size:.72rem;"><?= htmlspecialchars($user['email']) ?></div>
                    </td>
                    <td class="text-secondary small d-none d-md-table-cell"><?= htmlspecialchars($user['email']) ?></td>
                    <td class="text-secondary small d-none d-lg-table-cell"><?= htmlspecialchars($user['contact_number']) ?></td>
                    <td class="small text-dark"><?= htmlspecialchars($user['role']) ?></td>
                    <td class="d-none d-sm-table-cell">
                      <span class="badge rounded-pill" style="background: transparent; color: gray;">
                      <?= htmlspecialchars($user['status']) ?>
                      </span>
                    </td>
                    <td class="text-end" onclick="event.stopPropagation();">
                      <button type="button" class="btn btn-sm" style="background-color:#2F4858; color:#fff;" title="Edit"
                        data-bs-toggle="modal" data-bs-target="#editUserModal"
                        data-id="<?= $user['user_id'] ?>"
                        data-firstname="<?= htmlspecialchars($user['firstname']) ?>"
                        data-middlename="<?= htmlspecialchars($user['middlename'] ?? '') ?>"
                        data-lastname="<?= htmlspecialchars($user['lastname']) ?>"
                        data-birthday="<?= htmlspecialchars($user['birthday']) ?>"
                        data-gender="<?= htmlspecialchars($user['gender']) ?>"
                        data-address="<?= htmlspecialchars($user['address']) ?>"
                        data-contact="<?= htmlspecialchars($user['contact_number']) ?>"
                        data-email="<?= htmlspecialchars($user['email']) ?>"
                        data-role="<?= htmlspecialchars($user['role']) ?>"
                        data-skills="<?= htmlspecialchars(json_encode($userSkills)) ?>">
                        <i class="fa-regular fa-pen-to-square"></i>
                      </button>
                      <button type="button" class="btn btn-sm" style="background-color:#DF6E4F; color:#fff;" title="Delete"
                        data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                        data-id="<?= $user['user_id'] ?>"
                        data-name="<?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>">
                        <i class="fa-regular fa-trash-can"></i>
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

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" id="addUserForm" novalidate>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="skills" id="add_skills_input" value="[]">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Add User</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">First Name</label>
              <input type="text" name="firstname" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Middle Name</label>
              <input type="text" name="middlename" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Last Name</label>
              <input type="text" name="lastname" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Birthday</label>
              <input type="date" name="birthday" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Gender</label>
              <select name="gender" class="form-select" required>
                <option value="" selected disabled>Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Address</label>
              <input type="text" name="address" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact Number</label>
              <input type="tel" name="contact_number" class="form-control" placeholder="e.g. +639171234567 or 09171234567" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Role</label>
              <select name="role" class="form-select" id="add_role" required>
                <option value="" selected disabled>Select role</option>
                <option value="Manager">Manager</option>
                <option value="Supervisor">Supervisor</option>
                <option value="Staff">Staff</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password</label>
              <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required>
            </div>
            <div class="col-12 d-none" id="add_skills_wrapper">
              <label class="form-label fw-semibold">Skills</label>
              <div class="row g-2 mb-2">
                <div class="col-md-6">
                  <div class="input-group">
                    <select class="form-select" id="add_skill_select">
                      <option value="" selected disabled>Select existing skill</option>
                      <?php foreach ($existingSkills as $existingSkill): ?>
                        <option value="<?= htmlspecialchars($existingSkill) ?>"><?= htmlspecialchars($existingSkill) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" id="add_skill_select_btn">Add</button>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="input-group">
                    <input type="text" class="form-control" id="add_skill_input" placeholder="Add new skill">
                    <button type="button" class="btn btn-outline-secondary" id="add_skill_btn">Add</button>
                  </div>
                </div>
              </div>
              <div id="add_skills_list" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn" style="background-color:#2F4858; color:#fff;">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" id="editUserForm" novalidate>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="user_id" id="edit_user_id">
        <input type="hidden" name="skills" id="edit_skills_input" value="[]">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Edit User</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label fw-semibold">First Name</label>
              <input type="text" name="firstname" id="edit_firstname" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Middle Name</label>
              <input type="text" name="middlename" id="edit_middlename" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Last Name</label>
              <input type="text" name="lastname" id="edit_lastname" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Birthday</label>
              <input type="date" name="birthday" id="edit_birthday" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Gender</label>
              <select name="gender" id="edit_gender" class="form-select" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Address</label>
              <input type="text" name="address" id="edit_address" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Contact Number</label>
              <input type="tel" name="contact_number" id="edit_contact_number" class="form-control" placeholder="e.g. +639171234567 or 09171234567" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Role</label>
              <select name="role" id="edit_role" class="form-select" required>
                <option value="Manager">Manager</option>
                <option value="Supervisor">Supervisor</option>
                <option value="Staff">Staff</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email Address</label>
              <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">New Password</label>
              <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
            </div>
            <div class="col-12 d-none" id="edit_skills_wrapper">
              <label class="form-label fw-semibold">Skills</label>
              <div class="row g-2 mb-2">
                <div class="col-md-6">
                  <div class="input-group">
                    <select class="form-select" id="edit_skill_select">
                      <option value="" selected disabled>Select existing skill</option>
                      <?php foreach ($existingSkills as $existingSkill): ?>
                        <option value="<?= htmlspecialchars($existingSkill) ?>"><?= htmlspecialchars($existingSkill) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-outline-secondary" id="edit_skill_select_btn">Add</button>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="input-group">
                    <input type="text" class="form-control" id="edit_skill_input" placeholder="Add new skill">
                    <button type="button" class="btn btn-outline-secondary" id="edit_skill_btn">Add</button>
                  </div>
                </div>
              </div>
              <div id="edit_skills_list" class="d-flex flex-wrap gap-2"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn" style="background-color:#2F4858; color:#fff;">Update User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold">User Details</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="text-secondary small">First Name</div>
            <div class="fw-semibold" id="view_firstname"></div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary small">Middle Name</div>
            <div class="fw-semibold" id="view_middlename"></div>
          </div>
          <div class="col-md-4">
            <div class="text-secondary small">Last Name</div>
            <div class="fw-semibold" id="view_lastname"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Birthday</div>
            <div class="fw-semibold" id="view_birthday"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Gender</div>
            <div class="fw-semibold" id="view_gender"></div>
          </div>
          <div class="col-12">
            <div class="text-secondary small">Address</div>
            <div class="fw-semibold" id="view_address"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Contact Number</div>
            <div class="fw-semibold" id="view_contact"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Role</div>
            <div class="fw-semibold" id="view_role"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Email Address</div>
            <div class="fw-semibold" id="view_email"></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small">Status</div>
            <div class="fw-semibold" id="view_status"></div>
          </div>
          <div class="col-12 d-none" id="view_skills_wrapper">
            <div class="text-secondary small mb-1">Skills</div>
            <div id="view_skills_list"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer p-3">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="delete_user_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Delete User</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="delete_user_name"></strong>? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete User</button>
        </div>
      </form>
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
    tag.innerHTML = skill.replace(/</g, '&lt;').replace(/>/g, '&gt;') + ' <button type="button">&times;</button>';
    tag.querySelector('button').addEventListener('click', function () {
      onRemove(index);
    });
    container.appendChild(tag);
  });
  hiddenInput.value = JSON.stringify(skillsArray);
}

function toggleSkillsWrapper(roleValue, wrapperEl) {
  if (roleValue === 'Staff') {
    wrapperEl.classList.remove('d-none');
  } else {
    wrapperEl.classList.add('d-none');
  }
}

const addRoleSelect = document.getElementById('add_role');
const addSkillsWrapper = document.getElementById('add_skills_wrapper');
const addSkillsList = document.getElementById('add_skills_list');
const addSkillInput = document.getElementById('add_skill_input');
const addSkillBtn = document.getElementById('add_skill_btn');
const addSkillSelect = document.getElementById('add_skill_select');
const addSkillSelectBtn = document.getElementById('add_skill_select_btn');
const addSkillsHidden = document.getElementById('add_skills_input');

addRoleSelect.addEventListener('change', function () {
  toggleSkillsWrapper(this.value, addSkillsWrapper);
});

function removeAddSkill(idx) {
  addSkills.splice(idx, 1);
  renderSkillTags(addSkillsList, addSkills, addSkillsHidden, removeAddSkill);
}

function addSkillToAddList(val) {
  val = val.trim();
  if (val === '') return;
  if (addSkills.some(function (s) { return s.toLowerCase() === val.toLowerCase(); })) return;
  addSkills.push(val);
  renderSkillTags(addSkillsList, addSkills, addSkillsHidden, removeAddSkill);
}

addSkillBtn.addEventListener('click', function () {
  addSkillToAddList(addSkillInput.value);
  addSkillInput.value = '';
});

addSkillInput.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    addSkillBtn.click();
  }
});

addSkillSelectBtn.addEventListener('click', function () {
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

const editRoleSelect = document.getElementById('edit_role');
const editSkillsWrapper = document.getElementById('edit_skills_wrapper');
const editSkillsList = document.getElementById('edit_skills_list');
const editSkillInput = document.getElementById('edit_skill_input');
const editSkillBtn = document.getElementById('edit_skill_btn');
const editSkillSelect = document.getElementById('edit_skill_select');
const editSkillSelectBtn = document.getElementById('edit_skill_select_btn');
const editSkillsHidden = document.getElementById('edit_skills_input');

function removeEditSkill(idx) {
  editSkills.splice(idx, 1);
  renderSkillTags(editSkillsList, editSkills, editSkillsHidden, removeEditSkill);
}

function addSkillToEditList(val) {
  val = val.trim();
  if (val === '') return;
  if (editSkills.some(function (s) { return s.toLowerCase() === val.toLowerCase(); })) return;
  editSkills.push(val);
  renderSkillTags(editSkillsList, editSkills, editSkillsHidden, removeEditSkill);
}

editRoleSelect.addEventListener('change', function () {
  toggleSkillsWrapper(this.value, editSkillsWrapper);
});

editSkillBtn.addEventListener('click', function () {
  addSkillToEditList(editSkillInput.value);
  editSkillInput.value = '';
});

editSkillInput.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    editSkillBtn.click();
  }
});

editSkillSelectBtn.addEventListener('click', function () {
  if (editSkillSelect.value === '') return;
  addSkillToEditList(editSkillSelect.value);
  editSkillSelect.value = '';
});

document.getElementById('editUserModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
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

  editSkills = [];
  try {
    const parsed = JSON.parse(btn.dataset.skills || '[]');
    if (Array.isArray(parsed)) editSkills = parsed;
  } catch (e) {
    editSkills = [];
  }
  renderSkillTags(editSkillsList, editSkills, editSkillsHidden, removeEditSkill);
  toggleSkillsWrapper(btn.dataset.role, editSkillsWrapper);
});

document.getElementById('viewUserModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('view_firstname').textContent = btn.dataset.firstname;
  document.getElementById('view_middlename').textContent = btn.dataset.middlename || '-';
  document.getElementById('view_lastname').textContent = btn.dataset.lastname;
  document.getElementById('view_birthday').textContent = btn.dataset.birthday;
  document.getElementById('view_gender').textContent = btn.dataset.gender;
  document.getElementById('view_address').textContent = btn.dataset.address;
  document.getElementById('view_contact').textContent = btn.dataset.contact;
  document.getElementById('view_role').textContent = btn.dataset.role;
  document.getElementById('view_email').textContent = btn.dataset.email;
  document.getElementById('view_status').textContent = btn.dataset.status;

  const viewSkillsWrapper = document.getElementById('view_skills_wrapper');
  const viewSkillsList = document.getElementById('view_skills_list');
  viewSkillsList.innerHTML = '';

  let viewSkills = [];
  try {
    const parsed = JSON.parse(btn.dataset.skills || '[]');
    if (Array.isArray(parsed)) viewSkills = parsed;
  } catch (e) {
    viewSkills = [];
  }

  if (btn.dataset.role === 'Staff' && viewSkills.length > 0) {
    viewSkillsWrapper.classList.remove('d-none');
    viewSkills.forEach(function (skill) {
      const span = document.createElement('span');
      span.className = 'skill-badge';
      span.textContent = skill;
      viewSkillsList.appendChild(span);
    });
  } else {
    viewSkillsWrapper.classList.add('d-none');
  }
});

document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('delete_user_id').value = btn.dataset.id;
  document.getElementById('delete_user_name').textContent = btn.dataset.name;
});

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>