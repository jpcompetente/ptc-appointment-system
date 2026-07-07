<?php
require_once "config.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: registrar-login.php");
    exit();
}

$summary_sql = "SELECT
                COUNT(*) as total_appointments,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_appointments,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_appointments,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_appointments,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
                SUM(CASE WHEN document_type = 'cor' THEN 1 ELSE 0 END) as cor_count,
                SUM(CASE WHEN document_type = 'cog' THEN 1 ELSE 0 END) as cog_count,
                SUM(CASE WHEN document_type = 'other documents' THEN 1 ELSE 0 END) as other_count,
                SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today_count,
                SUM(CASE WHEN appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_count
                FROM appointments";
$summary_result = $conn->query($summary_sql);
$summary_data = $summary_result->fetch_assoc();

$app_sql = "SELECT a.id, a.name, a.email, a.phone, a.appointment_date, a.document_type, a.other_document, a.message, a.status, adm.username as reviewed_by_name, a.reviewed_at
            FROM appointments a
            LEFT JOIN admins adm ON a.reviewed_by = adm.id
            ORDER BY a.id DESC";
$app_result = $conn->query($app_sql);

$setting_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_appointments_per_day'");
$setting_stmt->execute();
$setting_row = $setting_stmt->get_result()->fetch_assoc();
$max_per_day = $setting_row ? $setting_row['setting_value'] : 10;
$setting_stmt->close();

$settings_message = isset($_GET['settings_saved']) ? "The new setting has been saved." : "";
$password_saved_message = isset($_GET['password_saved']) ? "Matagumpay na na-update ang password." : "";
$password_error_message = isset($_GET['password_error']) ? $_GET['password_error'] : "";

$allowed_days_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'allowed_days'");
$allowed_days_stmt->execute();
$allowed_days_row = $allowed_days_stmt->get_result()->fetch_assoc();
$allowed_days_raw = $allowed_days_row ? $allowed_days_row['setting_value'] : 'Mon,Tue,Wed,Thu,Fri';
$allowed_days_arr = array_filter(array_map('trim', explode(',', $allowed_days_raw)));
$allowed_days_stmt->close();

$blocked_dates_stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'blocked_dates'");
$blocked_dates_stmt->execute();
$blocked_dates_row = $blocked_dates_stmt->get_result()->fetch_assoc();
$blocked_dates_raw = $blocked_dates_row ? $blocked_dates_row['setting_value'] : '';
$blocked_dates_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false
            },
            theme: {
                extend: {
                    colors: {
                        'ptc-dark': '#0f3d2a',
                        'ptc-green': '#205e44',
                        'ptc-light': '#2e7d5b',
                    }
                }
            }
        }
    </script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
    <title>Appointments Dashboard</title>
    <style>
        h2 {
            font-size: 28px;
            font-weight: 800;
            margin: 0 20px 4px;
        }
        .page-subtext {
            margin: 0 20px 20px;
            font-size: 13.5px;
            color: var(--text-muted, #6b7d74);
        }
        .section-label {
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted, #6b7d74);
            margin: 28px 20px 12px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
            margin: 0 20px;
        }
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            border: 1px solid var(--border-soft, #e1e6e3);
            box-shadow: 0 2px 10px rgba(15, 61, 42, 0.06);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 24px rgba(15, 61, 42, 0.14);
        }
        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted, #6b7d74);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
            white-space: nowrap;
        }
        .stat-value {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark, #1a2b23);
            line-height: 1;
        }
        .clear-filter-btn {
            margin: 0 20px 16px;
            background: none;
            border: 1px solid var(--border-soft, #e1e6e3);
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            color: var(--text-muted, #6b7d74);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .clear-filter-btn:hover {
            background-color: #fff;
            border-color: var(--ptc-green, #205e44);
            color: var(--ptc-green, #205e44);
        }
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin: 24px 20px 20px;
        }
        .chart-card {
            background: #fff;
            border: 1px solid var(--border-soft, #e1e6e3);
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(15, 61, 42, 0.06);
        }
        .chart-card h3 {
            font-size: 14.5px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-dark, #1a2b23);
        }
        .chart-card .chart-sub {
            font-size: 12px;
            color: var(--text-muted, #6b7d74);
            margin-bottom: 16px;
        }
        .chart-wrap {
            position: relative;
            height: 240px;
        }
        .table-card {
            margin: 0 20px 20px;
            background: #fff;
            border: 1px solid var(--border-soft, #e1e6e3);
            border-radius: 14px;
            padding: 8px 16px 16px;
            box-shadow: 0 2px 10px rgba(15, 61, 42, 0.06);
            overflow-x: auto;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 20px;
            margin: 0 20px 20px;
            align-items: start;
        }
        .settings-card {
            background: #fff;
            border: 1px solid var(--border-soft, #e1e6e3);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(15, 61, 42, 0.06);
            transition: box-shadow 0.15s ease;
        }
        .settings-card:hover {
            box-shadow: 0 8px 22px rgba(15, 61, 42, 0.1);
        }
        .settings-card-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
        }
        .settings-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .settings-card-header h3 {
            font-size: 15.5px;
            font-weight: 700;
            color: var(--text-dark, #1a2b23);
            margin-bottom: 3px;
        }
        .settings-card-header p {
            font-size: 12.5px;
            color: var(--text-muted, #6b7d74);
            line-height: 1.4;
        }
        .settings-card label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-dark, #1a2b23);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 8px;
        }
        .settings-card input[type="text"],
        .settings-card input[type="number"],
        .settings-card input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border-soft, #e1e6e3);
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 6px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            background: #fafbfa;
        }
        .settings-card input:focus {
            outline: none;
            border-color: var(--ptc-green, #205e44);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(32,94,68,0.1);
        }
        .input-hint {
            display: block;
            font-size: 11.5px;
            color: var(--text-muted, #6b7d74);
            margin-bottom: 16px;
        }
        .btn-primary {
            background-color: var(--ptc-green, #205e44);
            color: #fff;
            border: none;
            padding: 11px 22px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 8px;
            transition: background-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-primary:hover {
            background-color: var(--ptc-green-dark, #0f3d2a);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 61, 42, 0.25);
        }
        .day-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }
        .day-pill {
            position: relative;
            cursor: pointer;
            margin: 0 !important;
            text-transform: none !important;
            font-weight: 500 !important;
        }
        .day-pill input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .day-pill span {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1.5px solid var(--border-soft, #e1e6e3);
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-muted, #6b7d74);
            background: #fafbfa;
            transition: all 0.15s ease;
        }
        .day-pill input:checked + span {
            background: var(--ptc-green, #205e44);
            border-color: var(--ptc-green, #205e44);
            color: #fff;
            box-shadow: 0 4px 10px rgba(32,94,68,0.25);
        }
        .day-pill:hover span {
            border-color: var(--ptc-green, #205e44);
        }
        .input-icon-wrapper {
            position: relative;
            margin-bottom: 16px;
        }
        .input-icon-wrapper input {
            margin-bottom: 0 !important;
            padding-right: 42px !important;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: var(--text-muted, #6b7d74);
            cursor: pointer;
        }
        .toggle-password:hover {
            color: var(--ptc-green, #205e44);
        }
        .settings-success {
            background-color: #e6f4ea;
            border: 1px solid #b7dfc0;
            color: #1e6b34;
            padding: 11px 16px;
            border-radius: 10px;
            margin: 0 20px 18px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
        }
        .settings-error {
            background-color: #fdeaea;
            border: 1px solid #f0b8b8;
            color: #b33a3a;
            padding: 11px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <header>
            <div class="logo-container">
                <img src="images/logo-ptc 2.png" alt="Logo">
                <h1>Pateros Technological College</h1>
            </div>
            <nav>
                <button class="nav-button" onclick="showDashboard()"><i class='bx bx-grid-alt'></i> Dashboard</button>
                <button class="nav-button" onclick="showAppointments()"><i class='bx bx-calendar-check'></i> Appointments</button>
                <button class="nav-button" onclick="showSettings()"><i class='bx bx-cog'></i> Settings</button>
                <button class="nav-button" onclick="logout()"><i class='bx bx-log-out'></i> Logout</button>
            </nav>
        </header>
        <main>
            <div id="dashboard" class="content">
                <h2>Dashboard</h2>
                <p class="page-subtext">Overview of all appointments. Click any card to see the details.</p>

                <p class="section-label">Status Overview</p>
                <div class="stats-grid">
                    <div class="stat-card" onclick="clearFilters()">
                        <div class="stat-icon" style="background:rgba(32,94,68,0.12); color:#205e44;"><i class='bx bx-list-check'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Total</p>
                            <p class="stat-value"><?php echo $summary_data['total_appointments']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('approved')">
                        <div class="stat-icon" style="background:rgba(33,150,243,0.12); color:#2196F3;"><i class='bx bx-check-circle'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Approved</p>
                            <p class="stat-value"><?php echo $summary_data['approved_appointments']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('pending')">
                        <div class="stat-icon" style="background:rgba(255,193,7,0.18); color:#b98900;"><i class='bx bx-time-five'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Pending</p>
                            <p class="stat-value"><?php echo $summary_data['pending_appointments']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('rejected')">
                        <div class="stat-icon" style="background:rgba(214,69,69,0.12); color:#d64545;"><i class='bx bx-x-circle'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Rejected</p>
                            <p class="stat-value"><?php echo $summary_data['rejected_appointments']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterByStatus('cancelled')">
                        <div class="stat-icon" style="background:rgba(158,158,158,0.18); color:#757575;"><i class='bx bx-block'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Cancelled</p>
                            <p class="stat-value"><?php echo $summary_data['cancelled_appointments']; ?></p>
                        </div>
                    </div>
                </div>

                <p class="section-label">Document Types</p>
                <div class="stats-grid">
                    <div class="stat-card" onclick="filterByDocType('cor')">
                        <div class="stat-icon" style="background:rgba(233,30,99,0.12); color:#E91E63;"><i class='bx bx-file'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">COR</p>
                            <p class="stat-value"><?php echo $summary_data['cor_count']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterByDocType('cog')">
                        <div class="stat-icon" style="background:rgba(156,39,176,0.12); color:#9C27B0;"><i class='bx bx-file-blank'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">COG</p>
                            <p class="stat-value"><?php echo $summary_data['cog_count']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterByDocType('other documents')">
                        <div class="stat-icon" style="background:rgba(255,87,34,0.12); color:#FF5722;"><i class='bx bx-folder'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Other Documents</p>
                            <p class="stat-value"><?php echo $summary_data['other_count']; ?></p>
                        </div>
                    </div>
                </div>

                <p class="section-label">Schedule</p>
                <div class="stats-grid">
                    <div class="stat-card" onclick="filterByDate('today')">
                        <div class="stat-icon" style="background:rgba(0,188,212,0.14); color:#00acc1;"><i class='bx bx-calendar-star'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Today's Appointments</p>
                            <p class="stat-value"><?php echo $summary_data['today_count']; ?></p>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterByDate('week')">
                        <div class="stat-icon" style="background:rgba(63,81,181,0.14); color:#3f51b5;"><i class='bx bx-calendar-week'></i></div>
                        <div class="stat-info">
                            <p class="stat-label">Upcoming This Week</p>
                            <p class="stat-value"><?php echo $summary_data['week_count']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="charts-row">
                    <div class="chart-card">
                        <h3>Status Breakdown</h3>
                        <p class="chart-sub">Distribution ng appointments per status</p>
                        <div class="chart-wrap"><canvas id="statusChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>Document Types</h3>
                        <p class="chart-sub">Pinaka-hinihinging documents</p>
                        <div class="chart-wrap"><canvas id="docChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div id="appointments" class="content" style="display:none;">
                <h2 class="text-2xl font-extrabold text-gray-900 mb-1 px-1">Appointments</h2>
                <p class="text-sm text-gray-500 px-1 mb-4">Manage and review all submitted appointment requests.</p>
                <div class="filter-bar">
                    <select id="statusFilterSelect" class="filter-select" onchange="filterByStatus(this.value)">
                        <option value="">All Statuses</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 border border-gray-200 rounded-full px-4 py-2 mx-1 hover:bg-white hover:border-ptc-green hover:text-ptc-green transition" onclick="clearFilters()"><i class='bx bx-x'></i> Clear Filters</button>
                </div>
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm mx-1 p-5 overflow-x-auto">
                    <table id="appointments-table" class="table table-striped w-full">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Appointment Date</th>
                                <th>Document Type</th>
                                <th>Other Document</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Reviewed At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($app_result->num_rows > 0) {
                                while ($row = $app_result->fetch_assoc()) {
                                    echo "<tr>";
                                    $initial = strtoupper(substr(trim($row['name']), 0, 1)); echo "<td><div class='name-cell'><div class='name-avatar'>" . htmlspecialchars($initial) . "</div>" . htmlspecialchars($row['name']) . "</div></td>";
                                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['appointment_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['document_type']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['other_document']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['message']) . "</td>";
                                    echo "<td><span class='status-badge status-" . htmlspecialchars($row['status']) . "'>" . htmlspecialchars($row['status']) . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row['reviewed_by_name'] ?? '-') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['reviewed_at'] ?? '-') . "</td>";
                                    if ($row['status'] === 'pending') {
                                        echo "<td>
                                                <button class='action-button' onclick='updateStatus(" . $row['id'] . ", \"approved\")'>Approve</button>
                                                <button class='action-button' onclick='updateStatus(" . $row['id'] . ", \"rejected\")'>Reject</button>
                                              </td>";
                                    } else {
                                        echo "<td>&mdash;</td>";
                                    }
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='11'>No appointments found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="settings" class="content" style="display:none;">
                <h2>Settings</h2>
                <p class="page-subtext">Manage system-wide appointment rules and your account security.</p>
                <?php if ($settings_message): ?>
                    <div class="settings-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($settings_message) ?></div>
                <?php endif; ?>

                <div class="settings-grid">
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-card-icon" style="background:rgba(32,94,68,0.12); color:#205e44;"><i class='bx bx-calendar-check'></i></div>
                            <div>
                                <h3>Max Appointments Per Day</h3>
                                <p>Set the maximum number of appointments that can be booked in a single day.</p>
                            </div>
                        </div>
                        <form method="POST" action="save-settings.php">
                            <label for="max_appointments_per_day">Maximum Slots</label>
                            <input type="number" name="max_appointments_per_day" id="max_appointments_per_day" min="1" value="<?= htmlspecialchars($max_per_day) ?>" required>
                            <br>
                            <button type="submit" class="btn-primary"><i class='bx bx-save'></i> Save Setting</button>
                        </form>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-card-icon" style="background:rgba(33,150,243,0.12); color:#2196F3;"><i class='bx bx-calendar-week'></i></div>
                            <div>
                                <h3>Booking Restrictions</h3>
                                <p>Choose which days are open for booking and block out holidays.</p>
                            </div>
                        </div>
                        <form method="POST" action="save-settings.php">
                            <label>Allowed Days for Appointments</label>
                            <div class="day-pills">
                                <?php
                                $days_full = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];
                                foreach ($days_full as $abbr => $full):
                                    $checked = in_array($abbr, $allowed_days_arr) ? 'checked' : '';
                                ?>
                                    <label class="day-pill">
                                        <input type="checkbox" name="allowed_days[]" value="<?= $abbr ?>" <?= $checked ?>>
                                        <span><?= substr($full, 0, 3) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <label for="blocked_dates">Blocked Dates (Holidays)</label>
                            <input type="text" name="blocked_dates" id="blocked_dates" value="<?= htmlspecialchars($blocked_dates_raw) ?>" placeholder="2026-12-25, 2026-01-01">
                            <span class="input-hint">Comma-separated, format YYYY-MM-DD</span>
                            <button type="submit" class="btn-primary"><i class='bx bx-save'></i> Save Booking Restrictions</button>
                        </form>
                    </div>

                    <div class="settings-card">
                        <div class="settings-card-header">
                            <div class="settings-card-icon" style="background:rgba(214,69,69,0.12); color:#d64545;"><i class='bx bx-shield-quarter'></i></div>
                            <div>
                                <h3>Account Security</h3>
                                <p>Update the password for your admin account.</p>
                            </div>
                        </div>
                        <?php if ($password_saved_message): ?>
                            <div class="settings-success"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($password_saved_message) ?></div>
                        <?php endif; ?>
                        <?php if ($password_error_message): ?>
                            <div class="settings-error"><i class='bx bx-error-circle'></i> <?= htmlspecialchars($password_error_message) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="change-password.php">
                            <label for="current_password">Current Password</label>
                            <div class="input-icon-wrapper">
                                <input type="password" name="current_password" id="current_password" required>
                                <i class='bx bx-hide toggle-password' onclick="togglePassword('current_password', this)"></i>
                            </div>
                            <label for="new_password">New Password</label>
                            <div class="input-icon-wrapper">
                                <input type="password" name="new_password" id="new_password" minlength="8" required>
                                <i class='bx bx-hide toggle-password' onclick="togglePassword('new_password', this)"></i>
                            </div>
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-icon-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" minlength="8" required>
                                <i class='bx bx-hide toggle-password' onclick="togglePassword('confirm_password', this)"></i>
                            </div>
                            <button type="submit" class="btn-primary"><i class='bx bx-lock-alt'></i> Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        var dateFilterMode = null;
        var table;

        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (!dateFilterMode) return true;

            var dateStr = data[3];
            var apptDate = new Date(dateStr);
            var today = new Date();
            today.setHours(0, 0, 0, 0);

            if (dateFilterMode === 'today') {
                var d2 = new Date(apptDate);
                d2.setHours(0, 0, 0, 0);
                return d2.getTime() === today.getTime();
            }

            if (dateFilterMode === 'week') {
                var weekLater = new Date(today);
                weekLater.setDate(weekLater.getDate() + 7);
                return apptDate >= today && apptDate <= weekLater;
            }

            return true;
        });

        $(document).ready(function() {
            table = $('#appointments-table').DataTable();
            renderCharts();
        });

        function showDashboard() {
            document.getElementById('dashboard').style.display = 'block';
            document.getElementById('appointments').style.display = 'none';
            document.getElementById('settings').style.display = 'none';
        }

        function showAppointments() {
            document.getElementById('dashboard').style.display = 'none';
            document.getElementById('appointments').style.display = 'block';
            document.getElementById('settings').style.display = 'none';
        }

        function showSettings() {
            document.getElementById('dashboard').style.display = 'none';
            document.getElementById('appointments').style.display = 'none';
            document.getElementById('settings').style.display = 'block';
        }

        function logout() {
            window.location.href = 'logout.php?type=admin';
        }

        function togglePassword(id, icon) {
            var input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bx-hide');
                icon.classList.add('bx-show');
            } else {
                input.type = 'password';
                icon.classList.remove('bx-show');
                icon.classList.add('bx-hide');
            }
        }

        function filterByStatus(status) {
            dateFilterMode = null;
            showAppointments();
            table.column(7).search('^' + status + '$', true, false).draw();
        }

        function filterByDocType(type) {
            dateFilterMode = null;
            showAppointments();
            table.column(4).search('^' + type + '$', true, false).draw();
        }

        function filterByDate(mode) {
            showAppointments();
            table.column(7).search('').draw();
            dateFilterMode = mode;
            table.draw();
        }

        function clearFilters() {
            dateFilterMode = null;
            showAppointments();
            table.search('').columns().search('').draw();
        }

        function updateStatus(id, status) {
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                data: { id: id, status: status },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Error updating status');
                    }
                },
                error: function() {
                    alert('Error updating status');
                }
            });
        }

        function renderCharts() {
            var statusCtx = document.getElementById('statusChart');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Rejected', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo (int) $summary_data['approved_appointments']; ?>,
                            <?php echo (int) $summary_data['pending_appointments']; ?>,
                            <?php echo (int) $summary_data['rejected_appointments']; ?>,
                            <?php echo (int) $summary_data['cancelled_appointments']; ?>
                        ],
                        backgroundColor: ['#2196F3', '#FFC107', '#d64545', '#9e9e9e'],
                        borderWidth: 0
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                    cutout: '65%'
                }
            });

            var docCtx = document.getElementById('docChart');
            new Chart(docCtx, {
                type: 'bar',
                data: {
                    labels: ['COR', 'COG', 'Other Documents'],
                    datasets: [{
                        label: 'Number of Requests',
                        data: [
                            <?php echo (int) $summary_data['cor_count']; ?>,
                            <?php echo (int) $summary_data['cog_count']; ?>,
                            <?php echo (int) $summary_data['other_count']; ?>
                        ],
                        backgroundColor: ['#E91E63', '#9C27B0', '#FF5722'],
                        borderRadius: 6,
                        maxBarThickness: 60
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0f0f0' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
    </script>
</body>
</html>