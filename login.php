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
            return 'login.php';
    }
}

foreach ($roleSessions as $role => $sessionName) {
    session_name($sessionName);

    if (isset($_COOKIE[$sessionName])) {
        session_id($_COOKIE[$sessionName]);
    }

    session_start();
    $loggedIn = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === $role;
    session_write_close();

    if ($loggedIn) {
        header('Location: ' . redirectForRole($role));
        exit;
    }
}

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

    $stmt = $pdo->prepare('SELECT admin_id AS user_id, fullname, email, password FROM administrator WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $account = $admin;
        $role    = 'admin';
    } else {
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
  }
  body{
    background:#f4f6f9;
  }

  .kmp-topbar{
    background:var(--kmp-navy-deep);
    color:#cfd8e3;
    font-size:.82rem;
  }
  .kmp-topbar a{
    color:#cfd8e3;
    text-decoration:none;
  }
  .kmp-topbar a:hover{ color:#ffffff; }
  .kmp-topbar .divider{
    width:1px; height:14px; background:rgba(255,255,255,.25);
  }

  .kmp-navbar{
    background:var(--kmp-navy);
    box-shadow:0 2px 10px rgba(0,0,0,.15);
  }
  .kmp-navbar .navbar-brand{
    color:#fff;
    font-weight:700;
    letter-spacing:.3px;
    display:flex;
    align-items:center;
    gap:.6rem;
  }
  .kmp-navbar .navbar-brand img{
    height:64px;
    width:auto;
    display:block;
    object-fit:contain;
  }
  @media (max-width:767.98px){
    .kmp-navbar .navbar-brand img{
      height:48px;
    }
  }
  @media (min-width:1200px){
    .kmp-navbar .navbar-brand img{
      height:72px;
    }
  }

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

  .kmp-footer{
    background:var(--kmp-navy-deep);
    color:#a9b6ca;
    font-size:.82rem;
  }
</style>
</head>
<body class="d-flex flex-column min-vh-100">

<div class="kmp-topbar py-1">
  <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <span><i class="fa-solid fa-location-dot me-1"></i>Zone III, City of Koronadal, South Cotabato, Philippines</span>
      <span class="divider d-none d-md-block"></span>
      <a href="tel:+639641351969"><i class="fa-solid fa-phone me-1"></i>+63-964-135-1969 (Smart)</a>
      <span class="divider d-none d-md-block"></span>
      <a href="tel:+639929908757"><i class="fa-solid fa-phone me-1"></i>+63-992-990-8757 (DITO)</a>
      <span class="divider d-none d-md-block"></span>
      <a href="mailto:info@kmp-consultancy.com"><i class="fa-regular fa-envelope me-1"></i>info@kmp-consultancy.com</a>
      <span class="divider d-none d-md-block"></span>
      <span><i class="fa-regular fa-clock me-1"></i>8:00 AM - 5:00 PM</span>
    </div>
    <div class="d-none d-md-flex align-items-center gap-3">
      <a href="https://www.facebook.com/profile.php?id=61571971492964"><i class="fa-brands fa-facebook-f"></i></a>
    </div>
  </div>
</div>

<nav class="kmp-navbar navbar navbar-expand-md py-2">
  <div class="container">
    <a href="index.php" class="navbar-brand mb-0 text-decoration-none">
      <img src="assets/img/system_img/logo.png" alt="KMP ConsultHub">
      <span>KMP ConsultHub</span>
    </a>
  </div>
</nav>

<main class="kmp-login-section d-flex align-items-center justify-content-center py-5 px-3">

  <section class="login-card card shadow-sm border-0 rounded-4">
    <div class="card-body">

      <div class="text-center mb-4">
        <img src="assets/img/system_img/logo.png" alt="KMP ConsultHub" class="login-logo">
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