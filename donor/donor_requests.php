
<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'donor') 
    {
        header("Location: ../login.php");
        exit();
    }

$user_id = (int)$_SESSION['user_id'];

// Get donor ID
$stmt = $conn->prepare(
    "SELECT donor_id FROM donor_profiles WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$donor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$donor) {
    die("Please complete your donor profile first.");
}

$donor_id = (int)$donor['donor_id'];

$success = "";
$error = "";

// Accept or reject a request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $response_id = filter_input(
        INPUT_POST, 'response_id', FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? '';

    if (!$response_id ||
        !in_array($action, ['accepted', 'rejected'], true)) {

        $error = "Invalid response.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE donor_request_responses
             SET response_status = ?, responded_at = NOW()
             WHERE response_id = ?
               AND donor_id = ?
               AND response_status = 'pending'"
        );

        $stmt->bind_param(
            "sii",
            $action,
            $response_id,
            $donor_id
        );

        if ($stmt->execute() && $stmt->affected_rows === 1) {
            $success = $action === 'accepted'
                ? "You accepted the donation request."
                : "You rejected the donation request.";
        } else {
            $error = "Request not found or already responded.";
        }

        $stmt->close();
    }
}

// Get requests sent to this donor
$stmt = $conn->prepare(
    "SELECT
        drr.response_id,
        drr.response_status,
        drr.responded_at,
        hr.request_id,
        hr.request_type,
        hr.blood_group,
        hr.required_units,
        hr.organ_type,
        hr.required_date,
        hr.urgency,
        hr.description,
        hr.created_at,
        hp.hospital_name,
        hp.district AS hospital_district
     FROM donor_request_responses drr
     INNER JOIN hospital_requests hr
        ON drr.request_id = hr.request_id
     INNER JOIN hospital_profiles hp
        ON hr.hospital_id = hp.hospital_id
     WHERE drr.donor_id = ?
     ORDER BY drr.response_id DESC"
);

