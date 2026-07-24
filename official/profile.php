<?php

require_once '../includes/session.php';
require_once '../config/db.php';

/*
|--------------------------------------------------------------------------
| Protect page
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'official'
) {
    header('Location: ../login.php');
    exit();
}

$userID = (int) ($_SESSION['userID'] ?? 0);

if ($userID <= 0) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve official profile
|--------------------------------------------------------------------------
*/

function getOfficialProfile(
    mysqli $conn,
    int $userID
): ?array {

    $sql = "
        SELECT
            co.admNo,
            co.officialName,
            co.position,
            co.email,
            co.phone,
            co.clubNumber,
            co.isChair,
            co.createdAt,

            c.clubName,
            c.clubDescription,

            u.username,
            u.status AS accountStatus,
            u.createdAt AS accountCreatedAt

        FROM club_officials co

        INNER JOIN clubs c
            ON co.clubNumber = c.clubNumber

        INNER JOIN users u
            ON co.userID = u.userID

        WHERE co.userID = ?

        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $userID
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $profile =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $profile ?: null;
}

$official = getOfficialProfile(
    $conn,
    $userID
);

if (!$official) {
    die(
        'Your club official profile could not be found. ' .
        'Contact the administrator.'
    );
}

/*
|--------------------------------------------------------------------------
| Default form values
|--------------------------------------------------------------------------
*/

$officialName =
    $official['officialName'];

$email =
    $official['email'] ?? '';

$phone =
    $official['phone'] ?? '';

$username =
    $official['username'];

$errors = [];

