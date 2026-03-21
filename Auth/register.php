<?php
session_start();
require_once "../Config/database.php"; 

if (isset($_POST['register'])) {
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $email     = htmlspecialchars(trim($_POST['email']));
    $username  = htmlspecialchars(trim($_POST['username']));
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $loft_name = htmlspecialchars(trim($_POST['loft_name']));
    $phone     = htmlspecialchars(trim($_POST['phone']));
    $role      = $_POST['role']; 

    // --- 1. DATA FORMAT VALIDATION ---
    if (!preg_match("/^[a-zA-Z\s]*$/", $full_name)) {
        $error = "Full Name can only contain letters and spaces.";
    } 
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $error = "Phone number must be 11 digits starting with '09'.";
    } 
    else {
        // --- 2. DUPLICATE CHECK ---
        $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkUser->bind_param("ss", $username, $email);
        $checkUser->execute();
        $resUser = $checkUser->get_result();

        $checkPhone = $conn->prepare("SELECT id FROM members WHERE phone = ?");
        $checkPhone->bind_param("s", $phone);
        $checkPhone->execute();
        $resPhone = $checkPhone->get_result();

        if ($resUser->num_rows > 0) {
            $error = "Username or Email is already taken.";
        } elseif ($resPhone->num_rows > 0) {
            $error = "This phone number is already registered.";
        } else {
            // --- 3. PROCEED WITH REGISTRATION ---
            $conn->begin_transaction();
            try {
                $verification_code = rand(100000, 999999); 

                $stmt = $conn->prepare("INSERT INTO users (role, full_name, email, username, password, verification_code, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                $stmt->bind_param("ssssss", $role, $full_name, $email, $username, $password, $verification_code);
                $stmt->execute();

                $new_user_id = $conn->insert_id; 

                $stmt2 = $conn->prepare("INSERT INTO members (user_id, loft_name, phone) VALUES (?, ?, ?)");
                $stmt2->bind_param("iss", $new_user_id, $loft_name, $phone);
                $stmt2->execute();

                // --- 4. SEND VERIFICATION EMAIL ---
                require_once "../Config/Send_mail.php";

                if (sendVerificationEmail($email, $full_name, $verification_code)) {
                    $conn->commit();
                    $_SESSION['pending_email'] = $email;
                    $show_verify_modal = true; 
                    $user_email_for_modal = $email;
                } else {
                    $conn->rollback();
                    $error = "Failed to send verification email. Please check your connection.";
                }

            } catch (Exception $e) {
                $conn->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Pigeon Racing System</title>
    <link rel="stylesheet" href="../Assets/Css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div id="verifyModal" class="modal-overlay" <?php if(isset($show_verify_modal)) echo 'style="display:flex;"'; ?>>
    <div class="modal-card">
        <div class="card-header">
            <h2>Verify Your Email</h2>
            <p>We've sent a 6-digit code to <br><strong><?php echo $user_email_for_modal ?? ''; ?></strong></p>
        </div>
        
        <form action="../Auth/EmailVerification/verify.php" method="POST">
            <div class="input-group">
                <label>Enter Code</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-key"></i>
                    <input type="text" name="verify_code" placeholder="000000" maxlength="6" required 
                           style="text-align: center; letter-spacing: 8px; font-size: 24px; font-weight: 800;">
                </div>
            </div>
            <button type="submit" name="submit_verification" class="login-btn">VERIFY ACCOUNT</button>
            
            <div class="signup-link" style="margin-top: 15px; text-align: center;">
                <p>Didn't get the code? <a href="../Auth/EmailVerification/ResendCode.php" id="resendBtn" style="color: #2ecc71; font-weight: bold;">Resend</a></p>
            </div>
        </form>
    </div>
</div>

<div class="login-container"> 
    <div class="form-section">
        <div class="login-card">
            <div class="card-header">
                <h2>Create Account</h2>
                <p>Join the Pigeon Racing community</p>
            </div>

            <?php if(isset($error)): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label>Account Type</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user-shield"></i>
                        <select name="role" required>
                            <option value="member">Member (Loft Owner)</option>
                            <option value="admin">System Administrator</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <i class="fa fa-id-card"></i>
                        <input type="text" name="full_name" placeholder="John Doe" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <i class="fa fa-envelope"></i>
                        <input type="email" name="email" placeholder="email@example.com" required>
                    </div>
                </div>

                <div class="input-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;"> 
                    <div class="input-group">
                        <label>Username</label>
                        <div class="input-wrapper">
                            <i class="fa fa-user"></i>
                            <input type="text" name="username" placeholder="Username" required>
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fa fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="Password" required>
                            <i class="fa-solid fa-eye" id="togglePassword" style="left: auto; right: 16px; cursor: pointer; position: absolute; top: 50%; transform: translateY(-50%);"></i>
                        </div>
                        <div id="strength-container" style="height: 4px; width: 100%; background: #edf2f7; margin-top: 8px; border-radius: 2px; overflow: hidden;">
                            <div id="strength-bar" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                        </div>
                        <small id="strength-text" style="font-size: 11px; color: #718096; margin-top: 4px; display: block;">Enter a password</small>
                    </div>
                </div>

                <div class="input-group">
                    <label>Loft Name</label>
                    <div class="input-wrapper">
                        <i class="fa fa-home"></i>
                        <input type="text" name="loft_name" placeholder="e.g. Blue Sky Loft" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Phone Number</label>
                    <div class="input-wrapper">
                        <i class="fa fa-phone"></i>
                        <input type="tel" name="phone" placeholder="09123456789" maxlength="11" required>
                    </div>
                </div>

                <button type="submit" name="register" class="login-btn">CREATE ACCOUNT</button>

                <div class="signup-link">
                    <p>Already have an account? <a href="login.php">LOGIN HERE</a></p>
                </div>
            </form>
        </div>
    </div>

    <div class="image-section">
        <img src="../Assets/Css/Images/BackG.jpg" alt="Pigeon Racing" class="side-image">
        <div class="brand-overlay">
            <h1>Start Your Journey</h1>
            <p>Register your loft and begin racing today.</p>
        </div>
    </div>
</div>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
        this.style.color = (type === 'text') ? '#2ecc71' : '#a0aec0';
    });

    const strengthBar = document.querySelector('#strength-bar');
    const strengthText = document.querySelector('#strength-text');

    passwordInput.addEventListener('input', () => {
        const val = passwordInput.value;
        let score = 0;
        if (!val) { updateBar(0, 'Enter a password', '#edf2f7'); return; }
        if (val.length >= 8) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;

        if (score <= 1) updateBar(25, 'Weak', '#e53e3e');
        else if (score === 2) updateBar(50, 'Fair', '#ed8936');
        else if (score === 3) updateBar(75, 'Good', '#38b2ac');
        else updateBar(100, 'Strong', '#2ecc71');
    });

    function updateBar(width, text, color) {
        strengthBar.style.width = width + '%';
        strengthBar.style.background = color;
        strengthText.innerText = text;
        strengthText.style.color = color;
    }

    document.getElementById('resendBtn').addEventListener('click', function(e) {
        e.preventDefault();
        const btn = this;
        btn.innerText = "Sending...";
        btn.style.pointerEvents = "none";

        fetch('../Auth/EmailVerification/ResendCode.php') // Match your file structure
            .then(response => response.text())
            .then(data => {
                if (data === 'success') {
                    alert('A new code has been sent to your email.');
                } else {
                    alert('Failed to resend code. Please try again later.');
                }
                btn.innerText = "Resend";
                btn.style.pointerEvents = "auto";
            });
    });
</script>

</body>
</html>