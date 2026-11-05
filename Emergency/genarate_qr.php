
<?php
session_start();

require_once "config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

// Get patient profile using correct primary key
$stmt = $conn->prepare(
    "SELECT profile_id, full_name, blood_group, qr_token
     FROM patient_profiles
     WHERE user_id = ?"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
    die("Medical profile not found. Please contact the administrator.");
}

$profile_id = $profile["profile_id"];
$full_name = $profile["full_name"];
$blood_group = $profile["blood_group"];
$qr_token = $profile["qr_token"];

// Generate token if it is missing
if (empty($qr_token)) {
    $qr_token = bin2hex(random_bytes(16));

    $stmt = $conn->prepare(
        "UPDATE patient_profiles
         SET qr_token = ?
         WHERE profile_id = ?"
    );

    $stmt->bind_param("si", $qr_token, $profile_id);
    $stmt->execute();
    $stmt->close();
}

// Build emergency profile URL
$emergency_url = "http://" . $_SERVER["HTTP_HOST"]
    . dirname($_SERVER["SCRIPT_NAME"])
    . "/emergency_view.php?token="
    . urlencode($qr_token);

// Remove duplicate slash from URL
$emergency_url = str_replace("\\", "/", $emergency_url);
$emergency_url = preg_replace("#(?<!:)//+#", "/", $emergency_url);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Generate QR Code</title>

    <link rel="stylesheet" href="css/style.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        body {
            background: #EFF6FF;
            color: #1E293B;
            font-family: Arial, sans-serif;
        }

        .qr-container {
            max-width: 750px;
            margin: 40px auto;
            padding: 20px;
        }

        .qr-card {
            background: #FFFFFF;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.10);
            text-align: center;
        }

        .qr-card h1 {
            color: #1D4ED8;
            margin-bottom: 12px;
        }

        .qr-card h2 {
            color: #0F766E;
        }

        .patient-info {
            background: #DBEAFE;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }

        .patient-info p {
            margin: 10px 0;
        }

        #qrcode {
            display: flex;
            justify-content: center;
            margin: 25px auto;
            padding: 15px;
            background: #FFFFFF;
            width: fit-content;
            border: 1px solid #BFDBFE;
            border-radius: 10px;
        }

        .button-group {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .qr-btn {
            background: #0D9488;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
        }

        .qr-btn:hover {
            background: #0F766E;
        }

        .back-btn {
            background: #60A5FA;
        }

        .back-btn:hover {
            background: #3B82F6;
        }

        .notice {
            background: #FEF3C7;
            color: #92400E;
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
        }

        .url-box {
            background: #F1F5F9;
            padding: 12px;
            border-radius: 7px;
            word-break: break-all;
            font-size: 13px;
            margin-top: 15px;
        }

        @media print {
            body {
                background: white;
            }

            .button-group,
            .back-link,
            .notice,
            .url-box {
                display: none;
            }

            .qr-card {
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

<div class="qr-container">

    <div class="qr-card">

        <h1>Emergency Medical ID</h1>

        <h2>Your QR Code</h2>

        <p>Scan this QR code to view your emergency medical information.</p>

        <div class="patient-info">

            <p>
                <strong>Patient Name:</strong>
                <?php echo htmlspecialchars($full_name); ?>
            </p>

            <p>
                <strong>Blood Group:</strong>
                <?php echo htmlspecialchars($blood_group ?: "Not provided"); ?>
            </p>

        </div>

        <div id="qrcode"></div>

        <p><strong>Emergency Medical QR Code</strong></p>

        <div class="button-group">

            <button type="button" class="qr-btn" onclick="downloadQR()">
                Download QR Code
            </button>

            <button type="button" class="qr-btn" onclick="window.print()">
                Print QR Code
            </button>

            <a href="dashboard.php" class="qr-btn back-btn">
                Back to Dashboard
            </a>

        </div>

        <div class="url-box">
            <strong>Emergency Profile URL:</strong><br>
            <?php echo htmlspecialchars($emergency_url); ?>
        </div>

        <div class="notice">
            <strong>Important:</strong> Anyone who scans this QR code
            may be able to view the emergency information available
            on the linked page. Keep your QR code secure.
        </div>

    </div>

</div>

<script>
    const emergencyURL = <?php echo json_encode($emergency_url); ?>;

    const qrContainer = document.getElementById("qrcode");

    const qr = new QRCode(qrContainer, {
        text: emergencyURL,
        width: 220,
        height: 220,
        colorDark: "#1E293B",
        colorLight: "#FFFFFF",
        correctLevel: QRCode.CorrectLevel.H
    });

    function downloadQR() {
        const canvas = qrContainer.querySelector("canvas");
        const image = qrContainer.querySelector("img");

        let imageURL;

        if (canvas) {
            imageURL = canvas.toDataURL("image/png");
        } else if (image) {
            imageURL = image.src;
        } else {
            alert("QR code is not ready. Please try again.");
            return;
        }

        const link = document.createElement("a");
        link.href = imageURL;
        link.download = "Emergency_Medical_QR.png";
        link.click();
    }
</script>

</body>
</html>
