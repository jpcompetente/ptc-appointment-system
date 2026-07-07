<?php
require_once "config.php";

// Kailangan naka-login bilang student bago makapag-book
if (!isset($_SESSION["student_id"])) {
    header("Location: student-auth.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $user_id = $_SESSION["student_id"];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $date = $_POST['date'];
    $docs = $_POST['docs'];
    $otherDoc = isset($_POST['otherDoc']) ? $_POST['otherDoc'] : '';
    $message = $_POST['message'];

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
        echo '<script>alert("Puno na ang slots para sa napiling araw. Pumili ng ibang date."); window.location.href = "Index.php";</script>';
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO appointments (user_id, name, email, phone, appointment_date, document_type, other_document, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    if ($stmt === false) {
        die('Could not prepare statement: ' . $conn->error);
    }

    $stmt->bind_param("isssssss", $user_id, $name, $email, $phone, $date, $docs, $otherDoc, $message);
    if ($stmt->execute()) {
        echo '<script>alert("Appointment submitted successfully!"); window.location.href = "student-dashboard.php";</script>';
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
    <link rel="stylesheet" href="css/Index.css?v=2">
    <title>PTC Web System | Appointment &amp; Queuing</title>
</head>

<body>

    <div class="main">

        <div class="navbar">
            <a href="#" class="logo">Pateros Technological College</a>
            <div class="nav-links">
                <span class="item selected">Home</span>
                <span id="scroll" class="item">Get an Appointment</span>
                <a href="student-dashboard.php" class="item" style="text-decoration:none;">My Appointments</a>
                <a href="logout.php" class="item" style="text-decoration:none;">Logout</a>
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
            <p>&copy; <span id="year"></span> Pateros Technological College — Registrar's Office</p>
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
        </script>
        <script src="js/Index.js"></script>
    </div>

</body>

</html>




