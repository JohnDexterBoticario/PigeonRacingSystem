<?php
session_start();
// Paths corrected for /Auth/ForgotPassword/ directory
require_once "../../Config/database.php";
require_once "../../Config/Send_mail.php"; 

if (isset($_POST['reset-request-submit'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // 1. Check if email exists in users table
    $userCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $userCheck->bind_param("s", $email);
    $userCheck->execute();
    if ($userCheck->get_result()->num_rows === 0) {
        echo "<script>alert('Email not found.'); window.history.back();</script>";
        exit();
    }

    // 2. Generate token (30 min expiry)
    $token = bin2hex(random_bytes(32));
    $expires = date("U") + 1800; 

    // 3. Database Transaction for the Reset Table
    $conn->begin_transaction();
    try {
        // Clear old tokens for this email
        $deleteSql = $conn->prepare("DELETE FROM pwdReset WHERE email = ?");
        $deleteSql->bind_param("s", $email);
        $deleteSql->execute();

        // Insert new token
        $insertSql = $conn->prepare("INSERT INTO pwdReset (email, token, expires) VALUES (?, ?, ?)");
        $insertSql->bind_param("sss", $email, $token, $expires);
        $insertSql->execute();

        // 4. Send Email using your PHPMailer function
        $subject = 'Reset your Pigeon Racing System Password';
        $reset_link = "http://localhost/pigeon-racing-system/Auth/ForgotPassword/create_newPassword.php?token=" . $token;
        
        $message = "
            <div style='font-family: sans-serif; padding: 20px; border: 1px solid #ddd;'>
                <h3>Password Reset Request</h3>
                <p>You requested a password reset for your Pigeon Racing account.</p>
                <p>Click the button below to set a new password. This link expires in 30 minutes.</p>
                <a href='$reset_link' style='background: #2ecc71; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                <p>If you did not request this, please ignore this email.</p>
            </div>";

        if (sendEmail($email, $subject, $message)) {
            $conn->commit();
            echo "<script>alert('Check your email for the reset link!'); window.location.href = '/pigeon-racing-system/Auth/login.php';</script>";
        } else {
            throw new Exception("Email delivery failed.");
        }

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.history.back();</script>";
    }
}