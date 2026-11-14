<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check hospital role
$roleQuery = $conn->prepare(
    "SELECT role FROM user_roles WHERE user_id = ? AND role = 'hospital'"
);
$roleQuery->bind_param("i", $user_id);
$roleQuery->execute();

if ($roleQuery->get_result()->num_rows === 0) {
    die("Access denied. Hospital account required.");
}

// Get hospital profile
$hospitalQuery = $conn->prepare(
    "SELECT hospital_id FROM hospital_profiles WHERE user_id = ?"
);
$hospitalQuery->bind_param("i", $user_id);
$hospitalQuery->execute();
$hospital = $hospitalQuery->get_result()->fetch_assoc();

$requests = [];

if ($hospital) {
    $requestQuery = $conn->prepare(
        "SELECT *
         FROM hospital_requests
         WHERE hospital_id = ?
         ORDER BY created_at DESC"
    );

    $requestQuery->bind_param("i", $hospital['hospital_id']);
    $requestQuery->execute();
    $result = $requestQuery->get_result();

    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Hospital Requests</title>
    <link rel="stylesheet" href="../css/style.css">

    <style>
        .requests-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .requests-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .requests-card h1 {
            color: #123B63;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #E2E8F0;
            text-align: left;
        }

        th {
            background: #F4F8FC;
            color: #123B63;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .pending {
            background: #FFF2CC;
            color: #805B00;
        }

        .approved {
            background: #DFF5E8;
            color: #167344;
        }

        .rejected, .cancelled {
            background: #FDECEC;
            color: #B42332;
        }

        .in_progress {
            background: #E5F0FF;
            color: #245B9B;
        }

        .completed {
            background: #DDF4F2;
            color: #08766F;
        }

        .create-button {
            display: inline-block;
            background: #123B63;
            color: white;
            padding: 11px 18px;
            border-radius: 7px;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .empty-message {
            background: #F4F8FC;
            padding: 20px;
            border-radius: 8px;
            color: #718096;
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h1>Medi Scan</h1>

        <nav class="nav-menu">
            <a href="hospital_dashboard.php">Dashboard</a>
            <a href="hospital_profile.php">Hospital Profile</a>
            <a href="create_request.php">Create Request</a>
            <a href="hospital_requests.php">My Requests</a>
            <a href="../logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="requests-container">

    <div class="requests-card">

        <h1>My Donation Requests</h1>

        <?php if ($hospital): ?>

            <?php if ($hospital['hospital_id'] > 0): ?>
                <a href="create_request.php" class="create-button">
                    + Create New Request
                </a>
            <?php endif; ?>

            <?php if (count($requests) > 0): ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Type</th>
                                <th>Requirement</th>
                                <th>Required Date</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($requests as $request): ?>

                                <tr>
                                    <td>
                                        #<?php echo (int)$request['request_id']; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(ucfirst($request['request_type'])); ?>
                                    </td>

                                    <td>
                                        <?php if ($request['request_type'] === 'blood'): ?>
                                            Blood Group:
                                            <?php echo htmlspecialchars($request['blood_group'] ?? 'N/A'); ?>
                                            <br>
                                            Units:
                                            <?php echo htmlspecialchars($request['required_units'] ?? 'N/A'); ?>
                                        <?php else: ?>
                                            Organ:
                                            <?php echo htmlspecialchars($request['organ_type'] ?? 'N/A'); ?>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($request['required_date'] ?? 'N/A'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars(ucfirst($request['urgency'])); ?>
                                    </td>

                                    <td>
                                        <?php
                                        $status = $request['request_status'];
                                        ?>
                                        <span class="status <?php echo htmlspecialchars($status); ?>">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($status))); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($request['created_at']); ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty-message">
                    You have not submitted any donation requests yet.
                </div>

            <?php endif; ?>

        <?php else: ?>

            <div class="empty-message">
                Please complete your hospital profile first.
            </div>

            <a href="hospital_profile.php" class="create-button">
                Complete Hospital Profile
            </a>

        <?php endif; ?>

    </div>

</main>


</body>
</html>