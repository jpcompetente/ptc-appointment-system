<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'apt_db');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

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

$app_sql = "SELECT id, name, email, phone, appointment_date, document_type, other_document, message, status FROM appointments";
$app_result = $conn->query($app_sql);

$conn->close();
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
                                echo "<td>
                                        <button class='action-button' onclick='updateStatus(" . $row['id'] . ", \"approved\")'>Approve</button>
                                        <button class='action-button' onclick='updateStatus(" . $row['id'] . ", \"rejected\")'>Reject</button>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='9'>No appointments found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
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
        }

        function showAppointments() {
            document.getElementById('dashboard').style.display = 'none';
            document.getElementById('appointments').style.display = 'block';
        }

        function logout() {
            window.location.href = 'logout.php';
        }

        function updateStatus(id, status) {
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                data: { id: id, status: status },
                success: function(response) {
                    alert(response);
                    location.reload();
                },
                error: function(error) {
                    alert('Error updating status');
                }
            });
        }
    </script>
</body>
</html>
