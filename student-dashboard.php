<?php
require_once "config.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: student-auth.php");
    exit;
}

$student_id = $_SESSION["student_id"];
$stmt = $conn->prepare("SELECT id, document_type, other_document, appointment_date, status, message FROM appointments WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$appointments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/dashboard.css">
    <title>My Appointments | PTC Web System</title>
    <style>
        .status-badge{ padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #fff; display: inline-block; }
        .status-pending{ background-color: #FFC107; color: #1a2b23; }
        .status-approved{ background-color: #2196F3; }
        .status-rejected{ background-color: #d64545; }
        .status-cancelled{ background-color: #9e9e9e; }
        .cancel-btn{
            background-color: #d64545;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .cancel-btn:hover{ opacity: 0.85; }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['booking_success'])): ?>
    <div class="custom-alert-overlay">
        <div class="alert-card success">
            <div class="alert-icon-wrap"><div class="alert-icon"><i class='bx bx-check'></i></div></div>
            <h3 class="alert-title">Success</h3>
            <p class="alert-message"><?php echo htmlspecialchars($_SESSION['booking_success']); ?></p>
            <button class="alert-btn" onclick="document.querySelector('.custom-alert-overlay').style.display='none'">OK</button>
        </div>
    </div>
    <style>
        .custom-alert-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999; }
        .custom-alert-overlay .alert-card { background:#ffffff; border-radius:18px; box-shadow:0 10px 30px rgba(0,0,0,0.15); padding:40px 36px; max-width:380px; width:90%; text-align:center; }
        .custom-alert-overlay .alert-icon-wrap { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; background:#d7f5e9; }
        .custom-alert-overlay .alert-icon { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:20px; background:#22b573; color:#fff; }
        .custom-alert-overlay .alert-title { font-size:20px; font-weight:600; color:#1f2937; margin:0 0 8px; }
        .custom-alert-overlay .alert-message { font-size:14px; color:#6b7280; margin:0 0 28px; line-height:1.5; }
        .custom-alert-overlay .alert-btn { border:none; padding:12px 32px; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer; width:100%; background:#22b573; color:#fff; }
        .custom-alert-overlay .alert-btn:hover { background:#1c9760; }
    </style>
    <?php unset($_SESSION['booking_success']); endif; ?>
    <div class="container">
        <header>
            <div class="logo-container">
                <img src="images/logo-ptc 2.png" alt="Logo">
                <h1>Pateros Technological College</h1>
            </div>
            <nav>
                <button class="nav-button" onclick="window.location.href='Index.php'">Book New Appointment</button>
                <button class="nav-button" onclick="showLogoutConfirm()">Logout</button>
            </nav>
        </header>
        <main>
            <div class="content">
                <h2>Welcome, <?= htmlspecialchars($_SESSION["student_name"]) ?></h2>

                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>Appointment Date</th>
                                <th>Status</th>
                                <th>Message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments->num_rows === 0): ?>
                            <tr><td colspan="5">Wala ka pang appointment. <a href="Index.php">Mag-book na</a>.</td></tr>
                            <?php endif; ?>
                            <?php while ($row = $appointments->fetch_assoc()): ?>
                            <tr id="row-<?= $row['id'] ?>">
                                <td>
                                    <?= htmlspecialchars(strtoupper($row["document_type"])) ?>
                                    <?php if (!empty($row["other_document"])): ?>
                                        (<?= htmlspecialchars($row["other_document"]) ?>)
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row["appointment_date"]) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($row["status"]) ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                                <td><?= htmlspecialchars($row["message"]) ?></td>
                                <td>
                                    <?php if ($row["status"] === "pending"): ?>
                                        <button class="cancel-btn" onclick="cancelAppointment(<?= $row['id'] ?>)">Cancel</button>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="confirmCancelOverlay" class="custom-alert-overlay" style="display:none;">
        <div class="alert-card">
            <div class="alert-icon-wrap"><div class="alert-icon">!</div></div>
            <h3 class="alert-title">Cancel appointment</h3>
            <p class="alert-message">Are you sure you want to cancel this appointment? This action cannot be undone.</p>
            <div style="display:flex; gap:12px;">
                <button class="alert-btn secondary" onclick="closeConfirmCancel()">Keep it</button>
                <button class="alert-btn" onclick="confirmCancelAppointment()">Yes, cancel</button>
            </div>
        </div>
    </div>

    <div id="resultAlertOverlay" class="custom-alert-overlay" style="display:none;">
        <div class="alert-card" id="resultAlertCard">
            <div class="alert-icon-wrap" id="resultAlertIconWrap"><div class="alert-icon" id="resultAlertIcon">!</div></div>
            <h3 class="alert-title" id="resultAlertTitle">Notice</h3>
            <p class="alert-message" id="resultAlertMessage"></p>
            <button class="alert-btn" id="resultAlertBtn" onclick="closeResultOverlay()">OK</button>
        </div>
    </div>

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

    <script>
        let _cancelTargetId = null;
        let _cancelReloadOnClose = false;

        function cancelAppointment(id) {
            _cancelTargetId = id;
            document.getElementById('confirmCancelOverlay').style.display = 'flex';
        }

        function closeConfirmCancel() {
            document.getElementById('confirmCancelOverlay').style.display = 'none';
            _cancelTargetId = null;
        }

        function confirmCancelAppointment() {
            const id = _cancelTargetId;
            closeConfirmCancel();
            fetch("cancel-appointment.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id=" + encodeURIComponent(id)
            })
            .then(res => res.json())
            .then(data => {
                _cancelReloadOnClose = !!data.success;
                showResultOverlay(data.message, data.success);
            })
            .catch(() => {
                _cancelReloadOnClose = false;
                showResultOverlay("Something went wrong while cancelling. Please try again.", false);
            });
        }

        function showResultOverlay(message, success) {
            document.getElementById('resultAlertMessage').innerText = message;
            document.getElementById('resultAlertTitle').innerText = success ? "Success" : "Cancellation failed";
            document.getElementById('resultAlertIcon').innerHTML = success ? "&#10003;" : "!";
            document.getElementById('resultAlertIconWrap').classList.toggle('success', success);
            document.getElementById('resultAlertIcon').classList.toggle('success', success);
            document.getElementById('resultAlertBtn').classList.toggle('success', success);
            document.getElementById('resultAlertOverlay').style.display = 'flex';
        }

        function closeResultOverlay() {
            document.getElementById('resultAlertOverlay').style.display = 'none';
            if (_cancelReloadOnClose) {
                location.reload();
            }
        }

        function showLogoutConfirm() {
            document.getElementById('logoutConfirmOverlay').style.display = 'flex';
        }
        function closeLogoutConfirm() {
            document.getElementById('logoutConfirmOverlay').style.display = 'none';
        }
    </script>

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
</body>
</html>
