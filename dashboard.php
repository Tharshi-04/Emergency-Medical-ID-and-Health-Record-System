
<?php
session_start();

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "patient") {
    header("Location: login.php");
    exit;
}

$username = htmlspecialchars($_SESSION["username"] ?? "Patient");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Dashboard | Emergency Medical ID</title>

    <link rel="stylesheet" href="css/style.css">

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

        .patient-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .patient-sidebar {
            width: 250px;
            min-width: 250px;
            background: #102d49;
            color: white;
            padding: 28px 18px;
            display: flex;
            flex-direction: column;
        }

        .sidebar-logo {
    color: white;
    font-size: 27px;
    font-weight: bold;
}

.sidebar-logo span {
    color: #16A6A1;
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
            font-size: 13px;
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
            font-size: 15px;
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
        .patient-main {
            flex: 1;
            min-width: 0;
        }

        /* Top header */
        .patient-topbar {
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
            font-size: 26px;
        }

        .topbar-title p {
            margin: 6px 0 0;
            color: #8291a1;
            font-size: 15px;
        }

        /* Patient profile */
        .patient-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .patient-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #e2f5f2;
            color: #087f72;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .patient-name-small {
            font-size: 16px;
            font-weight: bold;
            color: #263f56;
        }

        .patient-role-small {
            color: #8998a8;
            font-size: 14px;
            margin-top: 4px;
        }

        /* Dashboard content */
        .dashboard-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 35px 45px;
        }

        .welcome-section {
            background: linear-gradient(120deg, #0B2945, #1E5A8A);
            color: white;
            padding: 32px;
            border-radius: 14px;
            margin-bottom: 35px;
        }

        .welcome-section h2 {
            font-size: 28px;
            margin: 0 0 10px;
        }

        .welcome-section p {
            color: #DCE8F2;
            font-size: 16px;
            line-height: 1.7;
            margin: 0;
        }

        /* Section heading */
        .section-heading {
            color: #173b5b;
            font-size: 21px;
            margin: 0 0 20px;
        }

        /* Service cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .dashboard-card {
            background: #ffffff;
            padding: 27px;
            border-radius: 12px;
            border: 1px solid #e5ebf1;
            box-shadow: 0 3px 12px rgba(23, 59, 91, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(18,59,99,0.13);
        }

        .dashboard-icon {
            font-size: 34px;
            margin-bottom: 15px;
        }

        .dashboard-card h3 {
            color: #173b5b;
            font-size: 19px;
            margin: 0 0 12px;
        }

        .dashboard-card p {
            color: #718096;
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 20px;
            min-height: 76px;
        }

        .dashboard-card a {
            display: inline-block;
            background: #16A6A1;
            color: white;
            padding: 11px 17px;
            border-radius: 7px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }

        .dashboard-card a:hover {
            background: #108580;
        }

        /* Footer */
        .dashboard-footer {
            padding: 20px 35px;
            text-align: center;
            color: #8998a8;
            font-size: 13px;
            border-top: 1px solid #e5ebf1;
            background: #ffffff;
        }

        /* Responsive design */
        @media (max-width: 1050px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 750px) {
            .patient-layout {
                flex-direction: column;
            }

            .patient-sidebar {
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
                font-size: 13px;
            }

            .sidebar-menu a.active {
                padding-left: 7px;
            }

            .sidebar-menu .logout-link {
                margin-top: 0;
            }

            .patient-topbar {
                padding: 18px;
            }

            .dashboard-content {
                padding: 25px 18px 35px;
            }

            .dashboard-footer {
                padding: 18px;
            }
        }

        @media (max-width: 600px) {
            .patient-topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome-section {
                padding: 23px;
            }

            .welcome-section h2 {
                font-size: 24px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-card p {
                min-height: auto;
            }

            
        }
        .patient-sidebar .sidebar-brand h2.sidebar-logo {
    font-size: 30px !important;
    font-weight: bold;
    line-height: 1.5;
    margin: 0 0 7px;
}

.patient-sidebar .sidebar-brand h2.sidebar-logo span {
    color: #16A6A1 !important;
}
    </style>
</head>

<body>

<div class="patient-layout">

    <!-- Sidebar -->
    <aside class="patient-sidebar">

        <div class="sidebar-brand">
            <h2 class="sidebar-logo">Medi <span>Scan</span></h2>
            <p>Patient Portal</p>
        </div>

        <nav class="sidebar-menu">

            <a href="dashboard.php" class="active">
                Dashboard
            </a>

            <a href="medical_profile.php">
                Medical Profile
            </a>

            <a href="contacts.php">
                Emergency Contacts
            </a>

            <a href="generate_qr.php">
                Emergency QR Code
            </a>

            <a href="logout.php" class="logout-link">
                Logout
            </a>

        </nav>

    </aside>

    <!-- Main Dashboard -->
    <div class="patient-main">

        <!-- Top Header -->
        <header class="patient-topbar">

            <div class="topbar-title">
                <h1>Patient Dashboard</h1>
                <p>Overview of your personal medical services</p>
            </div>

            <div class="patient-profile">

                <div class="patient-avatar">
                    P
                </div>

                <div>
                    <div class="patient-name-small">
                        <?php echo $username; ?>
                    </div>

                    <div class="patient-role-small">
                        Patient Account
                    </div>
                </div>

            </div>

        </header>

        <main class="dashboard-content">

            <!-- Welcome Section -->
            <section class="welcome-section">

                <h2>
                    Welcome, <?php echo $username; ?>!
                </h2>

                <p>
                    Your personal medical information and emergency services
                    are available from this dashboard.
                </p>

            </section>

            <!-- Services -->
            <section>

                <h2 class="section-heading">Your Services</h2>

                <div class="dashboard-grid">

                    <div class="dashboard-card">

                        <div class="dashboard-icon">🩺</div>

                        <h3>Medical Profile</h3>

                        <p>
                            View and manage your medical details,
                            blood group, allergies and conditions.
                        </p>

                        <a href="medical_profile.php">
                            Manage Profile
                        </a>

                    </div>

                    <div class="dashboard-card">

                        <div class="dashboard-icon">📞</div>

                        <h3>Emergency Contacts</h3>

                        <p>
                            Add and manage the people who should be
                            contacted during an emergency.
                        </p>

                        <a href="contacts.php">
                            Manage Contacts
                        </a>

                    </div>

                    <div class="dashboard-card">

                        <div class="dashboard-icon">📱</div>

                        <h3>Emergency QR Code</h3>

                        <p>
                            Generate and view your emergency medical
                            identification QR code.
                        </p>

                        <a href="generate_qr.php">
                            Generate QR
                        </a>

                    </div>

                </div>

            </section>

        </main>

        

    </div>

</div>

</body>
</html>
