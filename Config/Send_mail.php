<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Correct paths to your libs folder
// Use __DIR__ to find the folder relative to THIS file's location
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

// This is the general function your Forgot Password handler is looking for
function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'd0945232@gmail.com';
        $mail->Password   = 'rmkr nswk ftdl civs'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('d0945232@gmail.com', 'Pigeon Racing System');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Keep your original function for Registration if you're using it elsewhere
function sendVerificationEmail($to, $full_name, $code) {
    $subject = 'Verify your Pigeon Racing Account';
    $message = "Hi <b>$full_name</b>,<br><br>Your verification code is: <h2 style='color:#2ecc71;'>$code</h2><br>Enter this code in the registration window.";
    
    return sendEmail($to, $subject, $message);
}