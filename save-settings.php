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

    if (isset($_POST["allowed_days"]) && count($_POST["allowed_days"]) > 0) {
        $allowed_days_arr = array_map('trim', $_POST["allowed_days"]);
        $allowed_days_str = implode(',', $allowed_days_arr);

        $check = $conn->prepare("SELECT 1 FROM settings WHERE setting_key = 'allowed_days'");
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $stmt2 = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'allowed_days'");
        } else {
            $stmt2 = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('allowed_days', ?)");
        }
        $stmt2->bind_param("s", $allowed_days_str);
        $stmt2->execute();
        $stmt2->close();
    }

    if (isset($_POST["blocked_dates"])) {
        $blocked_dates_str = trim($_POST["blocked_dates"]);

        $check2 = $conn->prepare("SELECT 1 FROM settings WHERE setting_key = 'blocked_dates'");
        $check2->execute();
        $exists2 = $check2->get_result()->num_rows > 0;
        $check2->close();

        if ($exists2) {
            $stmt3 = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'blocked_dates'");
        } else {
            $stmt3 = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('blocked_dates', ?)");
        }
        $stmt3->bind_param("s", $blocked_dates_str);
        $stmt3->execute();
        $stmt3->close();
    }
}

header("Location: dashboard.php?settings_saved=1");
exit;
