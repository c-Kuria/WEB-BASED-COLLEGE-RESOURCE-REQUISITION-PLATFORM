<?php

include '../includes/session.php';
include '../includes/workflow.php';

if ($_SESSION['role'] != "secretary") {
    header("Location: ../login.php");
    exit();
}

include '../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/secretary_sidebar.php';

/* Active Clubs */

$clubs = mysqli_query($conn, "
SELECT club_id,club_name
FROM clubs
WHERE status='Active'
ORDER BY club_name");

/* Available Resources */

$resources = mysqli_query($conn, "
SELECT
resource_id,
resource_name

FROM resources

WHERE

status='Active'

AND

availability_status='Available'

ORDER BY resource_name;
");

$message = "";

if (isset($_POST['submit'])) {

    $secretary_id = $_SESSION['user_id'];

    $club_id = intval($_POST['club_id']);

    $resource_id = intval($_POST['resource_id']);

    $purpose = trim($_POST['purpose']);

    $notes = trim($_POST['additional_notes']);

    $start_date = $_POST['start_date'];

    $end_date = $_POST['end_date'];

    /* Validate dates */

    if ($end_date < $start_date) {

        $message = "<div class='error'>
        End date cannot be earlier than the start date.
        </div>";
    } else {

        mysqli_begin_transaction($conn);

        try {
            /* Generate requisition number */

            $date = date("Ymd");
            $base_count = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(*) total
            FROM requisitions
            WHERE DATE(submitted_at)=CURDATE()
            "))['total'];
            $count = $base_count + 1;
            $requisition_number = "";
            $attempts = 0;

            do {
                $count = $base_count + $attempts + 1;

                $requisition_number =
                    "REQ-" . $date . "-" . str_pad($count, 3, "0", STR_PAD_LEFT);

                $check_stmt = mysqli_prepare($conn, "
                SELECT requisition_id
                FROM requisitions
                WHERE requisition_number=?
                LIMIT 1
                ");

                mysqli_stmt_bind_param($check_stmt, "s", $requisition_number);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);

                $exists = mysqli_stmt_num_rows($check_stmt) > 0;
                $attempts++;
            } while ($exists && $attempts < 100);

            if ($exists) {
                throw new Exception("Unable to generate a unique requisition number.");
            }

            $stmt = mysqli_prepare($conn, "
            INSERT INTO requisitions
            (
                requisition_number,
                secretary_id,
                club_id,
                resource_id,
                purpose,
                additional_notes,
                start_date,
                end_date
            )
            VALUES
            (?,?,?,?,?,?,?,?)
            ");

            mysqli_stmt_bind_param(

                $stmt,

                "siiissss",

                $requisition_number,

                $secretary_id,

                $club_id,

                $resource_id,

                $purpose,

                $notes,

                $start_date,

                $end_date

            );

            if(!mysqli_stmt_execute($stmt)){
                throw new Exception("Failed to save requisition.");
            }

            $requisition_id = mysqli_insert_id($conn);

            if(!generateWorkflow($conn, $requisition_id, $resource_id)){
                throw new Exception("Workflow generation failed.");
            }

            mysqli_commit($conn);

            $_SESSION['success'] =
                "Requisition submitted successfully.";

            header("Location: my_requisitions.php");
            exit();

        } catch(Exception $e) {

            mysqli_rollback($conn);

            $message = "<div class='error'>"
                    . htmlspecialchars($e->getMessage())
                    . "</div>";
        }
    }
}

?>

<div class="main">
    <h1>Create Resource Requisition</h1>

    <?php require_once __DIR__ . '/../includes/flash.php';
    echo $message; ?>

    <form method="POST">

        <div class="form-group">

            <label>Club</label>

            <select name="club_id" required>

                <option value="">Select Club</option>

                <?php while ($club = mysqli_fetch_assoc($clubs)) { ?>

                    <option value="<?= $club['club_id']; ?>">

                        <?= htmlspecialchars($club['club_name']); ?>

                    </option>

                <?php } ?>

            </select>

        </div>

        <div class="form-group">

            <label>Resource</label>

            <select name="resource_id" required>

                <option value="">Select Resource</option>

                <?php while ($resource = mysqli_fetch_assoc($resources)) { ?>

                    <option value="<?= $resource['resource_id']; ?>">

                        <?= htmlspecialchars($resource['resource_name']); ?>

                    </option>

                <?php } ?>

            </select>

        </div>

        <div class="form-group">

            <label>Purpose</label>

            <textarea
                name="purpose"
                rows="4"
                required></textarea>

        </div>

        <div class="form-group">

            <label>Additional Notes</label>

            <textarea
                name="additional_notes"
                rows="3"></textarea>

        </div>

        <div class="form-group">

            <label>Start Date</label>

            <input
                type="date"
                name="start_date"
                required>

        </div>

        <div class="form-group">

            <label>End Date</label>

            <input
                type="date"
                name="end_date"
                required>

        </div>

        <button
            class="btn"
            type="submit"
            name="submit">

            Submit Requisition

        </button>

    </form>
    <?php include '../includes/footer.php'; ?>