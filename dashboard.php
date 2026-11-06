
<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT
        u.username,
        p.profile_id,
        p.full_name,
        p.dob,
        p.gender,
        p.blood_group,
        p.height_cm,
        p.weight_kg,
        (SELECT COUNT(*)
         FROM medical_conditions mc
         WHERE mc.patient_id = p.profile_id) AS condition_count
     FROM users u
     LEFT JOIN patient_profiles p ON u.user_id = p.user_id
     WHERE u.user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$full_name = $user["full_name"] ?? $_SESSION["user"];
$dob = $user["dob"] ?: "Not Added";
$gender = $user["gender"] ?: "Not Added";
$blood_group = $user["blood_group"] ?: "Not Added";
$height = $user["height_cm"] !== null
    ? $user["height_cm"] . " cm" : "Not Added";
$weight = $user["weight_kg"] !== null
    ? $user["weight_kg"] . " kg" : "Not Added";
$condition_count = (int)($user["condition_count"] ?? 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Emergency Medical ID</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .dashboard-welcome {
            background: #DBEAFE;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #BFDBFE;
        }

        .dashboard-welcome h1 {
            color: #1E40AF;
        }

        .dashboard-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin: 20px 0 30px;
        }

        .summary-box {
            background: #FFFFFF;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #BFDBFE;
            box-shadow: 0 4px 15px rgba(59,130,246,0.10);
        }

        .summary-box h3 {
            color: #64748B;
            font-size: 15px;
        }

        .summary-box p {
            color: #0D9488;
            font-size: 19px;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .dashboard-card {
            text-align: center;
            padding: 30px 20px;
        }

        .dashboard-card .card-icon {
            font-size: 35px;
            margin-bottom: 12px;
        }

        .dashboard-card h3 {
            color: #1E40AF;
        }

        .dashboard-card p {
            color: #64748B;
            margin-bottom: 20px;
        }

        .logout-btn {
            background: #0D9488;
            color: white;
        }

        .logout-btn:hover {
            background: #0D9488;
            color: white;
        }
    </style>
</head>

<body>

<nav class="navbar">
    <h2>Emergency Medical ID</h2>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="medical_profile.php">Medical Profile</a>
        <a href="contacts.php">Contacts</a>
        <a href="logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <section class="dashboard-welcome">
        <h1>Welcome, <?php echo htmlspecialchars($full_name); ?>!</h1>
        <p>Manage your personal and emergency medical information.</p>
    </section>

    <h2>My Personal Information</h2>

    <div class="dashboard-summary">

        <div class="summary-box">
            <h3>Full Name</h3>
            <p><?php echo htmlspecialchars($full_name); ?></p>
        </div>

        <div class="summary-box">
            <h3>Date of Birth</h3>
            <p><?php echo htmlspecialchars($dob); ?></p>
        </div>

        <div class="summary-box">
            <h3>Gender</h3>
            <p><?php echo htmlspecialchars($gender); ?></p>
        </div>

        <div class="summary-box">
            <h3>Blood Group</h3>
            <p><?php echo htmlspecialchars($blood_group); ?></p>
        </div>

        <div class="summary-box">
            <h3>Height</h3>
            <p><?php echo htmlspecialchars($height); ?></p>
        </div>

        <div class="summary-box">
            <h3>Weight</h3>
            <p><?php echo htmlspecialchars($weight); ?></p>
        </div>

        <div class="summary-box">
            <h3>Chronic Conditions</h3>
            <p><?php echo $condition_count; ?></p>
        </div>

    </div>

    <h2>Manage Your Information</h2>

    <div class="dashboard-grid">

        <div class="dashboard-card">
            <div class="card-icon">🩺</div>
            <h3>Medical Profile</h3>
            <p>View, add, update and delete your personal and medical information.</p>
            <a href="medical_profile.php" class="btn">Manage Medical Profile</a>
        </div>

        <div class="dashboard-card">
            <div class="card-icon">📞</div>
            <h3>Emergency Contacts</h3>
            <p>Manage your emergency contact details.</p>
            <a href="contacts.php" class="btn">Manage Contacts</a>
        </div>

        <div class="dashboard-card">
            <div class="card-icon">📱</div>
            <h3>QR Code</h3>
            <p>Generate your emergency medical QR code.</p>
            <a href="generate_qr.php" class="btn">Generate QR Code</a>
        </div>

        <div class="dashboard-card">
            <div class="card-icon">❤️</div>
            <h3>Donor Registry</h3>
            <p>Manage your blood and organ donor interest.</p>
            <a href="registry.php" class="btn">View Registry</a>
        </div>

    </div>

    <section class="card">
        <h2>Important Reminder</h2>
        <p>Keep your personal and medical information updated.</p>
    </section>

    <div style="text-align:center; margin:30px 0;">
        <a href="logout.php" class="btn logout-btn">Logout</a>
    </div>

</div>

<footer class="footer">
    <p>&copy; 2026 Emergency Medical ID & Health Record System. All Rights Reserved.</p>
</footer>

</body>
</html>
