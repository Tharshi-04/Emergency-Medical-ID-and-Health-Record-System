<?php
session_start();

unset($_SESSION["admin_logged_in"]);
unset($_SESSION["admin_username"]);

session_regenerate_id(true);

header("Location: admin_login.php");
exit();
?>