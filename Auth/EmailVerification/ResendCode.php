<?php
session_start();
require_once "../Config/database.php";

if (isset($_SESSION['pending_email'])) {
    $email = $_SESSION['pending_email'];
    $new_code = rand(100000, 999999);

    // Update the code in the database
    $stmt = $conn->prepare("UPDATE users SET verification_code = ? WHERE email = ? AND is_verified = 0");
    $stmt->bind_param("ss", $new_code, $email);
    
    if ($stmt->execute()) {
        $to = $email;
        $subject = "Your New Verification Code";
        $message = "Your new 6-digit code is: " . $new_code;
        $headers = "From: no-reply@pigeonracing.com";

        if (mail($to, $subject, $message, $headers)) {
            echo "success";
        } else {
            echo "error_mail";
        }
    } else {
        echo "error_db";
    }
} else {
    echo "no_session";
}
?>