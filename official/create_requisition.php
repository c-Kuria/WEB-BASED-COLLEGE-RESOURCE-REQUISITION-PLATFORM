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
| Load approval routes
|--------------------------------------------------------------------------
*/

$approvalRoutesFile =
    '../includes/approval_routes.php';

if (!file_exists($approvalRoutesFile)) {
    die(
        'Approval route configuration could not be found.'
    );
}

$approvalRoutes = require $approvalRoutesFile;

if (!is_array($approvalRoutes)) {
    die('Approval route configuration is invalid.');
}

/*
|--------------------------------------------------------------------------
| Retrieve logged-in official
|--------------------------------------------------------------------------
*/

$officialSql = "
    SELECT
        co.admNo,
        co.officialName,
        co.position,
        co.clubNumber,
        c.clubName
    FROM club_officials co

    INNER JOIN clubs c
        ON co.clubNumber = c.clubNumber

    WHERE co.userID = ?

    LIMIT 1
";

$officialStmt = mysqli_prepare(
    $conn,
    $officialSql
);

if (!$officialStmt) {
    die(
        'Unable to prepare official profile query: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $officialStmt,
    'i',
    $userID
);

mysqli_stmt_execute($officialStmt);

$officialResult =
    mysqli_stmt_get_result($officialStmt);

$official =
    mysqli_fetch_assoc($officialResult);

mysqli_stmt_close($officialStmt);

if (!$official) {
    die(
        'Your club official profile could not be found. ' .
        'Contact the administrator.'
    );
}

$admNo = $official['admNo'];

/*
|--------------------------------------------------------------------------
| Retrieve active resources
|--------------------------------------------------------------------------
*/

$resourcesSql = "
    SELECT
        resourceID,
        resourceName,
        resourceCategory,
        resourceDescription,
        resourceQuantityTotal,
        resourceQuantityRemaining
    FROM resources
    WHERE status = 'Active'
    ORDER BY resourceCategory, resourceName
";

$resourcesResult = mysqli_query(
    $conn,
    $resourcesSql
);

if (!$resourcesResult) {
    die(
        'Unable to retrieve resources: ' .
        mysqli_error($conn)
    );
}

/*
|--------------------------------------------------------------------------
| Default form values
|--------------------------------------------------------------------------
*/

$resourceID = '';
$purpose = '';
$quantityRequested = 1;
$startDate = '';
$endDate = '';

$errors = [];

/*
|--------------------------------------------------------------------------
| Process form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $resourceID = filter_input(
        INPUT_POST,
        'resourceID',
        FILTER_VALIDATE_INT
    );

    $purpose = trim(
        $_POST['purpose'] ?? ''
    );

    $quantityRequested = filter_input(
        INPUT_POST,
        'quantityRequested',
        FILTER_VALIDATE_INT
    );

    $startDate = trim(
        $_POST['startDate'] ?? ''
    );

    $endDate = trim(
        $_POST['endDate'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | Basic validation
    |--------------------------------------------------------------------------
    */

    if (!$resourceID) {
        $errors[] = 'Select a valid resource.';
    }

    if ($purpose === '') {
        $errors[] = 'Purpose is required.';
    }

    if (strlen($purpose) > 1000) {
        $errors[] =
            'Purpose cannot exceed 1000 characters.';
    }

    if (
        $quantityRequested === false ||
        $quantityRequested < 1
    ) {
        $errors[] =
            'Requested quantity must be at least 1.';
    }

    if ($startDate === '') {
        $errors[] = 'Start date is required.';
    }

    if ($endDate === '') {
        $errors[] = 'End date is required.';
    }

    /*
     * Validate date format.
     */
    $startDateObject = DateTime::createFromFormat(
        'Y-m-d',
        $startDate
    );

    $endDateObject = DateTime::createFromFormat(
        'Y-m-d',
        $endDate
    );

    if (
        $startDate !== '' &&
        (
            !$startDateObject ||
            $startDateObject->format('Y-m-d') !==
            $startDate
        )
    ) {
        $errors[] = 'Start date is invalid.';
    }

    if (
        $endDate !== '' &&
        (
            !$endDateObject ||
            $endDateObject->format('Y-m-d') !==
            $endDate
        )
    ) {
        $errors[] = 'End date is invalid.';
    }

    if (
        $startDateObject &&
        $endDateObject &&
        $endDateObject < $startDateObject
    ) {
        $errors[] =
            'End date cannot be earlier than the start date.';
    }

    $today = new DateTime('today');

    if (
        $startDateObject &&
        $startDateObject < $today
    ) {
        $errors[] =
            'The resource start date cannot be in the past.';
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieve and validate resource
    |--------------------------------------------------------------------------
    */

    $selectedResource = null;

    if (empty($errors)) {

        $resourceSql = "
            SELECT
                resourceID,
                resourceName,
                resourceCategory,
                resourceQuantityTotal,
                resourceQuantityRemaining,
                status
            FROM resources
            WHERE resourceID = ?
            LIMIT 1
        ";

        $resourceStmt = mysqli_prepare(
            $conn,
            $resourceSql
        );

        if (!$resourceStmt) {

            $errors[] =
                'Unable to validate the selected resource.';

        } else {

            mysqli_stmt_bind_param(
                $resourceStmt,
                'i',
                $resourceID
            );

            mysqli_stmt_execute($resourceStmt);

            $resourceResult =
                mysqli_stmt_get_result($resourceStmt);

            $selectedResource =
                mysqli_fetch_assoc($resourceResult);

            mysqli_stmt_close($resourceStmt);

            if (!$selectedResource) {

                $errors[] =
                    'The selected resource does not exist.';

            } elseif (
                $selectedResource['status'] !== 'Active'
            ) {

                $errors[] =
                    'The selected resource is currently inactive.';

            } elseif (
                $quantityRequested >
                (int) $selectedResource[
                    'resourceQuantityRemaining'
                ]
            ) {

                $errors[] =
                    'Only ' .
                    (int) $selectedResource[
                        'resourceQuantityRemaining'
                    ] .
                    ' unit(s) of this resource are currently available.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate approval route
    |--------------------------------------------------------------------------
    */

    $approvalRoute = [];

    if (
        empty($errors) &&
        $selectedResource
    ) {

        $resourceCategory =
            $selectedResource['resourceCategory'];

        if (
            !isset($approvalRoutes[$resourceCategory]) ||
            !is_array(
                $approvalRoutes[$resourceCategory]
            ) ||
            empty($approvalRoutes[$resourceCategory])
        ) {
            $errors[] =
                'No approval route has been configured for the ' .
                $resourceCategory .
                ' category.';
        } else {
            $approvalRoute =
                $approvalRoutes[$resourceCategory];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Verify officers exist before creating requisition
    |--------------------------------------------------------------------------
    */

    $approvalAssignments = [];

    if (
        empty($errors) &&
        !empty($approvalRoute)
    ) {

        foreach (
            $approvalRoute as $approvalOrder => $role
        ) {

            /*
             * Retrieve the primary officer for this role.
             *
             * An available officer is preferred when more than
             * one officer has the same role.
             */
            $officerSql = "
                SELECT
                    o.officerStaffNo,
                    o.officerName,
                    o.officerRole,
                    o.availability,
                    o.proxyOfficerStaffNo,
                    u.status AS accountStatus
                FROM officers o

                INNER JOIN users u
                    ON o.userID = u.userID

                WHERE o.officerRole = ?
                  AND u.status = 'Active'

                ORDER BY
                    CASE
                        WHEN o.availability = 'Available'
                        THEN 0
                        ELSE 1
                    END,
                    o.createdAt ASC

                LIMIT 1
            ";

            $officerStmt = mysqli_prepare(
                $conn,
                $officerSql
            );

            if (!$officerStmt) {
                $errors[] =
                    'Unable to retrieve the officer responsible for ' .
                    $role .
                    '.';

                break;
            }

            mysqli_stmt_bind_param(
                $officerStmt,
                's',
                $role
            );

            mysqli_stmt_execute($officerStmt);

            $officerResult =
                mysqli_stmt_get_result($officerStmt);

            $primaryOfficer =
                mysqli_fetch_assoc($officerResult);

            mysqli_stmt_close($officerStmt);

            if (!$primaryOfficer) {
                $errors[] =
                    'No active officer has been registered for the role: ' .
                    $role .
                    '.';

                break;
            }

            $assignedAs = 'Primary';
            $delegatedToStaffNo = null;
            $delegatedToName = null;

            /*
             * When the primary officer is unavailable,
             * verify and assign their proxy.
             */
            if (
                $primaryOfficer['availability'] ===
                'Unavailable'
            ) {

                $proxyStaffNo =
                    $primaryOfficer[
                        'proxyOfficerStaffNo'
                    ];

                if (empty($proxyStaffNo)) {
                    $errors[] =
                        $primaryOfficer['officerName'] .
                        ' is unavailable and does not have a proxy officer.';

                    break;
                }

                $proxySql = "
                    SELECT
                        o.officerStaffNo,
                        o.officerName,
                        o.availability,
                        u.status AS accountStatus
                    FROM officers o

                    INNER JOIN users u
                        ON o.userID = u.userID

                    WHERE o.officerStaffNo = ?
                      AND u.status = 'Active'

                    LIMIT 1
                ";

                $proxyStmt = mysqli_prepare(
                    $conn,
                    $proxySql
                );

                if (!$proxyStmt) {
                    $errors[] =
                        'Unable to validate the proxy for ' .
                        $primaryOfficer['officerName'] .
                        '.';

                    break;
                }

                mysqli_stmt_bind_param(
                    $proxyStmt,
                    's',
                    $proxyStaffNo
                );

                mysqli_stmt_execute($proxyStmt);

                $proxyResult =
                    mysqli_stmt_get_result($proxyStmt);

                $proxyOfficer =
                    mysqli_fetch_assoc($proxyResult);

                mysqli_stmt_close($proxyStmt);

                if (!$proxyOfficer) {
                    $errors[] =
                        'The proxy assigned to ' .
                        $primaryOfficer['officerName'] .
                        ' does not have an active account.';

                    break;
                }

                if (
                    $proxyOfficer['availability'] ===
                    'Unavailable'
                ) {
                    $errors[] =
                        'The proxy officer assigned to ' .
                        $primaryOfficer['officerName'] .
                        ' is also unavailable.';

                    break;
                }

                $assignedAs = 'Proxy';

                /*
                 * actedBy temporarily stores the proxy who has
                 * been delegated this approval.
                 *
                 * It will remain the proxy's staff number when
                 * the proxy performs the approval action.
                 */
                $delegatedToStaffNo =
                    $proxyOfficer['officerStaffNo'];

                $delegatedToName =
                    $proxyOfficer['officerName'];
            }

            $approvalAssignments[] = [
                'approvalOrder' =>
                    $approvalOrder + 1,

                'role' =>
                    $role,

                'primaryStaffNo' =>
                    $primaryOfficer[
                        'officerStaffNo'
                    ],

                'primaryOfficerName' =>
                    $primaryOfficer[
                        'officerName'
                    ],

                'assignedAs' =>
                    $assignedAs,

                'delegatedToStaffNo' =>
                    $delegatedToStaffNo,

                'delegatedToName' =>
                    $delegatedToName
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create requisition and approval stages
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        mysqli_begin_transaction($conn);

        try {

            /*
             * Generate a unique requisition number.
             */
            $requisitionNumber =
                'REQ-' .
                date('Ymd-His') .
                '-' .
                strtoupper(
                    bin2hex(random_bytes(2))
                );

            $requisitionSql = "
                INSERT INTO requisitions (
                    requisitionNumber,
                    submittedByAdmNo,
                    resourceID,
                    purpose,
                    quantityRequested,
                    startDate,
                    endDate,
                    status
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Pending'
                )
            ";

            $requisitionStmt = mysqli_prepare(
                $conn,
                $requisitionSql
            );

            if (!$requisitionStmt) {
                throw new Exception(
                    'Unable to prepare the requisition record.'
                );
            }

            mysqli_stmt_bind_param(
                $requisitionStmt,
                'ssisiss',
                $requisitionNumber,
                $admNo,
                $resourceID,
                $purpose,
                $quantityRequested,
                $startDate,
                $endDate
            );

            if (
                !mysqli_stmt_execute(
                    $requisitionStmt
                )
            ) {
                throw new Exception(
                    'Unable to submit the requisition: ' .
                    mysqli_stmt_error(
                        $requisitionStmt
                    )
                );
            }

            $requisitionID =
                mysqli_insert_id($conn);

            mysqli_stmt_close(
                $requisitionStmt
            );

            /*
             * Create all approval stages.
             *
             * First stage: Pending
             * Other stages: Waiting
             */
            foreach (
                $approvalAssignments as $assignment
            ) {

                $approvalStatus =
                    $assignment['approvalOrder'] === 1
                    ? 'Pending'
                    : 'Waiting';

                $primaryStaffNo =
                    $assignment['primaryStaffNo'];

                $approvalOrder =
                    $assignment['approvalOrder'];

                $assignedAs =
                    $assignment['assignedAs'];

                $delegatedStaffNo =
                    $assignment[
                        'delegatedToStaffNo'
                    ];

                if ($delegatedStaffNo === null) {

                    $approvalSql = "
                        INSERT INTO approvals (
                            requisitionID,
                            officerStaffNo,
                            approvalOrder,
                            status,
                            assignedAs,
                            actedBy,
                            comments,
                            approvalTime
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            NULL,
                            NULL,
                            NULL
                        )
                    ";

                    $approvalStmt =
                        mysqli_prepare(
                            $conn,
                            $approvalSql
                        );

                    if (!$approvalStmt) {
                        throw new Exception(
                            'Unable to prepare an approval stage.'
                        );
                    }

                    mysqli_stmt_bind_param(
                        $approvalStmt,
                        'isiss',
                        $requisitionID,
                        $primaryStaffNo,
                        $approvalOrder,
                        $approvalStatus,
                        $assignedAs
                    );

                } else {

                    $approvalSql = "
                        INSERT INTO approvals (
                            requisitionID,
                            officerStaffNo,
                            approvalOrder,
                            status,
                            assignedAs,
                            actedBy,
                            comments,
                            approvalTime
                        )
                        VALUES (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            NULL,
                            NULL
                        )
                    ";

                    $approvalStmt =
                        mysqli_prepare(
                            $conn,
                            $approvalSql
                        );

                    if (!$approvalStmt) {
                        throw new Exception(
                            'Unable to prepare a delegated approval stage.'
                        );
                    }

                    mysqli_stmt_bind_param(
                        $approvalStmt,
                        'isisss',
                        $requisitionID,
                        $primaryStaffNo,
                        $approvalOrder,
                        $approvalStatus,
                        $assignedAs,
                        $delegatedStaffNo
                    );
                }

                if (
                    !mysqli_stmt_execute(
                        $approvalStmt
                    )
                ) {
                    throw new Exception(
                        'Unable to create approval stage ' .
                        $approvalOrder .
                        ': ' .
                        mysqli_stmt_error(
                            $approvalStmt
                        )
                    );
                }

                mysqli_stmt_close(
                    $approvalStmt
                );
            }

            /*
             * Create notification for the official.
             */
            $notificationMessage =
                'Your requisition ' .
                $requisitionNumber .
                ' for ' .
                $selectedResource['resourceName'] .
                ' has been submitted successfully and is awaiting approval.';

            $notificationSql = "
                INSERT INTO notifications (
                    approvalNumber,
                    requisitionID,
                    recipientAdmNo,
                    notifDescription,
                    isRead
                )
                VALUES (
                    NULL,
                    ?,
                    ?,
                    ?,
                    'No'
                )
            ";

            $notificationStmt =
                mysqli_prepare(
                    $conn,
                    $notificationSql
                );

            if (!$notificationStmt) {
                throw new Exception(
                    'Unable to prepare the notification.'
                );
            }

            mysqli_stmt_bind_param(
                $notificationStmt,
                'iss',
                $requisitionID,
                $admNo,
                $notificationMessage
            );

            if (
                !mysqli_stmt_execute(
                    $notificationStmt
                )
            ) {
                throw new Exception(
                    'Unable to create the requisition notification.'
                );
            }

            mysqli_stmt_close(
                $notificationStmt
            );

            mysqli_commit($conn);

            $_SESSION['success'] =
                'Requisition ' .
                $requisitionNumber .
                ' was submitted successfully.';

            header(
                'Location: my_requisitions.php'
            );
            exit();

        } catch (Throwable $exception) {

            mysqli_rollback($conn);

            $errors[] =
                $exception->getMessage();
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>Create Requisition</h1>

            <p>
                Request a college resource for
                <?= htmlspecialchars(
                    $official['clubName']
                ); ?>.
            </p>
        </div>

        <a
            href="my_requisitions.php"
            class="btn btn-secondary"
        >
            My Requisitions
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

            <div class="form-grid">

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
                        Submitted By
                    </label>

                    <input
                        type="text"
                        value="<?= htmlspecialchars(
                            $official['officialName'] .
                            ' (' .
                            $official['admNo'] .
                            ')'
                        ); ?>"
                        disabled
                    >

                </div>

                <div class="form-group full-width">

                    <label for="resourceID">
                        Resource
                        <span class="required">*</span>
                    </label>

                    <select
                        id="resourceID"
                        name="resourceID"
                        required
                    >

                        <option value="">
                            Select Resource
                        </option>

                        <?php while (
                            $resource =
                                mysqli_fetch_assoc(
                                    $resourcesResult
                                )
                        ): ?>

                            <option
                                value="<?= (int) $resource[
                                    'resourceID'
                                ]; ?>"
                                data-category="<?= htmlspecialchars(
                                    $resource[
                                        'resourceCategory'
                                    ]
                                ); ?>"
                                data-remaining="<?= (int) $resource[
                                    'resourceQuantityRemaining'
                                ]; ?>"
                                <?= (int) $resourceID ===
                                    (int) $resource[
                                        'resourceID'
                                    ]
                                    ? 'selected'
                                    : ''; ?>
                                <?= (int) $resource[
                                    'resourceQuantityRemaining'
                                ] <= 0
                                    ? 'disabled'
                                    : ''; ?>
                            >
                                <?= htmlspecialchars(
                                    $resource[
                                        'resourceName'
                                    ]
                                ); ?>
                                —
                                <?= htmlspecialchars(
                                    $resource[
                                        'resourceCategory'
                                    ]
                                ); ?>
                                (<?= (int) $resource[
                                    'resourceQuantityRemaining'
                                ]; ?> available)
                            </option>

                        <?php endwhile; ?>

                    </select>

                    <small
                        id="resourceInformation"
                        class="text-muted"
                    >
                        Select a resource to view its
                        availability.
                    </small>

                </div>

                <div class="form-group">

                    <label for="quantityRequested">
                        Quantity Required
                        <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        id="quantityRequested"
                        name="quantityRequested"
                        min="1"
                        value="<?= (int) $quantityRequested; ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="startDate">
                        Start Date
                        <span class="required">*</span>
                    </label>

                    <input
                        type="date"
                        id="startDate"
                        name="startDate"
                        min="<?= date('Y-m-d'); ?>"
                        value="<?= htmlspecialchars(
                            $startDate
                        ); ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="endDate">
                        End Date
                        <span class="required">*</span>
                    </label>

                    <input
                        type="date"
                        id="endDate"
                        name="endDate"
                        min="<?= date('Y-m-d'); ?>"
                        value="<?= htmlspecialchars(
                            $endDate
                        ); ?>"
                        required
                    >

                </div>

                <div class="form-group full-width">

                    <label for="purpose">
                        Purpose
                        <span class="required">*</span>
                    </label>

                    <textarea
                        id="purpose"
                        name="purpose"
                        rows="6"
                        maxlength="1000"
                        placeholder="Explain why the resource is required, where it will be used, and the planned activity."
                        required
                    ><?= htmlspecialchars($purpose); ?></textarea>

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Submit Requisition
                </button>

                <a
                    href="dashboard.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const resourceSelect =
        document.getElementById('resourceID');

    const quantityInput =
        document.getElementById('quantityRequested');

    const resourceInformation =
        document.getElementById('resourceInformation');

    const startDate =
        document.getElementById('startDate');

    const endDate =
        document.getElementById('endDate');

    function updateResourceInformation() {
        const selectedOption =
            resourceSelect.options[
                resourceSelect.selectedIndex
            ];

        if (
            !selectedOption ||
            selectedOption.value === ''
        ) {
            resourceInformation.textContent =
                'Select a resource to view its availability.';

            quantityInput.removeAttribute('max');
            return;
        }

        const category =
            selectedOption.dataset.category;

        const remaining =
            parseInt(
                selectedOption.dataset.remaining,
                10
            );

        resourceInformation.textContent =
            'Category: ' +
            category +
            ' | Available quantity: ' +
            remaining;

        quantityInput.max = remaining;

        if (
            parseInt(quantityInput.value, 10) >
            remaining
        ) {
            quantityInput.value = remaining;
        }
    }

    function updateEndDateMinimum() {
        if (startDate.value !== '') {
            endDate.min = startDate.value;

            if (
                endDate.value !== '' &&
                endDate.value < startDate.value
            ) {
                endDate.value = startDate.value;
            }
        }
    }

    resourceSelect.addEventListener(
        'change',
        updateResourceInformation
    );

    startDate.addEventListener(
        'change',
        updateEndDateMinimum
    );

    updateResourceInformation();
    updateEndDateMinimum();
});
</script>

<?php require_once '../includes/footer.php'; ?>