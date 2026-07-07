<?php
require_once "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: registrar-login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $admin_id = $_SESSION['admin_id'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (!$admin || !password_verify($current_password, $admin['password'])) {
        header("Location: dashboard.php?password_error=" . urlencode("Mali ang current password."));
        exit();
    }

    if (strlen($new_password) < 8) {
        header("Location: dashboard.php?password_error=" . urlencode("Ang bagong password ay dapat hindi bababa sa 8 characters."));
        exit();
    }

    if ($new_password !== $confirm_password) {
        header("Location: dashboard.php?password_error=" . urlencode("Hindi magkatugma ang new password at confirm password."));
        exit();
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_hash, $admin_id);

    if ($update_stmt->execute()) {
        header("Location: dashboard.php?password_saved=1");
        exit();
    } else {
        header("Location: dashboard.php?password_error=" . urlencode("May error sa pag-save. Subukan ulit."));
        exit();
    }
}

header("Location: dashboard.php");
exit();