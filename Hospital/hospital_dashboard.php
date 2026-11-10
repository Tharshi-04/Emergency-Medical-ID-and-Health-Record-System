<?php
session_start();
require_once "../config/db.php";

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check hospital role
$user_id = (int) $_SESSION['user_id'];

$roleQuery = $conn->prepare(
    "SELECT role FROM user_roles WHERE user_id = ? AND role = 'hospital'"
);
$roleQuery->bind_param("i", $user_id);
$roleQuery->execute();
$roleResult = $roleQuery->get_result();

if ($roleResult->num_rows === 0) {
    die("Access denied. Hospital account required.");
}

// Get hospital details
$hospitalQuery = $conn->prepare(
    "SELECT * FROM hospital_profiles WHERE user_id = ?"
);
$hospitalQuery->bind_param("i", $user_id);
$hospitalQuery->execute();
$hospitalResult = $hospitalQuery->get_result();
$hospital = $hospitalResult->fetch_assoc();

if (!$hospital) {
    $hospitalName = "Complete Your Hospital Profile";
    $hospitalId = 0;
    $verificationStatus = "Profile not completed";
} else {
    $hospitalName = $hospital['hospital_name'];
    $hospitalId = $hospital['hospital_id'];
    $verificationStatus = ucfirst($hospital['verification_status']);
}

// Request counts
$totalRequests = 0;
$pendingRequests = 0;
$approvedRequests = 0;
$completedRequests = 0;

if ($hospitalId > 0) {
    $countQuery = $conn->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(request_status = 'pending') AS pending,
            SUM(request_status = 'approved') AS approved,
            SUM(request_status = 'completed') AS completed
         FROM hospital_requests
         WHERE hospital_id = ?"
    );

    $countQuery->bind_param("i", $hospitalId);
    $countQuery->execute();
    $counts = $countQuery->get_result()->fetch_assoc();

    $totalRequests = $counts['total'] ?? 0;
    $pendingRequests = $counts['pending'] ?? 0;
    $approvedRequests = $counts['approved'] ?? 0;
    $completedRequests = $counts['completed'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Hospital Dashboard</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .dashboard-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .welcome-card {
            background: #123B63;
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .welcome-card h1 {
            margin-bottom: 10px;
        }

        .status {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 20px;
            background: #fff;
            color: #123B63;
            font-weight: bold;
            margin-top: 12px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            border: 1px solid #E2E8F0;
        }

        .stat-card h3 {
            color: #718096;
            font-size: 15px;
        }

        .stat-card p {
            font-size: 30px;
            font-weight: bold;
            color: #123B63;
            margin-top: 12px;
        }

        .dashboard-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .link-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .link-card h3 {
            color: #123B63;
            margin-bottom: 10px;
        }

        .link-card p {
            color: #718096;
            margin-bottom: 18px;
        }

        .dashboard-button {
            display: inline-block;
            background: #123B63;
            color: white;
            padding: 10px 18px;
            border-radius: 7px;
            text-decoration: none;
        }

        .dashboard-button:hover {
            background: #0B2945;
        }

        .notice {
            background: #FFF7E6;
            border: 1px solid #E9A23B;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #795313;
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">

        <h2>Emergency Medical ID</h2>

        <nav class="nav-menu">
            <a href="hospital_dashboard.php">Dashboard</a>
            <a href="hospital_profile.php">Hospital Profile</a>
            <a href="create_request.php">Create Request</a>
            <a href="hospital_requests.php">My Requests</a>
            <a href="accepted_donors.php">Accepted Donors</a>
            <a href="../logout.php">Logout</a>
        </nav>

    </div>
</header>

<main class="dashboard-container">

    <section class="welcome-card">

        <h1>
            Welcome, <?php echo htmlspecialchars($hospitalName); ?>
        </h1>

        <p>Manage your hospital profile and blood or organ requests.</p>

        <span class="status">
            Verification: <?php echo htmlspecialchars($verificationStatus); ?>
        </span>

    </section>

    <?php if (!$hospital): ?>

        <div class="notice">
            Please complete your hospital profile before creating requests.
        </div>

    <?php elseif ($hospital['verification_status'] !== 'verified'): ?>

        <div class="notice">
            Your hospital account is not verified yet.
            You may need to wait for administrator verification before submitting requests.
        </div>

    <?php endif; ?>

    <section class="stats-grid">

        <div class="stat-card">
            <h3>Total Requests</h3>
            <p><?php echo $totalRequests; ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Requests</h3>
            <p><?php echo $pendingRequests; ?></p>
        </div>

        <div class="stat-card">
            <h3>Approved Requests</h3>
            <p><?php echo $approvedRequests; ?></p>
        </div>

        <div class="stat-card">
            <h3>Completed Requests</h3>
            <p><?php echo $completedRequests; ?></p>
        </div>

    </section>

    <section class="dashboard-links">

        <div class="link-card">
            <h3>Hospital Profile</h3>
            <p>View and update your hospital information.</p>

            <a href="hospital_profile.php" class="dashboard-button">
                Manage Profile
            </a>
        </div>

        <div class="link-card">
            <h3>Create New Request</h3>
            <p>Submit a blood or organ donation request.</p>

            <a href="create_request.php" class="dashboard-button">
                Create Request
            </a>
        </div>

        <div class="link-card">
            <h3>My Requests</h3>
            <p>View the status of your submitted requests.</p>

            <a href="hospital_requests.php" class="dashboard-button">
                View Requests
            </a>
        </div>

        <div class="link-card">
            <h3>Accepted Donors</h3>
            <p>
                View the name, phone number and email of donors
                who accepted your donation requests and consented
                to contact sharing.
            </p>

            <a href="accepted_donors.php" class="dashboard-button">
                View Accepted Donors
            </a>
        </div>

    </section>

</main>

<footer class="main-footer">
    <p>
        © <?php echo date("Y"); ?> Emergency Medical ID System
    </p>
</footer>

</body>
</html>