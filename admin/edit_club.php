<?php
require_once '../includes/session.php';
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$clubNumber = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$clubNumber) {
    $_SESSION['error'] = 'Invalid club selected.';
    header('Location: clubs.php');
    exit();
}

$selectSql = "
    SELECT
        clubNumber,
        clubName,
        clubDescription
    FROM clubs
    WHERE clubNumber = ?
    LIMIT 1
";

$selectStmt = mysqli_prepare($conn, $selectSql);

if (!$selectStmt) {
    die('Unable to prepare club query.');
}

mysqli_stmt_bind_param(
    $selectStmt,
    'i',
    $clubNumber
);

mysqli_stmt_execute($selectStmt);

$result = mysqli_stmt_get_result($selectStmt);
$club = mysqli_fetch_assoc($result);

mysqli_stmt_close($selectStmt);

if (!$club) {
    $_SESSION['error'] = 'Club not found.';
    header('Location: clubs.php');
    exit();
}

$clubName = $club['clubName'];
$clubDescription = $club['clubDescription'] ?? '';
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

        $duplicateSql = "
            SELECT clubNumber
            FROM clubs
            WHERE clubName = ?
              AND clubNumber <> ?
            LIMIT 1
        ";

        $duplicateStmt = mysqli_prepare(
            $conn,
            $duplicateSql
        );

        if (!$duplicateStmt) {
            $errors[] = 'Unable to validate the club name.';
        } else {

            mysqli_stmt_bind_param(
                $duplicateStmt,
                'si',
                $clubName,
                $clubNumber
            );

            mysqli_stmt_execute($duplicateStmt);
            mysqli_stmt_store_result($duplicateStmt);

            if (mysqli_stmt_num_rows($duplicateStmt) > 0) {
                $errors[] = 'Another club already uses this name.';
            }

            mysqli_stmt_close($duplicateStmt);
        }
    }

    if (empty($errors)) {

        $updateSql = "
            UPDATE clubs
            SET
                clubName = ?,
                clubDescription = ?
            WHERE clubNumber = ?
        ";

        $updateStmt = mysqli_prepare($conn, $updateSql);

        if (!$updateStmt) {
            $errors[] = 'Unable to prepare the update.';
        } else {

            mysqli_stmt_bind_param(
                $updateStmt,
                'ssi',
                $clubName,
                $clubDescription,
                $clubNumber
            );

            if (mysqli_stmt_execute($updateStmt)) {

                $_SESSION['success'] = 'Club updated successfully.';

                mysqli_stmt_close($updateStmt);

                header('Location: clubs.php');
                exit();

            } else {
                $errors[] = 'Unable to update the club.';
            }

            mysqli_stmt_close($updateStmt);
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Edit Club</h1>
            <p>Update the club's information.</p>
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

                <label for="clubNumber">
                    Club Number
                </label>

                <input
                    type="text"
                    id="clubNumber"
                    value="<?= (int) $clubNumber; ?>"
                    disabled
                >

            </div>

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
                ><?= htmlspecialchars($clubDescription); ?></textarea>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn btn-primary">
                    Update Club
                </button>

                <a href="clubs.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>