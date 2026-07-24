<?php

require_once '../includes/session.php';
require_once '../config/db.php';
require_once '../includes/profile_photo.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'officer'
) {
    header('Location: ../login.php');
    exit();
}

$userID =
    (int) ($_SESSION['userID'] ?? 0);

if ($userID <= 0) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

function getOfficerProfile(
    mysqli $conn,
    int $userID
): ?array {

    $sql = "
        SELECT
            o.officerStaffNo,
            o.officerName,
            o.officerRole,
            o.availability,
            o.proxyOfficerStaffNo,
            o.createdAt,

            u.username,
            u.profilePhoto,
            u.status AS accountStatus,
            u.createdAt AS accountCreatedAt,

            p.officerName AS proxyOfficerName,
            p.officerRole AS proxyOfficerRole

        FROM officers o

        INNER JOIN users u
            ON o.userID = u.userID

        LEFT JOIN officers p
            ON o.proxyOfficerStaffNo =
               p.officerStaffNo

        WHERE o.userID = ?

        LIMIT 1
    ";

    $stmt =
        mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $userID
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $profile =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $profile ?: null;
}

$officer =
    getOfficerProfile(
        $conn,
        $userID
    );

if (!$officer) {
    die('Officer profile not found.');
}

$staffNo =
    $officer['officerStaffNo'];

$officerName =
    $officer['officerName'];

$username =
    $officer['username'];

$availability =
    $officer['availability'];

