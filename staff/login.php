<?php

session_name('STAFF_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['staff_id']) && ($_SESSION['role'] ?? '') === 'staff') {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $_SESSION['login_error'] = 'Please enter both email and password.';
        header('Location: login.php');
        exit;
    }

    $pdo = getConnection();

    $stmt = $pdo->prepare('SELECT user_id, firstname, lastname, email, password, role, status FROM users WHERE email = ? AND role = ?');
    $stmt->execute([$email, 'Staff']);
    $staff = $stmt->fetch();

    if (!$staff || !password_verify($password, $staff['password'])) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        header('Location: login.php');
        exit;
    }

    if ($staff['status'] !== 'Active') {
        $_SESSION['login_error'] = 'Your account has been deactivated. Please contact the administrator.';
        header('Location: login.php');
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['staff_id']       = $staff['user_id'];
    $_SESSION['staff_fullname'] = $staff['firstname'] . ' ' . $staff['lastname'];
    $_SESSION['staff_email']    = $staff['email'];
    $_SESSION['role']           = 'staff';

    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KMP ConsultHub - Sign In</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/fontawesome-free-7.3.1/css/all.min.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 p-3">

<main class="login-wrapper w-100" style="max-width:440px;">

  <section class="login-card card shadow-sm border-0 rounded-4">
    <div class="login-card-body card-body p-4 p-md-5">

      <header class="login-header mb-4">
        <h1 class="login-title h4 fw-bold mb-1">Welcome back</h1>
        <p class="login-subtitle text-secondary small mb-0">Please sign in to your account</p>
      </header>

      <?php if (isset($_SESSION['login_error'])): ?>
        <div class="login-alert alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?= htmlspecialchars($_SESSION['login_error']) ?></span>
        </div>
        <?php unset($_SESSION['login_error']); ?>
      <?php endif; ?>

      <form class="login-form" action="login.php" method="POST" novalidate>

        <div class="login-field mb-3">
          <label for="email" class="form-label fw-semibold small">Email address</label>
          <div class="login-input-group input-group">
            <span class="input-group-text bg-white border-end-0"><i class="fa-regular fa-envelope text-secondary"></i></span>
            <input type="email" class="form-control border-start-0" id="email" name="email" placeholder="Enter your email" required>
          </div>
        </div>

        <div class="login-field mb-3">
          <label for="password" class="form-label fw-semibold small">Password</label>
          <div class="login-input-group input-group">
            <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-lock text-secondary"></i></span>
            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Enter your password" required>
            <button class="login-toggle-password btn btn-light border" type="button" id="togglePassword">
              <i class="fa-regular fa-eye text-secondary"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="login-submit btn btn-primary w-100 py-2 fw-semibold">Sign in</button>

      </form>

    </div>
  </section>

</main>

<script src="../assets/vendor/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
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