<?php
session_start();
require_once "../Config/database.php";

// If already logged in, redirect to the correct dashboard automatically
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../Admin/Races/create.php");
        exit();
    } else if ($_SESSION['role'] === 'member') {
        header("Location: ../Member/Dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pigeon Racing System</title>
    <link rel="stylesheet" href="../Assets/Css/style.css">
    <link rel="stylesheet" href="../Assets/Css/Leaderboard.css">
</head>
<body>
    <div class="container" style="text-align: center; margin-top: 50px;">
        <h1>Welcome to the Pigeon Racing System</h1>
        <p>Please log in or register to manage your lofts and view race results.</p>
        
        <div style="margin-top: 20px;">
            <a href="../Auth/login.php"><button>Login</button></a>
            <a href="../Auth/register.php"><button style="background-color: #555;">Register</button></a>
        </div>
    </div>
</body>
</html>