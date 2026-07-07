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

$settings_message = isset($_GET['settings_saved']) ? "Naka-save na ang bagong setting." : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/dashboard.css">
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
        .settings-form {
            background: #fff;
            border: 1px solid var(--border-soft, #e1e6e3);
            border-radius: 14px;
            padding: 26px;
            max-width: 420px;
            margin: 0 20px;
            box-shadow: 0 2px 10px rgba(15, 61, 42, 0.06);
        }
        .settings-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .settings-form input {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border-soft, #e1e6e3);
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 14px;
        }
        .settings-form button {
            background-color: var(--ptc-green, #205e44);
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        .settings-success {
            background-color: #e6f4ea;
            border: 1px solid #b7dfc0;
            color: #1e6b34;
            padding: 10px 14px;
            border-radius: 8px;
            margin: 0 20px 16px;
            max-width: 420px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo-container">
                <img src="images/logo-ptc 2.png" alt="Logo">
                <h1>Pateros Technological College</h1>
            </div>
            <nav>
                <button class="nav-button" onclick="showDashboard()">Dashboard</button>
                <button class="nav-button" onclick="showAppointments()">Appointments</button>
                <button class="nav-button" onclick="showSettings()">Settings</button>
                <button class="nav-button" onclick="logout()">Logout</button>
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
                <h2>Appointments</h2>
                <button class="clear-filter-btn" onclick="clearFilters()"><i class='bx bx-x'></i> Clear Filters</button>
                <div class="table-card">
                    <table id="appointments-table" class="table table-striped">
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
                                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['appointment_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['document_type']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['other_document']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['message']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
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
                <?php if ($settings_message): ?>
                    <div class="settings-success"><?= htmlspecialchars($settings_message) ?></div>
                <?php endif; ?>
                <form class="settings-form" method="POST" action="save-settings.php">
                    <label for="max_appointments_per_day">Max Appointments Per Day</label>
                    <input type="number" name="max_appointments_per_day" id="max_appointments_per_day" min="1" value="<?= htmlspecialchars($max_per_day) ?>" required>
                    <button type="submit">Save Setting</button>
                </form>
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