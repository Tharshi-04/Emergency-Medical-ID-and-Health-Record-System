
<?php
session_start();

if (!isset($_SESSION["admin_logged_in"]) || $_SESSION["admin_logged_in"] !== true) {
    header("Location: ../admin_login.php");
    exit();
}

require_once "../config/db.php";

// Total Patients
$patientQuery = $conn->query("
    SELECT COUNT(*) AS total
    FROM patient_profiles
");
$totalPatients = $patientQuery->fetch_assoc()["total"] ?? 0;


// Total Donors
$donorQuery = $conn->query("
    SELECT COUNT(*) AS total
    FROM donor_profiles
");
$totalDonors = $donorQuery->fetch_assoc()["total"] ?? 0;


// Total Hospitals and Verification Status
$hospitalQuery = $conn->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(verification_status = 'pending'), 0) AS pending,
        COALESCE(SUM(verification_status = 'verified'), 0) AS verified,
        COALESCE(SUM(verification_status = 'rejected'), 0) AS rejected
    FROM hospital_profiles
");

$hospitalData = $hospitalQuery->fetch_assoc();


// Donation Request Statistics
$requestQuery = $conn->query("
    SELECT
        COUNT(*) AS total,
        COALESCE(SUM(request_status = 'pending'), 0) AS pending,
        COALESCE(SUM(request_status = 'approved'), 0) AS approved,
        COALESCE(SUM(request_status = 'rejected'), 0) AS rejected,
        COALESCE(SUM(request_status = 'completed'), 0) AS completed
    FROM hospital_requests
");

$requestData = $requestQuery->fetch_assoc();


// Total Registered Patients
// Counts all patients because created_at column is not available.
$newPatientQuery = $conn->query("
    SELECT COUNT(*) AS total
    FROM patient_profiles
");

$newPatients = $newPatientQuery->fetch_assoc()["total"] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Medi Scan</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #F4F8FC;
            color: #263746;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */

        .sidebar {
            width: 250px;
            min-height: 100vh;
            background: #0B2945;
            color: white;
            padding: 30px 20px;
            flex-shrink: 0;
        }

        .logo {
            font-size: 27px;
            font-weight: 800;
            margin-bottom: 45px;
            white-space: nowrap;
        }

        .logo span {
            color: #16A6A1;
        }

        .sidebar nav a {
            display: block;
            color: #D5E0EA;
            text-decoration: none;
            padding: 14px 15px;
            margin-bottom: 10px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 500;
            transition: 0.3s;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: #1E5A8A;
            color: white;
        }

        /* MAIN CONTENT */

        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #123B63;
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #718096;
            font-size: 15px;
        }

        /* SUMMARY CARDS */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            margin-bottom: 30px;
        }

        .summary-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            border-left: 4px solid #16A6A1;
            box-shadow: 0 4px 15px rgba(18,59,99,0.06);
            transition: 0.3s;
        }

        .summary-box:hover {
            transform: translateY(-4px);
        }

        .card-icon {
            font-size: 29px;
            margin-bottom: 12px;
        }

        .summary-box p {
            color: #718096;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .summary-box h2 {
            color: #123B63;
            font-size: 32px;
            font-weight: 800;
        }

        /* SECTION COMMON */

        .section {
            background: white;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 15px rgba(18,59,99,0.05);
        }

        .section-header {
            margin-bottom: 22px;
        }

        .section-header h2 {
            color: #123B63;
            font-size: 20px;
            margin-bottom: 6px;
        }

        .section-description {
            color: #718096;
            font-size: 14px;
        }

        /* NOTIFICATIONS */

        .notification-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 17px 0;
            border-bottom: 1px solid #E2E8F0;
        }

        .notification-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .notification-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .notification-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: #EAF4FF;
            font-size: 21px;
            flex-shrink: 0;
        }

        .notification-info h3 {
            color: #263746;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .notification-info p {
            color: #718096;
            font-size: 13px;
        }

        .notification-count {
            min-width: 32px;
            padding: 6px 11px;
            border-radius: 20px;
            text-align: center;
            background: #FFF5DF;
            color: #95620C;
            font-size: 13px;
            font-weight: 700;
        }

        .notification-count.success {
            background: #E6F7EF;
            color: #167347;
        }

        /* MANAGEMENT CARDS */

        .management-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .management-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 3px 12px rgba(18,59,99,0.05);
            transition: 0.3s;
        }

        .management-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 18px rgba(18,59,99,0.10);
        }

        .management-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .management-card h3 {
            color: #123B63;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .management-card p {
            color: #718096;
            font-size: 13px;
            line-height: 1.7;
            min-height: 65px;
            margin-bottom: 18px;
        }

        .manage-btn {
            display: block;
            width: 100%;
            text-align: center;
            background: #123B63;
            color: white;
            padding: 12px 15px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }

        .manage-btn:hover {
            background: #1E5A8A;
        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 1100px) {

            .management-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 900px) {

            .summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 768px) {

            .dashboard {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                padding: 20px;
            }

            .logo {
                margin-bottom: 20px;
            }

            .sidebar nav {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .sidebar nav a {
                margin: 0;
                padding: 10px 12px;
            }

            .main-content {
                padding: 20px;
            }

        }

        @media (max-width: 550px) {

            .summary,
            .management-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .section {
                padding: 18px;
            }

        }

    </style>

</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">
            Medi <span>Scan</span>
        </div>

        <nav>

            <a href="admin_dashboard.php" class="active">
                Dashboard
            </a>

            <a href="verify_hospitals.php">
                Hospitals
            </a>

            <a href="manage_requests.php">
                Requests
            </a>

            <a href="patient_overview.php">
                Patient Monitoring
            </a>

            <a href="../admin_logout.php">
                Logout
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <h1>Dashboard Overview</h1>

            <p>
                Welcome, Admin. Here is your system overview.
            </p>

        </div>


        <!-- SUMMARY CARDS -->

        <div class="summary">

            <div class="summary-box">

                <div class="card-icon">🧑‍⚕️</div>

                <p>Total Patients</p>

                <h2>
                    <?php echo $totalPatients; ?>
                </h2>

            </div>


            <div class="summary-box">

                <div class="card-icon">🩸</div>

                <p>Total Donors</p>

                <h2>
                    <?php echo $totalDonors; ?>
                </h2>

            </div>


            <div class="summary-box">

                <div class="card-icon">🏥</div>

                <p>Total Hospitals</p>

                <h2>
                    <?php echo $hospitalData["total"] ?? 0; ?>
                </h2>

            </div>

        </div>


        <!-- RECENT NOTIFICATIONS -->

        <section class="section">

            <div class="section-header">

                <h2>Recent Notifications</h2>

                <p class="section-description">
                    Important updates that require admin attention.
                </p>

            </div>


            <!-- Hospital Verification Notification -->

            <div class="notification-item">

                <div class="notification-info">

                    <div class="notification-icon">
                        🏥
                    </div>

                    <div>

                        <h3>Pending Hospital Verifications</h3>

                        <p>
                            Hospitals waiting for verification approval.
                        </p>

                    </div>

                </div>

                <span class="notification-count">
                    <?php echo $hospitalData["pending"] ?? 0; ?>
                </span>

            </div>


            <!-- Donation Request Notification -->

            <div class="notification-item">

                <div class="notification-info">

                    <div class="notification-icon">
                        📋
                    </div>

                    <div>

                        <h3>Pending Donation Requests</h3>

                        <p>
                            Donation requests waiting for admin review.
                        </p>

                    </div>

                </div>

                <span class="notification-count">
                    <?php echo $requestData["pending"] ?? 0; ?>
                </span>

            </div>


            <!-- Total Registered Patients Notification -->

            <div class="notification-item">

                <div class="notification-info">

                    <div class="notification-icon">
                        👤
                    </div>

                    <div>

                        <h3>Total Registered Patients</h3>

                        <p>
                            Total patients registered in the system.
                        </p>

                    </div>

                </div>

                <span class="notification-count success">
                    <?php echo $newPatients; ?>
                </span>

            </div>

        </section>


        <!-- MANAGEMENT SECTIONS -->

        <section class="section">

            <div class="section-header">

                <h2>Management Sections</h2>

                <p class="section-description">
                    Manage hospitals, donation requests and patient information.
                </p>

            </div>


            <div class="management-grid">


                <!-- HOSPITAL MANAGEMENT -->

                <div class="management-card">

                    <div class="management-icon">
                        🏥
                    </div>

                    <h3>Hospital Management</h3>

                    <p>
                        View hospital details and manage hospital
                        verification requests.
                    </p>

                    <a href="verify_hospitals.php" class="manage-btn">
                        Manage Hospitals →
                    </a>

                </div>


                <!-- DONATION REQUEST MANAGEMENT -->

                <div class="management-card">

                    <div class="management-icon">
                        🩸
                    </div>

                    <h3>Donation Request Management</h3>

                    <p>
                        View donation requests and update their
                        approval and completion status.
                    </p>

                    <a href="manage_requests.php" class="manage-btn">
                        Manage Requests →
                    </a>

                </div>


                <!-- PATIENT MONITORING -->

                <div class="management-card">

                    <div class="management-icon">
                        👨‍⚕️
                    </div>

                    <h3>Patient Monitoring</h3>

                    <p>
                        View registered patient details and monitor
                        patient information.
                    </p>

                    <a href="patient_overview.php" class="manage-btn">
                        View Patients →
                    </a>

                </div>


            </div>

        </section>

    </main>

</div>

</body>

</html>
