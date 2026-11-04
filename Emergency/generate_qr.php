<?php

$patient_name = "Gayani Udari";
$emergency_url = "http://localhost/AWP-Project/emergency_view.php?token=PATIENT_12345";

?>

<!DOCTYPE html>
    <html>
        <head>
            <title>Emergency ID Studio</title>
    
             <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <style>
             body { font-family: Arial, sans-serif; text-align: center; padding: 40px; background: #f9f9f9; }
            .card { background: white; width: 300px; margin: 0 auto; padding: 20px; border-radius: 8px; border: 2px solid #333; }
            #qrcode { margin: 20px auto; display: flex; justify-content: center; }
            button { background: #007BFF; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; margin-top: 15px; }
        </style>
        </head>
    <body>

    <div class="card">
        <h3>Emergency ID</h3>
        <p>Name: <?php echo $patient_name; ?></p>
        
        
        <div id="qrcode"></div>
    </div>

    <button onclick="window.print()">Print ID</button>

    <script>
        
        var link = "<?php echo $emergency_url; ?>";
        new QRCode(document.getElementById("qrcode"), {
            text: link,
            width: 120,
            height: 120
        });
    </script>

    </body>
</html>