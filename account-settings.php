<?php
require_once "config.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: student-auth.php");
    exit;
}

$student_id = $_SESSION["student_id"];

// Handle profile picture upload (separate form)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'picture') {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['settings_error'] = "Please choose an image to upload.";
        header("Location: account-settings.php");
        exit;
    }

    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 2 * 1024 * 1024;

    $file_info = getimagesize($file['tmp_name']);
    if ($file_info === false || !in_array($file_info['mime'], $allowed_types)) {
        $_SESSION['settings_error'] = "Only JPG, PNG, or WEBP images are allowed.";
        header("Location: account-settings.php");
        exit;
    }

    if ($file['size'] > $max_size) {
        $_SESSION['settings_error'] = "Image must be smaller than 2MB.";
        header("Location: account-settings.php");
        exit;
    }

    $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $ext_map[$file_info['mime']];
    $filename = "user_{$student_id}_" . time() . "." . $ext;
    $upload_dir = __DIR__ . "/images/profiles/";
    $upload_path = $upload_dir . $filename;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        $_SESSION['settings_error'] = "Failed to upload image. Please try again.";
        header("Location: account-settings.php");
        exit;
    }

    $old_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $old_stmt->bind_param("i", $student_id);
    $old_stmt->execute();
    $old_row = $old_stmt->get_result()->fetch_assoc();
    $old_stmt->close();
    if ($old_row && !empty($old_row['profile_picture'])) {
        $old_path = $upload_dir . $old_row['profile_picture'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }

    $update_stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $update_stmt->bind_param("si", $filename, $student_id);
    $update_stmt->execute();
    $update_stmt->close();

    $_SESSION['settings_success'] = "Your profile picture has been updated.";
    header("Location: account-settings.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'profile') {
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    // Basic validation
    if ($full_name === "" || $email === "") {
        $_SESSION['settings_error'] = "Full name and email cannot be empty.";
        header("Location: account-settings.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['settings_error'] = "Please enter a valid email address.";
        header("Location: account-settings.php");
        exit;
    }

    // Verify current password is required for any change
    if ($current_password === "") {
        $_SESSION['settings_error'] = "Please enter your current password to save changes.";
        header("Location: account-settings.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result || !password_verify($current_password, $result['password'])) {
        $_SESSION['settings_error'] = "Current password is incorrect.";
        header("Location: account-settings.php");
        exit;
    }

    // Check email uniqueness (excluding self)
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $student_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        $check->close();
        $_SESSION['settings_error'] = "That email is already in use by another account.";
        header("Location: account-settings.php");
        exit;
    }
    $check->close();

    // Handle optional password change
    if ($new_password !== "" || $confirm_password !== "") {
        if (strlen($new_password) < 8) {
            $_SESSION['settings_error'] = "New password must be at least 8 characters.";
            header("Location: account-settings.php");
            exit;
        }
        if ($new_password !== $confirm_password) {
            $_SESSION['settings_error'] = "New password and confirmation do not match.";
            header("Location: account-settings.php");
            exit;
        }
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $email, $hashed, $student_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $full_name, $email, $student_id);
    }

    if ($stmt->execute()) {
        $stmt->close();
        $_SESSION["student_name"] = $full_name;
        $_SESSION['settings_success'] = "Your account details have been updated.";
        header("Location: account-settings.php");
        exit;
    } else {
        $stmt->close();
        $_SESSION['settings_error'] = "Something went wrong. Please try again.";
        header("Location: account-settings.php");
        exit;
    }
}

// GET: load current values
$stmt = $conn->prepare("SELECT full_name, email, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/dashboard.css">
    <title>Account Settings | PTC Web System</title>
    <style>
        .custom-alert-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .custom-alert-overlay .alert-card { background:#ffffff; border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,0.15); padding:40px 36px; max-width:380px; width:90%; text-align:center; }
        .custom-alert-overlay .alert-icon-wrap { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; background:#fde2e1; }
        .custom-alert-overlay .alert-icon { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:20px; background:#f24236; color:#fff; }
        .custom-alert-overlay .alert-icon-wrap.success { background:#d7f5e9; }
        .custom-alert-overlay .alert-icon.success { background:#22b573; }
        .custom-alert-overlay .alert-title { font-size:20px; font-weight:600; color:#1f2937; margin:0 0 8px; }
        .custom-alert-overlay .alert-message { font-size:14px; color:#6b7280; margin:0 0 28px; line-height:1.5; }
        .custom-alert-overlay .alert-btn { border:none; padding:12px 32px; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; width:100%; background:#f24236; color:#fff; }
        .custom-alert-overlay .alert-btn:hover { background:#d93a30; }
        .custom-alert-overlay .alert-btn.success { background:#22b573; }
        .custom-alert-overlay .alert-btn.success:hover { background:#1c9760; }
        .custom-alert-overlay .alert-btn.secondary { background:#e5e7eb; color:#374151; }
        .custom-alert-overlay .alert-btn.secondary:hover { background:#d1d5db; }

        .settings-card{
            background:#fff;
            border:1px solid var(--border-soft);
            border-radius:16px;
            box-shadow:0 4px 16px rgba(15,61,42,0.07);
            padding:32px;
            max-width:560px;
            margin: 0 auto;
        }
        .settings-section-title{
            font-size:15px;
            font-weight:700;
            color:var(--text-dark);
            margin-bottom:4px;
            display:flex;
            align-items:center;
            gap:8px;
        }
        .settings-section-title i{ color: var(--ptc-green); font-size:18px; }
        .settings-section-desc{
            font-size:12.5px;
            color:var(--text-muted);
            margin-bottom:18px;
        }
        .settings-divider{
            border:none;
            border-top:1px solid var(--border-soft);
            margin:28px 0;
        }
        .form-group{ margin-bottom:16px; }
        .form-group label{
            display:block;
            font-size:13px;
            font-weight:600;
            color:var(--text-dark);
            margin-bottom:6px;
        }
        .form-group input{
            width:100%;
            padding:11px 14px;
            border:1.5px solid var(--border-soft);
            border-radius:10px;
            font-size:13.5px;
            font-family:'Inter', sans-serif;
            transition: border-color 0.15s ease;
        }
        .form-group input:focus{
            outline:none;
            border-color: var(--ptc-green);
        }
        .form-hint{
            font-size:11.5px;
            color:var(--text-muted);
            margin-top:4px;
        }
        .save-btn{
            width:100%;
            background: var(--ptc-green);
            color:#fff;
            border:none;
            padding:13px;
            border-radius:10px;
            font-size:14px;
            font-weight:700;
            cursor:pointer;
            transition: background-color 0.15s ease;
            margin-top: 8px;
        }
        .save-btn:hover{ background: var(--ptc-green-dark); }
        .back-link{
            display:inline-flex;
            align-items:center;
            gap:6px;
            font-size:13px;
            font-weight:600;
            color: var(--text-muted);
            text-decoration:none;
            margin-bottom:20px;
        }
        .back-link:hover{ color: var(--ptc-green); }
        .picture-card{ background:#fff; border:1px solid var(--border-soft); border-radius:16px; box-shadow:0 4px 16px rgba(15,61,42,0.07); padding:28px 32px; max-width:560px; margin:0 auto 20px; display:flex; align-items:center; gap:20px; }
        .picture-preview{ width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--border-soft); flex-shrink:0; }
        .picture-preview-fallback{ width:80px; height:80px; border-radius:50%; background:var(--ptc-green); color:#fff; font-size:28px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .picture-upload-controls{ flex:1; }
        .picture-upload-controls label{ display:block; font-size:13px; font-weight:600; color:var(--text-dark); margin-bottom:8px; }
        .picture-file-row{ display:flex; gap:10px; align-items:center; }
        .picture-file-row input[type="file"]{ flex:1; font-size:12.5px; }
        .picture-upload-btn{ background:var(--ptc-green); color:#fff; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; white-space:nowrap; }
        .picture-upload-btn:hover{ background:var(--ptc-green-dark); }
        .picture-hint{ font-size:11.5px; color:var(--text-muted); margin-top:6px; }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['settings_success'])): ?>
    <div class="custom-alert-overlay">
        <div class="alert-card">
            <div class="alert-icon-wrap success"><div class="alert-icon success"><i class='bx bx-check'></i></div></div>
            <h3 class="alert-title">Success</h3>
            <p class="alert-message"><?php echo htmlspecialchars($_SESSION['settings_success']); ?></p>
            <button class="alert-btn success" onclick="document.querySelector('.custom-alert-overlay').style.display='none'">OK</button>
        </div>
    </div>
    <?php unset($_SESSION['settings_success']); endif; ?>

    <?php if (isset($_SESSION['settings_error'])): ?>
    <div class="custom-alert-overlay">
        <div class="alert-card">
            <div class="alert-icon-wrap"><div class="alert-icon">!</div></div>
            <h3 class="alert-title">Could not save</h3>
            <p class="alert-message"><?php echo htmlspecialchars($_SESSION['settings_error']); ?></p>
            <button class="alert-btn" onclick="document.querySelector('.custom-alert-overlay').style.display='none'">OK</button>
        </div>
    </div>
    <?php unset($_SESSION['settings_error']); endif; ?>

    <div class="app-shell">
        <header>
            <div class="logo-container">
                <img src="images/logo-ptc 2.png" alt="Logo">
                <div>
                    <h1>Pateros Technological College</h1>
                    <p class="header-subtitle">Student Appointment Portal</p>
                </div>
            </div>
            <nav>
                <button class="nav-button" onclick="window.location.href='student-dashboard.php'"><i class='bx bx-arrow-back'></i> Back to Dashboard</button>
                <button class="nav-button" onclick="showLogoutConfirm()"><i class='bx bx-log-out'></i> Logout</button>
            </nav>
        </header>
        <main>
            <div class="content">
                <h2>Account Settings</h2>

                <div class="picture-card">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="images/profiles/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile picture" class="picture-preview">
                    <?php else: ?>
                        <div class="picture-preview-fallback"><?= htmlspecialchars(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
                    <?php endif; ?>
                    <div class="picture-upload-controls">
                        <form method="post" action="" enctype="multipart/form-data">
                            <input type="hidden" name="form_type" value="picture">
                            <label for="profile_picture">Profile Picture</label>
                            <div class="picture-file-row">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/webp" required>
                                <button type="submit" class="picture-upload-btn">Upload</button>
                            </div>
                            <p class="picture-hint">JPG, PNG, or WEBP. Max 2MB.</p>
                        </form>
                    </div>
                </div>

                <div class="settings-card">
                    <form method="post" action="">
                        <input type="hidden" name="form_type" value="profile">
                        <div class="settings-section-title"><i class='bx bx-user'></i> Profile Information</div>
                        <p class="settings-section-desc">Update your name and email address.</p>

                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">School Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <hr class="settings-divider">

                        <div class="settings-section-title"><i class='bx bx-lock-alt'></i> Change Password</div>
                        <p class="settings-section-desc">Leave the new password fields blank if you don't want to change it.</p>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                            <p class="form-hint">Minimum 8 characters.</p>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">
                        </div>

                        <hr class="settings-divider">

                        <div class="form-group">
                            <label for="current_password">Current Password <span style="color:#d64545;">*</span></label>
                            <input type="password" id="current_password" name="current_password" placeholder="Required to save any changes" required>
                        </div>

                        <button type="submit" class="save-btn">Save Changes</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div id="logoutConfirmOverlay" class="custom-alert-overlay" style="display:none;">
        <div class="alert-card">
            <div class="alert-icon-wrap"><div class="alert-icon">!</div></div>
            <h3 class="alert-title">Log out</h3>
            <p class="alert-message">Are you sure you want to log out?</p>
            <div style="display:flex; gap:12px;">
                <button class="alert-btn secondary" onclick="closeLogoutConfirm()">Stay logged in</button>
                <button class="alert-btn" onclick="window.location.href='logout.php'">Yes, log out</button>
            </div>
        </div>
    </div>

    <script>
        function showLogoutConfirm() {
            document.getElementById('logoutConfirmOverlay').style.display = 'flex';
        }
        function closeLogoutConfirm() {
            document.getElementById('logoutConfirmOverlay').style.display = 'none';
        }
    </script>

</body>
</html>