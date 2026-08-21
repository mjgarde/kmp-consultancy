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

    if ($action === 'assign') {
        $requestId = $_POST['request_id'] ?? null;
        $userId    = $_POST['user_id'] ?? null;

        if ($requestId && $userId) {
            $stmt = $pdo->prepare(
                "UPDATE service_requests SET assigned_to = ?, assigned_by = ?, status = 'In Progress' WHERE request_id = ?"
            );
            $stmt->execute([$userId, $_SESSION['manager_id'], $requestId]);

            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Staff assigned successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = 'Unable to assign staff. Please try again.';
        }

        header('Location: resource_matching.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$pendingStmt = $pdo->query(
    "SELECT sr.request_id, sr.request_title, sr.request_details, sr.required_skill, sr.status, sr.created_at,
            c.company_name, c.contact_person
     FROM service_requests sr
     INNER JOIN clients c ON sr.client_id = c.client_id
     WHERE sr.assigned_to IS NULL AND sr.status = 'New'
     ORDER BY sr.created_at ASC"
);
$pendingRequests = $pendingStmt->fetchAll();

$staffStmt = $pdo->query(
    "SELECT u.user_id, u.firstname, u.lastname, u.status,
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status = 'In Progress') AS workload
     FROM users u
     WHERE u.role = 'Staff'
     ORDER BY u.firstname ASC"
);
$staffList = $staffStmt->fetchAll();

$skillsByStaffStmt = $pdo->query('SELECT user_id, skill_name FROM staff_skills ORDER BY skill_id ASC');
$skillsByStaff = [];
foreach ($skillsByStaffStmt->fetchAll() as $row) {
    $skillsByStaff[$row['user_id']][] = $row['skill_name'];
}

