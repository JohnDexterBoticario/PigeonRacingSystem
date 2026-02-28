<?php
session_start();
require_once "../Config/database.php"; 

// Admin Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Auth/login.php");
    exit();
}

include "../Includes/sidebar.php"; 

if (isset($_POST['register'])) {
    $role = $_POST['role'];
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $username = htmlspecialchars(trim($_POST['username']));
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $loft_name = htmlspecialchars(trim($_POST['loft_name']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];

    // Validation
    if (!preg_match("/^[a-zA-Z\s]*$/", $full_name)) {
        $error = "Full Name can only contain letters and spaces.";
    } elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $error = "Phone number must be 11 digits starting with '09'.";
    } else {
        // Duplicate Check
        $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->bind_param("s", $username);
        $checkUser->execute();
        if ($checkUser->get_result()->num_rows > 0) {
            $error = "Username is already taken.";
        } else {
            $conn->begin_transaction();
            try {
                // Insert into Users Table
                $stmt = $conn->prepare("INSERT INTO users (role, full_name, username, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $role, $full_name, $username, $password);
                $stmt->execute();

                $new_user_id = $conn->insert_id; 

                // Insert into Members Table with Coordinates
                $stmt2 = $conn->prepare("INSERT INTO members (user_id, loft_name, phone, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issss", $new_user_id, $loft_name, $phone, $latitude, $longitude);
                $stmt2->execute();

                $conn->commit();
                echo "<script>alert('Registration successful!'); window.location.href='Dashboard.php';</script>";
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
            <h2>Register Account</h2>

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
                    <input type="text" name="full_name" placeholder="Full name" required>
                </div>
            </div>

            <div class="input-group">
                <label>Username</label>
                <div class="input-with-icon">
                    <i class="fa fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required>
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-with-icon">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
            </div>

            <div class="input-group">
                <label>Loft Name</label>
                <div class="input-with-icon">
                    <i class="fa fa-home"></i>
                    <input type="text" name="loft_name" placeholder="Loft name" required>
                </div>
            </div>

            <div class="input-group">
                <label>Phone Number</label>
                <div class="input-with-icon">
                    <i class="fa fa-phone"></i>
                    <input type="tel" name="phone" id="phone" placeholder="09123456789" maxlength="11" required>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <div class="input-group">
                    <label>Latitude</label>
                    <input type="text" name="latitude" id="lat" placeholder="14.5995" required>
                </div>
                <div class="input-group">
                    <label>Longitude</label>
                    <input type="text" name="longitude" id="lng" placeholder="120.9842" required>
                </div>
            </div>
            
            <button type="button" onclick="getLocation()" class="geo-btn">
                <i class="fa-solid fa-location-crosshairs"></i> Get Current Location
            </button>

            <button type="submit" name="register" class="auth-btn">Register Member</button>

            <div class="login-link">
                <a href="Dashboard.php"><i class="fa-solid fa-house"></i> Home</a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-fetch GPS coordinates
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('lat').value = position.coords.latitude.toFixed(8);
            document.getElementById('lng').value = position.coords.longitude.toFixed(8);
        }, function(error) {
            alert("Error getting location: " + error.message);
        });
    } else {
        alert("Geolocation is not supported by this browser.");
    }
}

// Name & Phone Input Masking
document.addEventListener("DOMContentLoaded", function() {
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener("input", function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 11);
    });
});
</script>

<style>
    /* Styling for the new GPS button */
    .geo-btn {
        width: 100%;
        background: #f0f0f0;
        border: 1px solid #ccc;
        padding: 8px;
        margin-bottom: 15px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 12px;
        transition: 0.3s;
    }
    .geo-btn:hover { background: #e0e0e0; }
    .input-group label { display: block; font-size: 12px; margin-bottom: 5px; color: #555; }
</style>

</body>
</html>