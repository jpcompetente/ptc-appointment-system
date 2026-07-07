<?php
require_once "config.php";

if (isset($_SESSION["student_id"])) {
    header("Location: student-dashboard.php");
    exit;
}

$error = isset($_GET['error']) ? $_GET['error'] : '';
$panel = isset($_GET['panel']) ? $_GET['panel'] : '';
$activeClass = ($panel === 'signup') ? ' active' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/signup.css">
    <title>PTC Web System | Login</title>
</head>
<body>
    <div class="container<?= $activeClass ?>" id="container">
        <div class="form-container sign-up">
            <form action="signup.php" method="post">
                <h1>Create Account</h1>
                <span>Use your school email for registration</span>
                <?php if ($error && $panel === 'signup'): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="School Email" required>
                <input type="password" name="password" placeholder="Password" required minlength="8">
                <button type="submit">Sign Up</button>
            </form>
        </div>
        <div class="form-container sign-in">
            <form action="signin.php" method="post">
                <h1>Sign In</h1>
                <span>Use your school email and password</span>
                <?php if ($error && $panel === 'signin'): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <input type="email" name="email" placeholder="School Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Sign In</button>
            </form>
        </div>
        <div class="toggle-container">
            <div class="toggle">
                <div class="toggle-panel toggle-left">
                    <img src="images/logo-ptc 2.png" alt="PTC Logo" class="toggle-logo">
                    <h1>Welcome Back!</h1>
                    <p>Sign in with your student account to view and manage your appointments</p>
                    <button class="hidden" id="login">Sign In</button>
                </div>
                <div class="toggle-panel toggle-right">
                    <img src="images/logo-ptc 2.png" alt="PTC Logo" class="toggle-logo">
                    <h1>New Here?</h1>
                    <p>Create a student account to book and track your document requests</p>
                    <button class="hidden" id="register">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
    <p style="text-align:center; margin-top:16px; font-size:13px;"><a href="registrar-login.php" style="color:#6b7d74; text-decoration:none;">For Registrar &rarr;</a></p>
    <script src="js/signup.js"></script>
</body>
</html>
