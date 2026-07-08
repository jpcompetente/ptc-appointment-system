<?php
require_once "config.php";

if (!isset($_SESSION["student_id"])) {
    header("Location: student-auth.php");
    exit;
}

$student_id = $_SESSION["student_id"];

$pic_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$pic_stmt->bind_param("i", $student_id);
$pic_stmt->execute();
$pic_row = $pic_stmt->get_result()->fetch_assoc();
$pic_stmt->close();
$student_picture = $pic_row['profile_picture'] ?? null;

$stmt = $conn->prepare("SELECT id, document_type, other_document, appointment_date, status, message FROM appointments WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$appointments_result = $stmt->get_result();
$appointments = $appointments_result->fetch_all(MYSQLI_ASSOC);
$total_count = count($appointments);
$pending_count = 0;
$approved_count = 0;
$cancelled_count = 0;
$rejected_count = 0;
foreach ($appointments as $appt) {
    switch ($appt['status']) {
        case 'pending': $pending_count++; break;
        case 'approved': $approved_count++; break;
        case 'cancelled': $cancelled_count++; break;
        case 'rejected': $rejected_count++; break;
    }
}
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
        .doc-type-dot{ display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:8px; }
        .doc-type-tor{ background:#FF5722; }
        .doc-type-cog{ background:#9C27B0; }
        .doc-type-cor{ background:#E91E63; }
        .cancel-icon-btn{
            display:inline-flex; align-items:center; gap:6px;
            background: rgba(214,69,69,0.1);
            color: #d64545;
            border: 1px solid rgba(214,69,69,0.25);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }
        .cancel-icon-btn:hover{ background:#d64545; color:#fff; transform: translateY(-1px); }
        .cancel-icon-btn i{ font-size: 14px; }
        .empty-state-row{ text-align:center !important; padding: 48px 20px !important; }
        .empty-state-row .empty-icon{ font-size: 40px; color: var(--ptc-green, #205e44); opacity:0.5; margin-bottom: 10px; display:block; }
        .empty-state-row a{ color: var(--ptc-green, #205e44); font-weight:700; text-decoration:none; }
        .empty-state-row a:hover{ text-decoration:underline; }
        .header-subtitle{ font-size:12px; font-weight:500; color:rgba(255,255,255,0.75); margin-top:2px; letter-spacing:0.2px; }
        .student-chip{ display:flex; align-items:center; gap:9px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); padding:6px 14px 6px 6px; border-radius:999px; }
        .student-avatar{ width:26px; height:26px; border-radius:50%; background:#fff; color:var(--ptc-green-dark, #0f3d2a); font-size:12px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .student-chip-name{ font-size:13px; font-weight:600; color:#fff; white-space:nowrap; }
        .student-avatar-img{ width:26px; height:26px; border-radius:50%; object-fit:cover; flex-shrink:0; border:1px solid rgba(255,255,255,0.4); }
        .profile-menu-wrapper{ position:relative; }
        .student-chip{ cursor:pointer; border:none; }
        .profile-chevron{ font-size:16px; margin-left:2px; transition: transform 0.15s ease; }
        .profile-menu-wrapper.open .profile-chevron{ transform: rotate(180deg); }
        .profile-dropdown{
            display:none;
            position:absolute;
            top:calc(100% + 10px);
            right:0;
            background:#fff;
            border-radius:12px;
            box-shadow:0 10px 30px rgba(15,61,42,0.18);
            border:1px solid var(--border-soft);
            min-width:220px;
            overflow:hidden;
            z-index:200;
        }
        .profile-menu-wrapper.open .profile-dropdown{ display:block; }
        .dropdown-item{
            display:flex;
            align-items:center;
            gap:10px;
            padding:12px 16px;
            font-size:13.5px;
            font-weight:600;
            color: var(--text-dark);
            text-decoration:none;
            transition: background-color 0.15s ease;
        }
        .dropdown-item i{ font-size:17px; color: var(--ptc-green); }
        .dropdown-item:hover{ background: var(--bg-soft); }
        .icon-nav-btn{
            width:40px;
            height:40px;
            border-radius:50%;
            background-color: rgba(255,255,255,0.12);
            color:#fff;
            border:1px solid rgba(255,255,255,0.25);
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:17px;
            cursor:pointer;
            transition: background-color 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
        }
        .icon-nav-btn:hover{
            background-color:#fff;
            color: var(--ptc-green-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        .icon-nav-btn{ position: relative; }
        .notif-badge{
            position:absolute;
            top:-2px;
            right:-2px;
            background:#d64545;
            color:#fff;
            font-size:10px;
            font-weight:800;
            min-width:16px;
            height:16px;
            border-radius:999px;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:0 3px;
            border:2px solid var(--ptc-green-dark, #0f3d2a);
        }
        .notif-dropdown{ width:320px; max-height:380px; display:none; flex-direction:column; }
        .profile-menu-wrapper.open .notif-dropdown{ display:flex; }
        .notif-header{ padding:14px 16px; font-size:13.5px; font-weight:700; color:var(--text-dark); border-bottom:1px solid var(--border-soft); }
        .notif-list{ overflow-y:auto; max-height:320px; }
        .notif-empty{ padding:24px 16px; text-align:center; font-size:13px; color:var(--text-muted); }
        .notif-item{ display:flex; gap:10px; padding:12px 16px; border-bottom:1px solid var(--border-soft); }
        .notif-item:last-child{ border-bottom:none; }
        .notif-item.unread{ background: rgba(32,94,68,0.05); }
        .notif-dot{ width:8px; height:8px; border-radius:50%; background:var(--ptc-green); flex-shrink:0; margin-top:5px; }
        .notif-item:not(.unread) .notif-dot{ background:transparent; }
        .notif-content p{ font-size:12.5px; color:var(--text-dark); line-height:1.4; margin-bottom:3px; }
        .notif-time{ font-size:11px; color:var(--text-muted); }
        @media (max-width:768px){ .student-chip-name{ display:none; } }
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
                <div>
                    <h1>Pateros Technological College</h1>
                    <p class="header-subtitle">Student Appointment Portal</p>
                </div>
            </div>
            <nav>
                <div class="profile-menu-wrapper">
                    <button class="student-chip" onclick="toggleProfileMenu(event)" type="button">
                        <?php if (!empty($student_picture)): ?>
                            <img src="images/profiles/<?= htmlspecialchars($student_picture) ?>" alt="" class="student-avatar-img">
                        <?php else: ?>
                            <span class="student-avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION["student_name"], 0, 1))) ?></span>
                        <?php endif; ?>
                        <span class="student-chip-name"><?= htmlspecialchars($_SESSION["student_name"]) ?></span>
                        <i class='bx bx-chevron-down profile-chevron'></i>
                    </button>
                    <div id="profileDropdown" class="profile-dropdown">
                        <a href="Index.php" class="dropdown-item"><i class='bx bx-calendar-plus'></i> Book New Appointment</a>
                        <a href="account-settings.php" class="dropdown-item"><i class='bx bx-cog'></i> Account Settings</a>
                    </div>
                </div>
                <div class="profile-menu-wrapper">
                    <button class="icon-nav-btn" onclick="toggleNotifDropdown(event)" title="Notifications" type="button">
                        <i class='bx bx-bell'></i>
                        <span id="notifBadge" class="notif-badge" style="display:none;">0</span>
                    </button>
                    <div id="notifDropdown" class="profile-dropdown notif-dropdown">
                        <div class="notif-header">Notifications</div>
                        <div id="notifList" class="notif-list">
                            <div class="notif-empty">Loading...</div>
                        </div>
                    </div>
                </div>
                <button class="icon-nav-btn" onclick="showLogoutConfirm()" title="Logout"><i class='bx bx-log-out'></i></button>
            </nav>
        </header>
        <main>
            <div class="content">
                <h2>Welcome, <?= htmlspecialchars($_SESSION["student_name"]) ?></h2>

                <div class="summary-boxes-container">
                    <div class="summary-box total-appointments">
                        <p>Total Appointments</p>
                        <span class="value"><?= $total_count ?></span>
                    </div>
                    <div class="summary-box pending-appointments">
                        <p>Pending</p>
                        <span class="value"><?= $pending_count ?></span>
                    </div>
                    <div class="summary-box approved-appointments">
                        <p>Approved</p>
                        <span class="value"><?= $approved_count ?></span>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="table" id="appointments-table">
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
                            <?php if ($total_count === 0): ?>
                            <tr>
                                <td colspan="5" class="empty-state-row">
                                    <i class='bx bx-calendar-x empty-icon'></i>
                                    Wala ka pang appointment. <a href="Index.php">Mag-book na</a>.
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($appointments as $row): ?>
                            <?php
                                $doc_type_lower = strtolower($row["document_type"]);
                                $dot_class = 'doc-type-tor';
                                if ($doc_type_lower === 'cog') { $dot_class = 'doc-type-cog'; }
                                elseif ($doc_type_lower === 'cor') { $dot_class = 'doc-type-cor'; }
                            ?>
                            <tr id="row-<?= $row['id'] ?>">
                                <td>
                                    <span class="doc-type-dot <?= $dot_class ?>"></span><?= htmlspecialchars(strtoupper($row["document_type"])) ?>
                                    <?php if (!empty($row["other_document"])): ?>
                                        (<?= htmlspecialchars($row["other_document"]) ?>)
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row["appointment_date"]) ?></td>
                                <td><span class="status-badge status-<?= htmlspecialchars($row["status"]) ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                                <td><?= htmlspecialchars($row["message"]) ?></td>
                                <td>
                                    <?php if ($row["status"] === "pending"): ?>
                                        <button class="cancel-icon-btn" onclick="cancelAppointment(<?= $row['id'] ?>)"><i class='bx bx-x-circle'></i> Cancel</button>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
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

        function toggleProfileMenu(event) {
            event.stopPropagation();
            const wrappers = document.querySelectorAll('.profile-menu-wrapper');
            const thisWrapper = event.currentTarget.closest('.profile-menu-wrapper');
            wrappers.forEach(w => { if (w !== thisWrapper) w.classList.remove('open'); });
            thisWrapper.classList.toggle('open');
        }
        document.addEventListener('click', function (e) {
            document.querySelectorAll('.profile-menu-wrapper').forEach(function (wrapper) {
                if (!wrapper.contains(e.target)) {
                    wrapper.classList.remove('open');
                }
            });
        });

        function timeAgo(dateStr) {
            const seconds = Math.floor((new Date() - new Date(dateStr.replace(' ', 'T'))) / 1000);
            if (seconds < 60) return 'Just now';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + 'm ago';
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + 'h ago';
            const days = Math.floor(hours / 24);
            return days + 'd ago';
        }

        function renderNotifications(data) {
            const badge = document.getElementById('notifBadge');
            const list = document.getElementById('notifList');
            if (data.unread_count > 0) {
                badge.style.display = 'flex';
                badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
            } else {
                badge.style.display = 'none';
            }
            if (!data.notifications || data.notifications.length === 0) {
                list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
                return;
            }
            list.innerHTML = data.notifications.map(function (n) {
                const unreadClass = n.is_read == 0 ? 'unread' : '';
                return '<div class="notif-item ' + unreadClass + '">' +
                    '<div class="notif-dot"></div>' +
                    '<div class="notif-content"><p>' + n.message.replace(/</g, '&lt;') + '</p>' +
                    '<span class="notif-time">' + timeAgo(n.created_at) + '</span></div>' +
                    '</div>';
            }).join('');
        }

        function fetchNotifications() {
            fetch('get_notifications.php')
                .then(res => res.json())
                .then(data => { if (data.success) renderNotifications(data); })
                .catch(() => {});
        }

        function toggleNotifDropdown(event) {
            event.stopPropagation();
            const wrappers = document.querySelectorAll('.profile-menu-wrapper');
            const thisWrapper = event.currentTarget.closest('.profile-menu-wrapper');
            const willOpen = !thisWrapper.classList.contains('open');
            wrappers.forEach(w => { if (w !== thisWrapper) w.classList.remove('open'); });
            thisWrapper.classList.toggle('open');
            if (willOpen) {
                fetch('mark_notifications_read.php', { method: 'POST' })
                    .then(() => fetchNotifications());
            }
        }

        fetchNotifications();
        setInterval(fetchNotifications, 30000);

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
