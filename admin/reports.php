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

$pageTitle = 'Reports';

/*
|--------------------------------------------------------------------------
| Validate filters
|--------------------------------------------------------------------------
*/

$statusFilter =
    trim($_GET['status'] ?? '');

$categoryFilter =
    trim($_GET['category'] ?? '');

$startDate =
    trim($_GET['startDate'] ?? '');

$endDate =
    trim($_GET['endDate'] ?? '');

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
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = '';
}

if (
    $categoryFilter !== '' &&
    !in_array(
        $categoryFilter,
        $allowedCategories,
        true
    )
) {
    $categoryFilter = '';
}

if (
    $startDate !== '' &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $startDate
    )
) {
    $startDate = '';
}

if (
    $endDate !== '' &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $endDate
    )
) {
    $endDate = '';
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
                WHEN status = 'Pending'
                THEN 1
                ELSE 0
            END
        ) AS pendingRequisitions,

        SUM(
            CASE
                WHEN status = 'Approved'
                THEN 1
                ELSE 0
            END
        ) AS approvedRequisitions,

        SUM(
            CASE
                WHEN status = 'Rejected'
                THEN 1
                ELSE 0
            END
        ) AS rejectedRequisitions,

        SUM(
            CASE
                WHEN status = 'Cancelled'
                THEN 1
                ELSE 0
            END
        ) AS cancelledRequisitions,

        SUM(quantityRequested)
            AS totalQuantityRequested

    FROM requisitions
";

$summaryResult =
    mysqli_query(
        $conn,
        $summarySql
    );

$summary =
    mysqli_fetch_assoc(
        $summaryResult
    );

$totalRequisitions =
    (int) (
        $summary[
            'totalRequisitions'
        ] ?? 0
    );

$pendingRequisitions =
    (int) (
        $summary[
            'pendingRequisitions'
        ] ?? 0
    );

$approvedRequisitions =
    (int) (
        $summary[
            'approvedRequisitions'
        ] ?? 0
    );

$rejectedRequisitions =
    (int) (
        $summary[
            'rejectedRequisitions'
        ] ?? 0
    );

$cancelledRequisitions =
    (int) (
        $summary[
            'cancelledRequisitions'
        ] ?? 0
    );

$totalQuantityRequested =
    (int) (
        $summary[
            'totalQuantityRequested'
        ] ?? 0
    );

