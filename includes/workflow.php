<?php

/**
 * ----------------------------------------------------
 * Workflow Helper Functions
 * Web-Based College Resource Requisition Platform
 * ----------------------------------------------------
 */

/**
 * Create a notification
 */
function notifyUser($conn, $user_id, $message, $requisition_id = null)
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO notifications
        (user_id, requisition_id, message)
        VALUES (?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iis",
        $user_id,
        $requisition_id,
        $message
    );

    mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);
}

/**
 * Returns the officer that should handle
 * the approval.
 *
 * If the assigned officer is unavailable,
 * the proxy officer is returned instead.
 */
function getOfficerByPosition($conn, $position_id)
{
    $stmt = mysqli_prepare(

        $conn,

        "SELECT

            officer_id,
            user_id,
            availability_status,
            proxy_officer_id

         FROM approving_officers

         WHERE position_id = ?

         LIMIT 1"

    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $position_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        return false;
    }

    $officer = mysqli_fetch_assoc($result);

    /* Officer is available */

    if ($officer['availability_status'] == "Available") {

        $officer['is_proxy'] = false;

        return $officer;
    }

    /* No proxy assigned */

    if (empty($officer['proxy_officer_id'])) {

        return false;
    }

    /* Load proxy officer */

    $proxy = mysqli_prepare(

        $conn,

        "SELECT

            officer_id,
            user_id

         FROM approving_officers

         WHERE officer_id=?"

    );

    mysqli_stmt_bind_param(

        $proxy,

        "i",

        $officer['proxy_officer_id']

    );

    mysqli_stmt_execute($proxy);

    $proxyResult = mysqli_stmt_get_result($proxy);

    if (mysqli_num_rows($proxyResult) == 0) {

        return false;
    }

    $proxyOfficer = mysqli_fetch_assoc($proxyResult);

    $proxyOfficer['is_proxy'] = true;

    return $proxyOfficer;
}

/**
 * Return proxy officer if assigned.
 */
function getProxyOfficer($conn, $officer_id)
{

    $stmt = mysqli_prepare(

        $conn,

        "SELECT proxy_officer_id

        FROM approving_officers

        WHERE officer_id=?"

    );

    mysqli_stmt_bind_param($stmt, "i", $officer_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $row = mysqli_fetch_assoc($result);

    if (!$row) {

        return false;
    }

    return $row['proxy_officer_id'];
}

/**
 * Return all workflow steps
 * for a resource category.
 */

function getWorkflowSteps($conn, $category_id)
{

    $stmt = mysqli_prepare(

        $conn,

        "SELECT

        position_id,

        approval_order

        FROM approval_workflow

        WHERE category_id=?

        ORDER BY approval_order"

    );

    mysqli_stmt_bind_param($stmt, "i", $category_id);

    mysqli_stmt_execute($stmt);

    return mysqli_stmt_get_result($stmt);
}

/**
 * Generate approval workflow for a requisition
 */
function generateWorkflow($conn, $requisition_id, $resource_id)
{
    /* Get the resource category */

    $stmt = mysqli_prepare(
        $conn,
        "SELECT category_id
         FROM resources
         WHERE resource_id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $resource_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        return false;
    }

    $resource = mysqli_fetch_assoc($result);

    $category_id = $resource['category_id'];

    /* Get workflow steps */

    $steps = getWorkflowSteps($conn, $category_id);

    if (mysqli_num_rows($steps) == 0) {
        return true;
    }

    $firstStep = true;
    $createdAny = false;

    while ($step = mysqli_fetch_assoc($steps)) {
        /* Find officer assigned to this position */

        $officer = getOfficerByPosition(
            $conn,
            $step['position_id']
        );

        if (!$officer) {
            continue;
        }

        $createdAny = true;
        $status = $firstStep ? "Pending" : "Waiting";

        /* Insert approval stage */

        $insert = mysqli_prepare(

            $conn,

            "INSERT INTO requisition_approvals
            (
                requisition_id,
                officer_id,
                approval_order,
                status
            )
            VALUES
            (?,?,?,?)"

        );

        mysqli_stmt_bind_param(

            $insert,

            "iiis",

            $requisition_id,

            $officer['officer_id'],

            $step['approval_order'],

            $status

        );

        mysqli_stmt_execute($insert);

        /* Notify first officer */

        if ($firstStep) {

            /* Get officer user id */

            $userQuery = mysqli_prepare(

                $conn,

                "SELECT user_id

                 FROM approving_officers

                 WHERE officer_id=?"

            );

            mysqli_stmt_bind_param(

                $userQuery,

                "i",

                $officer['officer_id']

            );

            mysqli_stmt_execute($userQuery);

            $userResult = mysqli_stmt_get_result($userQuery);

            $user = mysqli_fetch_assoc($userResult);

            notifyUser(

                $conn,

                $user['user_id'],

                "A new requisition requires your approval.",

                $requisition_id

            );
        }

        $firstStep = false;
    }

    return true;
}

/**
 * Mark requisition as approved
 */
function completeRequisition($conn, $requisition_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE requisitions
         SET status='Approved'
         WHERE requisition_id=?"
    );

    mysqli_stmt_bind_param($stmt, "i", $requisition_id);

    return mysqli_stmt_execute($stmt);
}

