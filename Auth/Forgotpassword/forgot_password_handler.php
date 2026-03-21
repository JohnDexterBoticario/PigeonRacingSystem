<?php
// 1. MUST BE AT THE VERY TOP: Force timezone to match your local time (Philippines)
date_default_timezone_set('Asia/Manila');

session_start();

// 2. Paths corrected for /Auth/ForgotPassword/ directory
require_once "../../Config/database.php";
require_once "../../Config/Send_mail.php"; 

if (isset($_POST['send_link']) || isset($_POST['reset-request-submit'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // 1. Check if user exists
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // 2. Generate unique token & 1-hour expiry (Now correctly synced with Manila time)
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // 3. Store token in database
        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
        $update->bind_param("ssi", $token, $expiry, $user['id']);
        
        if ($update->execute()) {
            // 4. Prepare the Reset Email
            // Note: Check if folder is 'Forgotpassword' or 'ForgotPassword' (case-sensitive on some servers)
            $reset_link = "http://localhost/pigeon-racing-system/Auth/Forgotpassword/create_newPassword.php?token=" . $token;
            $subject = "Password Reset Request - Pigeon Racing";
            
            $message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 15px;'>
                    <h2 style='color: #2c3e50;'>Password Reset</h2>
                    <p>Hi " . htmlspecialchars($user['full_name']) . ",</p>
                    <p>Click the button below to reset your password. This link will expire in 1 hour.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$reset_link' style='background-color: #2ecc71; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>RESET MY PASSWORD</a>
                    </div>
                    <p style='font-size: 12px; color: #7f8c8d;'>If you didn't request this, you can safely ignore this email.</p>
                </div>";

            // 5. Send using your PHPMailer config
            if (sendEmail($email, $subject, $message)) {
                echo "
                <!DOCTYPE html>
                <html>
                <head>
                    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                    <style> 
                        .swal2-popup { font-family: sans-serif; border-radius: 20px; } 
                    </style>
                </head>
                <body>
                    <script>
                        Swal.fire({
                            title: 'Email Sent!',
                            text: 'Please check your inbox for the reset link.',
                            icon: 'success',
                            confirmButtonColor: '#2ecc71'
                        }).then(() => {
                           window.location.href = '/pigeon-racing-system/Auth/login.php';
                        });
                    </script>
                </body>
                </html>";
            } else {
                echo "<script>alert('Mailer Error. Check your SMTP settings.'); window.history.back();</script>";
            }
        }
    } else {
        echo "<script>alert('Email not found in our records.'); window.history.back();</script>";
    }
}
?>