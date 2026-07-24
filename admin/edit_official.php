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

$admNo = trim($_GET['admNo'] ?? '');

if ($admNo === '') {
    $_SESSION['error'] = 'Invalid club official selected.';
    header('Location: officials.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve the existing official and login account
|--------------------------------------------------------------------------
*/

$selectSql = "
    SELECT
        co.admNo,
        co.userID,
        co.officialName,
        co.position,
        co.email,
        co.phone,
        co.clubNumber,
        co.isChair,
        u.username,
        u.status
    FROM club_officials co
    INNER JOIN users u
        ON co.userID = u.userID
    WHERE co.admNo = ?
    LIMIT 1
";

$selectStmt = mysqli_prepare($conn, $selectSql);

if (!$selectStmt) {
    die('Unable to prepare official query: ' . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $selectStmt,
    's',
    $admNo
);

mysqli_stmt_execute($selectStmt);

$result = mysqli_stmt_get_result($selectStmt);
$official = mysqli_fetch_assoc($result);

mysqli_stmt_close($selectStmt);

if (!$official) {
    $_SESSION['error'] = 'Club official not found.';
    header('Location: officials.php');
    exit();
}

/*
|--------------------------------------------------------------------------
| Retrieve clubs
|--------------------------------------------------------------------------
*/

$clubsResult = mysqli_query(
    $conn,
    "
        SELECT clubNumber, clubName
        FROM clubs
        ORDER BY clubName
    "
);

if (!$clubsResult) {
    die('Unable to retrieve clubs: ' . mysqli_error($conn));
}

/*
|--------------------------------------------------------------------------
| Initial form values
|--------------------------------------------------------------------------
*/

$userID = (int) $official['userID'];
$officialName = $official['officialName'];
$position = $official['position'];
$email = $official['email'] ?? '';
$phone = $official['phone'] ?? '';
$clubNumber = (int) $official['clubNumber'];
$isChair = $official['isChair'];
$username = $official['username'];
$status = $official['status'];

$errors = [];

/*
|--------------------------------------------------------------------------
| Process update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $officialName = trim($_POST['officialName'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $clubNumber = filter_input(
        INPUT_POST,
        'clubNumber',
        FILTER_VALIDATE_INT
    );

    $isChair = $_POST['isChair'] ?? 'No';
    $username = trim($_POST['username'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    $allowedPositions = [
        'Chairperson',
        'Secretary',
        'Treasurer'
    ];

    $allowedStatuses = [
        'Active',
        'Inactive'
    ];

    if ($officialName === '') {
        $errors[] = 'Official name is required.';
    }

    if (strlen($officialName) > 100) {
        $errors[] = 'Official name cannot exceed 100 characters.';
    }

    if (!in_array($position, $allowedPositions, true)) {
        $errors[] = 'Select a valid official position.';
    }

    if (!$clubNumber) {
        $errors[] = 'Select a valid club.';
    }

    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if (strlen($username) < 4) {
        $errors[] = 'Username must contain at least 4 characters.';
    }

    if (strlen($username) > 50) {
        $errors[] = 'Username cannot exceed 50 characters.';
    }

    if (!in_array($isChair, ['Yes', 'No'], true)) {
        $errors[] = 'Invalid chairperson selection.';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Invalid account status.';
    }

    /*
     * Password is optional when editing.
     * Validate only if the admin entered one.
     */
    if ($newPassword !== '') {

        if (strlen($newPassword) < 8) {
            $errors[] =
                'The new password must contain at least 8 characters.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'The new passwords do not match.';
        }
    }

    /*
     * Confirm that the selected club exists.
     */
    if (empty($errors)) {

        $clubCheckSql = "
            SELECT clubNumber
            FROM clubs
            WHERE clubNumber = ?
            LIMIT 1
        ";

        $clubCheckStmt = mysqli_prepare(
            $conn,
            $clubCheckSql
        );

        if (!$clubCheckStmt) {
            $errors[] = 'Unable to validate the selected club.';
        } else {

            mysqli_stmt_bind_param(
                $clubCheckStmt,
                'i',
                $clubNumber
            );

            mysqli_stmt_execute($clubCheckStmt);
            mysqli_stmt_store_result($clubCheckStmt);

            if (mysqli_stmt_num_rows($clubCheckStmt) === 0) {
                $errors[] = 'The selected club does not exist.';
            }

            mysqli_stmt_close($clubCheckStmt);
        }
    }

    /*
     * Prevent duplicate usernames.
     */
    if (empty($errors)) {

        $usernameCheckSql = "
            SELECT userID
            FROM users
            WHERE username = ?
              AND userID <> ?
            LIMIT 1
        ";

        $usernameCheckStmt = mysqli_prepare(
            $conn,
            $usernameCheckSql
        );

        if (!$usernameCheckStmt) {
            $errors[] = 'Unable to validate the username.';
        } else {

            mysqli_stmt_bind_param(
                $usernameCheckStmt,
                'si',
                $username,
                $userID
            );

            mysqli_stmt_execute($usernameCheckStmt);
            mysqli_stmt_store_result($usernameCheckStmt);

            if (mysqli_stmt_num_rows($usernameCheckStmt) > 0) {
                $errors[] =
                    'Another account already uses this username.';
            }

            mysqli_stmt_close($usernameCheckStmt);
        }
    }

    /*
     * Update both tables in one transaction.
     */
    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            /*
             * If this official becomes chairperson,
             * remove the chairperson flag from every other
             * official belonging to the selected club.
             */
            if ($isChair === 'Yes') {

                $resetSql = "
                    UPDATE club_officials
                    SET isChair = 'No'
                    WHERE clubNumber = ?
                      AND admNo <> ?
                ";

                $resetStmt = mysqli_prepare(
                    $conn,
                    $resetSql
                );

                if (!$resetStmt) {
                    throw new Exception(
                        'Unable to prepare chairperson update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $resetStmt,
                    'is',
                    $clubNumber,
                    $admNo
                );

                if (!mysqli_stmt_execute($resetStmt)) {
                    throw new Exception(
                        'Unable to update the club chairperson.'
                    );
                }

                mysqli_stmt_close($resetStmt);
            }

            /*
             * Update the club official profile.
             */
            $officialSql = "
                UPDATE club_officials
                SET
                    officialName = ?,
                    position = ?,
                    email = ?,
                    phone = ?,
                    clubNumber = ?,
                    isChair = ?
                WHERE admNo = ?
            ";

            $officialStmt = mysqli_prepare(
                $conn,
                $officialSql
            );

            if (!$officialStmt) {
                throw new Exception(
                    'Unable to prepare the official update.'
                );
            }

            mysqli_stmt_bind_param(
                $officialStmt,
                'ssssiss',
                $officialName,
                $position,
                $email,
                $phone,
                $clubNumber,
                $isChair,
                $admNo
            );

            if (!mysqli_stmt_execute($officialStmt)) {
                throw new Exception(
                    'Unable to update the official profile.'
                );
            }

            mysqli_stmt_close($officialStmt);

            /*
             * Update the account.
             * Password is changed only when supplied.
             */
            if ($newPassword !== '') {

                $passwordHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $userSql = "
                    UPDATE users
                    SET
                        username = ?,
                        password = ?,
                        status = ?
                    WHERE userID = ?
                ";

                $userStmt = mysqli_prepare(
                    $conn,
                    $userSql
                );

                if (!$userStmt) {
                    throw new Exception(
                        'Unable to prepare the account update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $userStmt,
                    'sssi',
                    $username,
                    $passwordHash,
                    $status,
                    $userID
                );

            } else {

                $userSql = "
                    UPDATE users
                    SET
                        username = ?,
                        status = ?
                    WHERE userID = ?
                ";

                $userStmt = mysqli_prepare(
                    $conn,
                    $userSql
                );

                if (!$userStmt) {
                    throw new Exception(
                        'Unable to prepare the account update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $userStmt,
                    'ssi',
                    $username,
                    $status,
                    $userID
                );
            }

            if (!mysqli_stmt_execute($userStmt)) {
                throw new Exception(
                    'Unable to update the login account.'
                );
            }

            mysqli_stmt_close($userStmt);

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Club official updated successfully.';

            header('Location: officials.php');
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
            <h1>Edit Club Official</h1>
            <p>Update the official profile and login account.</p>
        </div>

        <a href="officials.php" class="btn btn-secondary">
            Back to Officials
        </a>

    </div>

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error); ?></p>
            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <div class="card form-card">

        <form method="POST" action="">

            <h3>Official Information</h3>

            <div class="form-grid">

                <div class="form-group">

                    <label for="admNo">
                        Admission Number
                    </label>

                    <input
                        type="text"
                        id="admNo"
                        value="<?= htmlspecialchars($admNo); ?>"
                        disabled
                    >

                    <small class="text-muted">
                        Admission numbers cannot be changed.
                    </small>

                </div>

                <div class="form-group">

                    <label for="officialName">
                        Full Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="officialName"
                        name="officialName"
                        maxlength="100"
                        value="<?= htmlspecialchars($officialName); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="clubNumber">
                        Club
                        <span class="required">*</span>
                    </label>

                    <select
                        id="clubNumber"
                        name="clubNumber"
                        required
                    >

                        <option value="">Select Club</option>

                        <?php while ($club = mysqli_fetch_assoc($clubsResult)): ?>

                            <option
                                value="<?= (int) $club['clubNumber']; ?>"
                                <?= (int) $clubNumber ===
                                    (int) $club['clubNumber']
                                    ? 'selected'
                                    : ''; ?>
                            >
                                <?= htmlspecialchars($club['clubName']); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="position">
                        Position
                        <span class="required">*</span>
                    </label>

                    <select
                        id="position"
                        name="position"
                        required
                    >

                        <option value="">Select Position</option>

                        <option
                            value="Chairperson"
                            <?= $position === 'Chairperson'
                                ? 'selected'
                                : ''; ?>
                        >
                            Chairperson
                        </option>

                        <option
                            value="Secretary"
                            <?= $position === 'Secretary'
                                ? 'selected'
                                : ''; ?>
                        >
                            Secretary
                        </option>

                        <option
                            value="Treasurer"
                            <?= $position === 'Treasurer'
                                ? 'selected'
                                : ''; ?>
                        >
                            Treasurer
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="100"
                        value="<?= htmlspecialchars($email); ?>"
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
                        value="<?= htmlspecialchars($phone); ?>"
                    >

                </div>

                <div class="form-group">

                    <label for="isChair">
                        Club Chairperson?
                    </label>

                    <select
                        id="isChair"
                        name="isChair"
                    >

                        <option
                            value="No"
                            <?= $isChair === 'No'
                                ? 'selected'
                                : ''; ?>
                        >
                            No
                        </option>

                        <option
                            value="Yes"
                            <?= $isChair === 'Yes'
                                ? 'selected'
                                : ''; ?>
                        >
                            Yes
                        </option>

                    </select>

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

                    <label for="status">
                        Account Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="Active"
                            <?= $status === 'Active'
                                ? 'selected'
                                : ''; ?>
                        >
                            Active
                        </option>

                        <option
                            value="Inactive"
                            <?= $status === 'Inactive'
                                ? 'selected'
                                : ''; ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label for="newPassword">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="newPassword"
                        name="newPassword"
                        minlength="8"
                    >

                    <small class="text-muted">
                        Leave blank to keep the current password.
                    </small>

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
                    >

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Official
                </button>

                <a
                    href="officials.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>