/*
|--------------------------------------------------------------------------
| Build report query
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

        rs.resourceName,
        rs.resourceCategory,

        co.officialName,
        co.admNo,

        c.clubName

    FROM requisitions r

    INNER JOIN resources rs
        ON r.resourceID =
           rs.resourceID

    INNER JOIN club_officials co
        ON r.submittedByAdmNo =
           co.admNo

    INNER JOIN clubs c
        ON co.clubNumber =
           c.clubNumber

    WHERE 1 = 1
";

$types = '';
$params = [];

if ($statusFilter !== '') {

    $reportSql .= "
        AND r.status = ?
    ";

    $types .= 's';
    $params[] =
        $statusFilter;
}

if ($categoryFilter !== '') {

    $reportSql .= "
        AND rs.resourceCategory = ?
    ";

    $types .= 's';
    $params[] =
        $categoryFilter;
}

if ($startDate !== '') {

    $reportSql .= "
        AND DATE(r.requestTime) >= ?
    ";

    $types .= 's';
    $params[] =
        $startDate;
}

if ($endDate !== '') {

    $reportSql .= "
        AND DATE(r.requestTime) <= ?
    ";

    $types .= 's';
    $params[] =
        $endDate;
}

$reportSql .= "
    ORDER BY r.requestTime DESC
";

$reportStmt =
    mysqli_prepare(
        $conn,
        $reportSql
    );

if (!$reportStmt) {
    die(
        'Unable to prepare report query.'
    );
}

if ($types !== '') {

    mysqli_stmt_bind_param(
        $reportStmt,
        $types,
        ...$params
    );
}

mysqli_stmt_execute(
    $reportStmt
);

$reportResult =
    mysqli_stmt_get_result(
        $reportStmt
    );

require_once '../includes/header.php';
?>

<div class="page-header">

    <div>

        <h1>Reports</h1>

        <p>
            Review requisition activity and filter institutional
            resource requests.
        </p>

    </div>

</div>

<div class="report-summary-grid">

    <div class="report-summary-tile">

        <div class="report-summary-icon">
            T
        </div>

        <div class="report-summary-content">

            <span>
                Total Requisitions
            </span>

            <strong>
                <?= $totalRequisitions; ?>
            </strong>

            <small>
                All submitted requests
            </small>

        </div>

    </div>

    <div class="report-summary-tile">

        <div class="report-summary-icon report-icon-warning">
            P
        </div>

        <div class="report-summary-content">

            <span>
                Pending
            </span>

            <strong>
                <?= $pendingRequisitions; ?>
            </strong>

            <small>
                Awaiting approval
            </small>

        </div>

    </div>

    <div class="report-summary-tile">

        <div class="report-summary-icon report-icon-success">
            A
        </div>

        <div class="report-summary-content">

            <span>
                Approved
            </span>

            <strong>
                <?= $approvedRequisitions; ?>
            </strong>

            <small>
                Fully approved
            </small>

        </div>

    </div>

    <div class="report-summary-tile">

        <div class="report-summary-icon report-icon-danger">
            R
        </div>

        <div class="report-summary-content">

            <span>
                Rejected
            </span>

            <strong>
                <?= $rejectedRequisitions; ?>
            </strong>

            <small>
                Declined requests
            </small>

        </div>

    </div>

    <div class="report-summary-tile">

        <div class="report-summary-icon report-icon-secondary">
            C
        </div>

        <div class="report-summary-content">

            <span>
                Cancelled
            </span>

            <strong>
                <?= $cancelledRequisitions; ?>
            </strong>

            <small>
                Cancelled requests
            </small>

        </div>

    </div>

    <div class="report-summary-tile">

        <div class="report-summary-icon report-icon-info">
            Q
        </div>

        <div class="report-summary-content">

            <span>
                Quantity Requested
            </span>

            <strong>
                <?= $totalQuantityRequested; ?>
            </strong>

            <small>
                Combined requested units
            </small>

        </div>

    </div>

</div>

<div class="card report-filter-card">

    <div class="section-header">

        <h2>Filter Report</h2>

        <p>
            Narrow the report by status, category or date.
        </p>

    </div>

    <form
        method="GET"
        class="report-filter-form"
    >

        <div class="form-grid report-filter-grid">

            <div class="form-group">

                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option value="">
                        All statuses
                    </option>

                    <?php foreach (
                        $allowedStatuses as $status
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $status
                            ); ?>"
                            <?= $statusFilter === $status
                                ? 'selected'
                                : ''; ?>
                        >
                            <?= htmlspecialchars(
                                $status
                            ); ?>
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
                    name="category"
                >

                    <option value="">
                        All categories
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
                                : ''; ?>
                        >
                            <?= htmlspecialchars(
                                $category
                            ); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label for="startDate">
                    From Date
                </label>

                <input
                    type="date"
                    id="startDate"
                    name="startDate"
                    value="<?= htmlspecialchars(
                        $startDate
                    ); ?>"
                >

            </div>

            <div class="form-group">

                <label for="endDate">
                    To Date
                </label>

                <input
                    type="date"
                    id="endDate"
                    name="endDate"
                    value="<?= htmlspecialchars(
                        $endDate
                    ); ?>"
                >

            </div>

        </div>

        <div class="form-actions">

            <a
                href="reports.php"
                class="btn btn-secondary"
            >
                Clear Filters
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Apply Filters
            </button>

        </div>

    </form>

</div>

<div class="card table-card report-table-card">

    <div class="table-card-header">

        <div>

            <h2>Requisition Report</h2>

            <p>
                <?= mysqli_num_rows(
                    $reportResult
                ); ?>
                record(s) found
            </p>

        </div>

    </div>

    <div class="table-responsive">

        <table class="data-table report-table">

            <thead>

                <tr>
                    <th>Requisition</th>
                    <th>Official</th>
                    <th>Club</th>
                    <th>Resource</th>
                    <th>Quantity</th>
                    <th>Required Period</th>
                    <th>Submitted</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

                <?php if (
                    mysqli_num_rows(
                        $reportResult
                    ) > 0
                ): ?>

                    <?php while (
                        $row =
                            mysqli_fetch_assoc(
                                $reportResult
                            )
                    ): ?>

                        <?php

                        $statusClass =
                            match (
                                $row['status']
                            ) {
                                'Approved' =>
                                    'badge-success',

                                'Rejected' =>
                                    'badge-danger',

                                'Pending' =>
                                    'badge-warning',

                                'Cancelled' =>
                                    'badge-secondary',

                                default =>
                                    'badge-info'
                            };

                        ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $row[
                                            'requisitionNumber'
                                        ] ?? 'Not assigned'
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    <?= htmlspecialchars(
                                        $row['purpose']
                                    ); ?>
                                </small>

                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $row[
                                            'officialName'
                                        ]
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    <?= htmlspecialchars(
                                        $row['admNo']
                                    ); ?>
                                </small>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['clubName']
                                ); ?>
                            </td>

                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $row[
                                            'resourceName'
                                        ]
                                    ); ?>
                                </strong>

                                <small class="table-subtext">
                                    <?= htmlspecialchars(
                                        $row[
                                            'resourceCategory'
                                        ]
                                    ); ?>
                                </small>

                            </td>

                            <td>
                                <?= (int) $row[
                                    'quantityRequested'
                                ]; ?>
                            </td>

                            <td>

                                <?php if (
                                    !empty(
                                        $row['startDate']
                                    ) &&
                                    !empty(
                                        $row['endDate']
                                    )
                                ): ?>

                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $row['startDate']
                                        )
                                    ); ?>

                                    <span class="table-date-separator">
                                        –
                                    </span>

                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $row['endDate']
                                        )
                                    ); ?>

                                <?php else: ?>

                                    <span class="table-muted">
                                        Not specified
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= date(
                                    'd M Y, H:i',
                                    strtotime(
                                        $row['requestTime']
                                    )
                                ); ?>
                            </td>

                            <td>

                                <span
                                    class="badge <?= $statusClass; ?>"
                                >
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
                            colspan="8"
                            class="empty-state"
                        >

                            <strong>
                                No requisitions found
                            </strong>

                            <p>
                                No records match the selected
                                filters.
                            </p>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php

mysqli_stmt_close(
    $reportStmt
);

require_once '../includes/footer.php';

?>