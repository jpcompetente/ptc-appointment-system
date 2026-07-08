<?php
require_once "config.php";
header("Content-Type: application/json");
if (!isset($_SESSION["student_id"])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}
$student_id = $_SESSION["student_id"];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->close();

echo json_encode(["success" => true]);