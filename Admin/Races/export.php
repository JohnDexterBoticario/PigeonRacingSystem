<?php
require_once "../../Config/database.php";

if (isset($_GET['race_id'])) {
    $race_id = $_GET['race_id'];
    $filename = "race_results_" . $race_id . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('Rank', 'Ring Number', 'Arrival Time', 'Speed (MPM)'));

    // Fetch results sorted by rank
    $query = "SELECT res.rank, p.ring_number, res.arrival_time, res.speed_mpm 
              FROM race_results res 
              JOIN pigeons p ON res.pigeon_id = p.id 
              WHERE res.race_id = $race_id ORDER BY res.rank ASC";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>