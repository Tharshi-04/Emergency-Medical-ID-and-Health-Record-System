
<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'donor') {
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
<title>Donation Requests</title>
<link rel="stylesheet" href="../css/style.css">

<style>
.container {
    max-width: 1000px;
    margin: 35px auto;
    padding: 20px;
}
.request-card {
    background: white;
    padding: 23px;
    margin-bottom: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.07);
}
h1, h2 { color: #123B63; }
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
.pending { background: #FFF2CC; color: #805B00; }
.accepted { background: #DFF5E8; color: #167344; }
.rejected { background: #FDECEC; color: #B42332; }
button {
    padding: 10px 17px;
    border: none;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    margin-right: 8px;
}
.accept { background: #168653; }
.reject { background: #DC3545; }
.message {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }
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

<main class="container">

<h1>My Donation Requests</h1>
<p>View and respond to requests sent by the administrator.</p>

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

<footer class="main-footer">
    <p>© <?php echo date("Y"); ?> Emergency Medical ID System</p>
</footer>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
