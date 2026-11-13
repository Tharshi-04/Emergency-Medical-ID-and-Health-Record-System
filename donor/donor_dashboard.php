
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'donor') {
    header("Location: ../login.php");
    exit();
}

require_once "../config/db.php";

$user_id = $_SESSION['user_id'];

// Get donor profile
$stmt = $conn->prepare(
    "SELECT donor_id, full_name, blood_group, district
     FROM donor_profiles
     WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .dashboard-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .welcome-section {
            background: linear-gradient(135deg, #123B63, #1E5A8A);
            color: white;
            padding: 30px;
            border-radius: 14px;
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            margin-bottom: 10px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        .dashboard-card h3 {
            color: #123B63;
            margin-bottom: 12px;
        }

        .dashboard-card p {
            color: #718096;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .dashboard-card a {
            display: inline-block;
            background: #123B63;
            color: white;
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 7px;
        }

        .profile-notice {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .dashboard-container {
                padding: 12px;
            }
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h2>Emergency Medical ID</h2>

        <nav class="nav-menu">
            <a href="donor_dashboard.php">Dashboard</a>
            <a href="donor_profile.php">My Profile</a>
            <a href="donor_requests.php">Donation Requests</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="dashboard-container">

    <section class="welcome-section">
        <h1>
            Welcome,
            <?php
            echo htmlspecialchars(
                $donor['full_name'] ?? $_SESSION['username']
            );
            ?>!
        </h1>

        <p>
            Thank you for being part of our donor community.
            Your contribution can help save lives.
        </p>
    </section>

    <?php if (!$donor): ?>
        <div class="profile-notice">
            Your donor profile has not been completed yet.
            Please create your donor profile first.
        </div>
    <?php endif; ?>

    <h2>Donor Dashboard</h2>

    <div class="dashboard-grid">

        <div class="dashboard-card">
            <h3>My Donor Profile</h3>
            <p>
                Add or update your personal information,
                blood group and district.
            </p>
            <a href="donor_profile.php">Manage Profile</a>
        </div>


        <div class="dashboard-card">
            <h3>Donation Requests</h3>
            <p>
                View donation requests and respond when
                you are available to donate.
            </p>
            <a href="donor_requests.php">View Requests</a>
        </div>

    </div>

</main>

<footer class="main-footer">
    <p>© 2026 Emergency Medical ID System</p>
</footer>

</body>
</html>

<?php
$conn->close();
?>