/**
 * Reject requisition
 */
function rejectRequisition($conn, $requisition_id)
{
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE requisitions
         SET status='Rejected'
         WHERE requisition_id=?"
    );

    mysqli_stmt_bind_param($stmt, "i", $requisition_id);

    return mysqli_stmt_execute($stmt);
}

/**
 * Activate the next approval stage
 */
function activateNextApproval($conn, $requisition_id)
{
    /* Find next waiting stage */

    $stmt = mysqli_prepare(
        $conn,
        "SELECT approval_id, officer_id
         FROM requisition_approvals
         WHERE requisition_id=?
         AND status='Waiting'
         ORDER BY approval_order
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "i", $requisition_id);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {

        /* No more stages */

        completeRequisition($conn, $requisition_id);

        /* Notify secretary */

        $q = mysqli_prepare(
            $conn,
            "SELECT secretary_id
             FROM requisitions
             WHERE requisition_id=?"
        );

        mysqli_stmt_bind_param($q, "i", $requisition_id);

        mysqli_stmt_execute($q);

        $res = mysqli_stmt_get_result($q);

        $row = mysqli_fetch_assoc($res);

        notifyUser(

            $conn,

            $row['secretary_id'],

            "Your requisition has been fully approved.",

            $requisition_id

        );

        return;
    }

    $next = mysqli_fetch_assoc($result);

    /* Resolve the officer who should actively handle this approval */

    $officerStmt = mysqli_prepare(
        $conn,
        "SELECT
            officer_id,
            user_id,
            availability_status,
            proxy_officer_id
         FROM approving_officers
         WHERE officer_id=?"
    );

    mysqli_stmt_bind_param(
        $officerStmt,
        "i",
        $next['officer_id']
    );

    mysqli_stmt_execute($officerStmt);

    $officerResult = mysqli_stmt_get_result($officerStmt);
    $officer = mysqli_fetch_assoc($officerResult);

    $assignedOfficer = $next['officer_id'];
    $assignedAs = 'Primary';

    if ($officer['availability_status'] == 'Unavailable') {
        if (empty($officer['proxy_officer_id'])) {
            throw new Exception('Officer has no proxy assigned.');
        }

        $assignedOfficer = $officer['proxy_officer_id'];
        $assignedAs = 'Proxy';
    }

    /* Activate */

    $update = mysqli_prepare(
        $conn,
        "UPDATE requisition_approvals
         SET officer_id=?,
             assigned_as=?,
             status='Pending'
         WHERE approval_id=?"
    );

    mysqli_stmt_bind_param(
        $update,
        "isi",
        $assignedOfficer,
        $assignedAs,
        $next['approval_id']
    );

    mysqli_stmt_execute($update);

    /* Notify the effective officer */

    $userStmt = mysqli_prepare(
        $conn,
        "SELECT user_id
         FROM approving_officers
         WHERE officer_id=?"
    );

    mysqli_stmt_bind_param(
        $userStmt,
        "i",
        $assignedOfficer
    );

    mysqli_stmt_execute($userStmt);

    $userResult = mysqli_stmt_get_result($userStmt);
    $user = mysqli_fetch_assoc($userResult);

    notifyUser(
        $conn,
        $user['user_id'],
        $assignedAs == 'Proxy'
            ? 'You have a delegated approval request.'
            : 'A requisition requires your approval.',
        $requisition_id
    );
}
