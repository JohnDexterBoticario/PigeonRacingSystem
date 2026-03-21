<?php
// 1. Force Timezone to Philippines (Fixes the "Link Expired" mismatch)
date_default_timezone_set('Asia/Manila');

require_once "../../Config/database.php";

$token = $_GET['token'] ?? '';
$is_valid = false;

if (!empty($token)) {
    /** * 2. Improved Query: 
     * We compare the expiry string in the DB against the current PHP time.
     * This avoids issues where MySQL NOW() might be set to UTC.
     */
    $current_time = date("Y-m-d H:i:s");
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > ?");
    $stmt->bind_param("ss", $token, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $is_valid = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Password | Pigeon Racing</title>
    <link rel="stylesheet" href="../../Assets/Css/login.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-btn:hover { background: #27ae60 !important; transform: translateY(-1px); }
        .input-wrapper input:focus { border-color: #2ecc71 !important; box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1); }
    </style>
</head>
<body style="background: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-family: sans-serif;">

    <div class="login-card" style="width: 100%; max-width: 400px; background: white; padding: 2.5rem; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);">
        <?php if ($is_valid): ?>
            <div class="card-header" style="text-align: center; margin-bottom: 2rem;">
                <h2 style="font-weight: 800; color: #1a202c; margin-bottom: 8px;">New Password</h2>
                <p style="color: #718096; font-size: 0.9rem;">Please enter your new secure password.</p>
            </div>

            <form action="process_newPassword.php" method="POST" id="resetForm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="input-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #a0aec0; margin-bottom: 8px;">New Password</label>
                    <div class="input-wrapper" style="position: relative;">
                        <i class="fa fa-lock" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                        <input type="password" name="password" id="password" placeholder="••••••••" required 
                               style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; transition: 0.2s;">
                    </div>
                </div>

                <div class="input-group" style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #a0aec0; margin-bottom: 8px;">Confirm Password</label>
                    <div class="input-wrapper" style="position: relative;">
                        <i class="fa fa-check-double" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" required 
                               style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; transition: 0.2s;">
                    </div>
                </div>

                <button type="submit" name="update_password" class="login-btn" 
                        style="width: 100%; background: #2ecc71; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.3s;">
                    UPDATE PASSWORD
                </button>
            </form>

            <script>
                // Basic front-end validation to check if passwords match
                document.getElementById('resetForm').onsubmit = function(e) {
                    const pass = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm_password').value;
                    if (pass !== confirm) {
                        e.preventDefault();
                        Swal.fire('Error', 'Passwords do not match!', 'error');
                    }
                };
            </script>

        <?php else: ?>
            <div style="text-align: center; padding: 1rem;">
                <i class="fa-solid fa-clock-rotate-left" style="font-size: 3rem; color: #e53e3e; margin-bottom: 1rem;"></i>
                <h2 style="font-weight: 800; color: #1a202c;">Link Expired</h2>
                <p style="color: #718096; margin-bottom: 2rem; line-height: 1.5;">This password reset link is invalid or has already expired for security reasons.</p>
                <a href="../login.php" style="display: inline-block; color: #2ecc71; font-weight: 700; text-decoration: none; border: 2px solid #2ecc71; padding: 10px 20px; border-radius: 12px; transition: 0.2s;">Request New Link</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>