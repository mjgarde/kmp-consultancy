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

    if ($action === 'assign') {
        $requestId = $_POST['request_id'] ?? null;
        $userId    = $_POST['user_id'] ?? null;

        if ($requestId && $userId) {
            $stmt = $pdo->prepare(
                "UPDATE service_requests SET assigned_to = ?, assigned_by = ? WHERE request_id = ?"
            );
            $stmt->execute([$userId, $_SESSION['admin_id'], $requestId]);

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
            (SELECT COUNT(*) FROM service_requests sr WHERE sr.assigned_to = u.user_id AND sr.status IN ('New', 'In Progress')) AS workload
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/resource_matching.css">
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Resource Matching</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Assign the most suitable staff to pending service requests.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <div class="row g-3">

        <div class="col-lg-5">
          <section class="card border-0 shadow-sm h-100">
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Pending Service Requests</h2>
              <p class="small mb-0">Select a request to find recommended staff.</p>
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
                    <div class="card-body">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div class="fw-semibold small"><?= htmlspecialchars($request['request_title']) ?></div>
                          <div style="font-size:.75rem; color:var(--ink-soft);"><?= htmlspecialchars($request['company_name']) ?></div>
                        </div>
                        <span class="badge-new">New</span>
                      </div>
                      <?php if (!empty($request['required_skill'])): ?>
                        <div class="mt-2"><span class="skill-badge"><?= htmlspecialchars($request['required_skill']) ?></span></div>
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
            <div class="card-header">
              <h2 class="h6 fw-bold mb-0">Recommended Staff</h2>
              <p class="small mb-0" id="matching_subtitle">Select a service request first.</p>
            </div>
            <div class="card-body p-3" id="matching_panel">

              <div id="matching_empty_state" class="text-center py-5">
                <i class="fa-regular fa-hand-pointer fs-3 mb-2 d-block"></i>
                <p class="small mb-0">Choose a service request on the left to view recommended staff.</p>
              </div>

              <div id="matching_content" class="d-none">

                <form method="POST" id="assignForm">
                  <input type="hidden" name="action" value="assign">
                  <input type="hidden" name="request_id" id="assign_request_id">
                  <input type="hidden" name="user_id" id="assign_user_id">
                </form>

                <div class="mb-3 p-3" style="background-color:var(--navy-soft); border-radius:12px;">
                  <div class="fw-semibold small" id="selected_request_title"></div>
                  <div class="small" id="selected_request_company" style="color:var(--ink-soft);"></div>
                  <p class="small mt-2 mb-0" id="selected_request_details" style="color:var(--ink-soft);"></p>
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
        <div class="card-header">
          <h2 class="h6 fw-bold mb-0">Recently Assigned</h2>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Request</th>
                <th scope="col" class="d-none d-md-table-cell">Client</th>
                <th scope="col">Assigned To</th>
                <th scope="col" class="d-none d-lg-table-cell">Assigned By</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($assignedRequests)): ?>
                <tr>
                  <td colspan="5" class="text-center text-secondary py-4">No assignments yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($assignedRequests as $row): ?>
                  <?php
                    $statusClass = 'status-default';
                    if ($row['status'] === 'In Progress') { $statusClass = 'status-in-progress'; }
                    if ($row['status'] === 'Completed') { $statusClass = 'status-completed'; }
                  ?>
                  <tr>
                    <td class="small fw-semibold"><?= htmlspecialchars($row['request_title']) ?></td>
                    <td class="small d-none d-md-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars($row['company_name']) ?></td>
                    <td class="small"><?= htmlspecialchars(trim(($row['staff_firstname'] ?? '') . ' ' . ($row['staff_lastname'] ?? ''))) ?></td>
                    <td class="small d-none d-lg-table-cell" style="color:var(--ink-soft);"><?= htmlspecialchars(trim(($row['assigned_by_firstname'] ?? '-') . ' ' . ($row['assigned_by_lastname'] ?? ''))) ?></td>
                    <td class="small">
                      <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span>
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
        <button type="button" class="btn btn-confirm" id="confirm_assign_btn">Confirm Assign</button>
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
        '<div style="font-size:.75rem; color:var(--ink-soft);">' +
          '<i class="fa-regular fa-clock"></i> ' + staff.workload + ' active task(s) &middot; ' + escapeHtml(staff.status) +
        '</div>' +
      '</div>' +
      '<button type="button" class="btn btn-assign flex-shrink-0" ' +
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