$stmt->bind_param("i", $donor_id);
$stmt->execute();
$requests = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Donation Requests | Medi Scan</title>

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

    .donor-layout {
        display: flex;
        min-height: 100vh;
    }

    /* Sidebar */
    .donor-sidebar {
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

    /* Medi Scan Logo */
    .donor-sidebar .sidebar-brand h2.sidebar-logo {
        font-size: 30px !important;
        font-weight: bold;
        color: white;
        margin: 0 0 7px;
        line-height: 1.5;
    }

    .donor-sidebar .sidebar-brand h2.sidebar-logo span {
        color: #16A6A1 !important;
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
    .donor-main {
        flex: 1;
        min-width: 0;
    }

    /* Top header */
    .donor-topbar {
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

    /* Donor profile */
    .donor-profile {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .donor-avatar {
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

    .donor-name-small {
        font-size: 16px;
        font-weight: bold;
        color: #263f56;
    }

    .donor-role-small {
        color: #8998a8;
        font-size: 14px;
        margin-top: 4px;
    }

    /* Request page content */
    .container {
        max-width: 1000px;
        margin: 35px auto;
        padding: 20px;
    }

    h1, h2 {
        color: #123B63;
    }

    .container > h1 {
        font-size: 27px;
    }

    .container > p {
        color: #718096;
        font-size: 15px;
        line-height: 1.6;
    }

    .request-card {
        background: white;
        padding: 23px;
        margin-bottom: 20px;
        border-radius: 10px;
        box-shadow: 0 3px 12px rgba(0,0,0,.07);
    }

    .details {
        line-height: 1.9;
        margin: 15px 0;
    }

    .status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: bold;
        text-transform: capitalize;
    }

    .pending {
        background: #FFF2CC;
        color: #805B00;
    }

    .accepted {
        background: #DFF5E8;
        color: #167344;
    }

    .rejected {
        background: #FDECEC;
        color: #B42332;
    }

    button {
        padding: 10px 17px;
        border: none;
        border-radius: 6px;
        color: white;
        cursor: pointer;
        margin-right: 8px;
    }

    .accept {
        background: #168653;
    }

    .reject {
        background: #DC3545;
    }

    .message {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
    }

    .success {
        background: #d4edda;
        color: #155724;
    }

    .error {
        background: #f8d7da;
        color: #721c24;
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
    @media (max-width: 750px) {

        .donor-layout {
            flex-direction: column;
        }

        .donor-sidebar {
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

        .donor-topbar {
            padding: 18px;
        }

        .container {
            padding: 20px 18px;
        }

        .dashboard-footer {
            padding: 18px;
        }
    }

    @media (max-width: 600px) {

        .donor-topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .request-card {
            padding: 18px;
        }
    }
</style>
</head>

<body>

<div class="donor-layout">

    <!-- Sidebar -->
    <aside class="donor-sidebar">

        <div class="sidebar-brand">

            <h2 class="sidebar-logo">Medi <span>Scan</span></h2>

            <p>Donor Portal</p>

        </div>

        <nav class="sidebar-menu">

            <a href="donor_dashboard.php">
                Dashboard
            </a>

            <a href="donor_profile.php">
                My Profile
            </a>

            <a href="donor_requests.php" class="active">
                Donation Requests
            </a>

            <a href="../logout.php" class="logout-link">
                Logout
            </a>

        </nav>

    </aside>

    <!-- Main Content -->
    <div class="donor-main">

        <!-- Top Header -->
        <header class="donor-topbar">

            <div class="topbar-title">
                <h1>Donation Requests</h1>
                <p>View and respond to requests sent by the administrator</p>
            </div>

            <div class="donor-profile">

                <div class="donor-avatar">
                    D
                </div>

                <div>
                    <div class="donor-name-small">
                        Donor Account
                    </div>

                    <div class="donor-role-small">
                        Donor Portal
                    </div>
                </div>

            </div>

        </header>

        <main class="container">

            <h1>My Donation Requests</h1>

            <p>
                View and respond to requests sent by the administrator.
            </p>

            <?php if ($success): ?>
                <div class="message success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($requests->num_rows > 0): ?>

                <?php while ($request = $requests->fetch_assoc()): ?>

                    <section class="request-card">

                        <h2>
                            <?php echo htmlspecialchars(ucfirst($request['request_type'])); ?>
                            Donation Request
                        </h2>

                        <div class="details">

                            <strong>Hospital:</strong>
                            <?php echo htmlspecialchars($request['hospital_name']); ?><br>

                            <strong>Hospital District:</strong>
                            <?php echo htmlspecialchars($request['hospital_district']); ?><br>

                            <?php if ($request['request_type'] === 'blood'): ?>

                                <strong>Blood Group:</strong>
                                <?php echo htmlspecialchars($request['blood_group'] ?? 'N/A'); ?><br>

                                <strong>Required Units:</strong>
                                <?php echo htmlspecialchars($request['required_units'] ?? 'N/A'); ?><br>

                            <?php else: ?>

                                <strong>Required Organ:</strong>
                                <?php echo htmlspecialchars($request['organ_type'] ?? 'N/A'); ?><br>

                            <?php endif; ?>

                            <strong>Required Date:</strong>
                            <?php echo htmlspecialchars($request['required_date']); ?><br>

                            <strong>Urgency:</strong>
                            <?php echo htmlspecialchars($request['urgency']); ?><br>

                            <strong>Description:</strong>
                            <?php echo htmlspecialchars($request['description']); ?><br>

                            <strong>Status:</strong>

                            <span class="status <?php echo htmlspecialchars($request['response_status']); ?>">
                                <?php echo htmlspecialchars(ucfirst($request['response_status'])); ?>
                            </span>

                        </div>

                        <?php if ($request['response_status'] === 'pending'): ?>

                            <form method="POST">

                                <input type="hidden" name="response_id"
                                       value="<?php echo (int)$request['response_id']; ?>">

                                <button type="submit" name="action"
                                        value="accepted" class="accept"
                                        onclick="return confirm('Accept this donation request?')">
                                    Accept
                                </button>

                                <button type="submit" name="action"
                                        value="rejected" class="reject"
                                        onclick="return confirm('Reject this donation request?')">
                                    Reject
                                </button>

                            </form>

                        <?php else: ?>

                            <p>You have already responded to this request.</p>

                            <?php if ($request['responded_at']): ?>
                                <small>
                                    Responded at:
                                    <?php echo htmlspecialchars($request['responded_at']); ?>
                                </small>
                            <?php endif; ?>

                        <?php endif; ?>

                    </section>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="request-card">
                    No donation requests have been sent to you yet.
                </div>

            <?php endif; ?>

        </main>

        

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
