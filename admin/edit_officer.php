<?php

require_once '../includes/session.php';
require_once '../config/db.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header('Location: ../login.php');
    exit();
}

$pageTitle = 'Edit Officer';
$errors = [];

/*
|--------------------------------------------------------------------------
| Validate staff number
|--------------------------------------------------------------------------
*/

$officerStaffNo =
    trim($_GET['staffNo'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $officerStaffNo =
        trim(
            $_POST['officerStaffNo'] ?? ''
        );
}

if ($officerStaffNo === '') {

    $_SESSION['error'] =
        'Invalid officer selected.';

    header('Location: officers.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Fetch officer
|--------------------------------------------------------------------------
*/

function getOfficerForEdit(
    mysqli $conn,
    string $officerStaffNo
): ?array {

    $sql = "
        SELECT
            o.officerStaffNo,
            o.userID,
            o.officerName,
            o.officerRole,
            o.availability,
            o.proxyOfficerStaffNo,
            o.createdAt,

            u.username,
            u.status AS accountStatus,
            u.createdAt AS accountCreatedAt

        FROM officers o

        INNER JOIN users u
            ON o.userID = u.userID

        WHERE o.officerStaffNo = ?

        LIMIT 1
    ";

    $stmt =
        mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        's',
        $officerStaffNo
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $officer =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $officer ?: null;
}

$officer =
    getOfficerForEdit(
        $conn,
        $officerStaffNo
    );

if (!$officer) {

    $_SESSION['error'] =
        'The selected officer was not found.';

    header('Location: officers.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Default form values
|--------------------------------------------------------------------------
*/

$officerName =
    $officer['officerName'];

$officerRole =
    $officer['officerRole'];

$username =
    $officer['username'];

$availability =
    $officer['availability'];

$proxyOfficerStaffNo =
    $officer['proxyOfficerStaffNo'] ?? '';

$accountStatus =
    $officer['accountStatus'];

/*
|--------------------------------------------------------------------------
| Update officer
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $officerName =
        trim($_POST['officerName'] ?? '');

    $officerRole =
        trim($_POST['officerRole'] ?? '');

    $username =
        trim($_POST['username'] ?? '');

    $availability =
        trim($_POST['availability'] ?? '');

    $proxyOfficerStaffNo =
        trim(
            $_POST['proxyOfficerStaffNo'] ?? ''
        );

    $accountStatus =
        trim($_POST['accountStatus'] ?? '');

    $newPassword =
        $_POST['newPassword'] ?? '';

    $confirmPassword =
        $_POST['confirmPassword'] ?? '';

    $allowedRoles = [
        'Dean',
        'Finance Officer',
        'Transport Manager',
        'Student Affairs Officer',
        'Games Coordinator',
        'ICT Officer',
        'Principal'
    ];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($officerName === '') {

        $errors[] =
            'Officer name is required.';

    } elseif (
        mb_strlen($officerName) > 100
    ) {

        $errors[] =
            'Officer name must not exceed 100 characters.';
    }

    if (
        !in_array(
            $officerRole,
            $allowedRoles,
            true
        )
    ) {
        $errors[] =
            'Select a valid officer role.';
    }

    if (
        !preg_match(
            '/^[A-Za-z0-9._-]{3,50}$/',
            $username
        )
    ) {
        $errors[] =
            'Username must contain 3 to 50 letters, numbers, dots, underscores or hyphens.';
    }

    if (
        !in_array(
            $availability,
            ['Available', 'Unavailable'],
            true
        )
    ) {
        $errors[] =
            'Select a valid availability status.';
    }

    if (
        !in_array(
            $accountStatus,
            ['Active', 'Inactive'],
            true
        )
    ) {
        $errors[] =
            'Select a valid account status.';
    }

    if (
        $proxyOfficerStaffNo !== '' &&
        $proxyOfficerStaffNo ===
        $officerStaffNo
    ) {
        $errors[] =
            'An officer cannot be assigned as their own proxy.';
    }

    if (
        $availability === 'Unavailable' &&
        $proxyOfficerStaffNo === ''
    ) {
        $errors[] =
            'Select a proxy officer when the officer is unavailable.';
    }

    /*
    |--------------------------------------------------------------------------
    | Validate optional password
    |--------------------------------------------------------------------------
    */

    if (
        $newPassword !== '' ||
        $confirmPassword !== ''
    ) {

        if (strlen($newPassword) < 8) {
            $errors[] =
                'The new password must contain at least 8 characters.';
        }

        if (
            $newPassword !== '' &&
            !preg_match(
                '/[A-Z]/',
                $newPassword
            )
        ) {
            $errors[] =
                'The new password must contain an uppercase letter.';
        }

        if (
            $newPassword !== '' &&
            !preg_match(
                '/[a-z]/',
                $newPassword
            )
        ) {
            $errors[] =
                'The new password must contain a lowercase letter.';
        }

        if (
            $newPassword !== '' &&
            !preg_match(
                '/[0-9]/',
                $newPassword
            )
        ) {
            $errors[] =
                'The new password must contain a number.';
        }

        if (
            $newPassword !==
            $confirmPassword
        ) {
            $errors[] =
                'Password confirmation does not match.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check duplicate username
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $usernameSql = "
            SELECT userID

            FROM users

            WHERE username = ?
              AND userID <> ?

            LIMIT 1
        ";

        $usernameStmt =
            mysqli_prepare(
                $conn,
                $usernameSql
            );

        if (!$usernameStmt) {

            $errors[] =
                'Unable to validate the username.';

        } else {

            mysqli_stmt_bind_param(
                $usernameStmt,
                'si',
                $username,
                $officer['userID']
            );

            mysqli_stmt_execute(
                $usernameStmt
            );

            $usernameResult =
                mysqli_stmt_get_result(
                    $usernameStmt
                );

            if (
                mysqli_fetch_assoc(
                    $usernameResult
                )
            ) {
                $errors[] =
                    'That username is already in use.';
            }

            mysqli_stmt_close(
                $usernameStmt
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate proxy officer
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $proxyOfficerStaffNo !== ''
    ) {

        $proxySql = "
            SELECT
                officerStaffNo,
                availability,
                userID

            FROM officers

            WHERE officerStaffNo = ?
              AND officerStaffNo <> ?

            LIMIT 1
        ";

        $proxyStmt =
            mysqli_prepare(
                $conn,
                $proxySql
            );

        if (!$proxyStmt) {

            $errors[] =
                'Unable to validate the proxy officer.';

        } else {

            mysqli_stmt_bind_param(
                $proxyStmt,
                'ss',
                $proxyOfficerStaffNo,
                $officerStaffNo
            );

            mysqli_stmt_execute(
                $proxyStmt
            );

            $proxyResult =
                mysqli_stmt_get_result(
                    $proxyStmt
                );

            $proxyOfficer =
                mysqli_fetch_assoc(
                    $proxyResult
                );

            mysqli_stmt_close(
                $proxyStmt
            );

            if (!$proxyOfficer) {

                $errors[] =
                    'The selected proxy officer was not found.';

            } elseif (
                $availability === 'Unavailable' &&
                $proxyOfficer[
                    'availability'
                ] !== 'Available'
            ) {

                $errors[] =
                    'The selected proxy officer must be available.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Save officer changes
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            $userSql = "
                UPDATE users

                SET
                    username = ?,
                    status = ?

                WHERE userID = ?
                  AND role = 'officer'
            ";

            $userStmt =
                mysqli_prepare(
                    $conn,
                    $userSql
                );

            if (!$userStmt) {
                throw new RuntimeException(
                    'Unable to prepare the account update.'
                );
            }

            mysqli_stmt_bind_param(
                $userStmt,
                'ssi',
                $username,
                $accountStatus,
                $officer['userID']
            );

            if (
                !mysqli_stmt_execute(
                    $userStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to update the officer account.'
                );
            }

            mysqli_stmt_close($userStmt);

            $officerSql = "
                UPDATE officers

                SET
                    officerName = ?,
                    officerRole = ?,
                    availability = ?,
                    proxyOfficerStaffNo =
                        NULLIF(?, '')

                WHERE officerStaffNo = ?
            ";

            $officerStmt =
                mysqli_prepare(
                    $conn,
                    $officerSql
                );

            if (!$officerStmt) {
                throw new RuntimeException(
                    'Unable to prepare the officer update.'
                );
            }

            mysqli_stmt_bind_param(
                $officerStmt,
                'sssss',
                $officerName,
                $officerRole,
                $availability,
                $proxyOfficerStaffNo,
                $officerStaffNo
            );

            if (
                !mysqli_stmt_execute(
                    $officerStmt
                )
            ) {
                throw new RuntimeException(
                    'Unable to update the officer profile.'
                );
            }

            mysqli_stmt_close($officerStmt);

            /*
            |--------------------------------------------------------------------------
            | Update password when supplied
            |--------------------------------------------------------------------------
            */

            if ($newPassword !== '') {

                $passwordHash =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                $passwordSql = "
                    UPDATE users

                    SET password = ?

                    WHERE userID = ?
                      AND role = 'officer'
                ";

                $passwordStmt =
                    mysqli_prepare(
                        $conn,
                        $passwordSql
                    );

                if (!$passwordStmt) {
                    throw new RuntimeException(
                        'Unable to prepare the password update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $passwordStmt,
                    'si',
                    $passwordHash,
                    $officer['userID']
                );

                if (
                    !mysqli_stmt_execute(
                        $passwordStmt
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to update the officer password.'
                    );
                }

                mysqli_stmt_close(
                    $passwordStmt
                );
            }

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Officer updated successfully.';

            header('Location: officers.php');
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
| Fetch proxy officer options
|--------------------------------------------------------------------------
*/

$proxySql = "
    SELECT
        o.officerStaffNo,
        o.officerName,
        o.officerRole,
        o.availability,
        u.status AS accountStatus

    FROM officers o

    INNER JOIN users u
        ON o.userID = u.userID

    WHERE o.officerStaffNo <> ?

    ORDER BY
        o.officerName
";

$proxyStmt =
    mysqli_prepare(
        $conn,
        $proxySql
    );

mysqli_stmt_bind_param(
    $proxyStmt,
    's',
    $officerStaffNo
);

mysqli_stmt_execute($proxyStmt);

$proxyResult =
    mysqli_stmt_get_result($proxyStmt);

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Edit Officer</h1>

        <p>
            Update officer details, account access and proxy
            assignment.
        </p>

    </div>

    <a
        href="officers.php"
        class="btn btn-secondary"
    >
        Back to Officers
    </a>

</div>

<?php if (!empty($errors)): ?>

    <div class="alert alert-danger">

        <?php foreach ($errors as $error): ?>

            <p>
                <?= htmlspecialchars($error); ?>
            </p>

        <?php endforeach; ?>

    </div>

<?php endif; ?>

<div class="card">

    <div class="section-header">

        <h2>Officer Information</h2>

        <p>
            Staff number cannot be changed after registration.
        </p>

    </div>

    <form method="POST">

        <input
            type="hidden"
            name="officerStaffNo"
            value="<?= htmlspecialchars(
                $officerStaffNo
            ); ?>"
        >

        <div class="form-grid">

            <div class="form-group">

                <label>
                    Staff Number
                </label>

                <input
                    type="text"
                    value="<?= htmlspecialchars(
                        $officerStaffNo
                    ); ?>"
                    disabled
                >

            </div>

            <div class="form-group">

                <label for="officerName">
                    Officer Name
                </label>

                <input
                    type="text"
                    id="officerName"
                    name="officerName"
                    maxlength="100"
                    value="<?= htmlspecialchars(
                        $officerName
                    ); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="officerRole">
                    Officer Role
                </label>

                <select
                    id="officerRole"
                    name="officerRole"
                    required
                >

                    <option value="">
                        Select officer role
                    </option>

                    <?php
                    $roles = [
                        'Dean',
                        'Finance Officer',
                        'Transport Manager',
                        'Student Affairs Officer',
                        'Games Coordinator',
                        'ICT Officer',
                        'Principal'
                    ];
                    ?>

                    <?php foreach (
                        $roles as $role
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $role
                            ); ?>"
                            <?= $officerRole === $role
                                ? 'selected'
                                : ''; ?>
                        >
                            <?= htmlspecialchars(
                                $role
                            ); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label for="username">
                    Username
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

                <label for="availability">
                    Availability
                </label>

                <select
                    id="availability"
                    name="availability"
                    required
                >

                    <option
                        value="Available"
                        <?= $availability ===
                        'Available'
                            ? 'selected'
                            : ''; ?>
                    >
                        Available
                    </option>

                    <option
                        value="Unavailable"
                        <?= $availability ===
                        'Unavailable'
                            ? 'selected'
                            : ''; ?>
                    >
                        Unavailable
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label for="proxyOfficerStaffNo">
                    Proxy Officer
                </label>

                <select
                    id="proxyOfficerStaffNo"
                    name="proxyOfficerStaffNo"
                >

                    <option value="">
                        No proxy selected
                    </option>

                    <?php while (
                        $proxy =
                            mysqli_fetch_assoc(
                                $proxyResult
                            )
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $proxy[
                                    'officerStaffNo'
                                ]
                            ); ?>"
                            <?= $proxyOfficerStaffNo ===
                            $proxy['officerStaffNo']
                                ? 'selected'
                                : ''; ?>
                        >
                            <?= htmlspecialchars(
                                $proxy['officerName']
                            ); ?>
                            —
                            <?= htmlspecialchars(
                                $proxy['officerRole']
                            ); ?>
                            (
                            <?= htmlspecialchars(
                                $proxy['availability']
                            ); ?>
                            /
                            <?= htmlspecialchars(
                                $proxy['accountStatus']
                            ); ?>
                            )
                        </option>

                    <?php endwhile; ?>

                </select>

                <small class="form-help">
                    A proxy is required when availability is
                    set to Unavailable.
                </small>

            </div>

            <div class="form-group">

                <label for="accountStatus">
                    Account Status
                </label>

                <select
                    id="accountStatus"
                    name="accountStatus"
                    required
                >

                    <option
                        value="Active"
                        <?= $accountStatus === 'Active'
                            ? 'selected'
                            : ''; ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?= $accountStatus ===
                        'Inactive'
                            ? 'selected'
                            : ''; ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Account Created
                </label>

                <input
                    type="text"
                    value="<?= date(
                        'd M Y, H:i',
                        strtotime(
                            $officer[
                                'accountCreatedAt'
                            ]
                        )
                    ); ?>"
                    disabled
                >

            </div>

        </div>

        <div class="section-divider"></div>

        <div class="section-header">

            <h2>Reset Password</h2>

            <p>
                Leave these fields blank to keep the current
                password.
            </p>

        </div>

        <div class="form-grid">

            <div class="form-group">

                <label for="newPassword">
                    New Password
                </label>

                <input
                    type="password"
                    id="newPassword"
                    name="newPassword"
                    minlength="8"
                    autocomplete="new-password"
                >

            </div>

            <div class="form-group">

                <label for="confirmPassword">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    id="confirmPassword"
                    name="confirmPassword"
                    minlength="8"
                    autocomplete="new-password"
                >

            </div>

        </div>

        <div class="form-actions">

            <a
                href="officers.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save Officer
            </button>

        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const availability =
        document.getElementById(
            'availability'
        );

    const proxy =
        document.getElementById(
            'proxyOfficerStaffNo'
        );

    function updateProxyRequirement() {

        if (!availability || !proxy) {
            return;
        }

        proxy.required =
            availability.value ===
            'Unavailable';
    }

    if (availability) {

        availability.addEventListener(
            'change',
            updateProxyRequirement
        );
    }

    updateProxyRequirement();
});
</script>

<?php

mysqli_stmt_close($proxyStmt);

require_once '../includes/footer.php';

?>