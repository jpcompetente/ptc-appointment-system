<?php
require_once "config.php";

header("Content-Type: application/json");

if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
$status = isset($_POST["status"]) ? strtolower(trim($_POST["status"])) : "";
$admin_id = $_SESSION["admin_id"];

$allowed_statuses = ["pending", "approved", "rejected"];

if ($id <= 0 || !in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid input."]);
    exit;
}

$stmt = $conn->prepare("UPDATE appointments SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
$stmt->bind_param("sii", $status, $admin_id, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Status updated."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error."]);
}
$stmt->close();
