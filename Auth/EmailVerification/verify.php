<?php
session_start();
require_once "../../Config/database.php";

if (isset($_POST['submit_verification'])) {
    $code = trim($_POST['verify_code']);
    $email = $_SESSION['pending_email'] ?? '';

    // Check if the code matches for this specific email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND verification_code = ? AND is_verified = 0");
    $stmt->bind_param("ss", $email, $code);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<!DOCTYPE html>
    <html>
    <head>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&display=swap' rel='stylesheet'>
        <style>
            .swal2-popup { font-family: 'Plus Jakarta Sans', sans-serif !important; border-radius: 20px !important; }
        </style>
    </head>
    <body>";

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Update user to verified status
        $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
        $update->bind_param("i", $user['id']);
        
        if ($update->execute()) {
            unset($_SESSION['pending_email']); // Clear the temporary session
            
            echo "
            <script>
                Swal.fire({
                    title: 'Account Verified!',
                    text: 'You can now login to your account.',
                    icon: 'success',
                    confirmButtonColor: '#2ecc71',
                    confirmButtonText: 'LOGIN NOW'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../login.php';
                    }
                });
            </script>";
        }
    } else {
        // Error Modal for Invalid Code
        echo "
        <script>
            Swal.fire({
                title: 'Invalid Code',
                text: 'The code you entered is incorrect. Please check your email.',
                icon: 'error',
                confirmButtonColor: '#e53e3e',
                confirmButtonText: 'TRY AGAIN'
            }).then(() => {
                window.history.back();
            });
        </script>";
    }

    echo "</body></html>";
}
?>