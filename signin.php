<?php
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user["password"])) {
            $_SESSION["student_id"] = $user["id"];
            $_SESSION["student_name"] = $user["full_name"];
            $stmt->close();
            header("Location: Index.php");
            exit;
        }
    }
    $stmt->close();
    header("Location: student-auth.php?panel=signin&error=" . urlencode("Incorrect email or password."));
    exit;
} else {
    header("Location: student-auth.php");
    exit;
}

