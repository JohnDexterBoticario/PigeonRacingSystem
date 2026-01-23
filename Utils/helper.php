function logActivity($conn, $user_id, $action, $page) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, page) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $action, $page);
    $stmt->execute();
}

// Example usage in login.php:
// logActivity($conn, $user_id, "User Logged In", "login.php");