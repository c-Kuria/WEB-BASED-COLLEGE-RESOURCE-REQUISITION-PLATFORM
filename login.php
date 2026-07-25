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

$loginError =
    $loginError ?? '';

$username =
    $username ?? '';
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Login | Resource Requisition
    </title>

    <link
        rel="stylesheet"
        href="/resource_requisition/assets/css/style.css"
    >

</head>

<body class="login-body">

<div class="simple-login-page">

    <div class="simple-login-background-shape shape-one"></div>
    <div class="simple-login-background-shape shape-two"></div>

    <div class="simple-login-container">

        <a
            href="/resource_requisition/login.php"
            class="simple-login-logo"
        >

            <span class="simple-login-logo-mark">
                RR
            </span>

            <span class="simple-login-logo-text">

                <strong>
                    Resource Requisition
                </strong>

                <small>
                    College Management System
                </small>

            </span>

        </a>

        <div class="simple-login-card">

            <div class="simple-login-header">

                <h1>Sign in</h1>

                <p>
                    Enter your account details to continue.
                </p>

            </div>

            <?php if ($loginError !== ''): ?>

                <div class="alert alert-danger">
                    <?= htmlspecialchars($loginError); ?>
                </div>

            <?php endif; ?>

            <form
                method="POST"
                class="simple-login-form"
            >

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars(
                            $username
                        ); ?>"
                        placeholder="Enter your username"
                        autocomplete="username"
                        required
                        autofocus
                    >

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="password-input-wrapper">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle"
                            data-target="password"
                        >
                            Show
                        </button>

                    </div>

                </div>

                <button
                    type="submit"
                    class="btn btn-primary simple-login-button"
                >
                    Sign In
                </button>

            </form>

        </div>

        <p class="simple-login-footer">
            Authorized users only
        </p>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const passwordButtons =
        document.querySelectorAll('.password-toggle');

    passwordButtons.forEach(function (button) {

        button.addEventListener('click', function () {

            const input =
                document.getElementById(
                    button.dataset.target
                );

            if (!input) {
                return;
            }

            if (input.type === 'password') {

                input.type = 'text';
                button.textContent = 'Hide';

            } else {

                input.type = 'password';
                button.textContent = 'Show';
            }
        });
    });
});
</script>

</body>
</html>