$assignedStmt = $pdo->query(
    "SELECT sr.request_id, sr.request_title, sr.status, sr.updated_at,
            c.company_name,
            u.firstname AS staff_firstname, u.lastname AS staff_lastname,
            ab.firstname AS assigned_by_firstname, ab.lastname AS assigned_by_lastname
     FROM service_requests sr
     INNER JOIN clients c ON sr.client_id = c.client_id
     LEFT JOIN users u ON sr.assigned_to = u.user_id
     LEFT JOIN users ab ON sr.assigned_by = ab.user_id
     WHERE sr.assigned_to IS NOT NULL
     ORDER BY sr.updated_at DESC
     LIMIT 10"
);
$assignedRequests = $assignedStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resource Matching</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/dashboard.css">
<style>
.form-control:focus, .form-select:focus, .form-control:hover, .form-select:hover {
  border-color:#2F4858;
  box-shadow:0 0 0 .2rem rgba(47,72,88,.15);
  outline:none;
}
.request-card {
  cursor:pointer;
  transition:border-color .15s ease;
  border:1px solid rgba(0,0,0,.1);
}
.request-card:hover, .request-card.active {
  border-color:#3AA394;
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
.skill-badge.matched {
  background-color:#2F4858;
}
.skill-badge.unmatched {
  background-color:#adb5bd;
}
.staff-match-row {
  border:1px solid rgba(0,0,0,.08);
  border-radius:.5rem;
}
.match-score-pill {
  font-size:.75rem;
  font-weight:600;
  padding:.25rem .6rem;
  border-radius:999px;
}
.match-score-high { background-color:#3AA39422; color:#2F4858; }
.match-score-mid { background-color:#DF6E4F22; color:#DF6E4F; }
.match-score-none { background-color:#e9ecef; color:#6c757d; }
</style>
</head>
<body class="bg-light">

<div class="dashboard-layout d-flex">

<?php require __DIR__ . '/../includes/manager/sidebar.php'; ?>

  <div class="dashboard-main flex-grow-1" style="min-width:0;">

    <header class="dashboard-topbar bg-white border-bottom d-flex align-items-center justify-content-between px-3 px-md-4">
      <div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-link text-dark p-0 d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
          <i class="fa-solid fa-bars fs-5"></i>
        </button>
        <div>
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Resource Matching</h1>
          <p class="dashboard-subtitle text-secondary small mb-0 d-none d-sm-block">Assign the most suitable staff to pending service requests.</p>
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
              <a href="../config/logout.php?role=manager" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
              </a>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="row g-3">

        <div class="col-lg-5">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
              <h2 class="h6 fw-bold mb-0">Pending Service Requests</h2>
              <p class="text-secondary small mb-0">Select a request to find recommended staff.</p>
            </div>
            <div class="card-body p-2 p-md-3" style="max-height:70vh; overflow-y:auto;">
              <?php if (empty($pendingRequests)): ?>
                <p class="text-secondary small text-center py-4 mb-0">No pending service requests.</p>
              <?php else: ?>
                <?php foreach ($pendingRequests as $request): ?>
                  <div class="request-card card mb-2"
                    data-request-id="<?= $request['request_id'] ?>"
                    data-request-title="<?= htmlspecialchars($request['request_title']) ?>"
                    data-request-details="<?= htmlspecialchars($request['request_details'] ?? '') ?>"
                    data-required-skill="<?= htmlspecialchars($request['required_skill'] ?? '') ?>"
                    data-company="<?= htmlspecialchars($request['company_name']) ?>">
                    <div class="card-body p-3">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div class="fw-semibold small"><?= htmlspecialchars($request['request_title']) ?></div>
                          <div class="text-secondary" style="font-size:.75rem;"><?= htmlspecialchars($request['company_name']) ?></div>
                        </div>
                        <span class="badge rounded-pill" style="background-color:#DF6E4F22; color:#DF6E4F; font-size:.7rem;">New</span>
                      </div>
                      <?php if (!empty($request['required_skill'])): ?>
                        <span class="skill-badge mt-2"><?= htmlspecialchars($request['required_skill']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>
        </div>

        <div class="col-lg-7">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
              <h2 class="h6 fw-bold mb-0">Recommended Staff</h2>
              <p class="text-secondary small mb-0" id="matching_subtitle">Select a service request first.</p>
            </div>
            <div class="card-body p-3" id="matching_panel">

              <div id="matching_empty_state" class="text-center text-secondary py-5">
                <i class="fa-regular fa-hand-pointer fs-3 mb-2 d-block"></i>
                <p class="small mb-0">Choose a service request on the left to view recommended staff.</p>
              </div>

              <div id="matching_content" class="d-none">

                <form method="POST" id="assignForm">
                  <input type="hidden" name="action" value="assign">
                  <input type="hidden" name="request_id" id="assign_request_id">
                  <input type="hidden" name="user_id" id="assign_user_id">
                </form>

                <div class="mb-3">
                  <div class="fw-semibold small" id="selected_request_title"></div>
                  <div class="text-secondary small" id="selected_request_company"></div>
                  <p class="text-secondary small mt-2 mb-0" id="selected_request_details"></p>
                </div>

                <div class="mb-3">
                  <label class="form-label fw-semibold small">Required Skill</label>
                  <div id="required_skill_display">
                    <span class="text-secondary small">No specific skill set for this request — showing all staff.</span>
                  </div>
                </div>

                <div id="staff_match_list" class="d-flex flex-column gap-2"></div>

              </div>

            </div>
          </section>
        </div>

      </div>

      <section class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white border-bottom">
          <h2 class="h6 fw-bold mb-0">Recently Assigned</h2>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col" class="small text-uppercase text-secondary">Request</th>
                <th scope="col" class="small text-uppercase text-secondary d-none d-md-table-cell">Client</th>
                <th scope="col" class="small text-uppercase text-secondary">Assigned To</th>
                <th scope="col" class="small text-uppercase text-secondary d-none d-lg-table-cell">Assigned By</th>
                <th scope="col" class="small text-uppercase text-secondary">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($assignedRequests)): ?>
                <tr>
                  <td colspan="5" class="text-center text-secondary py-4">No assignments yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($assignedRequests as $row): ?>
                  <tr>
                    <td class="small fw-semibold"><?= htmlspecialchars($row['request_title']) ?></td>
                    <td class="small text-secondary d-none d-md-table-cell"><?= htmlspecialchars($row['company_name']) ?></td>
                    <td class="small"><?= htmlspecialchars(trim(($row['staff_firstname'] ?? '') . ' ' . ($row['staff_lastname'] ?? ''))) ?></td>
                    <td class="small text-secondary d-none d-lg-table-cell"><?= htmlspecialchars(trim(($row['assigned_by_firstname'] ?? '-') . ' ' . ($row['assigned_by_lastname'] ?? ''))) ?></td>
                    <td class="small">
                      <span class="badge rounded-pill" style="background:transparent; color:gray;"><?= htmlspecialchars($row['status']) ?></span>
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

<div class="modal fade" id="confirmAssignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5 fw-bold">Confirm Assignment</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Assign <strong id="confirm_staff_name"></strong> to <strong id="confirm_request_title"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn" style="background-color:#2F4858; color:#fff;" id="confirm_assign_btn">Confirm Assign</button>
      </div>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
const staffData = <?= json_encode(array_map(function ($s) use ($skillsByStaff) {
    return [
        'user_id'   => (int) $s['user_id'],
        'name'      => $s['firstname'] . ' ' . $s['lastname'],
        'status'    => $s['status'],
        'workload'  => (int) $s['workload'],
        'skills'    => $skillsByStaff[$s['user_id']] ?? [],
    ];
}, $staffList)) ?>;

let selectedRequestId = null;
let selectedRequestTitle = '';

const matchingEmptyState = document.getElementById('matching_empty_state');
const matchingContent = document.getElementById('matching_content');
const matchingSubtitle = document.getElementById('matching_subtitle');
const requiredSkillDisplay = document.getElementById('required_skill_display');
const staffMatchList = document.getElementById('staff_match_list');

document.querySelectorAll('.request-card').forEach(function (card) {
  card.addEventListener('click', function () {
    document.querySelectorAll('.request-card').forEach(function (c) { c.classList.remove('active'); });
    card.classList.add('active');

    selectedRequestId = card.dataset.requestId;
    selectedRequestTitle = card.dataset.requestTitle;
    const requiredSkill = card.dataset.requiredSkill || '';

    document.getElementById('selected_request_title').textContent = card.dataset.requestTitle;
    document.getElementById('selected_request_company').textContent = card.dataset.company;
    document.getElementById('selected_request_details').textContent = card.dataset.requestDetails || 'No additional details provided.';

    if (requiredSkill !== '') {
      requiredSkillDisplay.innerHTML = '<span class="skill-badge matched">' + escapeHtml(requiredSkill) + '</span>';
    } else {
      requiredSkillDisplay.innerHTML = '<span class="text-secondary small">No specific skill set for this request — showing all staff.</span>';
    }

    matchingSubtitle.textContent = 'Showing recommended staff for this request.';
    matchingEmptyState.classList.add('d-none');
    matchingContent.classList.remove('d-none');

    renderStaffMatches(requiredSkill);
  });
});

function renderStaffMatches(requiredSkill) {
  staffMatchList.innerHTML = '';

  const ranked = staffData.map(function (staff) {
    const hasSkill = requiredSkill === '' ? null : staff.skills.some(function (sk) {
      return sk.toLowerCase() === requiredSkill.toLowerCase();
    });
    return Object.assign({}, staff, { hasSkill: hasSkill });
  });

  ranked.sort(function (a, b) {
    if (requiredSkill !== '') {
      if (a.hasSkill !== b.hasSkill) return a.hasSkill ? -1 : 1;
    }
    if (a.status !== b.status) return a.status === 'Active' ? -1 : 1;
    return a.workload - b.workload;
  });

  if (ranked.length === 0) {
    staffMatchList.innerHTML = '<p class="text-secondary small text-center py-3 mb-0">No staff accounts found.</p>';
    return;
  }

  ranked.forEach(function (staff) {
    const row = document.createElement('div');
    row.className = 'staff-match-row p-3 d-flex justify-content-between align-items-start gap-2';

    let scoreHtml = '';
    if (requiredSkill !== '') {
      if (staff.hasSkill) {
        scoreHtml = '<span class="match-score-pill match-score-high">Skill Match</span>';
      } else {
        scoreHtml = '<span class="match-score-pill match-score-none">No Match</span>';
      }
    }

    const skillsHtml = staff.skills.length > 0
      ? staff.skills.map(function (sk) {
          const cls = (requiredSkill !== '' && sk.toLowerCase() === requiredSkill.toLowerCase()) ? 'matched' : '';
          return '<span class="skill-badge ' + cls + '">' + escapeHtml(sk) + '</span>';
        }).join('')
      : '<span class="text-secondary small">No skills on record</span>';

    row.innerHTML =
      '<div class="flex-grow-1">' +
        '<div class="d-flex align-items-center gap-2 mb-1">' +
          '<span class="fw-semibold small">' + escapeHtml(staff.name) + '</span>' +
          scoreHtml +
        '</div>' +
        '<div class="mb-2">' + skillsHtml + '</div>' +
        '<div class="text-secondary" style="font-size:.75rem;">' +
          '<i class="fa-regular fa-clock"></i> ' + staff.workload + ' active task(s) &middot; ' + escapeHtml(staff.status) +
        '</div>' +
      '</div>' +
      '<button type="button" class="btn btn-sm flex-shrink-0" style="background-color:#3AA394; color:#fff;" ' +
        (staff.status !== 'Active' ? 'disabled' : '') +
        ' data-staff-id="' + staff.user_id + '" data-staff-name="' + escapeHtml(staff.name) + '">Assign</button>';

    row.querySelector('button').addEventListener('click', function () {
      openConfirmModal(this.dataset.staffId, this.dataset.staffName);
    });

    staffMatchList.appendChild(row);
  });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function openConfirmModal(staffId, staffName) {
  document.getElementById('confirm_staff_name').textContent = staffName;
  document.getElementById('confirm_request_title').textContent = selectedRequestTitle;

  const modal = new bootstrap.Modal(document.getElementById('confirmAssignModal'));
  modal.show();

  document.getElementById('confirm_assign_btn').onclick = function () {
    document.getElementById('assign_request_id').value = selectedRequestId;
    document.getElementById('assign_user_id').value = staffId;
    document.getElementById('assignForm').submit();
  };
}

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>