<?php
session_start();
require_once "../Config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $loft_name = $_POST['loft_name'];
    $phone = $_POST['phone'];

    // This SQL statement will UPDATE the record if it exists, 
    // or INSERT a new one if it doesn't.
    $sql = "INSERT INTO members (user_id, loft_name, phone) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE loft_name = ?, phone = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $user_id, $loft_name, $phone, $loft_name, $phone);
    
    if ($stmt->execute()) {
        header("Location: profile.php?success=1");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>