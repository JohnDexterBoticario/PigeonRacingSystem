<?php
date_default_timezone_set('Asia/Manila');
require_once "../../Config/database.php";

if (isset($_POST['update_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Basic Validation
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit();
    }

    // 2. Verify token is still valid (Safety check)
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $user_id = $user['id'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 3. Update password and CLEAR the token so it can't be used again
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        $update->bind_param("si", $hashed_password, $user_id);

        if ($update->execute()) {
            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                <style> .swal2-popup { font-family: sans-serif; border-radius: 20px; } </style>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: 'Success!',
                        text: 'Your password has been updated. You can now login.',
                        icon: 'success',
                        confirmButtonColor: '#2ecc71'
                    }).then(() => {
                        window.location.href = '/pigeon-racing-system/Auth/login.php';
                    });
                </script>
            </body>
            </html>";
        }
    } else {
        echo "<script>alert('Invalid session or link expired.'); window.location.href='/pigeon-racing-system/Auth/login.php';p';</script>";
    }
}