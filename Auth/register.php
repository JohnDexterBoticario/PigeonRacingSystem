<?php
session_start();
require_once "../Config/database.php"; 

if (isset($_POST['register'])) {
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $username  = htmlspecialchars(trim($_POST['username']));
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $loft_name = htmlspecialchars(trim($_POST['loft_name']));
    $phone     = htmlspecialchars(trim($_POST['phone']));
    $role      = $_POST['role']; 

    // --- 1. DATA FORMAT VALIDATION ---
    if (!preg_match("/^[a-zA-Z\s]*$/", $full_name)) {
        $error = "Full Name can only contain letters and spaces.";
    } 
    elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $error = "Phone number must be 11 digits starting with '09'.";
    } 
    else {
        // --- 2. DUPLICATE CHECK ---
        $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->bind_param("s", $username);
        $checkUser->execute();
        $resUser = $checkUser->get_result();

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
    <title>Create Account - Pigeon Racing System</title>
    <link rel="stylesheet" href="../Assets/Css/register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="login-container"> <div class="form-section">
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
                        <input type="text" name="full_name" placeholder="Type your full name" required>
                    </div>
                </div>

                <div class="input-row"> <div class="input-group">
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
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                    </div>
                </div>

                <div class="input-group">
                    <label>Loft Name</label>
                    <div class="input-wrapper">
                        <i class="fa fa-home"></i>
                        <input type="text" name="loft_name" placeholder="Type your loft name" required>
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