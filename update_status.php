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
    $info_stmt = $conn->prepare("SELECT user_id, document_type FROM appointments WHERE id = ?");
    $info_stmt->bind_param("i", $id);
    $info_stmt->execute();
    $appt = $info_stmt->get_result()->fetch_assoc();
    $info_stmt->close();

    if ($appt && $appt['user_id']) {
        $doc_label = strtoupper($appt['document_type']);
        $status_label = ucfirst($status);
        $message = "Your {$doc_label} appointment request has been {$status_label}.";

        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, appointment_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $notif_stmt->bind_param("iis", $appt['user_id'], $id, $message);
        $notif_stmt->execute();
        $notif_stmt->close();
    }

    echo json_encode(["success" => true, "message" => "Status updated."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error."]);
}
$stmt->close();