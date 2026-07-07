<?php
require_once "config.php";

if (!isset($_SESSION["admin_id"])) {
    header("Location: registrar-login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $max_per_day = isset($_POST["max_appointments_per_day"]) ? (int) $_POST["max_appointments_per_day"] : 0;

    if ($max_per_day < 1) {
        $max_per_day = 1;
    }

    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'max_appointments_per_day'");
    $stmt->bind_param("s", $max_per_day);
    $stmt->execute();
    $stmt->close();
}

header("Location: dashboard.php?settings_saved=1");
exit;
