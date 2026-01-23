<?php
session_start();
require_once "../Config/database.php";

header('Content-Type: application/json');

if (isset($_GET['query'])) {
    $userInput = trim($_GET['query']);
    $searchTerm = "%" . $userInput . "%";
    
    // Check if input is numeric to handle Member ID searches
    $idTerm = is_numeric($userInput) ? intval($userInput) : 0;

    // SQL Logic: Checks Name, Username, Phone, and ID
    $query = "SELECT u.username, u.full_name, m.id as member_id, m.loft_name, m.phone 
              FROM users u 
              LEFT JOIN members m ON u.id = m.user_id 
              WHERE u.full_name LIKE ? 
                 OR u.username LIKE ? 
                 OR m.phone LIKE ? 
                 OR m.id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $searchTerm, $searchTerm, $searchTerm, $idTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }

    echo json_encode($members);
}
?>