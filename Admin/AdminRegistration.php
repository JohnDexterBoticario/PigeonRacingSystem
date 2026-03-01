<?php
/**
 * Admin Member Registration
 * Facilitates account creation for both Admins and Members with GPS coordinates.
 */

session_start();
require_once "../Config/database.php"; 

/**
 * Function: checkAdminAccess
 * Restricts access to this page to logged-in administrators only.
 */
function checkAdminAccess() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../Auth/login.php");
        exit();
    }
}
checkAdminAccess();

// Include Sidebar navigation
include "../Includes/sidebar.php"; 

/**
 * Logic: Handle Form Submission
 * Sanitizes input, validates data patterns, and performs a two-table database transaction.
 */
if (isset($_POST['register'])) {
    // 1. Sanitize and Collect Input
    $role       = $_POST['role'];
    $full_name  = htmlspecialchars(trim($_POST['full_name']));
    $username   = htmlspecialchars(trim($_POST['username']));
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $loft_name  = htmlspecialchars(trim($_POST['loft_name']));
    $phone      = htmlspecialchars(trim($_POST['phone']));
    $latitude   = $_POST['latitude'];
    $longitude  = $_POST['longitude'];

    // 2. Data Validation
    if (!preg_match("/^[a-zA-Z\s]*$/", $full_name)) {
        $error = "Full Name can only contain letters and spaces.";
    } elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $error = "Phone number must be 11 digits starting with '09'.";
    } else {
        // 3. Check for Existing Username
        $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->bind_param("s", $username);
        $checkUser->execute();
        
        if ($checkUser->get_result()->num_rows > 0) {
            $error = "Username is already taken.";
        } else {
            /**
             * Database Transaction:
             * Ensures that both the 'users' and 'members' records are created together.
             * If one fails, neither are saved (Atomicity).
             */
            $conn->begin_transaction();
            try {
                // Insert core account credentials
                $stmt = $conn->prepare("INSERT INTO users (role, full_name, username, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $role, $full_name, $username, $password);
                $stmt->execute();

                $new_user_id = $conn->insert_id; 

                // Insert profile and GPS data
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
    <title>Register Member - Pigeon Racing System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="../Assets/Css/Registration.css">
</head>
<body>

<div class="main-content">
    <div class="auth-wrapper">
        <div class="auth-card">
            <form method="POST" action="">
                <h2>Register Account</h2>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger py-2" style="font-size: 13px;">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= $error ?>
                    </div>
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
                        <input type="text" name="full_name" placeholder="Juan Dela Cruz" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Username</label>
                    <div class="input-with-icon">
                        <i class="fa fa-user"></i>
                        <input type="text" name="username" placeholder="juan_pigeon123" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <i class="fa fa-lock"></i>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group">
                            <label>Loft Name</label>
                            <div class="input-with-icon">
                                <i class="fa fa-home"></i>
                                <input type="text" name="loft_name" placeholder="Speed Loft" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <label>Phone Number</label>
                            <div class="input-with-icon">
                                <i class="fa fa-phone"></i>
                                <input type="tel" name="phone" id="phone" placeholder="09123456789" maxlength="11" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="input-group">
                            <label>Latitude</label>
                            <input type="text" name="latitude" id="lat" class="form-control form-control-sm" placeholder="14.5995" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="input-group">
                            <label>Longitude</label>
                            <input type="text" name="longitude" id="lng" class="form-control form-control-sm" placeholder="120.9842" required>
                        </div>
                    </div>
                </div>
                
                <button type="button" onclick="getLocation()" class="geo-btn">
                    <i class="fa-solid fa-location-crosshairs me-1"></i> Use My Current Location
                </button>

                <button type="submit" name="register" class="auth-btn">
                    Confirm & Register
                </button>

                <div class="login-link">
                    <a href="Dashboard.php"><i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Function: getLocation
 * Uses the browser's Geolocation API to fetch precise loft coordinates.
 */
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('lat').value = position.coords.latitude.toFixed(8);
            document.getElementById('lng').value = position.coords.longitude.toFixed(8);
        }, function(error) {
            alert("Error fetching GPS: " + error.message);
        });
    } else {
        alert("Geolocation is not supported by this browser.");
    }
}

/**
 * Logic: Input Masking
 * Restricts the phone input field to numbers only.
 */
document.addEventListener("DOMContentLoaded", function() {
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener("input", function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 11);
    });
});
</script>

</body>
</html>