/*
|--------------------------------------------------------------------------
| Process profile update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Update personal information
    |--------------------------------------------------------------------------
    */

    if ($action === 'update_profile') {

        $officialName = trim(
            $_POST['officialName'] ?? ''
        );

        $email = trim(
            $_POST['email'] ?? ''
        );

        $phone = trim(
            $_POST['phone'] ?? ''
        );

        $username = trim(
            $_POST['username'] ?? ''
        );

        /*
         * Validation
         */
        if ($officialName === '') {
            $errors[] =
                'Official name is required.';
        }

        if (strlen($officialName) > 150) {
            $errors[] =
                'Official name cannot exceed 150 characters.';
        }

        if ($username === '') {
            $errors[] =
                'Username is required.';
        }

        if (
            $username !== '' &&
            !preg_match(
                '/^[A-Za-z0-9._-]{3,50}$/',
                $username
            )
        ) {
            $errors[] =
                'Username must contain 3 to 50 letters, numbers, dots, underscores, or hyphens.';
        }

        if (
            $email !== '' &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $errors[] =
                'Enter a valid email address.';
        }

        if (
            $phone !== '' &&
            !preg_match(
                '/^[0-9+\-\s()]{7,20}$/',
                $phone
            )
        ) {
            $errors[] =
                'Enter a valid phone number.';
        }

        /*
         * Check duplicate username.
         */
        if (empty($errors)) {

            $usernameCheckSql = "
                SELECT userID
                FROM users
                WHERE username = ?
                  AND userID <> ?
                LIMIT 1
            ";

            $usernameCheckStmt =
                mysqli_prepare(
                    $conn,
                    $usernameCheckSql
                );

            if (!$usernameCheckStmt) {

                $errors[] =
                    'Unable to validate the username.';

            } else {

                mysqli_stmt_bind_param(
                    $usernameCheckStmt,
                    'si',
                    $username,
                    $userID
                );

                mysqli_stmt_execute(
                    $usernameCheckStmt
                );

                $usernameCheckResult =
                    mysqli_stmt_get_result(
                        $usernameCheckStmt
                    );

                if (
                    mysqli_fetch_assoc(
                        $usernameCheckResult
                    )
                ) {
                    $errors[] =
                        'That username is already being used.';
                }

                mysqli_stmt_close(
                    $usernameCheckStmt
                );
            }
        }

        /*
         * Check duplicate email when an email is provided.
         */
        if (
            empty($errors) &&
            $email !== ''
        ) {

            $emailCheckSql = "
                SELECT admNo
                FROM club_officials
                WHERE email = ?
                  AND userID <> ?
                LIMIT 1
            ";

            $emailCheckStmt =
                mysqli_prepare(
                    $conn,
                    $emailCheckSql
                );

            if (!$emailCheckStmt) {

                $errors[] =
                    'Unable to validate the email address.';

            } else {

                mysqli_stmt_bind_param(
                    $emailCheckStmt,
                    'si',
                    $email,
                    $userID
                );

                mysqli_stmt_execute(
                    $emailCheckStmt
                );

                $emailCheckResult =
                    mysqli_stmt_get_result(
                        $emailCheckStmt
                    );

                if (
                    mysqli_fetch_assoc(
                        $emailCheckResult
                    )
                ) {
                    $errors[] =
                        'That email address is already registered.';
                }

                mysqli_stmt_close(
                    $emailCheckStmt
                );
            }
        }

        /*
         * Save changes.
         */
        if (empty($errors)) {

            mysqli_begin_transaction($conn);

            try {

                $userUpdateSql = "
                    UPDATE users
                    SET username = ?
                    WHERE userID = ?
                ";

                $userUpdateStmt =
                    mysqli_prepare(
                        $conn,
                        $userUpdateSql
                    );

                if (!$userUpdateStmt) {
                    throw new Exception(
                        'Unable to prepare account update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $userUpdateStmt,
                    'si',
                    $username,
                    $userID
                );

                if (
                    !mysqli_stmt_execute(
                        $userUpdateStmt
                    )
                ) {
                    throw new Exception(
                        'Unable to update the username.'
                    );
                }

                mysqli_stmt_close(
                    $userUpdateStmt
                );

                $officialUpdateSql = "
                    UPDATE club_officials
                    SET
                        officialName = ?,
                        email = NULLIF(?, ''),
                        phone = NULLIF(?, '')
                    WHERE userID = ?
                ";

                $officialUpdateStmt =
                    mysqli_prepare(
                        $conn,
                        $officialUpdateSql
                    );

                if (!$officialUpdateStmt) {
                    throw new Exception(
                        'Unable to prepare profile update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $officialUpdateStmt,
                    'sssi',
                    $officialName,
                    $email,
                    $phone,
                    $userID
                );

                if (
                    !mysqli_stmt_execute(
                        $officialUpdateStmt
                    )
                ) {
                    throw new Exception(
                        'Unable to update your profile.'
                    );
                }

                mysqli_stmt_close(
                    $officialUpdateStmt
                );

                mysqli_commit($conn);

                $_SESSION['username'] =
                    $username;

                $_SESSION['success'] =
                    'Your profile was updated successfully.';

                header('Location: profile.php');
                exit();

            } catch (Throwable $exception) {

                mysqli_rollback($conn);

                $errors[] =
                    $exception->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Change password
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'change_password') {

        $currentPassword =
            $_POST['currentPassword'] ?? '';

        $newPassword =
            $_POST['newPassword'] ?? '';

        $confirmPassword =
            $_POST['confirmPassword'] ?? '';

        if ($currentPassword === '') {
            $errors[] =
                'Current password is required.';
        }

        if ($newPassword === '') {
            $errors[] =
                'New password is required.';
        }

        if (
            $newPassword !== '' &&
            strlen($newPassword) < 8
        ) {
            $errors[] =
                'New password must contain at least 8 characters.';
        }

        if (
            $newPassword !== '' &&
            !preg_match('/[A-Z]/', $newPassword)
        ) {
            $errors[] =
                'New password must contain at least one uppercase letter.';
        }

        if (
            $newPassword !== '' &&
            !preg_match('/[a-z]/', $newPassword)
        ) {
            $errors[] =
                'New password must contain at least one lowercase letter.';
        }

        if (
            $newPassword !== '' &&
            !preg_match('/[0-9]/', $newPassword)
        ) {
            $errors[] =
                'New password must contain at least one number.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] =
                'New password and confirmation do not match.';
        }

        /*
         * Retrieve existing password hash.
         */
        if (empty($errors)) {

            $passwordSql = "
                SELECT password
                FROM users
                WHERE userID = ?
                LIMIT 1
            ";

            $passwordStmt =
                mysqli_prepare(
                    $conn,
                    $passwordSql
                );

            if (!$passwordStmt) {

                $errors[] =
                    'Unable to verify the current password.';

            } else {

                mysqli_stmt_bind_param(
                    $passwordStmt,
                    'i',
                    $userID
                );

                mysqli_stmt_execute(
                    $passwordStmt
                );

                $passwordResult =
                    mysqli_stmt_get_result(
                        $passwordStmt
                    );

                $passwordRow =
                    mysqli_fetch_assoc(
                        $passwordResult
                    );

                mysqli_stmt_close(
                    $passwordStmt
                );

                if (
                    !$passwordRow ||
                    !password_verify(
                        $currentPassword,
                        $passwordRow['password']
                    )
                ) {
                    $errors[] =
                        'The current password is incorrect.';
                }

                if (
                    empty($errors) &&
                    password_verify(
                        $newPassword,
                        $passwordRow['password']
                    )
                ) {
                    $errors[] =
                        'The new password must be different from the current password.';
                }
            }
        }

        /*
         * Save the new password.
         */
        if (empty($errors)) {

            $passwordHash =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

            $updatePasswordSql = "
                UPDATE users
                SET password = ?
                WHERE userID = ?
            ";

            $updatePasswordStmt =
                mysqli_prepare(
                    $conn,
                    $updatePasswordSql
                );

            if (!$updatePasswordStmt) {

                $errors[] =
                    'Unable to prepare the password update.';

            } else {

                mysqli_stmt_bind_param(
                    $updatePasswordStmt,
                    'si',
                    $passwordHash,
                    $userID
                );

                if (
                    mysqli_stmt_execute(
                        $updatePasswordStmt
                    )
                ) {

                    $_SESSION['success'] =
                        'Your password was changed successfully.';

                    mysqli_stmt_close(
                        $updatePasswordStmt
                    );

                    header('Location: profile.php');
                    exit();

                } else {

                    $errors[] =
                        'Unable to change your password.';

                    mysqli_stmt_close(
                        $updatePasswordStmt
                    );
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Refresh profile after unsuccessful submission
|--------------------------------------------------------------------------
*/

$official = getOfficialProfile(
    $conn,
    $userID
);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>My Profile</h1>

            <p>
                View and update your club official account.
            </p>
        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>

    </div>

    <?php if (isset($_SESSION['success'])): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars(
                $_SESSION['success']
            ); ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <?php foreach ($errors as $error): ?>

                <p>
                    <?= htmlspecialchars($error); ?>
                </p>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <div class="profile-layout">

        <!-- Profile summary -->

        <div class="card profile-summary-card">

            <div class="profile-avatar">

                <?= htmlspecialchars(
                    strtoupper(
                        substr(
                            $official[
                                'officialName'
                            ],
                            0,
                            1
                        )
                    )
                ); ?>

            </div>

            <h2>
                <?= htmlspecialchars(
                    $official['officialName']
                ); ?>
            </h2>

            <p class="profile-position">
                <?= htmlspecialchars(
                    $official['position']
                ); ?>
            </p>

            <span
                class="badge <?= $official['accountStatus']
                    === 'Active'
                    ? 'badge-success'
                    : 'badge-danger'; ?>"
            >
                <?= htmlspecialchars(
                    $official['accountStatus']
                ); ?>
            </span>

            <div class="profile-summary-details">

                <div>
                    <strong>Admission Number</strong>

                    <span>
                        <?= htmlspecialchars(
                            $official['admNo']
                        ); ?>
                    </span>
                </div>

                <div>
                    <strong>Club</strong>

                    <span>
                        <?= htmlspecialchars(
                            $official['clubName']
                        ); ?>
                    </span>
                </div>

                <div>
                    <strong>Chairperson</strong>

                    <span>
                        <?= $official['isChair'] === 'Yes'
                            ? 'Yes'
                            : 'No'; ?>
                    </span>
                </div>

                <div>
                    <strong>Member Since</strong>

                    <span>
                        <?= date(
                            'd M Y',
                            strtotime(
                                $official['createdAt']
                            )
                        ); ?>
                    </span>
                </div>

            </div>

        </div>

        <div class="profile-content">

            <!-- Personal information -->

            <div class="card">

                <div class="section-header">

                    <h2>Personal Information</h2>

                    <p>
                        Update your contact information and
                        account username.
                    </p>

                </div>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="action"
                        value="update_profile"
                    >

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="officialName">
                                Full Name
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="officialName"
                                name="officialName"
                                maxlength="150"
                                value="<?= htmlspecialchars(
                                    $officialName
                                ); ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="username">
                                Username
                                <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                maxlength="50"
                                value="<?= htmlspecialchars(
                                    $username
                                ); ?>"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                                value="<?= htmlspecialchars(
                                    $email
                                ); ?>"
                            >

                        </div>

                        <div class="form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                maxlength="20"
                                value="<?= htmlspecialchars(
                                    $phone
                                ); ?>"
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                Admission Number
                            </label>

                            <input
                                type="text"
                                value="<?= htmlspecialchars(
                                    $official['admNo']
                                ); ?>"
                                disabled
                            >

                            <small class="text-muted">
                                Contact the administrator to
                                change this value.
                            </small>

                        </div>

                        <div class="form-group">

                            <label>
                                Official Position
                            </label>

                            <input
                                type="text"
                                value="<?= htmlspecialchars(
                                    $official['position']
                                ); ?>"
                                disabled
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                Club
                            </label>

                            <input
                                type="text"
                                value="<?= htmlspecialchars(
                                    $official['clubName']
                                ); ?>"
                                disabled
                            >

                        </div>

                        <div class="form-group">

                            <label>
                                Account Status
                            </label>

                            <input
                                type="text"
                                value="<?= htmlspecialchars(
                                    $official[
                                        'accountStatus'
                                    ]
                                ); ?>"
                                disabled
                            >

                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Profile
                        </button>

                    </div>

                </form>

            </div>

            <!-- Change password -->

            <div class="card password-card">

                <div class="section-header">

                    <h2>Change Password</h2>

                    <p>
                        Use a strong password that you do not use
                        for another account.
                    </p>

                </div>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="action"
                        value="change_password"
                    >

                    <div class="form-grid">

                        <div class="form-group full-width">

                            <label for="currentPassword">
                                Current Password
                                <span class="required">*</span>
                            </label>

                            <div class="password-input-wrapper">

                                <input
                                    type="password"
                                    id="currentPassword"
                                    name="currentPassword"
                                    autocomplete="current-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="currentPassword"
                                >
                                    Show
                                </button>

                            </div>

                        </div>

                        <div class="form-group">

                            <label for="newPassword">
                                New Password
                                <span class="required">*</span>
                            </label>

                            <div class="password-input-wrapper">

                                <input
                                    type="password"
                                    id="newPassword"
                                    name="newPassword"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="newPassword"
                                >
                                    Show
                                </button>

                            </div>

                            <small class="text-muted">
                                At least 8 characters, including
                                uppercase, lowercase, and a number.
                            </small>

                        </div>

                        <div class="form-group">

                            <label for="confirmPassword">
                                Confirm New Password
                                <span class="required">*</span>
                            </label>

                            <div class="password-input-wrapper">

                                <input
                                    type="password"
                                    id="confirmPassword"
                                    name="confirmPassword"
                                    minlength="8"
                                    autocomplete="new-password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="password-toggle"
                                    data-target="confirmPassword"
                                >
                                    Show
                                </button>

                            </div>

                        </div>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Change Password
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleButtons =
        document.querySelectorAll('.password-toggle');

    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const inputID =
                button.dataset.target;

            const input =
                document.getElementById(inputID);

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

<?php require_once '../includes/footer.php'; ?>