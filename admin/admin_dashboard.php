<?php
session_start();
require_once "../config/db.php";

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Only admin users can access this page
$adminQuery = $conn->prepare(
    "SELECT is_admin FROM users WHERE user_id = ?"
);
$adminQuery->bind_param("i", $user_id);
$adminQuery->execute();

$admin = $adminQuery->get_result()->fetch_assoc();

if (!$admin || (int)$admin['is_admin'] !== 1) {
    die("Access denied. Administrator permission required.");
}

// Count hospital verification statuses
$hospitalCounts = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(verification_status = 'pending') AS pending,
        SUM(verification_status = 'verified') AS verified,
        SUM(verification_status = 'rejected') AS rejected
     FROM hospital_profiles"
)->fetch_assoc();

// Count hospital requests
$requestCounts = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(request_status = 'pending') AS pending,
        SUM(request_status = 'approved') AS approved,
        SUM(request_status = 'rejected') AS rejected,
        SUM(request_status = 'completed') AS completed
     FROM hospital_requests"
)->fetch_assoc();

// Count registered users
$userCounts = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(is_admin = 0) AS regular_users,
        SUM(is_admin = 1) AS administrators
     FROM users"
)->fetch_assoc();

// Count patient access requests
$accessCounts = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(request_status = 'pending') AS pending,
        SUM(request_status = 'approved') AS approved,
        SUM(request_status = 'rejected') AS rejected
     FROM patient_access_requests"
)->fetch_assoc();

function displayCount($value) {
    return (int)($value ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        .admin-container {
            max-width: 1150px;
            margin: 40px auto;
            padding: 20px;
        }

        .welcome-card {
            background: #123B63;
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .welcome-card h1 {
            margin-bottom: 10px;
        }

        .section-title {
            color: #123B63;
            margin: 30px 0 18px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            color: #718096;
            font-size: 15px;
            margin-bottom: 12px;
        }

        .stat-card p {
            color: #123B63;
            font-size: 30px;
            font-weight: bold;
        }

        .admin-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .link-card {
            background: white;
            padding: 24px;
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

        .admin-button {
            display: inline-block;
            background: #123B63;
            color: white;
            padding: 10px 16px;
            border-radius: 7px;
            text-decoration: none;
        }

        .admin-button:hover {
            background: #0B2945;
        }

        @media (max-width: 600px) {
            .welcome-card {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h2>Emergency Medical ID</h2>

        <nav class="nav-menu">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="verify_hospitals.php">Hospitals</a>
            <a href="manage_requests.php">Requests</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="admin-container">

    <section class="welcome-card">
        <h1>Welcome to Admin Dashboard</h1>
        <p>
            Manage registered users, hospital verification,
            donation requests and patient medical access.
        </p>
    </section>

    <h2 class="section-title">User Overview</h2>

    <section class="stats-grid">

        <div class="stat-card">
            <h3>Total Registered Users</h3>
            <p><?php echo displayCount($userCounts['total']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Regular Users</h3>
            <p><?php echo displayCount($userCounts['regular_users']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Administrators</h3>
            <p><?php echo displayCount($userCounts['administrators']); ?></p>
        </div>

    </section>

    <h2 class="section-title">Hospital Overview</h2>

    <section class="stats-grid">

        <div class="stat-card">
            <h3>Total Hospitals</h3>
            <p><?php echo displayCount($hospitalCounts['total']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Verification</h3>
            <p><?php echo displayCount($hospitalCounts['pending']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Verified Hospitals</h3>
            <p><?php echo displayCount($hospitalCounts['verified']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Rejected Hospitals</h3>
            <p><?php echo displayCount($hospitalCounts['rejected']); ?></p>
        </div>

    </section>

    <h2 class="section-title">Donation Request Overview</h2>

    <section class="stats-grid">

        <div class="stat-card">
            <h3>Total Requests</h3>
            <p><?php echo displayCount($requestCounts['total']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Requests</h3>
            <p><?php echo displayCount($requestCounts['pending']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Approved Requests</h3>
            <p><?php echo displayCount($requestCounts['approved']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Rejected Requests</h3>
            <p><?php echo displayCount($requestCounts['rejected']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Completed Requests</h3>
            <p><?php echo displayCount($requestCounts['completed']); ?></p>
        </div>

    </section>

    <h2 class="section-title">Patient Medical Access Overview</h2>

    <section class="stats-grid">

        <div class="stat-card">
            <h3>Total Access Requests</h3>
            <p><?php echo displayCount($accessCounts['total']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Pending Access Requests</h3>
            <p><?php echo displayCount($accessCounts['pending']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Approved Access Requests</h3>
            <p><?php echo displayCount($accessCounts['approved']); ?></p>
        </div>

        <div class="stat-card">
            <h3>Rejected Access Requests</h3>
            <p><?php echo displayCount($accessCounts['rejected']); ?></p>
        </div>

    </section>

    <h2 class="section-title">Admin Management</h2>

    <section class="admin-links">

        <div class="link-card">
            <h3>Hospital Verification</h3>
            <p>Review hospital details and verify or reject hospital accounts.</p>
            <a href="verify_hospitals.php" class="admin-button">
                Manage Hospitals
            </a>
        </div>

        <div class="link-card">
            <h3>Donation Requests</h3>
            <p>Review blood and organ requests submitted by hospitals.</p>
            <a href="manage_requests.php" class="admin-button">
                Manage Requests
            </a>
        </div>

        

    </section>

</main>

<footer class="main-footer">
    <p>© <?php echo date("Y"); ?> Emergency Medical ID System</p>
</footer>

</body>
</html>