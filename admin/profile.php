<?php

require_once '../includes/session.php';
require_once '../config/db.php';
require_once '../includes/profile_photo.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
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

function getAdminProfile(
    mysqli $conn,
    int $userID
): ?array {

    $sql = "
        SELECT
            userID,
            username,
            role,
            status,
            profilePhoto,
            createdAt
        FROM users
        WHERE userID = ?
          AND role = 'admin'
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

$admin =
    getAdminProfile(
        $conn,
        $userID
    );

if (!$admin) {
    die('Administrator account not found.');
}

$username =
    $admin['username'];

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
                        $admin['profilePhoto'] ?? null
                    );

                $sql = "
                    UPDATE users
                    SET profilePhoto = ?
                    WHERE userID = ?
                      AND role = 'admin'
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
            $admin['profilePhoto'] ?? null;

        $sql = "
            UPDATE users
            SET profilePhoto = NULL
            WHERE userID = ?
              AND role = 'admin'
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

        $username =
            trim($_POST['username'] ?? '');

        if (
            !preg_match(
                '/^[A-Za-z0-9._-]{3,50}$/',
                $username
            )
        ) {
            $errors[] =
                'Enter a valid username.';
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

        if (empty($errors)) {

            $sql = "
                UPDATE users
                SET username = ?
                WHERE userID = ?
                  AND role = 'admin'
            ";

            $stmt =
                mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                'si',
                $username,
                $userID
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                $_SESSION['username'] =
                    $username;

                $_SESSION['success'] =
                    'Administrator profile updated successfully.';

                header('Location: profile.php');
                exit();

            } else {

                $errors[] =
                    'Unable to update the profile.';

                mysqli_stmt_close($stmt);
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
                  AND role = 'admin'
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
                  AND role = 'admin'
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

$admin =
    getAdminProfile(
        $conn,
        $userID
    );

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1>Administrator Profile</h1>
            <p>Manage your administrator account.</p>
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
                                    $admin['profilePhoto']
                                )
                            ): ?>

                                <img
                                    src="../<?= htmlspecialchars(
                                        $admin['profilePhoto']
                                    ); ?>"
                                    alt="Administrator profile photo"
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
                                                $admin['username'],
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
                    !empty($admin['profilePhoto'])
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
                    $admin['username']
                ); ?>
            </h2>

            <p class="profile-position">
                System Administrator
            </p>

            <span
                class="badge <?= $admin['status'] ===
                'Active'
                    ? 'badge-success'
                    : 'badge-danger'; ?>"
            >
                <?= htmlspecialchars(
                    $admin['status']
                ); ?>
            </span>

            <div class="profile-summary-details">

                <div>
                    <strong>Account Role</strong>
                    <span>Administrator</span>
                </div>

                <div>
                    <strong>Account Created</strong>
                    <span>
                        <?= date(
                            'd M Y',
                            strtotime(
                                $admin['createdAt']
                            )
                        ); ?>
                    </span>
                </div>

            </div>

        </div>

        <div class="profile-content">

            <div class="card">

                <div class="section-header">
                    <h2>Account Information</h2>
                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="update_profile"
                    >

                    <div class="form-grid">

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
                            <label>Role</label>
                            <input
                                type="text"
                                value="Administrator"
                                disabled
                            >
                        </div>

                        <div class="form-group">
                            <label>Account Status</label>
                            <input
                                type="text"
                                value="<?= htmlspecialchars(
                                    $admin['status']
                                ); ?>"
                                disabled
                            >
                        </div>

                        <div class="form-group">
                            <label>Created On</label>
                            <input
                                type="text"
                                value="<?= date(
                                    'd M Y',
                                    strtotime(
                                        $admin['createdAt']
                                    )
                                ); ?>"
                                disabled
                            >
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

require '../includes/profile_javascript.php';
require_once '../includes/footer.php';

?>