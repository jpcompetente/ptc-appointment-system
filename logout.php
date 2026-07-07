<?php
session_start();
$type = isset($_GET['type']) ? $_GET['type'] : 'student';
session_unset();
session_destroy();

if ($type === 'admin') {
    header("Location: registrar-login.php");
} else {
    header("Location: student-auth.php");
}
exit();
?>
