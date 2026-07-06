<?php
session_start();
$hostname = 'localhost';
$username = 'root';  
$password = '';      
$database = 'login_db'; 
$conn = new mysqli($hostname, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            header("Location: dashboard.php");
            exit();
        } else {
            header("Location: signup.html?error=invalid_password");
            exit();
        }
    } else {
        header("Location: signup.html?error=user_not_found");
        exit();
    }
} else {
    header("Location: signup.html");
    exit();
}
$conn->close();
?>
