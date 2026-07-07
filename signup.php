<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: student-auth.php?panel=signup&error=" . urlencode("Invalid email format."));
        exit;
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        header("Location: student-auth.php?panel=signup&error=" . urlencode("Email is already registered."));
        exit;
    }
    $check->close();

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $full_name, $email, $hashed);

    if ($stmt->execute()) {
        $_SESSION["student_id"] = $stmt->insert_id;
        $_SESSION["student_name"] = $full_name;
        $stmt->close();
        header("Location: Index.php");
        exit;
    } else {
        $stmt->close();
        header("Location: student-auth.php?panel=signup&error=" . urlencode("Something went wrong. Please try again."));
        exit;
    }
} else {
    header("Location: student-auth.php");
    exit;
}

