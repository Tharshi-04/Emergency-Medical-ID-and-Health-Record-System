<?php
// Start the session
session_start();
// Remove the admin login status from the session
unset($_SESSION["admin_logged_in"]);
unset($_SESSION["admin_username"]);

session_regenerate_id(true);

header("Location: admin_login.php");
// Stop executing the script
exit();
?>
