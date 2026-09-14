<?php

require_once __DIR__ . '/config/database.php';

$roleSessions = [
    'admin'      => 'ADMIN_SESSION',
    'manager'    => 'MANAGER_SESSION',
    'supervisor' => 'SUPERVISOR_SESSION',
    'staff'      => 'STAFF_SESSION',
];

function redirectForRole(string $role): string
{
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'manager':
            return 'manager/dashboard.php';
        case 'supervisor':
            return 'supervisor/dashboard.php';
        case 'staff':
            return 'staff/dashboard.php';
        default:
            return '../login.php';
    }
}

// Kung may naka-login na role sa kasalukuyang browser, diretso sa dashboard nito
foreach ($roleSessions as $role => $sessionName) {
    session_name($sessionName);
    session_start();
    $loggedIn = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === $role;
    session_write_close();

    if ($loggedIn) {
        header('Location: ' . redirectForRole($role));
        exit;
    }
}

// Pansamantalang session, para lang sa error message bago malaman ang role
session_name('KMP_LOGIN_MSG');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $_SESSION['login_error'] = 'Please enter both email and password.';
        header('Location: login.php');
        exit;
    }

    $pdo = getConnection();
    $account = null;
    $role    = null;

    // 1. Tignan muna sa administrator table
    $stmt = $pdo->prepare('SELECT admin_id AS user_id, fullname, email, password FROM administrator WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $account = $admin;
        $role    = 'admin';
    } else {
        // 2. Tignan sa users table (Staff, Manager, Supervisor)
        $stmt = $pdo->prepare(
            "SELECT user_id, firstname, lastname, email, password, role, status
             FROM users
             WHERE email = ? AND role IN ('Staff', 'Manager', 'Supervisor')"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            if ($user['status'] !== 'Active') {
                $_SESSION['login_error'] = 'Your account has been deactivated. Please contact the administrator.';
                header('Location: login.php');
                exit;
            }

            $account = $user;
            $role    = strtolower($user['role']);
        }
    }

    if (!$account) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        header('Location: login.php');
        exit;
    }

    // May tamang role na, kaya i-close muna ang temporary session bago lumipat
    session_write_close();

    session_name($roleSessions[$role]);
    session_start();
    session_regenerate_id(true);

    $_SESSION['user_id']  = $account['user_id'];
    $_SESSION['fullname'] = $role === 'admin'
        ? $account['fullname']
        : $account['firstname'] . ' ' . $account['lastname'];
    $_SESSION['email']    = $account['email'];
    $_SESSION['role']     = $role;

    header('Location: ' . redirectForRole($role));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KMP ConsultHub - Sign In</title>
<link rel="stylesheet" href="assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
<style>
  :root{
    --kmp-navy:#0d2444;
    --kmp-navy-deep:#081930;
    --kmp-gold:#c9a24b;
  }
  body{
    background:#f4f6f9;
  }

  /* ===== Top info bar ===== */
  .kmp-topbar{
    background:var(--kmp-navy-deep);
    color:#cfd8e3;
    font-size:.82rem;
  }
  .kmp-topbar a{
    color:#cfd8e3;
    text-decoration:none;
  }
  .kmp-topbar a:hover{ color:var(--kmp-gold); }
  .kmp-topbar .divider{
    width:1px; height:14px; background:rgba(255,255,255,.25);
  }

  /* ===== Main navbar ===== */
  .kmp-navbar{
    background:var(--kmp-navy);
  }
  .kmp-navbar .navbar-brand{
    color:#fff;
    font-weight:700;
    letter-spacing:.3px;
  }
  .kmp-navbar .navbar-brand img{
    height:40px;
    width:auto;
  }
  .kmp-navbar .navbar-brand small{
    display:block;
    font-size:.7rem;
    font-weight:400;
    color:#a9b6ca;
    letter-spacing:.5px;
  }
  .kmp-navbar .nav-link{
    color:#dfe6f0 !important;
    font-weight:500;
    font-size:.92rem;
  }
  .kmp-navbar .nav-link:hover{
    color:var(--kmp-gold) !important;
  }

  /* ===== Login section ===== */
  .kmp-login-section{
    min-height:calc(100vh - 118px);
  }
  .login-card{
    max-width:430px;
    width:100%;
  }
  .login-card .card-body{
    padding:2.5rem 2.25rem;
  }
  .login-logo{
    height:64px;
    width:auto;
    margin-bottom:.75rem;
  }
  .login-title{
    color:var(--kmp-navy);
  }
  .btn-kmp{
    background:var(--kmp-navy);
    border-color:var(--kmp-navy);
    color:#fff;
  }
  .btn-kmp:hover{
    background:var(--kmp-navy-deep);
    border-color:var(--kmp-navy-deep);
    color:#fff;
  }
  .login-input-group .input-group-text{
    background:#fff;
  }
  .login-input-group .form-control:focus{
    border-color:var(--kmp-navy);
    box-shadow:0 0 0 .2rem rgba(13,36,68,.15);
  }

  /* ===== Footer ===== */
  .kmp-footer{
    background:var(--kmp-navy-deep);
    color:#a9b6ca;
    font-size:.82rem;
  }
