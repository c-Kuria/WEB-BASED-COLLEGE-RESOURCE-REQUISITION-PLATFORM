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

$staffNo = '';
$officerName = '';
$officerRole = '';
$availability = 'Available';
$proxyOfficerStaffNo = '';
$username = '';
$accountStatus = 'Active';

$errors = [];

/*
|--------------------------------------------------------------------------
| Retrieve available proxy officers
|--------------------------------------------------------------------------
*/

$proxyResult = mysqli_query(
    $conn,
    "
        SELECT
            officerStaffNo,
            officerName,
            officerRole
        FROM officers
        ORDER BY officerName
    "
);

if (!$proxyResult) {
    die(
        'Unable to retrieve proxy officers: ' .
        mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Process form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $staffNo = strtoupper(
        trim($_POST['staffNo'] ?? '')
    );

    $officerName = trim(
        $_POST['officerName'] ?? ''
    );

    $officerRole = trim(
        $_POST['officerRole'] ?? ''
    );

    $availability = $_POST['availability'] ?? 'Available';

    $proxyOfficerStaffNo = trim(
        $_POST['proxyOfficerStaffNo'] ?? ''
    );

    $username = trim(
        $_POST['username'] ?? ''
    );

    $password = $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirmPassword'] ?? '';

    $accountStatus =
        $_POST['accountStatus'] ?? 'Active';

    $allowedRoles = [
        'Dean',
        'Finance Officer',
        'Transport Manager',
        'Student Affairs Officer',
        'Games Coordinator',
        'ICT Officer',
        'Principal'
    ];

    $allowedAvailability = [
        'Available',
        'Unavailable'
    ];

    $allowedAccountStatuses = [
        'Active',
        'Inactive'
    ];

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($staffNo === '') {
        $errors[] = 'Staff number is required.';
    }

    if (strlen($staffNo) > 30) {
        $errors[] =
            'Staff number cannot exceed 30 characters.';
    }

    if ($officerName === '') {
        $errors[] = 'Officer name is required.';
    }

    if (strlen($officerName) > 100) {
        $errors[] =
            'Officer name cannot exceed 100 characters.';
    }

    if (!in_array(
        $officerRole,
        $allowedRoles,
        true
    )) {
        $errors[] = 'Select a valid officer role.';
    }

    if (!in_array(
        $availability,
        $allowedAvailability,
        true
    )) {
        $errors[] = 'Invalid availability selection.';
    }

    if (!in_array(
        $accountStatus,
        $allowedAccountStatuses,
        true
    )) {
        $errors[] = 'Invalid account status.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if (strlen($username) < 4) {
        $errors[] =
            'Username must contain at least 4 characters.';
    }

    if (strlen($username) > 50) {
        $errors[] =
            'Username cannot exceed 50 characters.';
    }

    if (strlen($password) < 8) {
        $errors[] =
            'Password must contain at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    /*
     * A proxy is only required when the officer
     * is marked unavailable.
     */
    if (
        $availability === 'Unavailable' &&
        $proxyOfficerStaffNo === ''
    ) {
        $errors[] =
            'Assign a proxy officer when the officer is unavailable.';
    }

    /*
    |--------------------------------------------------------------------------
    | Check duplicate staff number
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $staffCheckSql = "
            SELECT officerStaffNo
            FROM officers
            WHERE officerStaffNo = ?
            LIMIT 1
        ";

        $staffCheckStmt = mysqli_prepare(
            $conn,
            $staffCheckSql
        );

        if (!$staffCheckStmt) {

            $errors[] =
                'Unable to validate the staff number.';

        } else {

            mysqli_stmt_bind_param(
                $staffCheckStmt,
                's',
                $staffNo
            );

            mysqli_stmt_execute($staffCheckStmt);
            mysqli_stmt_store_result($staffCheckStmt);

            if (
                mysqli_stmt_num_rows($staffCheckStmt) > 0
            ) {
                $errors[] =
                    'This staff number is already registered.';
            }

            mysqli_stmt_close($staffCheckStmt);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check duplicate username
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $usernameCheckSql = "
            SELECT userID
            FROM users
            WHERE username = ?
            LIMIT 1
        ";

        $usernameCheckStmt = mysqli_prepare(
            $conn,
            $usernameCheckSql
        );

        if (!$usernameCheckStmt) {

            $errors[] =
                'Unable to validate the username.';

        } else {

            mysqli_stmt_bind_param(
                $usernameCheckStmt,
                's',
                $username
            );

            mysqli_stmt_execute($usernameCheckStmt);
            mysqli_stmt_store_result($usernameCheckStmt);

            if (
                mysqli_stmt_num_rows(
                    $usernameCheckStmt
                ) > 0
            ) {
                $errors[] =
                    'This username is already in use.';
            }

            mysqli_stmt_close(
                $usernameCheckStmt
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Check proxy officer
    |--------------------------------------------------------------------------
    */

    if (
        empty($errors) &&
        $proxyOfficerStaffNo !== ''
    ) {

        $proxyCheckSql = "
            SELECT officerStaffNo
            FROM officers
            WHERE officerStaffNo = ?
            LIMIT 1
        ";

        $proxyCheckStmt = mysqli_prepare(
            $conn,
            $proxyCheckSql
        );

        if (!$proxyCheckStmt) {

            $errors[] =
                'Unable to validate the proxy officer.';

        } else {

            mysqli_stmt_bind_param(
                $proxyCheckStmt,
                's',
                $proxyOfficerStaffNo
            );

            mysqli_stmt_execute($proxyCheckStmt);
            mysqli_stmt_store_result($proxyCheckStmt);

            if (
                mysqli_stmt_num_rows(
                    $proxyCheckStmt
                ) === 0
            ) {
                $errors[] =
                    'The selected proxy officer does not exist.';
            }

            mysqli_stmt_close($proxyCheckStmt);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Insert user and officer
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /*
             * Create officer login account.
             */
            $userSql = "
                INSERT INTO users (
                    username,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, 'officer', ?)
            ";

            $userStmt = mysqli_prepare(
                $conn,
                $userSql
            );

            if (!$userStmt) {
                throw new Exception(
                    'Unable to prepare the officer account.'
                );
            }

            mysqli_stmt_bind_param(
                $userStmt,
                'sss',
                $username,
                $passwordHash,
                $accountStatus
            );

            if (!mysqli_stmt_execute($userStmt)) {
                throw new Exception(
                    'Unable to create the officer login account.'
                );
            }

            $userID = mysqli_insert_id($conn);

            mysqli_stmt_close($userStmt);

            /*
             * Create officer business record.
             *
             * The proxy value may be NULL.
             */
            if ($proxyOfficerStaffNo === '') {

                $officerSql = "
                    INSERT INTO officers (
                        officerStaffNo,
                        userID,
                        officerName,
                        officerRole,
                        availability,
                        proxyOfficerStaffNo
                    )
                    VALUES (?, ?, ?, ?, ?, NULL)
                ";

                $officerStmt = mysqli_prepare(
                    $conn,
                    $officerSql
                );

                if (!$officerStmt) {
                    throw new Exception(
                        'Unable to prepare the officer record.'
                    );
                }

                mysqli_stmt_bind_param(
                    $officerStmt,
                    'sisss',
                    $staffNo,
                    $userID,
                    $officerName,
                    $officerRole,
                    $availability
                );

            } else {

                $officerSql = "
                    INSERT INTO officers (
                        officerStaffNo,
                        userID,
                        officerName,
                        officerRole,
                        availability,
                        proxyOfficerStaffNo
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ";

                $officerStmt = mysqli_prepare(
                    $conn,
                    $officerSql
                );

                if (!$officerStmt) {
                    throw new Exception(
                        'Unable to prepare the officer record.'
                    );
                }

                mysqli_stmt_bind_param(
                    $officerStmt,
                    'sissss',
                    $staffNo,
                    $userID,
                    $officerName,
                    $officerRole,
                    $availability,
                    $proxyOfficerStaffNo
                );
            }

            if (!mysqli_stmt_execute($officerStmt)) {
                throw new Exception(
                    'Unable to create the officer profile: ' .
                    mysqli_stmt_error($officerStmt)
                );
            }

            mysqli_stmt_close($officerStmt);

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Officer and login account created successfully.';

            header('Location: officers.php');
            exit();

        } catch (Throwable $exception) {

            mysqli_rollback($conn);

            $errors[] = $exception->getMessage();
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Add Officer</h1>
            <p>
                Create an approving officer profile and login account.
            </p>
        </div>

        <a href="officers.php" class="btn btn-secondary">
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

    <div class="card form-card">

        <form method="POST" action="">

            <h3>Officer Information</h3>

            <div class="form-grid">

                <div class="form-group">

                    <label for="staffNo">
                        Staff Number
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="staffNo"
                        name="staffNo"
                        maxlength="30"
                        value="<?= htmlspecialchars($staffNo); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="officerName">
                        Officer Name
                        <span class="required">*</span>
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
                        <span class="required">*</span>
                    </label>

                    <select
                        id="officerRole"
                        name="officerRole"
                        required
                    >

                        <option value="">
                            Select Officer Role
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

                        <?php foreach ($roles as $role): ?>

                            <option
                                value="<?= htmlspecialchars($role); ?>"
                                <?= $officerRole === $role
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?= htmlspecialchars($role); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="availability">
                        Availability
                    </label>

                    <select
                        id="availability"
                        name="availability"
                    >

                        <option
                            value="Available"
                            <?= $availability === 'Available'
                                ? 'selected'
                                : ''; ?>
                        >
                            Available
                        </option>

                        <option
                            value="Unavailable"
                            <?= $availability === 'Unavailable'
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
                            No Proxy Officer
                        </option>

                        <?php while (
                            $proxy = mysqli_fetch_assoc($proxyResult)
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $proxy['officerStaffNo']
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
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <small class="text-muted">
                        Required when the officer is unavailable.
                    </small>

                </div>

            </div>

            <hr>

            <h3>Login Account</h3>

            <div class="form-grid">

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
                        value="<?= htmlspecialchars($username); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="accountStatus">
                        Account Status
                    </label>

                    <select
                        id="accountStatus"
                        name="accountStatus"
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
                            <?= $accountStatus === 'Inactive'
                                ? 'selected'
                                : ''; ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="password">
                        Password
                        <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="confirmPassword">
                        Confirm Password
                        <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        id="confirmPassword"
                        name="confirmPassword"
                        minlength="8"
                        required
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Officer
                </button>

                <a
                    href="officers.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>