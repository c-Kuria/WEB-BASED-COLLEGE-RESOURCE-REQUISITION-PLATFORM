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

/*
|--------------------------------------------------------------------------
| Filter values
|--------------------------------------------------------------------------
*/

$dateFrom = trim($_GET['dateFrom'] ?? '');
$dateTo = trim($_GET['dateTo'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$clubFilter = filter_input(
    INPUT_GET,
    'clubNumber',
    FILTER_VALIDATE_INT
);

$allowedStatuses = [
    'Pending',
    'Approved',
    'Rejected',
    'Cancelled'
];

$allowedCategories = [
    'Transport',
    'Venue',
    'Equipment',
    'Finance',
    'ICT',
    'Other'
];

if (
    $statusFilter !== '' &&
    !in_array($statusFilter, $allowedStatuses, true)
) {
    $statusFilter = '';
}

if (
    $categoryFilter !== '' &&
    !in_array($categoryFilter, $allowedCategories, true)
) {
    $categoryFilter = '';
}

/*
|--------------------------------------------------------------------------
| Retrieve clubs for the filter
|--------------------------------------------------------------------------
*/

$clubsResult = mysqli_query(
    $conn,
    "
        SELECT
            clubNumber,
            clubName
        FROM clubs
        ORDER BY clubName
    "
);

if (!$clubsResult) {
    die('Unable to retrieve clubs: ' .
        mysqli_error($conn));
}

/*
|--------------------------------------------------------------------------
| Build report conditions
|--------------------------------------------------------------------------
*/

$whereConditions = [];
$parameterTypes = '';
$parameterValues = [];

if ($dateFrom !== '') {
    $whereConditions[] = "DATE(r.requestTime) >= ?";
    $parameterTypes .= 's';
    $parameterValues[] = $dateFrom;
}

if ($dateTo !== '') {
    $whereConditions[] = "DATE(r.requestTime) <= ?";
    $parameterTypes .= 's';
    $parameterValues[] = $dateTo;
}

if ($statusFilter !== '') {
    $whereConditions[] = "r.status = ?";
    $parameterTypes .= 's';
    $parameterValues[] = $statusFilter;
}

if ($categoryFilter !== '') {
    $whereConditions[] = "res.resourceCategory = ?";
    $parameterTypes .= 's';
    $parameterValues[] = $categoryFilter;
}

if ($clubFilter) {
    $whereConditions[] = "c.clubNumber = ?";
    $parameterTypes .= 'i';
    $parameterValues[] = $clubFilter;
}

$whereSql = '';

if (!empty($whereConditions)) {
    $whereSql = ' WHERE ' . implode(
        ' AND ',
        $whereConditions
    );
}

/*
|--------------------------------------------------------------------------
| Function for binding dynamic parameters
|--------------------------------------------------------------------------
*/

function bindDynamicParameters(
    mysqli_stmt $stmt,
    string $types,
    array &$values
): void {
    if ($types === '' || empty($values)) {
        return;
    }

    $bindValues = [];
    $bindValues[] = $types;

    foreach ($values as $key => &$value) {
        $bindValues[] = &$value;
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bindValues
    );
}

/*
|--------------------------------------------------------------------------
| Summary statistics
|--------------------------------------------------------------------------
*/

$summarySql = "
    SELECT
        COUNT(*) AS totalRequisitions,

        SUM(
            CASE
                WHEN r.status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pendingTotal,

        SUM(
            CASE
                WHEN r.status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approvedTotal,

        SUM(
            CASE
                WHEN r.status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejectedTotal,

        SUM(
            CASE
                WHEN r.status = 'Cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelledTotal,

        COALESCE(
            SUM(r.quantityRequested),
            0
        ) AS totalQuantityRequested

    FROM requisitions r

    INNER JOIN club_officials co
        ON r.submittedByAdmNo = co.admNo

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    $whereSql
";

$summaryStmt = mysqli_prepare(
    $conn,
    $summarySql
);

if (!$summaryStmt) {
    die('Unable to prepare report summary: ' .
        mysqli_error($conn));
}

bindDynamicParameters(
    $summaryStmt,
    $parameterTypes,
    $parameterValues
);

mysqli_stmt_execute($summaryStmt);

$summaryResult =
    mysqli_stmt_get_result($summaryStmt);

$summary =
    mysqli_fetch_assoc($summaryResult);

mysqli_stmt_close($summaryStmt);

$totalRequisitions =
    (int) ($summary['totalRequisitions'] ?? 0);

$pendingTotal =
    (int) ($summary['pendingTotal'] ?? 0);

$approvedTotal =
    (int) ($summary['approvedTotal'] ?? 0);

$rejectedTotal =
    (int) ($summary['rejectedTotal'] ?? 0);

$cancelledTotal =
    (int) ($summary['cancelledTotal'] ?? 0);

$totalQuantityRequested =
    (int) ($summary['totalQuantityRequested'] ?? 0);

/*
|--------------------------------------------------------------------------
| Detailed requisition report
|--------------------------------------------------------------------------
*/

$reportSql = "
    SELECT
        r.requisitionID,
        r.requisitionNumber,
        r.purpose,
        r.quantityRequested,
        r.startDate,
        r.endDate,
        r.requestTime,
        r.status,

        co.admNo,
        co.officialName,

        c.clubName,

        res.resourceName,
        res.resourceCategory

    FROM requisitions r

    INNER JOIN club_officials co
        ON r.submittedByAdmNo = co.admNo

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    INNER JOIN resources res
        ON r.resourceID = res.resourceID

    $whereSql

    ORDER BY r.requestTime DESC
";

$reportStmt = mysqli_prepare(
    $conn,
    $reportSql
);

if (!$reportStmt) {
    die('Unable to prepare requisition report: ' .
        mysqli_error($conn));
}

bindDynamicParameters(
    $reportStmt,
    $parameterTypes,
    $parameterValues
);

mysqli_stmt_execute($reportStmt);

$reportResult =
    mysqli_stmt_get_result($reportStmt);

/*
|--------------------------------------------------------------------------
| Resource usage report
|--------------------------------------------------------------------------
*/

$resourceSql = "
    SELECT
        res.resourceID,
        res.resourceName,
        res.resourceCategory,
        res.resourceQuantityTotal,
        res.resourceQuantityRemaining,

        COUNT(r.requisitionID) AS requisitionCount,

        COALESCE(
            SUM(
                CASE
                    WHEN r.status = 'Approved'
                    THEN r.quantityRequested
                    ELSE 0
                END
            ),
            0
        ) AS approvedQuantity

    FROM resources res

    LEFT JOIN requisitions r
        ON res.resourceID = r.resourceID

    GROUP BY
        res.resourceID,
        res.resourceName,
        res.resourceCategory,
        res.resourceQuantityTotal,
        res.resourceQuantityRemaining

    ORDER BY requisitionCount DESC,
             res.resourceName
";

$resourceResult = mysqli_query(
    $conn,
    $resourceSql
);

if (!$resourceResult) {
    die('Unable to generate resource report: ' .
        mysqli_error($conn));
}

/*
|--------------------------------------------------------------------------
| Club activity report
|--------------------------------------------------------------------------
*/

$clubActivitySql = "
    SELECT
        c.clubNumber,
        c.clubName,

        COUNT(r.requisitionID) AS totalRequests,

        SUM(
            CASE
                WHEN r.status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approvedRequests,

        SUM(
            CASE
                WHEN r.status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pendingRequests,

        SUM(
            CASE
                WHEN r.status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejectedRequests

    FROM clubs c

    LEFT JOIN club_officials co
        ON c.clubNumber = co.clubNumber

    LEFT JOIN requisitions r
        ON co.admNo = r.submittedByAdmNo

    GROUP BY
        c.clubNumber,
        c.clubName

    ORDER BY totalRequests DESC,
             c.clubName
";

$clubActivityResult = mysqli_query(
    $conn,
    $clubActivitySql
);

if (!$clubActivityResult) {
    die('Unable to generate club report: ' .
        mysqli_error($conn));
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Reports</h1>

            <p>
                View requisition activity, resource usage,
                and club participation.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-secondary"
            onclick="window.print();">
            Print Report
        </button>

    </div>

    <!-- Report filters -->

    <div class="card report-filter-card">

        <form
            method="GET"
            action="reports.php"
            class="report-filter-form">

            <div class="form-grid">

                <div class="form-group">

                    <label for="dateFrom">
                        Date From
                    </label>

                    <input
                        type="date"
                        id="dateFrom"
                        name="dateFrom"
                        value="<?= htmlspecialchars($dateFrom); ?>">

                </div>

                <div class="form-group">

                    <label for="dateTo">
                        Date To
                    </label>

                    <input
                        type="date"
                        id="dateTo"
                        name="dateTo"
                        value="<?= htmlspecialchars($dateTo); ?>">

                </div>

                <div class="form-group">

                    <label for="status">
                        Requisition Status
                    </label>

                    <select
                        id="status"
                        name="status">

                        <option value="">
                            All Statuses
                        </option>

                        <?php foreach (
                            $allowedStatuses as $status
                        ): ?>

                            <option
                                value="<?= htmlspecialchars($status); ?>"
                                <?= $statusFilter === $status
                                    ? 'selected'
                                    : ''; ?>>
                                <?= htmlspecialchars($status); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="category">
                        Resource Category
                    </label>

                    <select
                        id="category"
                        name="category">

                        <option value="">
                            All Categories
                        </option>

                        <?php foreach (
                            $allowedCategories as $category
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                            $category
                                        ); ?>"
                                <?= $categoryFilter === $category
                                    ? 'selected'
                                    : ''; ?>>
                                <?= htmlspecialchars($category); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="clubNumber">
                        Club
                    </label>

                    <select
                        id="clubNumber"
                        name="clubNumber">

                        <option value="">
                            All Clubs
                        </option>

                        <?php while (
                            $club = mysqli_fetch_assoc($clubsResult)
                        ): ?>

                            <option
                                value="<?= (int) $club['clubNumber']; ?>"
                                <?= (int) $clubFilter ===
                                    (int) $club['clubNumber']
                                    ? 'selected'
                                    : ''; ?>>
                                <?= htmlspecialchars(
                                    $club['clubName']
                                ); ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary">
                    Generate Report
                </button>

                <a
                    href="reports.php"
                    class="btn btn-secondary">
                    Clear Filters
                </a>

            </div>

        </form>

    </div>

    <!-- Summary cards -->

    <div class="stats-grid report-summary">

        <div class="stat-card">
            <h3>Total Requisitions</h3>

            <div class="stat-number">
                <?= $totalRequisitions; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Pending</h3>

            <div class="stat-number">
                <?= $pendingTotal; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Approved</h3>

            <div class="stat-number">
                <?= $approvedTotal; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Rejected</h3>

            <div class="stat-number">
                <?= $rejectedTotal; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Cancelled</h3>

            <div class="stat-number">
                <?= $cancelledTotal; ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Quantity Requested</h3>

            <div class="stat-number">
                <?= $totalQuantityRequested; ?>
            </div>
        </div>

    </div>

    <!-- Detailed requisitions report -->

    <div class="card report-section">

        <div class="section-header">
            <h2>Requisition Report</h2>

            <p>
                Detailed requisitions matching the selected filters.
            </p>
        </div>

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Requisition</th>
                        <th>Club</th>
                        <th>Submitted By</th>
                        <th>Resource</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Required Dates</th>
                        <th>Submitted</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows($reportResult) > 0
                    ): ?>

                        <?php while (
                            $row = mysqli_fetch_assoc($reportResult)
                        ): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?php
                                        if (
                                            !empty($row['requisitionNumber'])
                                        ) {
                                            echo htmlspecialchars(
                                                $row['requisitionNumber']
                                            );
                                        } else {
                                            echo 'RQ-' .
                                                str_pad(
                                                    $row['requisitionID'],
                                                    4,
                                                    '0',
                                                    STR_PAD_LEFT
                                                );
                                        }
                                        ?>
                                    </strong>

                                    <?php if (
                                        !empty($row['purpose'])
                                    ): ?>

                                        <br>

                                        <small class="text-muted">
                                            <?= htmlspecialchars(
                                                $row['purpose']
                                            ); ?>
                                        </small>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['clubName']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['officialName']
                                    ); ?>

                                    <br>

                                    <small class="text-muted">
                                        <?= htmlspecialchars(
                                            $row['admNo']
                                        ); ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['resourceName']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['resourceCategory']
                                    ); ?>
                                </td>

                                <td>
                                    <?= (int) $row['quantityRequested']; ?>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y',
                                        strtotime($row['startDate'])
                                    ); ?>

                                    <br>

                                    <small class="text-muted">
                                        to
                                        <?= date(
                                            'd M Y',
                                            strtotime($row['endDate'])
                                        ); ?>
                                    </small>
                                </td>

                                <td>
                                    <?= date(
                                        'd M Y H:i',
                                        strtotime(
                                            $row['requestTime']
                                        )
                                    ); ?>
                                </td>

                                <td>

                                    <?php
                                    $statusClass =
                                        'badge-secondary';

                                    if (
                                        $row['status'] === 'Approved'
                                    ) {
                                        $statusClass =
                                            'badge-success';
                                    } elseif (
                                        $row['status'] === 'Rejected'
                                    ) {
                                        $statusClass =
                                            'badge-danger';
                                    } elseif (
                                        $row['status'] === 'Pending'
                                    ) {
                                        $statusClass =
                                            'badge-warning';
                                    }
                                    ?>

                                    <span
                                        class="badge <?= $statusClass; ?>">
                                        <?= htmlspecialchars(
                                            $row['status']
                                        ); ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="9"
                                class="empty-state">
                                No requisitions match the selected
                                filters.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Resource usage -->

    <div class="card report-section">

        <div class="section-header">
            <h2>Resource Usage Report</h2>

            <p>
                Requisition frequency and approved quantities
                for each resource.
            </p>
        </div>

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Category</th>
                        <th>Total Quantity</th>
                        <th>Remaining</th>
                        <th>Total Requests</th>
                        <th>Approved Quantity</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows($resourceResult) > 0
                    ): ?>

                        <?php while (
                            $resource = mysqli_fetch_assoc(
                                $resourceResult
                            )
                        ): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $resource['resourceName']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $resource['resourceCategory']
                                    ); ?>
                                </td>

                                <td>
                                    <?= (int) $resource['resourceQuantityTotal']; ?>
                                </td>

                                <td>
                                    <?= (int) $resource['resourceQuantityRemaining']; ?>
                                </td>

                                <td>
                                    <?= (int) $resource['requisitionCount']; ?>
                                </td>

                                <td>
                                    <?= (int) $resource['approvedQuantity']; ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="6"
                                class="empty-state">
                                No resources have been registered.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- Club activity -->

    <div class="card report-section">

        <div class="section-header">
            <h2>Club Activity Report</h2>

            <p>
                Requisition activity for each registered club.
            </p>
        </div>

        <div class="table-responsive">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>Club</th>
                        <th>Total Requests</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Rejected</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (
                        mysqli_num_rows(
                            $clubActivityResult
                        ) > 0
                    ): ?>

                        <?php while (
                            $club = mysqli_fetch_assoc(
                                $clubActivityResult
                            )
                        ): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $club['clubName']
                                    ); ?>
                                </td>

                                <td>
                                    <?= (int) $club['totalRequests']; ?>
                                </td>

                                <td>
                                    <?= (int) $club['approvedRequests']; ?>
                                </td>

                                <td>
                                    <?= (int) $club['pendingRequests']; ?>
                                </td>

                                <td>
                                    <?= (int) $club['rejectedRequests']; ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="5"
                                class="empty-state">
                                No club activity is available.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php

mysqli_stmt_close($reportStmt);

require_once '../includes/footer.php';

?>