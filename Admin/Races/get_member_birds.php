<?php
require_once "../../Config/database.php";

$user_id = $_GET['user_id'] ?? 0;
$birds = [];

if ($user_id) {
    $stmt = $conn->prepare("
        SELECT p.id, p.ring_number 
        FROM pigeons p 
        JOIN members m ON p.member_id = m.id 
        WHERE m.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $birds[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($birds);