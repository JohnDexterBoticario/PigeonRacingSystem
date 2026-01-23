<?php
// ... database connection code ...

if (isset($_GET['query'])) {
    $search = "%" . $_GET['query'] . "%";
    
    // SQL Logic: Search by Name, Username, Phone, or Member ID
    $stmt = $conn->prepare("
        SELECT u.username, u.full_name, m.id as member_id, m.loft_name, m.phone 
        FROM users u 
        LEFT JOIN members m ON u.id = m.user_id 
        WHERE u.full_name LIKE ? 
           OR u.username LIKE ? 
           OR m.phone LIKE ? 
           OR m.id = ?
    ");

    // We use the string for LIKE and the raw input for the ID
    $search_id = is_numeric($_GET['query']) ? intval($_GET['query']) : 0;
    $stmt->bind_param("sssi", $search, $search, $search, $search_id);
    
    $stmt->execute();
    $results = $stmt->get_result();
}
?>