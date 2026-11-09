
<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit();
}

require_once "config/db.php";

$user_id = $_SESSION['user_id'];
$message = "";
$qr_token = "";

// Get patient profile
$stmt = $conn->prepare(
    "SELECT profile_id, full_name, qr_token
     FROM patient_profiles
     WHERE user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
    header("Location: medical_profile.php");
    exit();
}

// Generate a unique token if one does not exist
if (empty($profile['qr_token'])) {

    $token_created = false;

    for ($attempt = 0; $attempt < 5; $attempt++) {

        $new_token = bin2hex(random_bytes(32));

        $stmt = $conn->prepare(
            "UPDATE patient_profiles
             SET qr_token = ?
             WHERE profile_id = ? AND qr_token IS NULL"
        );

        $stmt->bind_param("si", $new_token, $profile['profile_id']);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            $qr_token = $new_token;
            $token_created = true;
            $stmt->close();
            break;
        }

        $stmt->close();

        // Check whether another request created the token
        $stmt = $conn->prepare(
            "SELECT qr_token FROM patient_profiles WHERE profile_id = ?"
        );
        $stmt->bind_param("i", $profile['profile_id']);
        $stmt->execute();
        $check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($check['qr_token'])) {
            $qr_token = $check['qr_token'];
            $token_created = true;
            break;
        }
    }

    if (!$token_created) {
        $message = "Unable to generate the QR code. Please try again.";
    }

} else {
    $qr_token = $profile['qr_token'];
}

// Create the emergency page URL
if ($qr_token !== "") {
    $emergency_url =
        "http://" . $_SERVER['HTTP_HOST'] .
        rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') .
        "/emergency_view.php?token=" . urlencode($qr_token);

    $qr_image_url =
        "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" .
        urlencode($emergency_url);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Emergency QR Code</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .qr-container {
            max-width: 750px;
            margin: 40px auto;
            padding: 25px;
        }

        .qr-card {
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(18, 59, 99, 0.08);
            text-align: center;
        }

        .qr-card h1 {
            color: #123B63;
            margin-bottom: 12px;
        }

        .qr-image {
            width: 300px;
            max-width: 100%;
            height: auto;
            margin: 20px auto;
            display: block;
            border: 1px solid #e2e8f0;
            padding: 10px;
            border-radius: 10px;
        }

        .qr-note {
            background: #fff7e6;
            color: #805500;
            padding: 14px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }

        .qr-url {
            overflow-wrap: anywhere;
            color: #555;
            font-size: 13px;
            margin: 15px 0;
        }

        .qr-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .qr-actions a,
        .qr-actions button {
            padding: 11px 18px;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .download-btn {
            background: #123B63;
            color: white;
        }

        .back-btn {
            background: #e8f1fa;
            color: #123B63;
        }

        .error-message {
            background: #fde8e8;
            color: #c62828;
            padding: 12px;
            border-radius: 7px;
        }

        @media print {
            header, footer, .qr-note, .qr-actions, .back-link {
                display: none !important;
            }

            .qr-container {
                margin: 0;
            }

            .qr-card {
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <h2>Emergency Medical ID</h2>
        <nav class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="medical_profile.php">Medical Profile</a>
            <a href="contacts.php">Emergency Contacts</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="qr-container">

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

    <div class="qr-card">

        <?php if ($message !== ""): ?>

            <p class="error-message">
                <?php echo htmlspecialchars($message); ?>
            </p>

        <?php elseif ($qr_token !== ""): ?>

            <h1>My Emergency QR Code</h1>

            <p>
                Scan this QR code to view your emergency medical information.
            </p>

            <img
                src="<?php echo htmlspecialchars($qr_image_url); ?>"
                alt="Patient Emergency QR Code"
                class="qr-image"
            >

            <p>
                <strong>
                    <?php echo htmlspecialchars($profile['full_name']); ?>
                </strong>
            </p>

            <div class="qr-note">
                <strong>Privacy and safety:</strong>
                Anyone who scans this QR code may be able to view the
                emergency information displayed on the public emergency page.
                Share it carefully.
            </div>

            <p class="qr-url">
                Emergency page URL:<br>
                <?php echo htmlspecialchars($emergency_url); ?>
            </p>

            <div class="qr-actions">

                <button
                    type="button"
                    class="download-btn"
                    onclick="downloadQR()">
                    Download QR Code
                </button>

                <button
                    type="button"
                    class="back-btn"
                    onclick="window.print()">
                    Print QR Code
                </button>

            </div>

        <?php endif; ?>

    </div>

</main>

<footer class="main-footer">
    <p>© 2026 Emergency Medical ID System</p>
</footer>

<script>
function downloadQR() {
    const imageUrl = <?php echo json_encode($qr_image_url ?? ""); ?>;

    if (!imageUrl) {
        alert("QR code is not available.");
        return;
    }

    fetch(imageUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error("Unable to download QR code.");
            }
            return response.blob();
        })
        .then(blob => {
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement("a");

            link.href = objectUrl;
            link.download = "Emergency_Medical_QR.png";

            document.body.appendChild(link);
            link.click();
            link.remove();

            URL.revokeObjectURL(objectUrl);
        })
        .catch(() => {
            alert("Download failed. Please try printing the QR code instead.");
        });
}
</script>

</body>
</html>
