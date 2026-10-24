<?php

if (isset($_GET['role'])) {

    switch ($_GET['role']) {

        case 'admin':
            session_name('ADMIN_SESSION');
            break;

        case 'manager':
            session_name('MANAGER_SESSION');
            break;

        case 'supervisor':
            session_name('SUPERVISOR_SESSION');
            break;

        case 'staff':
            session_name('STAFF_SESSION');
            break;
    }
}

session_start();

$role = $_SESSION['role'] ?? ($_GET['role'] ?? 'admin');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ../login.php');
exit;