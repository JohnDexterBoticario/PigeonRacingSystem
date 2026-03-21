<?php
session_start();
require_once "../Config/database.php"; 

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, full_name, password, role, is_verified FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            
            if ($user['is_verified'] == 0) {
                $error = "Please verify your email address before logging in.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                $location = ($user['role'] === 'admin') ? "../Admin/AdminDashboard.php" : "../Member/Dashboard.php";
                header("Location: $location");
                exit();
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Pigeon Racing System</title>
    <link rel="stylesheet" href="../Assets/Css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Ensure Modal is hidden by default and uses flex for centering */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .error-msg {
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>

<div id="forgotPasswordModal" class="modal-overlay">
    <div class="modal-card" style="background: white; padding: 2rem; border-radius: 20px; width: 100%; max-width: 400px; position: relative;">
        <div class="card-header">
            <span class="close-modal" id="closeModal" style="position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 1.5rem;">&times;</span>
            <h2 style="font-weight: 800; margin-bottom: 5px;">Reset Password</h2>
            <p style="color: #718096; font-size: 0.9rem; margin-bottom: 20px;">Enter your email to receive a reset link.</p>
        </div>
        
        <form action="Forgotpassword/forgot_password_handler.php" method="POST">
            <div class="input-group" style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #a0aec0; margin-bottom: 8px;">Email Address</label>
                <div class="input-wrapper" style="position: relative;">
                    <i class="fa fa-envelope" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                    <input type="email" name="email" placeholder="email@example.com" required 
                           style="width: 100%; padding: 12px 12px 12px 45px; border: 1px solid #e2e8f0; border-radius: 12px; outline: none;">
                </div>
            </div>
            <button type="submit" name="reset-request-submit" class="login-btn" 
                    style="width: 100%; background: #1a1a1a; color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; cursor: pointer;">
                SEND RESET LINK
            </button>
        </form>
    </div>
</div>

<div class="login-container">
    <div class="image-section">
        <img src="../Assets/Css/Images/BackG.jpg" alt="Pigeon Racing" class="side-image">
        <div class="brand-overlay">
            <h1>Pigeon Racing</h1>
            <p>Elevating the sport, one flight at a time.</p>
        </div>
    </div>

    <div class="form-section">
        <div class="login-card">
            <div class="card-header">
                <h2>Welcome Back</h2>
                <p>Please log in to your account to continue.</p>
            </div>

            <?php if(isset($error)): ?>
                <div class="error-msg" style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 12px; margin-bottom: 20px; font-size: 0.85rem; border: 1px solid #fecaca;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fa fa-user"></i>
                        <input type="text" name="username" placeholder="Enter your username" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fa fa-lock"></i> 
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <i class="fa-solid fa-eye" id="togglePassword" style="left: auto; right: 16px; cursor: pointer; position: absolute; top: 50%; transform: translateY(-50%); color: #a0aec0;"></i>
                    </div>
                    <a href="#" class="forgot-password-link" style="text-align: right; display: block; margin-top: 8px; color: #2ecc71; text-decoration: none; font-size: 0.85rem; font-weight: 600;">Forgot password?</a>
                </div>

                <button type="submit" name="login" class="login-btn">SIGN IN</button>

                <div class="signup-link" style="text-align: center; margin-top: 25px;">
                    <p style="color: #718096; font-size: 0.9rem;">New to the club? <a href="register.php" style="color: #2ecc71; font-weight: 700; text-decoration: none;">Create an account</a></p>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Password Toggle
    const togglePassword = document.querySelector('#togglePassword');
    const passwordField = document.querySelector('#password');

    togglePassword.addEventListener('click', function () {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
        this.style.color = (type === 'text') ? '#2ecc71' : '#a0aec0';
    });

    // Modal Logic
    const modal = document.getElementById("forgotPasswordModal");
    const forgotLink = document.querySelector(".forgot-password-link");
    const closeBtn = document.getElementById("closeModal");

    forgotLink.onclick = (e) => { e.preventDefault(); modal.style.display = "flex"; }
    closeBtn.onclick = () => modal.style.display = "none";
    window.onclick = (e) => { if (e.target == modal) modal.style.display = "none"; }
</script>

</body>
</html>