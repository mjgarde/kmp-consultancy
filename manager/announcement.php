<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('MANAGER_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: ../login.php');
    exit;
}

$pdo = getConnection();
$currentUserId = $_SESSION['user_id'];
$validAudiences = ['All', 'Staff', 'Manager'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title    = trim($_POST['title'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $audience = $_POST['audience'] ?? 'All';

        $errors = [];
        if ($title === '') $errors[] = 'Title is required.';
        if ($message === '') $errors[] = 'Message is required.';
        if (!in_array($audience, $validAudiences)) $errors[] = 'Please select a valid audience.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO announcements (title, message, audience, created_by) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$title, $message, $audience, $currentUserId]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Announcement posted successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: announcements.php');
        exit;

    } elseif ($action === 'edit') {
        $announcementId = $_POST['announcement_id'] ?? null;
        $title    = trim($_POST['title'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        $audience = $_POST['audience'] ?? 'All';

        $errors = [];
        if ($title === '') $errors[] = 'Title is required.';
        if ($message === '') $errors[] = 'Message is required.';
        if (!in_array($audience, $validAudiences)) $errors[] = 'Please select a valid audience.';

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'UPDATE announcements SET title = ?, message = ?, audience = ? WHERE announcement_id = ?'
            );
            $stmt->execute([$title, $message, $audience, $announcementId]);
            $_SESSION['alert_type'] = 'success';
            $_SESSION['alert_message'] = 'Announcement updated successfully.';
        } else {
            $_SESSION['alert_type'] = 'error';
            $_SESSION['alert_message'] = implode(' ', $errors);
        }

        header('Location: announcements.php');
        exit;

    } elseif ($action === 'delete') {
        $announcementId = $_POST['announcement_id'] ?? null;
        $stmt = $pdo->prepare('DELETE FROM announcements WHERE announcement_id = ?');
        $stmt->execute([$announcementId]);
        $_SESSION['alert_type'] = 'success';
        $_SESSION['alert_message'] = 'Announcement deleted successfully.';
        header('Location: announcements.php');
        exit;
    }
}

$alertType    = $_SESSION['alert_type'] ?? null;
$alertMessage = $_SESSION['alert_message'] ?? null;
unset($_SESSION['alert_type'], $_SESSION['alert_message']);

$audienceFilter = $_GET['audience'] ?? '';

$query = "SELECT a.*, u.firstname, u.lastname
          FROM announcements a
          LEFT JOIN users u ON a.created_by = u.user_id
          WHERE 1=1";
$params = [];

if (in_array($audienceFilter, $validAudiences)) {
    $query .= ' AND a.audience = ?';
    $params[] = $audienceFilter;
}

$query .= ' ORDER BY a.created_at DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

$totalAnnouncements = (int) $pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();

function audiencePalette(string $audience): array
{
    return match ($audience) {
        'Staff' => ['bg' => '#E3F3EC', 'fg' => '#0F5F49'],
        'Manager' => ['bg' => '#FBF0DD', 'fg' => '#8A5A15'],
        default => ['bg' => '#E9ECF6', 'fg' => '#2E3E70'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Announcements | Manager</title>
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

.stat-card {
  background-color: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1rem 1.15rem;
  display: flex;
  align-items: center;
  gap: .85rem;
  height: 100%;
}
.stat-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1rem;
}
.stat-label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-soft); }
.stat-value { font-family: 'Lexend', sans-serif; font-size: 1.35rem; font-weight: 700; margin-top: .1rem; }

