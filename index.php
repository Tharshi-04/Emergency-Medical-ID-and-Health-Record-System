
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Medical ID | Your Health, Within Reach</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        .hero {
            background: linear-gradient(120deg, #0B2945, #123B63 65%, #1E5A8A);
            color: white;
            padding: 85px 0;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
            gap: 45px;
        }

        .hero h1 {
            font-size: clamp(34px, 5vw, 52px);
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero h1 span {
            color: #4DE0D4;
        }

        .hero p {
            color: #DCE8F2;
            font-size: 16px;
            max-width: 600px;
            margin-bottom: 28px;
        }

        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .hero-buttons a {
            display: inline-block;
            padding: 13px 24px;
            border-radius: 8px;
            font-weight: 700;
        }

        .hero-primary {
            background: #16A6A1;
            color: white;
        }

        .hero-secondary {
            border: 1px solid white;
            color: white;
        }

        .hero-visual {
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 22px;
            padding: 35px;
            text-align: center;
        }

        .medical-icon {
            font-size: 85px;
            margin-bottom: 15px;
        }

        .hero-visual h3 {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .hero-visual p {
            font-size: 14px;
            margin: auto;
        }

        .section-heading {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 35px;
        }

        .section-heading h2 {
            color: #123B63;
            font-size: 30px;
            margin-bottom: 10px;
        }

        .section-heading p {
            color: #718096;
        }

        .feature-card {
            text-align: center;
            padding: 28px 22px;
        }

        .feature-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 18px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: #E6F7F5;
            color: #108580;
            font-size: 30px;
        }

        .feature-card p {
            color: #718096;
        }

        .how-section {
            background: #EAF4FF;
        }

        .step-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 18px rgba(18,59,99,0.06);
        }

        .step-number {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            margin: 0 auto 15px;
            border-radius: 50%;
            background: #16A6A1;
            color: white;
            font-weight: 800;
        }

        .step-card p {
            color: #718096;
        }

        .cta-section {
            background: #123B63;
            color: white;
            text-align: center;
            padding: 55px 20px;
            border-radius: 16px;
        }

        .cta-section h2 {
            font-size: 29px;
            margin-bottom: 12px;
        }

        .cta-section p {
            color: #DCE8F2;
            margin-bottom: 22px;
        }

        .cta-section a {
            display: inline-block;
            background: #16A6A1;
            color: white;
            padding: 13px 25px;
            border-radius: 8px;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 55px 0;
            }

            .hero-visual {
                padding: 25px;
            }
        }
    </style>
</head>

<body>

<header class="main-header">
    <div class="header-container">
        <a href="index.php" class="logo">
            Emergency <span>Medical ID</span>
        </a>

        <nav class="nav-menu">
            <a href="index.php" class="active">Home</a>
            <a href="#features">Features</a>
            <a href="#how-it-works">How It Works</a>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        </nav>
    </div>
</header>

<main>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="container hero-content">

            <div>
                <h1>
                    Your Health Information,
                    <span>When It Matters Most.</span>
                </h1>

                <p>
                    Emergency Medical ID is a digital healthcare platform
                    that helps individuals securely manage their medical
                    information and make essential details accessible
                    during emergencies.
                </p>

                <div class="hero-buttons">
                    <a href="register.php" class="hero-primary">
                        Get Started
                    </a>

                    <a href="login.php" class="hero-secondary">
                        Login to Account
                    </a>
                </div>
            </div>

            <div class="hero-visual">
                <div class="medical-icon">🏥</div>
                <h3>Emergency Medical ID</h3>
                <p>
                    Important medical information available when
                    emergency care is needed.
                </p>
            </div>

        </div>
    </section>


    <!-- FEATURES SECTION -->
    <section class="section" id="features">
        <div class="container">

            <div class="section-heading">
                <h2>Our Key Features</h2>
                <p>
                    Essential healthcare features designed to support
                    patients, donors and healthcare professionals.
                </p>
            </div>

            <div class="card-grid">

                <div class="card feature-card">
                    <div class="feature-icon">🩺</div>
                    <h3>Medical Profile</h3>
                    <p>
                        Store important medical details, allergies,
                        medical conditions and blood group information.
                    </p>
                </div>

                <div class="card feature-card">
                    <div class="feature-icon">📱</div>
                    <h3>Emergency QR Code</h3>
                    <p>
                        Provide a quick way to access authorized
                        emergency medical information through a QR code.
                    </p>
                </div>

                <div class="card feature-card">
                    <div class="feature-icon">👨‍⚕️</div>
                    <h3>Hospital Access</h3>
                    <p>
                        Support authorized healthcare professionals
                        in requesting access to patient information.
                    </p>
                </div>

                <div class="card feature-card">
                    <div class="feature-icon">🩸</div>
                    <h3>Donor Support</h3>
                    <p>
                        Help connect registered donors with relevant
                        donation requests.
                    </p>
                </div>

                <div class="card feature-card">
                    <div class="feature-icon">📞</div>
                    <h3>Emergency Contacts</h3>
                    <p>
                        Keep important emergency contact information
                        organized in one place.
                    </p>
                </div>

                <div class="card feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3>Secure Information</h3>
                    <p>
                        Support controlled access to personal
                        medical information.
                    </p>
                </div>

            </div>
        </div>
    </section>


    <!-- HOW IT WORKS -->
    <section class="section how-section" id="how-it-works">
        <div class="container">

            <div class="section-heading">
                <h2>How It Works</h2>
                <p>
                    Start using your Emergency Medical ID in a few
                    simple steps.
                </p>
            </div>

            <div class="card-grid">

                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3>Create an Account</h3>
                    <p>
                        Register as a patient, donor or hospital user.
                    </p>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3>Add Information</h3>
                    <p>
                        Enter the relevant profile and medical details
                        based on your account role.
                    </p>
                </div>

                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3>Use the Platform</h3>
                    <p>
                        Access available services and manage your
                        information through your dashboard.
                    </p>
                </div>

            </div>
        </div>
    </section>


    <!-- CALL TO ACTION -->
    <section class="section">
        <div class="container">
            <div class="cta-section">
                <h2>Be Prepared. Stay Informed.</h2>
                <p>
                    Create your Emergency Medical ID account and
                    organize your essential medical information.
                </p>

                <a href="register.php">Create Your Account</a>
            </div>
        </div>
    </section>

</main>


<!-- FOOTER -->
<footer class="main-footer">
    <div class="footer-container">

        <div>
            <h2>Emergency Medical ID</h2>
            <p>
                A digital platform designed to support emergency
                medical information management.
            </p>
        </div>

        <div class="footer-bottom">
            <p>
                &copy; <?php echo date("Y"); ?>
                Emergency Medical ID. All rights reserved.
            </p>
        </div>

    </div>
</footer>

</body>
</html>
