<?php
session_start();
session_unset(); // Clear all variables
session_destroy(); // Destroy the session
header("Location: /pigeon-racing-system/Auth/login.php"); // Redirect to login
exit();
?>