.btn-repo {
  border-radius: 7px;
  font-weight: 600;
  font-size: .82rem;
  border: 1px solid transparent;
}
.btn-repo-primary { background-color: var(--indigo); color: #fff; }
.btn-repo-primary:hover { background-color: var(--indigo-text); color: #fff; }
.btn-repo-danger { background-color: var(--danger-soft); color: var(--danger-text); border-color: var(--danger-border); }
.btn-repo-danger:hover { background-color: var(--danger); color: #fff; border-color: var(--danger); }

.repo-filter-pills a {
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: .35rem .9rem;
  font-size: .78rem;
  color: var(--ink-soft);
  text-decoration: none;
  background-color: #fff;
}
.repo-filter-pills a:hover { border-color: var(--indigo); color: var(--indigo-text); }

.announcement-item {
  border-bottom: 1px solid var(--line);
  padding: 1rem 1.15rem;
}
.announcement-item:last-child { border-bottom: none; }
.announcement-title { font-family: 'Lexend', sans-serif; font-weight: 700; font-size: .95rem; color: var(--ink); }
.announcement-message { font-size: .85rem; color: var(--ink-soft); white-space: pre-wrap; }
.announcement-meta { font-size: .74rem; color: var(--ink-soft); }
.audience-pill {
  font-size: .68rem;
  font-weight: 700;
  padding: .2rem .6rem;
  border-radius: 999px;
  white-space: nowrap;
}

.modal-content { border-radius: 14px; border: none; }
.modal-header { border-bottom: 1px solid var(--line); }
.modal-footer { border-top: 1px solid var(--line); }
.form-label.fw-semibold { font-size: .8rem; font-weight: 700 !important; color: var(--slate); text-transform: uppercase; letter-spacing: .02em; }
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
          <h1 class="dashboard-title h6 h5-md fw-bold mb-0">Announcements</h1>
          <p class="dashboard-subtitle small mb-0 d-none d-sm-block">Post updates and notices for staff and the team.</p>
        </div>
      </div>
    </header>

    <main class="dashboard-content p-3 p-md-4">

      <section class="row g-2 g-md-3 mb-3">
        <div class="col-12 col-md-4">
          <div class="stat-card">
            <span class="stat-icon" style="background-color:var(--indigo-soft);">
              <i class="fa-solid fa-bullhorn" style="color:var(--indigo-text);"></i>
            </span>
            <div class="overflow-hidden">
              <div class="stat-label text-truncate">Total Announcements</div>
              <div class="stat-value" style="color:var(--indigo-text);"><?= $totalAnnouncements ?></div>
            </div>
          </div>
        </div>
      </section>

      <section class="card mb-3">
        <div class="card-body p-2 p-md-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
          <nav class="repo-filter-pills d-flex flex-wrap gap-2" aria-label="Filter by audience">
            <a href="?" style="<?= $audienceFilter === '' ? 'background-color:var(--indigo-soft); border-color:var(--indigo-soft); color:var(--indigo-text); font-weight:700;' : '' ?>">All</a>
            <?php foreach ($validAudiences as $aud): ?>
              <?php $audColors = audiencePalette($aud); ?>
              <a href="?audience=<?= urlencode($aud) ?>" style="<?= $audienceFilter === $aud ? 'background-color:' . $audColors['bg'] . '; border-color:' . $audColors['bg'] . '; color:' . $audColors['fg'] . '; font-weight:700;' : '' ?>"><?= htmlspecialchars($aud) ?></a>
            <?php endforeach; ?>
          </nav>
          <button type="button" class="btn btn-repo btn-repo-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
            <i class="fa-solid fa-plus"></i> New Announcement
          </button>
        </div>
      </section>

      <section class="card">
        <?php if (empty($announcements)): ?>
          <div class="text-center py-5" style="color:var(--ink-soft);">
            <i class="fa-regular fa-bell fs-2 d-block mb-2"></i>
            <p class="mb-0 small">No announcements posted yet.</p>
          </div>
        <?php else: ?>
          <?php foreach ($announcements as $a): ?>
            <?php $audColors = audiencePalette($a['audience']); ?>
            <div class="announcement-item">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <h2 class="announcement-title mb-0"><?= htmlspecialchars($a['title']) ?></h2>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                  <span class="audience-pill" style="background-color:<?= $audColors['bg'] ?>; color:<?= $audColors['fg'] ?>;"><?= htmlspecialchars($a['audience']) ?></span>
                  <button type="button" class="btn btn-repo btn-repo-primary btn-sm" title="Edit"
                    data-bs-toggle="modal" data-bs-target="#editAnnouncementModal"
                    data-id="<?= $a['announcement_id'] ?>"
                    data-title="<?= htmlspecialchars($a['title']) ?>"
                    data-message="<?= htmlspecialchars($a['message']) ?>"
                    data-audience="<?= htmlspecialchars($a['audience']) ?>">
                    <i class="fa-regular fa-pen-to-square"></i>
                  </button>
                  <button type="button" class="btn btn-repo btn-repo-danger btn-sm" title="Delete"
                    data-bs-toggle="modal" data-bs-target="#deleteAnnouncementModal"
                    data-id="<?= $a['announcement_id'] ?>"
                    data-title="<?= htmlspecialchars($a['title']) ?>">
                    <i class="fa-regular fa-trash-can"></i>
                  </button>
                </div>
              </div>
              <p class="announcement-message mb-2"><?= htmlspecialchars($a['message']) ?></p>
              <div class="announcement-meta">
                Posted by <?= htmlspecialchars(trim(($a['firstname'] ?? '') . ' ' . ($a['lastname'] ?? '')) ?: 'Unknown') ?>
                &middot; <?= date('M d, Y g:i A', strtotime($a['created_at'])) ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>

    </main>

  </div>

</div>

<div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">New Announcement</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Title</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Office closed on Friday" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Audience</label>
              <select name="audience" class="form-select" required>
                <option value="All">All</option>
                <option value="Staff">Staff</option>
                <option value="Manager">Manager</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Message</label>
              <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-repo btn-repo-primary">Post Announcement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form method="POST" novalidate>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="announcement_id" id="edit_announcement_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Edit Announcement</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Title</label>
              <input type="text" name="title" id="edit_announcement_title" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Audience</label>
              <select name="audience" id="edit_announcement_audience" class="form-select" required>
                <option value="All">All</option>
                <option value="Staff">Staff</option>
                <option value="Manager">Manager</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Message</label>
              <textarea name="message" id="edit_announcement_message" class="form-control" rows="4" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-repo btn-repo-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteAnnouncementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="announcement_id" id="delete_announcement_id">
        <div class="modal-header">
          <h2 class="modal-title h5 fw-bold">Delete Announcement</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete <strong id="delete_announcement_title"></strong>?</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-repo btn-repo-danger">Delete Announcement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editAnnouncementModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('edit_announcement_id').value = btn.dataset.id;
  document.getElementById('edit_announcement_title').value = btn.dataset.title;
  document.getElementById('edit_announcement_message').value = btn.dataset.message;
  document.getElementById('edit_announcement_audience').value = btn.dataset.audience;
});

document.getElementById('deleteAnnouncementModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('delete_announcement_id').value = btn.dataset.id;
  document.getElementById('delete_announcement_title').textContent = btn.dataset.title;
});

<?php if ($alertType && $alertMessage): ?>
window.addEventListener('DOMContentLoaded', function () {
  alert(<?= json_encode($alertMessage) ?>);
});
<?php endif; ?>
</script>

</body>
</html>