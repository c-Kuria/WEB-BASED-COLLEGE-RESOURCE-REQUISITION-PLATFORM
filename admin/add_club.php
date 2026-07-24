<?php
require_once '../includes/session.php';
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$clubName = '';
$clubDescription = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clubName = trim($_POST['clubName'] ?? '');
    $clubDescription = trim($_POST['clubDescription'] ?? '');

    if ($clubName === '') {
        $errors[] = 'Club name is required.';
    }

    if (strlen($clubName) < 3) {
        $errors[] = 'Club name must contain at least 3 characters.';
    }

    if (strlen($clubName) > 100) {
        $errors[] = 'Club name cannot exceed 100 characters.';
    }

    if (empty($errors)) {

        $checkSql = "
            SELECT clubNumber
            FROM clubs
            WHERE clubName = ?
            LIMIT 1
        ";

        $checkStmt = mysqli_prepare($conn, $checkSql);

        if (!$checkStmt) {
            $errors[] = 'Unable to validate the club name.';
        } else {

            mysqli_stmt_bind_param(
                $checkStmt,
                's',
                $clubName
            );

            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);

            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors[] = 'A club with this name already exists.';
            }

            mysqli_stmt_close($checkStmt);
        }
    }

    if (empty($errors)) {

        $insertSql = "
            INSERT INTO clubs (
                clubName,
                clubDescription
            )
            VALUES (?, ?)
        ";

        $insertStmt = mysqli_prepare($conn, $insertSql);

        if (!$insertStmt) {
            $errors[] = 'Unable to prepare the club record.';
        } else {

            mysqli_stmt_bind_param(
                $insertStmt,
                'ss',
                $clubName,
                $clubDescription
            );

            if (mysqli_stmt_execute($insertStmt)) {

                $_SESSION['success'] = 'Club added successfully.';

                mysqli_stmt_close($insertStmt);

                header('Location: clubs.php');
                exit();

            } else {
                $errors[] = 'Unable to add the club.';
            }

            mysqli_stmt_close($insertStmt);
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Add Club</h1>
            <p>Register a new college club.</p>
        </div>

        <a href="clubs.php" class="btn btn-secondary">
            Back to Clubs
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

            <div class="form-group">

                <label for="clubName">
                    Club Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="clubName"
                    name="clubName"
                    maxlength="100"
                    value="<?= htmlspecialchars($clubName); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="clubDescription">
                    Club Description
                </label>

                <textarea
                    id="clubDescription"
                    name="clubDescription"
                    rows="5"
                    placeholder="Enter a brief description of the club"
                ><?= htmlspecialchars($clubDescription); ?></textarea>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn btn-primary">
                    Save Club
                </button>

                <a href="clubs.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>