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
    <div class="container">
        <header>
            <div class="logo-container">
                <img src="images/logo-ptc 2.png" alt="Logo">
                <h1>Pateros Technological College</h1>
            </div>
            <nav>
                <button class="nav-button" onclick="window.location.href='Index.php'">Book New Appointment</button>
                <button class="nav-button" onclick="window.location.href='logout.php'">Logout</button>
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

    <script>
        function cancelAppointment(id) {
            if (!confirm("Sigurado ka bang i-cancel ang appointment na ito?")) {
                return;
            }
            fetch("cancel-appointment.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id=" + encodeURIComponent(id)
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(() => {
                alert("May error sa pag-cancel. Subukan ulit.");
            });
        }
    </script>
</body>
</html>