</style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- ============ Info Topbar ============ -->
<div class="kmp-topbar py-1">
  <div class="container d-flex flex-wrap justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
      <span><i class="fa-regular fa-envelope me-1"></i>info@kmpconsulthub.com</span>
      <span class="divider d-none d-sm-block"></span>
      <span><i class="fa-solid fa-phone me-1"></i>(02) 8123-4567</span>
    </div>
    <div class="d-none d-md-flex align-items-center gap-3">
      <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
      <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
    </div>
  </div>
</div>

<!-- ============ Main Navbar ============ -->
<nav class="kmp-navbar navbar navbar-expand-md py-2">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="assets/img/logo.png" alt="KMP ConsultHub Logo">
      <span>
        KMP ConsultHub
        <small>Business &amp; Enterprise Consulting</small>
      </span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#kmpNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="kmpNav">
      <ul class="navbar-nav align-items-md-center gap-md-3">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- ============ Login Section ============ -->
<main class="kmp-login-section d-flex align-items-center justify-content-center py-5 px-3">

  <section class="login-card card shadow-sm border-0 rounded-4">
    <div class="card-body">

      <div class="text-center mb-4">
        <img src="assets/img/logo.png" alt="KMP ConsultHub" class="login-logo">
        <h1 class="login-title h4 fw-bold mb-1">KMP ConsultHub</h1>
        <p class="text-secondary small mb-0">Sign in to your account</p>
      </div>

      <?php if (isset($_SESSION['login_error'])): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?= htmlspecialchars($_SESSION['login_error']) ?></span>
        </div>
        <?php unset($_SESSION['login_error']); ?>
      <?php endif; ?>

      <form action="login.php" method="POST" novalidate>

        <div class="mb-3">
          <label for="email" class="form-label fw-semibold small">Email address</label>
          <div class="login-input-group input-group">
            <span class="input-group-text border-end-0"><i class="fa-regular fa-envelope text-secondary"></i></span>
            <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="Enter your email" required autofocus>
          </div>
        </div>

        <div class="mb-4">
          <label for="password" class="form-label fw-semibold small">Password</label>
          <div class="login-input-group input-group">
            <span class="input-group-text border-end-0"><i class="fa-solid fa-lock text-secondary"></i></span>
            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Enter your password" required>
            <button class="btn btn-light border" type="button" id="togglePassword">
              <i class="fa-regular fa-eye text-secondary"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-kmp w-100 py-2 fw-semibold">Sign in</button>

      </form>

    </div>
  </section>

</main>

<!-- ============ Footer ============ -->
<footer class="kmp-footer py-3 text-center">
  <div class="container">
    &copy; <?= date('Y') ?> KMP ConsultHub. All rights reserved.
  </div>
</footer>

<script src="assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePassword').addEventListener('click', function() {
  const pwInput = document.getElementById('password');
  const icon = this.querySelector('i');
  if (pwInput.type === 'password') {
    pwInput.type = 'text';
    icon.classList.remove('fa-eye');
    icon.classList.add('fa-eye-slash');
  } else {
    pwInput.type = 'password';
    icon.classList.remove('fa-eye-slash');
    icon.classList.add('fa-eye');
  }
});
</script>

</body>
</html>