$proxyOfficerStaffNo =
    $officer['proxyOfficerStaffNo'] ?? '';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';

    if ($action === 'update_photo') {

        if (
            !isset($_FILES['profilePhoto']) ||
            $_FILES['profilePhoto']['error'] ===
            UPLOAD_ERR_NO_FILE
        ) {
            $errors[] =
                'Select a profile image.';
        }

        if (empty($errors)) {

            $newPhotoPath = null;

            try {

                $newPhotoPath =
                    uploadProfilePhoto(
                        $_FILES['profilePhoto'],
                        $userID,
                        $officer['profilePhoto'] ?? null
                    );

                $sql = "
                    UPDATE users
                    SET profilePhoto = ?
                    WHERE userID = ?
                      AND role = 'officer'
                ";

                $stmt =
                    mysqli_prepare($conn, $sql);

                if (!$stmt) {
                    throw new RuntimeException(
                        'Unable to prepare the photo update.'
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    'si',
                    $newPhotoPath,
                    $userID
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException(
                        'Unable to update the profile photo.'
                    );
                }

                mysqli_stmt_close($stmt);

                $_SESSION['success'] =
                    'Profile photo updated successfully.';

                header('Location: profile.php');
                exit();

            } catch (Throwable $exception) {

                if ($newPhotoPath !== null) {
                    deleteProfilePhotoFile(
                        $newPhotoPath
                    );
                }

                $errors[] =
                    $exception->getMessage();
            }
        }
    }

    elseif ($action === 'remove_photo') {

        $currentPhoto =
            $officer['profilePhoto'] ?? null;

        $sql = "
            UPDATE users
            SET profilePhoto = NULL
            WHERE userID = ?
              AND role = 'officer'
        ";

        $stmt =
            mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $errors[] =
                'Unable to prepare photo removal.';

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $userID
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                deleteProfilePhotoFile(
                    $currentPhoto
                );

                $_SESSION['success'] =
                    'Profile photo removed successfully.';

                header('Location: profile.php');
                exit();

            } else {

                $errors[] =
                    'Unable to remove the profile photo.';

                mysqli_stmt_close($stmt);
            }
        }
    }

    elseif ($action === 'update_profile') {

        $officerName =
            trim($_POST['officerName'] ?? '');

        $username =
            trim($_POST['username'] ?? '');

        $availability =
            trim($_POST['availability'] ?? '');

        $proxyOfficerStaffNo =
            trim(
                $_POST['proxyOfficerStaffNo'] ?? ''
            );

        if ($officerName === '') {
            $errors[] =
                'Officer name is required.';
        }

        if (
            !preg_match(
                '/^[A-Za-z0-9._-]{3,50}$/',
                $username
            )
        ) {
            $errors[] =
                'Enter a valid username.';
        }

        if (
            !in_array(
                $availability,
                ['Available', 'Unavailable'],
                true
            )
        ) {
            $errors[] =
                'Select a valid availability status.';
        }

        if (
            $availability === 'Unavailable' &&
            $proxyOfficerStaffNo === ''
        ) {
            $errors[] =
                'Select a proxy officer before becoming unavailable.';
        }

        if ($proxyOfficerStaffNo === $staffNo) {
            $errors[] =
                'You cannot select yourself as proxy.';
        }

        if (empty($errors)) {

            $sql = "
                SELECT userID
                FROM users
                WHERE username = ?
                  AND userID <> ?
                LIMIT 1
            ";

            $stmt =
                mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                'si',
                $username,
                $userID
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            if (mysqli_fetch_assoc($result)) {
                $errors[] =
                    'That username is already in use.';
            }

            mysqli_stmt_close($stmt);
        }

        if (
            empty($errors) &&
            $proxyOfficerStaffNo !== ''
        ) {

            $sql = "
                SELECT
                    officerStaffNo,
                    availability
                FROM officers
                WHERE officerStaffNo = ?
                  AND officerStaffNo <> ?
                LIMIT 1
            ";

            $stmt =
                mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                'ss',
                $proxyOfficerStaffNo,
                $staffNo
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $proxy =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (!$proxy) {
                $errors[] =
                    'The selected proxy was not found.';

            } elseif (
                $availability === 'Unavailable' &&
                $proxy['availability'] !== 'Available'
            ) {
                $errors[] =
                    'The selected proxy must be available.';
            }
        }

        if (empty($errors)) {

            mysqli_begin_transaction($conn);

            try {

                $sql = "
                    UPDATE users
                    SET username = ?
                    WHERE userID = ?
                      AND role = 'officer'
                ";

                $stmt =
                    mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'si',
                    $username,
                    $userID
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException(
                        'Unable to update username.'
                    );
                }

                mysqli_stmt_close($stmt);

                $sql = "
                    UPDATE officers
                    SET
                        officerName = ?,
                        availability = ?,
                        proxyOfficerStaffNo =
                            NULLIF(?, '')
                    WHERE userID = ?
                ";

                $stmt =
                    mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'sssi',
                    $officerName,
                    $availability,
                    $proxyOfficerStaffNo,
                    $userID
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new RuntimeException(
                        'Unable to update officer profile.'
                    );
                }

                mysqli_stmt_close($stmt);

                mysqli_commit($conn);

                $_SESSION['username'] =
                    $username;

                $_SESSION['success'] =
                    'Officer profile updated successfully.';

                header('Location: profile.php');
                exit();

            } catch (Throwable $exception) {

                mysqli_rollback($conn);

                $errors[] =
                    $exception->getMessage();
            }
        }
    }

    elseif ($action === 'change_password') {

        $currentPassword =
            $_POST['currentPassword'] ?? '';

        $newPassword =
            $_POST['newPassword'] ?? '';

        $confirmPassword =
            $_POST['confirmPassword'] ?? '';

        if ($currentPassword === '') {
            $errors[] =
                'Current password is required.';
        }

        if (strlen($newPassword) < 8) {
            $errors[] =
                'The password must contain at least 8 characters.';
        }

        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] =
                'The password must contain an uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $newPassword)) {
            $errors[] =
                'The password must contain a lowercase letter.';
        }

        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] =
                'The password must contain a number.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] =
                'Password confirmation does not match.';
        }

        if (empty($errors)) {

            $sql = "
                SELECT password
                FROM users
                WHERE userID = ?
                  AND role = 'officer'
                LIMIT 1
            ";

            $stmt =
                mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $userID
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            $row =
                mysqli_fetch_assoc($result);

            mysqli_stmt_close($stmt);

            if (
                !$row ||
                !password_verify(
                    $currentPassword,
                    $row['password']
                )
            ) {
                $errors[] =
                    'The current password is incorrect.';

            } elseif (
                password_verify(
                    $newPassword,
                    $row['password']
                )
            ) {
                $errors[] =
                    'The new password must be different.';
            }
        }

        if (empty($errors)) {

            $hash =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

            $sql = "
                UPDATE users
                SET password = ?
                WHERE userID = ?
                  AND role = 'officer'
            ";

            $stmt =
                mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                'si',
                $hash,
                $userID
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                $_SESSION['success'] =
                    'Password changed successfully.';

                header('Location: profile.php');
                exit();

            } else {

                $errors[] =
                    'Unable to change the password.';

                mysqli_stmt_close($stmt);
            }
        }
    }
}

