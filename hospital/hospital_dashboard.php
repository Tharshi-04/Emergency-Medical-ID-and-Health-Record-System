
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
    $statusClass = "not-completed";
} else {
    $hospitalName = $hospital['hospital_name'];
    $hospitalId = $hospital['hospital_id'];
    $verificationStatus = ucfirst($hospital['verification_status']);
    $statusClass = strtolower($hospital['verification_status']);
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

    <title>Hospital Dashboard | Emergency Medical ID</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #263548;
        }

        .hospital-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .hospital-sidebar {
            width: 250px;
            min-width: 250px;
            background: #102d49;
            color: white;
            padding: 28px 18px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 0 12px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .sidebar-brand h2 {
            font-size: 21px;
            margin: 0 0 7px;
            line-height: 1.4;
        }

        .sidebar-brand p {
            margin: 0;
            color: #a9c2d8;
            font-size: 12px;
        }

        .sidebar-menu {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .sidebar-menu a {
            display: block;
            padding: 13px 14px;
            color: #dce8f2;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #1e496b;
            color: #ffffff;
        }

        .sidebar-menu a.active {
            border-left: 4px solid #35c6b0;
            padding-left: 10px;
        }

        .sidebar-menu .logout-link {
            margin-top: 18px;
            color: #ffc4c4;
        }

        .sidebar-menu .logout-link:hover {
            background: #693b45;
            color: white;
        }

        /* Main content */
        .hospital-main {
            flex: 1;
            min-width: 0;
        }

        /* Top header */
        .hospital-topbar {
            min-height: 82px;
            background: #ffffff;
            padding: 18px 35px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 1px solid #e5ebf1;
        }

        .topbar-title h1 {
            margin: 0;
            color: #173b5b;
            font-size: 23px;
        }

        .topbar-title p {
            margin: 6px 0 0;
            color: #8291a1;
            font-size: 13px;
        }

        /* Simple hospital profile in top right */
        .hospital-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hospital-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #e2f5f2;
            color: #087f72;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 17px;
        }

        .hospital-name-small {
            font-size: 14px;
            font-weight: bold;
            color: #263f56;
            max-width: 220px;
            overflow-wrap: anywhere;
        }

        .hospital-role-small {
            color: #8998a8;
            font-size: 12px;
            margin-top: 4px;
        }

        /* Dashboard content */
        .dashboard-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 35px 45px;
        }

        .welcome-section {
            margin-bottom: 28px;
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e5ebf1;
            box-shadow: 0 3px 12px rgba(23, 59, 91, 0.04);
        }

        .welcome-section h2 {
            color: #173b5b;
            font-size: 28px;
            margin: 0 0 9px;
        }

        .welcome-section p {
            color: #75869a;
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }

        /* Verification status badge */
        .verification-info {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .verification-label {
            color: #718297;
            font-size: 13px;
            font-weight: 600;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.verified {
            background: #e4f7ee;
            color: #16794c;
        }

        .status.pending {
            background: #fff3d9;
            color: #94620b;
        }

        .status.rejected {
            background: #fdeaea;
            color: #b42318;
        }

        .status.not-completed {
            background: #edf1f5;
            color: #526477;
        }

        /* Notices */
        .notice {
            padding: 15px 18px;
            border-radius: 9px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.6;
        }

        .notice.warning {
            background: #fff7e6;
            border: 1px solid #f0d18b;
            color: #795313;
        }

        /* Statistics */
        .section-heading {
            color: #173b5b;
            font-size: 18px;
            margin: 0 0 17px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: #ffffff;
            padding: 23px;
            border-radius: 12px;
            border: 1px solid #e5ebf1;
            box-shadow: 0 3px 12px rgba(23, 59, 91, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 18px rgba(23, 59, 91, 0.09);
        }

        .stat-card h3 {
            color: #718297;
            font-size: 13px;
            font-weight: 600;
            margin: 0 0 14px;
        }

        .stat-card p {
            color: #173b5b;
            font-size: 30px;
            font-weight: bold;
            margin: 0;
        }

        .stat-card.total {
            border-top: 4px solid #24618c;
        }

        .stat-card.pending {
            border-top: 4px solid #e5a52e;
        }

        .stat-card.approved {
            border-top: 4px solid #25a887;
        }

        .stat-card.completed {
            border-top: 4px solid #6686c4;
        }

        /* Action cards */
        .dashboard-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .link-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e5ebf1;
            box-shadow: 0 3px 12px rgba(23, 59, 91, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .link-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 18px rgba(23, 59, 91, 0.09);
        }

        .link-card h3 {
            color: #173b5b;
            font-size: 17px;
            margin: 0 0 10px;
        }

        .link-card p {
            color: #78899b;
            font-size: 13px;
            line-height: 1.7;
            margin: 0 0 20px;
        }

        .dashboard-button {
            display: inline-block;
            background: #173f60;
            color: white;
            padding: 10px 17px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .dashboard-button:hover {
            background: #087f72;
        }

        /* Footer */
        .dashboard-footer {
            padding: 20px 35px;
            text-align: center;
            color: #8998a8;
            font-size: 12px;
            border-top: 1px solid #e5ebf1;
            background: #ffffff;
        }

        /* Responsive design */
        @media (max-width: 1050px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 750px) {
            .hospital-layout {
                flex-direction: column;
            }

            .hospital-sidebar {
                width: 100%;
                min-width: 0;
                padding: 18px;
            }

            .sidebar-brand {
                padding-bottom: 15px;
            }

            .sidebar-menu {
                margin-top: 15px;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 7px;
            }

            .sidebar-menu a {
                padding: 11px;
                font-size: 12px;
            }

            .sidebar-menu a.active {
                padding-left: 7px;
            }

            .sidebar-menu .logout-link {
                margin-top: 0;
            }

            .hospital-topbar {
                padding: 18px;
            }

            .dashboard-content {
                padding: 25px 18px 35px;
            }

            .dashboard-links {
                grid-template-columns: 1fr;
            }

            .dashboard-footer {
                padding: 18px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .hospital-topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome-section h2 {
                font-size: 21px;
            }

            .stat-card {
                padding: 20px;
            }
        }
        .hospital-sidebar .sidebar-brand h2.sidebar-logo {
            font-size: 30px !important;
            font-weight: bold;
            color: white;
            margin: 0 0 7px;
            line-height: 1.5;
        }

        .hospital-sidebar .sidebar-brand h2.sidebar-logo span {
            color: #16A6A1 !important;
        }
    </style>
</head>

<body>

<div class="hospital-layout">

    <!-- Sidebar -->
    <aside class="hospital-sidebar">

        <div class="sidebar-brand">
            <h2 class="sidebar-logo">Medi <span>Scan</span></h2>
            <p>Hospital Portal</p>
        </div>

        <nav class="sidebar-menu">

            <a href="hospital_dashboard.php" class="active">
                Dashboard
            </a>

            <a href="hospital_profile.php">
                Hospital Profile
            </a>

            <a href="create_request.php">
                Create New Request
            </a>

            <a href="hospital_requests.php">
                My Requests
            </a>

            <a href="accepted_donors.php">
                Accepted Donors
            </a>

            <a href="../logout.php" class="logout-link">
                Logout
            </a>

        </nav>

    </aside>

    <!-- Main Dashboard -->
    <div class="hospital-main">

        <!-- Top Header -->
        <header class="hospital-topbar">

            <div class="topbar-title">
                <h1>Hospital Dashboard</h1>
                <p>Overview of your hospital activities</p>
            </div>

            <!-- Hospital name only: verification badge removed from here -->
            <div class="hospital-profile">

                <div class="hospital-avatar">
                    H
                </div>

                <div>
                    <div class="hospital-name-small">
                        <?php echo htmlspecialchars($hospitalName); ?>
                    </div>

                    <div class="hospital-role-small">
                        Hospital Account
                    </div>
                </div>

            </div>

        </header>

        <main class="dashboard-content">

            <!-- Welcome Section -->
            <section class="welcome-section">

                <h2>
                    Welcome, <?php echo htmlspecialchars($hospitalName); ?>!
                </h2>

                <p>
                    Manage your hospital profile, submit donation requests,
                    and monitor your request activities from one place.
                </p>

                <div class="verification-info">
                    <span class="verification-label">
                        Account Verification:
                    </span>

                    <span class="status <?php echo htmlspecialchars($statusClass); ?>">
                        <?php echo htmlspecialchars($verificationStatus); ?>
                    </span>
                </div>

            </section>

            <!-- Verification Notices -->
            <?php if (!$hospital): ?>

                <div class="notice warning">
                    Please complete your hospital profile before creating requests.
                </div>

            <?php elseif ($hospital['verification_status'] !== 'verified'): ?>

                <div class="notice warning">
                    Your hospital account is not verified yet.
                    You may need to wait for administrator verification
                    before submitting requests.
                </div>

            <?php endif; ?>

            <!-- Statistics -->
            <section>

                <h2 class="section-heading">Request Overview</h2>

                <div class="stats-grid">

                    <div class="stat-card total">
                        <h3>Total Requests</h3>
                        <p><?php echo (int) $totalRequests; ?></p>
                    </div>

                    <div class="stat-card pending">
                        <h3>Pending Requests</h3>
                        <p><?php echo (int) $pendingRequests; ?></p>
                    </div>

                    <div class="stat-card approved">
                        <h3>Approved Requests</h3>
                        <p><?php echo (int) $approvedRequests; ?></p>
                    </div>

                    <div class="stat-card completed">
                        <h3>Completed Requests</h3>
                        <p><?php echo (int) $completedRequests; ?></p>
                    </div>

                </div>

            </section>

            <!-- Management Actions -->
            <section>

                <h2 class="section-heading">Hospital Management</h2>

                <div class="dashboard-links">

                    <div class="link-card">
                        <h3>Hospital Profile</h3>

                        <p>
                            View and update your hospital information.
                        </p>

                        <a href="hospital_profile.php" class="dashboard-button">
                            Manage Profile
                        </a>
                    </div>

                    <div class="link-card">
                        <h3>Create New Request</h3>

                        <p>
                            Submit a blood or organ donation request.
                        </p>

                        <a href="create_request.php" class="dashboard-button">
                            Create Request
                        </a>
                    </div>

                    <div class="link-card">
                        <h3>My Requests</h3>

                        <p>
                            View and monitor the status of your submitted requests.
                        </p>

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

                </div>

            </section>

        </main>

        

    </div>

</div>

</body>
</html>
