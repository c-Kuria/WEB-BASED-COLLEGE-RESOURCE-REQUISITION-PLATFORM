<?php

require_once __DIR__ . '/../includes/session.php';

if ($_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$message = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$user_id = (int) $_GET['id'];

$stmt = mysqli_prepare($conn, "
SELECT user_id, full_name, username, email, phone, role, status
FROM users
WHERE user_id=?
");

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    header('Location: users.php');
    exit();
}

if (isset($_POST['save'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    $update = mysqli_prepare($conn, "
    UPDATE users
    SET full_name=?, username=?, email=?, phone=?, role=?, status=?
    WHERE user_id=?
    ");

    mysqli_stmt_bind_param($update, 'ssssssi', $full_name, $username, $email, $phone, $role, $status, $user_id);
    mysqli_stmt_execute($update);

    $_SESSION['success'] = 'User updated successfully.';
    header('Location: users.php');
    exit();
}
?>

<div class="main">
    <h1>Edit User</h1>

    <?php require_once __DIR__ . '/../includes/flash.php'; ?>

    <div class="form-container">
        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Administrator</option>
                    <option value="secretary" <?= $user['role'] == 'secretary' ? 'selected' : '' ?>>Secretary</option>
                    <option value="officer" <?= $user['role'] == 'officer' ? 'selected' : '' ?>>Approving Officer</option>
                </select>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" required>
                    <option value="Active" <?= $user['status'] == 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $user['status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <button type="submit" name="save" class="btn">Save User</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
