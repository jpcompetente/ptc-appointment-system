<?php
require_once "config.php";

$is_logged_in = isset($_SESSION["student_id"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$is_logged_in) {
        header("Location: student-auth.php");
        exit;
    }

    $user_id = $_SESSION["student_id"];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $date = $_POST['date'];
    $docs = $_POST['docs'];
    $otherDoc = isset($_POST['otherDoc']) ? $_POST['otherDoc'] : '';
    $message = $_POST['message'];

    // Reusable styled alert modal (replaces plain browser alert())
    function show_custom_alert($message, $redirect = 'Index.php') {
        $_SESSION['booking_alert'] = $message;
        header("Location: " . $redirect);
        exit();
    }
    // Check if the selected date is blocked
    $blocked_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'blocked_dates'");
    $blocked_stmt->execute();
    $blocked_result = $blocked_stmt->get_result()->fetch_assoc();
    $blocked_dates_raw = $blocked_result ? $blocked_result['setting_value'] : '';
    $blocked_dates_arr = array_filter(array_map('trim', explode(',', $blocked_dates_raw)));
    $blocked_stmt->close();
    if (in_array($date, $blocked_dates_arr)) {
        show_custom_alert("This date is not available for appointments. Please choose another date.");
    }
    // Check if the selected day of the week is allowed
    $days_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'allowed_days'");
    $days_stmt->execute();
    $days_result = $days_stmt->get_result()->fetch_assoc();
    $allowed_days_raw = $days_result ? $days_result['setting_value'] : 'Mon,Tue,Wed,Thu,Fri';
    $allowed_days_arr = array_filter(array_map('trim', explode(',', $allowed_days_raw)));
    $days_stmt->close();
    $day_abbr_map = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $selected_day_index = date('N', strtotime($date)) - 1;
    $selected_day_abbr = $day_abbr_map[$selected_day_index];
    if (!in_array($selected_day_abbr, $allowed_days_arr)) {
        show_custom_alert("Appointments are not accepted on this day of the week. Please choose an allowed day.");
    }
    // Check slot limit for the selected date
    $limit_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_appointments_per_day'");
    $limit_stmt->execute();
    $limit_result = $limit_stmt->get_result()->fetch_assoc();
    $max_per_day = $limit_result ? (int) $limit_result['setting_value'] : 10;
    $limit_stmt->close();

    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE appointment_date = ? AND status != 'rejected'");
    $count_stmt->bind_param("s", $date);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $current_count = (int) $count_result['total'];
    $count_stmt->close();

    if ($current_count >= $max_per_day) {
        show_custom_alert("Puno na ang slots para sa napiling araw. Pumili ng ibang date.");
    }

    // Check if the student already has a pending/approved request for the same document type
    $dup_stmt = $conn->prepare("SELECT id FROM appointments WHERE user_id = ? AND document_type = ? AND status IN ('pending', 'approved') LIMIT 1");
    $dup_stmt->bind_param("is", $user_id, $docs);
    $dup_stmt->execute();
    $dup_result = $dup_stmt->get_result();
    $has_duplicate = $dup_result->fetch_assoc();
    $dup_stmt->close();

    if ($has_duplicate) {
        show_custom_alert("You already have a pending or approved request for " . strtoupper($docs) . ". Please wait for it or cancel it before booking again.");
    }

    $stmt = $conn->prepare("INSERT INTO appointments (user_id, name, email, phone, appointment_date, document_type, other_document, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    if ($stmt === false) {
        die('Could not prepare statement: ' . $conn->error);
    }

    $stmt->bind_param("isssssss", $user_id, $name, $email, $phone, $date, $docs, $otherDoc, $message);
    if ($stmt->execute()) {
        $_SESSION['booking_success'] = "Appointment submitted successfully!";
        header("Location: Index.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/Index.css?v=<?php echo time(); ?>">
    <title>PTC Web System | Appointment &amp; Queuing</title>
</head>

<body>

    <style>
        .custom-alert-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .custom-alert-overlay .alert-card { background:#ffffff; border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,0.15); padding:40px 36px; max-width:380px; width:90%; text-align:center; }
        .custom-alert-overlay .alert-icon-wrap { width:64px; height:64px; border-radius:50%; background:#fde2e1; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; }
        .custom-alert-overlay .alert-icon { width:36px; height:36px; border-radius:50%; background:#f24236; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:20px; }
        .custom-alert-overlay .alert-title { font-size:20px; font-weight:600; color:#1f2937; margin:0 0 8px; }
        .custom-alert-overlay .alert-message { font-size:14px; color:#6b7280; margin:0 0 28px; line-height:1.5; }
        .custom-alert-overlay .alert-btn { background:#f24236; color:#fff; border:none; padding:12px 32px; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; width:100%; }
        .custom-alert-overlay .alert-btn:hover { background:#d93a30; }
        .custom-alert-overlay .alert-btn.secondary { background:#e5e7eb; color:#374151; }
        .custom-alert-overlay .alert-btn.secondary:hover { background:#d1d5db; }
        .custom-alert-overlay .alert-icon-wrap.success { background:#d7f5e9; }
        .custom-alert-overlay .alert-icon.success { background:#22b573; }
        .custom-alert-overlay .alert-btn.success { background:#22b573; }
        .custom-alert-overlay .alert-btn.success:hover { background:#1c9760; }
    </style>

    <?php if (isset($_SESSION['booking_alert'])): ?>
    <div class="custom-alert-overlay">
        <div class="alert-card">
            <div class="alert-icon-wrap"><div class="alert-icon">!</div></div>
            <h3 class="alert-title">Booking not allowed</h3>
            <p class="alert-message"><?php echo htmlspecialchars($_SESSION['booking_alert']); ?></p>
            <button class="alert-btn" onclick="document.querySelector('.custom-alert-overlay').style.display='none'">OK</button>
        </div>
    </div>
    <?php unset($_SESSION['booking_alert']); endif; ?>

    <?php if (isset($_SESSION['booking_success'])): ?>
    <div class="custom-alert-overlay">
        <div class="alert-card success">
            <div class="alert-icon-wrap success"><div class="alert-icon success"><i class='bx bx-check'></i></div></div>
            <h3 class="alert-title">Success</h3>
            <p class="alert-message"><?php echo htmlspecialchars($_SESSION['booking_success']); ?></p>
            <button class="alert-btn success" onclick="document.querySelector('.custom-alert-overlay').style.display='none'">OK</button>
        </div>
    </div>
    <?php unset($_SESSION['booking_success']); endif; ?>

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

    <div class="main">

        <div class="navbar">
            <a href="#" class="logo">Pateros Technological College</a>
            <div class="nav-links">
                <span class="item selected">Home</span>
                <span id="scroll" class="item">Get an Appointment</span>
                <?php if ($is_logged_in): ?>
                <a href="student-dashboard.php" class="item" style="text-decoration:none;">My Appointments</a>
                <a href="#" class="item" style="text-decoration:none;" onclick="showLogoutConfirm(); return false;">Logout</a>
                <?php else: ?>
                <a href="student-auth.php" class="item" style="text-decoration:none;">Login / Sign up</a>
                <?php endif; ?>

            </div>
            <button class="toggler">
                <i class='bx bx-menu'></i>
            </button>
        </div>


        <div class="top-container">
            <div class="info-box">
                <p class="header">
                    Appointment &amp; Queuing System with the Registrar
                </p>
                <p class="info-text">
                    Designed to enhance your experience, eliminate long waits, and ensure you never miss an important document again.
                </p>
                <div class="info-buttons">
                    <button class="info-btn" id="scrollToBooking">Book an Appointment</button>

                </div>
            </div>
            <div class="nft-box">
                <img src="images/img2.png" class="nft-pic" alt="Registrar office">
                <div class="nft-content">
                    <div class="info">
                        <img src="images/logo-ptc 2.png" class="info-img" alt="PTC Logo">
                        <div>
                            <p>PTC</p>
                            <p class="since">Since 1993</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="get-started">
            <p class="header">Getting Started</p>
            <p class="info-text">Experience a seamless, hassle-free process</p>
            <div class="items-box">
                <div class="item-container">
                    <div class="item">
                        <i class='bx bxs-shield'></i>
                    </div>
                    <p>Transactions are safe</p>
                </div>
                <div class="item-container">
                    <div class="item">
                        <i class='bx bxs-user-account'></i>
                    </div>
                    <p>Use your institutional account</p>
                </div>
                <div class="item-container">
                    <div class="item">
                        <i class='bx bxs-credit-card'></i>
                    </div>
                    <p>Free to use, no charges</p>
                </div>
                <div class="item-container">
                    <div class="item">
                        <i class='bx bxs-rocket'></i>
                    </div>
                    <p>Fast transactions</p>
                </div>
            </div>
        </div>


        <section class="section-padding" id="booking">
            <div class="booking-wrapper">
                <div class="booking-form">
                    <h2>Appointment of Documents</h2>
                    <p class="booking-subtext">Fill out the form below to request your document</p>
                    <form role="form" method="post" action="">
                        <div class="form-grid">
                            <div class="form-field">
                                <label for="name">Full Name</label>
                                <input type="text" name="name" id="name" class="form-control" placeholder="Enter your full name" required>
                            </div>
                            <div class="form-field">
                                <label for="email">Email Address</label>
                                <input type="email" name="email" id="email" pattern="[^ @]*@[^ @]*" class="form-control" placeholder="Enter your email address" required>
                            </div>
                            <div class="form-field">
                                <label for="phone">Phone Number</label>
                                <input type="tel" name="phone" id="phone" class="form-control" placeholder="Enter your phone number" maxlength="10">
                            </div>
                            <div class="form-field">
                                <label for="date">Appointment Date</label>
                                <input type="date" name="date" id="date" class="form-control" required>
                            </div>
                            <div class="form-field form-field-full">
                                <label for="docs">Choose a Documentation</label>
                                <select name="docs" id="docs" class="form-control" onchange="showOtherInput()">
                                    <option value="" selected disabled hidden>Choose a Document</option>
                                    <option value="cor">COR</option>
                                    <option value="cog">COG</option>
                                    <option value="other documents">Other Documents</option>
                                </select>
                                <div id="otherInput">
                                    <label for="otherDoc">Please specify</label>
                                    <input type="text" id="otherDoc" name="otherDoc" class="form-control">
                                </div>
                            </div>
                            <div class="form-field form-field-full">
                                <label for="message">Additional Message</label>
                                <textarea class="form-control" rows="4" id="message" name="message" placeholder="Enter any additional message"></textarea>
                            </div>
                            <div class="form-field form-field-full form-submit">
                                <button type="submit" name="submit" id="submit-button">Appoint Now</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <footer class="main-footer">
            <p>&copy; <span id="year"></span> Pateros Technological College - Registrar's Office</p>
        </footer>

        <script>

            function showOtherInput() {
                var select = document.getElementById("docs");
                var otherInput = document.getElementById("otherInput");
                otherInput.style.display = (select.value === "other documents") ? "block" : "none";
            }

            document.getElementById("scroll").addEventListener("click", function () {
                document.getElementById("booking").scrollIntoView({ behavior: "smooth" });
            });
            document.getElementById("scrollToBooking").addEventListener("click", function () {
            });

            document.getElementById("year").textContent = new Date().getFullYear();

            function showLogoutConfirm() {
                document.getElementById('logoutConfirmOverlay').style.display = 'flex';
            }
            function closeLogoutConfirm() {
                document.getElementById('logoutConfirmOverlay').style.display = 'none';
            }
        </script>
        <script src="js/Index.js"></script>
    </div>

</body>

</html>




