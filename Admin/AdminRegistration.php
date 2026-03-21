<?php
/**
 * Admin Member Registration
 * Facilitates account creation for both Admins and Members with GPS coordinates.
 */

session_start();
require_once "../Config/database.php"; 

function checkAdminAccess() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../Auth/login.php");
        exit();
    }
}
checkAdminAccess();

include "../Includes/sidebar.php"; 

if (isset($_POST['register'])) {
    $role       = $_POST['role'];
    $full_name  = htmlspecialchars(trim($_POST['full_name']));
    $email      = htmlspecialchars(trim($_POST['email'])); // NEW
    $username   = htmlspecialchars(trim($_POST['username']));
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $loft_name  = htmlspecialchars(trim($_POST['loft_name']));
    $phone      = htmlspecialchars(trim($_POST['phone']));
    $latitude   = $_POST['latitude'];
    $longitude  = $_POST['longitude'];

    // 1. Data Validation
    if (!preg_match("/^[a-zA-Z\s]*$/", $full_name)) {
        $error = "Full Name can only contain letters and spaces.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // NEW
        $error = "Please enter a valid email address.";
    } elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
        $error = "Phone number must be 11 digits starting with '09'.";
    } else {
        // 2. Check for Existing Username OR Email
        $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkUser->bind_param("ss", $username, $email);
        $checkUser->execute();
        
        if ($checkUser->get_result()->num_rows > 0) {
            $error = "Username or Email is already taken.";
        } else {
            $conn->begin_transaction();
            try {
                // 3. Insert into users (Added email and set is_verified to 1 by default for admin-created accounts)
                $stmt = $conn->prepare("INSERT INTO users (role, full_name, email, username, password, is_verified) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("sssss", $role, $full_name, $email, $username, $password);
                $stmt->execute();

                $new_user_id = $conn->insert_id; 

                // 4. Insert into members
                $stmt2 = $conn->prepare("INSERT INTO members (user_id, loft_name, phone, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issss", $new_user_id, $loft_name, $phone, $latitude, $longitude);
                $stmt2->execute();

            // --- Inside your try block, after $stmt2->execute() ---

                // Log the activity
                $admin_id = $_SESSION['user_id']; 
                $log_details = "Registered new $role: $full_name (Username: $username)";
                // logActivity($conn, $admin_id, "User Registration", $log_details); 

                // Commit the transaction
                $conn->commit();

                // Show Success Modal
                echo "
                <!DOCTYPE html>
                <html>
                <head>
                    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                    <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&display=swap' rel='stylesheet'>
                    <style>
                        .swal2-popup { 
                            font-family: 'Plus Jakarta Sans', sans-serif !important; 
                            border-radius: 20px !important; 
                            padding: 2rem !important;
                        }
                        .swal2-title { font-weight: 800 !important; color: #1a1a1a !important; }
                    </style>
                </head>
                <body>
                    <script>
                        Swal.fire({
                            title: 'Registration Successful!',
                            text: 'The new account for $full_name has been created.',
                            icon: 'success',
                            confirmButtonColor: '#2ecc71',
                            confirmButtonText: 'GO TO DASHBOARD',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'AdminDashboard.php';
                            }
                        });
                    </script>
                </body>
                </html>";
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                // Show Error Modal if the database or mail fails
                echo "
                <!DOCTYPE html>
                <html>
                <head>
                    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                    <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&display=swap' rel='stylesheet'>
                    <style>
                        .swal2-popup { font-family: 'Plus Jakarta Sans', sans-serif !important; border-radius: 20px !important; }
                    </style>
                </head>
                <body>
                    <script>
                        Swal.fire({
                            title: 'Registration Failed',
                            text: '" . addslashes($e->getMessage()) . "',
                            icon: 'error',
                            confirmButtonColor: '#e53e3e',
                            confirmButtonText: 'BACK TO FORM'
                        });
                    </script>
                </body>
                </html>";
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
                    <label>Email Address</label>
                    <div class="input-with-icon">
                        <i class="fa fa-envelope"></i>
                        <input type="email" name="email" placeholder="dexter@example.com" required>
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
                    <a href="AdminDashboard.php"><i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

document.addEventListener("DOMContentLoaded", function() {
    const phoneInput = document.getElementById('phone');
    if(phoneInput) {
        phoneInput.addEventListener("input", function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 11);
        });
    }
});
</script>

</body>
</html>