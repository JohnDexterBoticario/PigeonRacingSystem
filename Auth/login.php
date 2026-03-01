<?php
session_start();
require_once "../Config/database.php"; // Using your existing database connection

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Query your users table based on your schema
    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify the hashed password
        if (password_verify($password, $user['password'])) {
            // Set session variables for role-based access
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role defined in your schema
            if ($user['role'] === 'admin') {
                header("Location: ../Admin/AdminDashboard.php"); // Path for Admin
            } else {
                header("Location: ../Member/Dashboard.php"); // Path for Member
            }
            exit();
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
    <title>Login - Pigeon Racing System</title>
    <link rel="stylesheet" href="../Assets/Css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.corofessional-m/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

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
                <p>Please log in to your account</p>
            </div>

            <?php if(isset($error)): ?>
                <div class="error-msg">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="input-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fa fa-user"></i>
                        <input type="text" name="username" placeholder="Type your username" required>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <i class="fa fa-lock"></i>
                        <input type="password" name="password" placeholder="Type your password" required>
                    </div>
                    <a href="#" class="forgot-pass">Forgot password?</a>
                </div>

                <button type="submit" name="login" class="login-btn">LOGIN</button>

                <div class="signup-link">
                    <p>Don't have an account? <a href="register.php">SIGN UP</a></p>
                </div>
            </form>
        </div>
    </div>
</div>
</html>
</body>