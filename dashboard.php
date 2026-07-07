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
                SUM(CASE WHEN document_type = 'cor' THEN 1 ELSE 0 END) as cor_count,
                SUM(CASE WHEN document_type = 'cog' THEN 1 ELSE 0 END) as cog_count,
                SUM(CASE WHEN document_type = 'other documents' THEN 1 ELSE 0 END) as other_count
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
            font-size: 35px;
            margin-left: 20px;
        }
        .settings-form {
            background: #fff;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            padding: 24px;
            max-width: 420px;
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
            border: 1.5px solid var(--border-soft);
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 14px;
        }
        .settings-form button {
            background-color: var(--ptc-green);
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
            margin-bottom: 16px;
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
                <h2>DASHBOARD</h2>
                <div class="summary-boxes-container">
                    <div class="summary-box total-appointments">
                        <p>Total Appointments: <?php echo $summary_data['total_appointments']; ?></p>
                    </div>
                    <div class="summary-box approved-appointments">
                        <p>Approved Appointments: <?php echo $summary_data['approved_appointments']; ?></p>
                    </div>
                    <div class="summary-box pending-appointments">
                        <p>Pending Appointments: <?php echo $summary_data['pending_appointments']; ?></p>
                    </div>
                    <div class="summary-box cor-documents">
                        <p>COR: <?php echo $summary_data['cor_count']; ?></p>
                    </div>
                    <div class="summary-box cog-documents">
                        <p>COG: <?php echo $summary_data['cog_count']; ?></p>
                    </div>
                    <div class="summary-box other-documents">
                        <p>Other Documents: <?php echo $summary_data['other_count']; ?></p>
                    </div>
                </div>
            </div>
            <div id="appointments" class="content" style="display:none;">
                <h2>APPOINTMENTS</h2>
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
            <div id="settings" class="content" style="display:none;">
                <h2>SETTINGS</h2>
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
    <script>
        $(document).ready(function() {
            $('#appointments-table').DataTable();
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
    </script>
</body>
</html>
