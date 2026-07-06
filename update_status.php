<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Content-Type: application/json");
    http_response_code(401);
    echo "Unauthorized";
    exit();
}

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'apt_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $status = $_POST['status'];

    $sql = "UPDATE appointments SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $status, $id);

    if ($stmt->execute()) {
        echo "Appointment status updated successfully.";
    } else {
        echo "Error updating appointment status: " . $conn->error;
    }
    $stmt->close();
}
$conn->close();
?>
