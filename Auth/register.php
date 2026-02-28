<?php
session_start();
require_once "../Config/database.php"; 

if (isset($_POST['register'])) {
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $loft_name = htmlspecialchars(trim($_POST['loft_name']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $role = $_POST['role']; 

    // --- 1. DATA FORMAT VALIDATION ---
    if (!preg_match("/^[a-zA-Z\s]*$/", $full_name)) {
        $error = "Full Name can only contain letters and spaces.";
    } 
    elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $error = "Phone number must be 11 digits starting with '09'.";
    } 
    else {
        // --- 2. DUPLICATE CHECK ---
        // Check if username already exists in 'users' table
        $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->bind_param("s", $username);
        $checkUser->execute();
        $resUser = $checkUser->get_result();

        // Check if phone already exists in 'members' table
        $checkPhone = $conn->prepare("SELECT id FROM members WHERE phone = ?");
        $checkPhone->bind_param("s", $phone);
        $checkPhone->execute();
        $resPhone = $checkPhone->get_result();

        if ($resUser->num_rows > 0) {
            $error = "Username is already taken. Please choose another.";
        } elseif ($resPhone->num_rows > 0) {
            $error = "This phone number is already registered.";
        } else {
            // --- 3. PROCEED WITH REGISTRATION ---
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO users (role, full_name, username, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $role, $full_name, $username, $password);
                $stmt->execute();

                $new_user_id = $conn->insert_id; 

                $stmt2 = $conn->prepare("INSERT INTO members (user_id, loft_name, phone) VALUES (?, ?, ?)");
                $stmt2->bind_param("iss", $new_user_id, $loft_name, $phone);
                $stmt2->execute();

                $conn->commit();
                echo "<script>alert('Registration successful!'); window.location.href='login.php';</script>";
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
    <title>Register - Pigeon Racing System</title>
    <link rel="stylesheet" href="../Assets/Css/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <form method="POST" action="">
            <h2>Create an Account</h2>

            <?php if(isset($error)): ?>
                <p style="color: #ff4d4d; margin-bottom: 20px; font-size: 14px;"><?php echo $error; ?></p>
            <?php endif; ?>

            <div class="input-group">
           <label>Account Type</label>
            <div class="input-with-icon">
           <i class="fa-solid fa-user-shield"></i>
           <select name="role" required>
            <option value="member">Member (Loft Owner)</option>
            <option value="admin">System Administrator</option>
           </select>
        </div>
      </div>

      <div class="input-group">
    <label>Full Name</label>
    <div class="input-with-icon">
        <i class="fa fa-id-card"></i>
        <input type="text" 
               name="full_name" 
               placeholder="Type your full name" 
               pattern="[A-Za-z\s]+" 
               title="Letters and spaces only" 
               required>
    </div>
</div>

            <div class="input-group">
                <label>Username</label>
                <div class="input-with-icon">
                    <i class="fa fa-user"></i>
                    <input type="text" name="username" placeholder="Type your username" required>
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-with-icon">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" placeholder="Type your password" required>
                </div>
            </div>

            <div class="input-group">
                <label>Loft Name</label>
                <div class="input-with-icon">
                    <i class="fa fa-home"></i>
                    <input type="text" name="loft_name" placeholder="Type your loft name" required>
                </div>
            </div>

            <div class="input-group">
    <label>Phone Number</label>
    <div class="input-with-icon">
        <i class="fa fa-phone"></i>
        <input type="tel" 
               name="phone" 
               placeholder="09123456789" 
               maxlength="11" 
               pattern="09[0-9]{9}" 
               title="Must be 11 digits starting with 09" 
               required>
    </div>
</div>

            <button type="submit" name="register" class="auth-btn">Create</button>

            <div class="login-link" style="text-align: center; margin-top: 15px;">
    <p style="font-size: 14px; color: #666;">
        Already have an account? 
        <a href="login.php" style="color: #8a6b49; font-weight: bold; text-decoration: none;">
            <i class="fa-solid fa-right-to-bracket"></i> Login here
        </a>
    </p>
</div>
        </form>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const fullNameInput = document.querySelector('input[name="full_name"]');
    const phoneInput = document.querySelector('input[name="phone"]');

    // Block numbers in Full Name as they type
    fullNameInput.addEventListener("input", function() {
        this.value = this.value.replace(/[0-9]/g, '');
    });

    // Block letters and limit to 11 digits in Phone
    phoneInput.addEventListener("input", function() {
        // Remove anything that isn't a number
        let val = this.value.replace(/\D/g, '');
        
        // Force the first two digits to be '09' if they start typing
        if (val.length > 0 && val[0] !== '0') val = '0' + val;
        if (val.length > 1 && val[1] !== '9') val = val[0] + '9' + val.slice(1);
        
        // Cap at 11 digits
        this.value = val.substring(0, 11);
    });
});
</script>
</body>
</html>