<?php

require_once 'includes/session.php';
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter both username and password.';
    } else {

        $sql = "
            SELECT
                userID,
                username,
                password,
                role,
                status
            FROM users
            WHERE username = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            die('Unable to prepare login query: ' . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = 'Invalid username or password.';
        } elseif ($user['status'] !== 'Active') {
            $error = 'This account is inactive.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid username or password.';
        } else {

            session_regenerate_id(true);

            $_SESSION['userID'] = (int) $user['userID'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = strtolower($user['role']);

            switch ($_SESSION['role']) {

                case 'admin':
                    header('Location: admin/admin_dashboard.php');
                    exit();

                case 'official':
                    header('Location: official/dashboard.php');
                    exit();

                case 'officer':
                    header('Location: officer/dashboard.php');
                    exit();

                default:
                    session_destroy();
                    $error = 'Invalid account role.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Login</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >
</head>

<body>

<div class="login-container">

    <div class="login-card">

        <h1>System Login</h1>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="username">Username</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary">
                Login
            </button>

        </form>

    </div>

</div>

</body>
</html>