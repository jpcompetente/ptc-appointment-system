<?php
require_once "config.php";
header("Content-Type: application/json");
if (!isset($_SESSION["student_id"])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}
$student_id = $_SESSION["student_id"];

$stmt = $conn->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

$count_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$count_stmt->bind_param("i", $student_id);
$count_stmt->execute();
$unread = $count_stmt->get_result()->fetch_assoc()['unread'];
$count_stmt->close();

echo json_encode(["success" => true, "notifications" => $notifications, "unread_count" => (int) $unread]);