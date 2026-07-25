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

$clubsResult = mysqli_query(
    $conn,
    "SELECT clubNumber, clubName
     FROM clubs
     ORDER BY clubName"
);

if (!$clubsResult) {
    die('Unable to retrieve clubs: ' . mysqli_error($conn));
}

$admNo = '';
$officialName = '';
$position = '';
$email = '';
$phone = '';
$clubNumber = '';
$isChair = 'No';
$username = '';
$status = 'Active';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $admNo = strtoupper(trim($_POST['admNo'] ?? ''));
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
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    $allowedPositions = [
        'Chairperson',
        'Secretary',
        'Treasurer'
    ];

    $allowedStatus = [
        'Active',
        'Inactive'
    ];

    if ($admNo === '') {
        $errors[] = 'Admission number is required.';
    }

    if ($officialName === '') {
        $errors[] = 'Official name is required.';
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
        $errors[] = 'Username must have at least 4 characters.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must have at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!in_array($isChair, ['Yes', 'No'], true)) {
        $errors[] = 'Invalid chairperson selection.';
    }

    if (!in_array($status, $allowedStatus, true)) {
        $errors[] = 'Invalid account status.';
    }

    if (empty($errors)) {

        $checkSql = "
            SELECT
                u.userID,
                co.admNo
            FROM users u
            LEFT JOIN club_officials co
                ON u.userID = co.userID
            WHERE u.username = ?
               OR co.admNo = ?
            LIMIT 1
        ";

        $checkStmt = mysqli_prepare($conn, $checkSql);

        if (!$checkStmt) {
            $errors[] = 'Unable to validate account information.';
        } else {

            mysqli_stmt_bind_param(
                $checkStmt,
                'ss',
                $username,
                $admNo
            );

            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] =
                    'The username or admission number is already registered.';
            }

            mysqli_stmt_close($checkStmt);
        }
    }

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $userSql = "
                INSERT INTO users (
                    username,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, 'official', ?)
            ";

            $userStmt = mysqli_prepare($conn, $userSql);

            if (!$userStmt) {
                throw new Exception(
                    'Unable to prepare the user account.'
                );
            }

            mysqli_stmt_bind_param(
                $userStmt,
                'sss',
                $username,
                $passwordHash,
                $status
            );

            if (!mysqli_stmt_execute($userStmt)) {
                throw new Exception(
                    'Unable to create the login account.'
                );
            }

            $userID = mysqli_insert_id($conn);

            mysqli_stmt_close($userStmt);

            /*
             * A club should only have one chairperson.
             * If this person is selected as chairperson,
             * remove the chair flag from other officials.
             */
            if ($isChair === 'Yes') {

                $resetChairSql = "
                    UPDATE club_officials
                    SET isChair = 'No'
                    WHERE clubNumber = ?
                ";

                $resetChairStmt = mysqli_prepare(
                    $conn,
                    $resetChairSql
                );

                if (!$resetChairStmt) {
                    throw new Exception(
                        'Unable to update the club chairperson.'
                    );
                }

                mysqli_stmt_bind_param(
                    $resetChairStmt,
                    'i',
                    $clubNumber
                );

                if (!mysqli_stmt_execute($resetChairStmt)) {
                    throw new Exception(
                        'Unable to update the existing chairperson.'
                    );
                }

                mysqli_stmt_close($resetChairStmt);
            }

            $officialSql = "
                INSERT INTO club_officials (
                    admNo,
                    userID,
                    officialName,
                    position,
                    email,
                    phone,
                    clubNumber,
                    isChair
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $officialStmt = mysqli_prepare(
                $conn,
                $officialSql
            );

            if (!$officialStmt) {
                throw new Exception(
                    'Unable to prepare the official record.'
                );
            }

            mysqli_stmt_bind_param(
                $officialStmt,
                'sissssis',
                $admNo,
                $userID,
                $officialName,
                $position,
                $email,
                $phone,
                $clubNumber,
                $isChair
            );

            if (!mysqli_stmt_execute($officialStmt)) {
                throw new Exception(
                    'Unable to create the official record.'
                );
            }

            mysqli_stmt_close($officialStmt);

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Club official created successfully.';

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
            <h1>Add Club Official</h1>
            <p>Create a club official account.</p>
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

        <?php if (mysqli_num_rows($clubsResult) === 0): ?>

            <div class="alert alert-danger">
                Add at least one club before registering an official.
            </div>

            <a href="add_club.php" class="btn btn-primary">
                Add Club
            </a>

        <?php else: ?>

            <form method="POST" action="">

                <h3>Official Information</h3>

                <div class="form-grid">

                    <div class="form-group">
                        <label for="admNo">
                            Admission Number
                            <span class="required">*</span>
                        </label>

                        <input
                            type="text"
                            id="admNo"
                            name="admNo"
                            maxlength="30"
                            value="<?= htmlspecialchars($admNo); ?>"
                            required
                        >
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
                                    <?= (string) $clubNumber ===
                                        (string) $club['clubNumber']
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
                        <label for="email">Email Address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="100"
                            value="<?= htmlspecialchars($email); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            maxlength="20"
                            value="<?= htmlspecialchars($phone); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="isChair">Club Chairperson?</label>

                        <select id="isChair" name="isChair">
                            <option
                                value="No"
                                <?= $isChair === 'No' ? 'selected' : ''; ?>
                            >
                                No
                            </option>

                            <option
                                value="Yes"
                                <?= $isChair === 'Yes' ? 'selected' : ''; ?>
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
                        <label for="status">Account Status</label>

                        <select id="status" name="status">
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

                    <button type="submit" class="btn btn-primary">
                        Save Official
                    </button>

                    <a href="officials.php" class="btn btn-secondary">
                        Cancel
                    </a>

                </div>

            </form>

        <?php endif; ?>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>