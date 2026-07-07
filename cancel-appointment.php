<?php
require_once "config.php";

header("Content-Type: application/json");

if (!isset($_SESSION["student_id"])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized."]);
    exit;
}

$id = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
$student_id = $_SESSION["student_id"];

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid appointment."]);
    exit;
}

$stmt = $conn->prepare("SELECT status, user_id FROM appointments WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment || (int) $appointment["user_id"] !== (int) $student_id) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Hindi mo pwedeng i-cancel ang appointment na ito."]);
    exit;
}

if ($appointment["status"] !== "pending") {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Pwede lang i-cancel ang mga appointment na 'pending' pa. Naproseso na ito ng registrar."]);
    exit;
}

$update = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?");
$update->bind_param("ii", $id, $student_id);

if ($update->execute()) {
    echo json_encode(["success" => true, "message" => "Na-cancel na ang appointment mo."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Nagkaroon ng error sa database."]);
}
$update->close();