$officer =
    getOfficerProfile(
        $conn,
        $userID
    );

$proxySql = "
    SELECT
        officerStaffNo,
        officerName,
        officerRole,
        availability
    FROM officers
    WHERE officerStaffNo <> ?
    ORDER BY officerName
";

$proxyStmt =
    mysqli_prepare($conn, $proxySql);

mysqli_stmt_bind_param(
    $proxyStmt,
    's',
    $staffNo
);

mysqli_stmt_execute($proxyStmt);

$proxyResult =
    mysqli_stmt_get_result($proxyStmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>My Profile</h1>
            <p>Manage your officer account.</p>
        </div>

        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars(
                $_SESSION['success']
            ); ?>
        </div>

        <?php unset($_SESSION['success']); ?>

    <?php endif; ?>

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p>
                    <?= htmlspecialchars($error); ?>
                </p>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <div class="profile-layout">

        <div class="card profile-summary-card">

            <div class="profile-photo-section">

                <form
                    method="POST"
                    enctype="multipart/form-data"
                    id="profilePhotoForm"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="update_photo"
                    >

                    <div class="profile-avatar-wrapper">

                        <div class="profile-avatar">

                            <?php if (
                                !empty(
                                    $officer['profilePhoto']
                                )
                            ): ?>

                                <img
                                    src="../<?= htmlspecialchars(
                                        $officer[
                                            'profilePhoto'
                                        ]
                                    ); ?>"
                                    alt="Officer profile photo"
                                    class="profile-photo-image"
                                    id="profilePhotoPreview"
                                >

                            <?php else: ?>

                                <span
                                    class="profile-avatar-letter"
                                    id="profileAvatarLetter"
                                >
                                    <?= htmlspecialchars(
                                        strtoupper(
                                            substr(
                                                $officer[
                                                    'officerName'
                                                ],
                                                0,
                                                1
                                            )
                                        )
                                    ); ?>
                                </span>

                                <img
                                    src=""
                                    alt="Selected photo"
                                    class="profile-photo-image hidden"
                                    id="profilePhotoPreview"
                                >

                            <?php endif; ?>

                        </div>

                        <label
                            for="profilePhoto"
                            class="profile-photo-plus"
                            title="Change profile photo"
                        >
                            <span>+</span>
                        </label>

                        <input
                            type="file"
                            id="profilePhoto"
                            name="profilePhoto"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="profile-photo-input"
                        >

                    </div>

                    <div
                        class="profile-photo-actions"
                        id="profilePhotoActions"
                    >
                        <span
                            id="profilePhotoName"
                            class="profile-photo-filename"
                        ></span>

                        <div class="profile-photo-action-buttons">

                            <button
                                type="submit"
                                class="btn btn-primary btn-small"
                            >
                                Save Photo
                            </button>

                            <button
                                type="button"
                                class="btn btn-secondary btn-small"
                                id="cancelPhotoSelection"
                            >
                                Cancel
                            </button>

                        </div>
                    </div>

                </form>

                <?php if (
                    !empty($officer['profilePhoto'])
                ): ?>

                    <form
                        method="POST"
                        class="remove-photo-form"
                        onsubmit="return confirm('Remove your profile photo?');"
                    >
                        <input
                            type="hidden"
                            name="action"
                            value="remove_photo"
                        >

                        <button
                            type="submit"
                            class="profile-photo-remove"
                        >
                            Remove photo
                        </button>
                    </form>

                <?php endif; ?>

            </div>

            <h2>
                <?= htmlspecialchars(
                    $officer['officerName']
                ); ?>
            </h2>

            <p class="profile-position">
                <?= htmlspecialchars(
                    $officer['officerRole']
                ); ?>
            </p>

            <span
                class="badge <?= $officer['accountStatus'] ===
                'Active'
                    ? 'badge-success'
                    : 'badge-danger'; ?>"
            >
                <?= htmlspecialchars(
                    $officer['accountStatus']
                ); ?>
            </span>

            <div class="profile-summary-details">

                <div>
                    <strong>Staff Number</strong>
                    <span>
                        <?= htmlspecialchars(
                            $officer['officerStaffNo']
                        ); ?>
                    </span>
                </div>

                <div>
                    <strong>Availability</strong>
                    <span>
                        <?= htmlspecialchars(
                            $officer['availability']
                        ); ?>
                    </span>
                </div>

                <div>
                    <strong>Proxy Officer</strong>
                    <span>
                        <?= !empty(
                            $officer['proxyOfficerName']
                        )
                            ? htmlspecialchars(
                                $officer[
                                    'proxyOfficerName'
                                ]
                            )
                            : 'Not assigned'; ?>
                    </span>
                </div>

                <div>
                    <strong>Account Created</strong>
                    <span>
                        <?= date(
                            'd M Y',
                            strtotime(
                                $officer[
                                    'accountCreatedAt'
                                ]
                            )
                        ); ?>
                    </span>
                </div>

            </div>

        </div>

        <div class="profile-content">

            <div class="card">

                <div class="section-header">
                    <h2>Officer Information</h2>
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="update_profile"
                    >

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="officerName">
                                Officer Name
                            </label>

                            <input
                                type="text"
                                id="officerName"
                                name="officerName"
                                value="<?= htmlspecialchars(
                                    $officerName
                                ); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="username">
                                Username
                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="<?= htmlspecialchars(
                                    $username
                                ); ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="availability">
                                Availability
                            </label>

                            <select
                                id="availability"
                                name="availability"
                                required
                            >
                                <option
                                    value="Available"
                                    <?= $availability ===
                                    'Available'
                                        ? 'selected'
                                        : ''; ?>
                                >
                                    Available
                                </option>

                                <option
                                    value="Unavailable"
                                    <?= $availability ===
                                    'Unavailable'
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
                                    No proxy selected
                                </option>

                                <?php while (
                                    $proxy =
                                        mysqli_fetch_assoc(
                                            $proxyResult
                                        )
                                ): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $proxy[
                                                'officerStaffNo'
                                            ]
                                        ); ?>"
                                        <?= $proxyOfficerStaffNo ===
                                        $proxy[
                                            'officerStaffNo'
                                        ]
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        <?= htmlspecialchars(
                                            $proxy[
                                                'officerName'
                                            ]
                                        ); ?>
                                        —
                                        <?= htmlspecialchars(
                                            $proxy[
                                                'officerRole'
                                            ]
                                        ); ?>
                                        (
                                        <?= htmlspecialchars(
                                            $proxy[
                                                'availability'
                                            ]
                                        ); ?>
                                        )
                                    </option>

                                <?php endwhile; ?>
                            </select>
                        </div>

                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Save Profile
                        </button>
                    </div>

                </form>

            </div>

            <?php
            require '../includes/profile_password_form.php';
            ?>

        </div>

    </div>

</div>

<?php

mysqli_stmt_close($proxyStmt);

require '../includes/profile_javascript.php';
require_once '../includes/footer.php';

?>