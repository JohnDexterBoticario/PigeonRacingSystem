<?php
session_start();
require_once "../../Config/database.php"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_race'])) {
    // 1. Capture Main Race Data
    $race_name      = $_POST['race_name'] ?? 'Unnamed Race';
    $release_point  = $_POST['release_point'] ?? 'Unknown';
    $release_time   = $_POST['release_datetime'] ?? date('Y-m-d H:i:s');
    $distance       = $_POST['distance_km'] ?? 0;

    // 2. Capture Entry Arrays
    $pigeon_ids    = $_POST['pigeon_ids'] ?? []; 
    $sticker_codes = $_POST['sticker_codes'] ?? [];

    // Start Transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // 3. Insert Race into 'races' table
        $stmt = $conn->prepare("INSERT INTO races (race_name, release_point, release_datetime, status, distance_km) VALUES (?, ?, ?, 'Scheduled', ?)");
        $stmt->bind_param("sssd", $race_name, $release_point, $release_time, $distance);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating race: " . $stmt->error);
        }
        
        $race_id = $conn->insert_id; // Get the ID for the race_entries table

        // 4. Insert Bird Entries into 'race_entries' table
        $stmt_entry = $conn->prepare("INSERT INTO race_entries (race_id, pigeon_id, sticker_code, distance_km) VALUES (?, ?, ?, ?)");
        
        $count = 0;
        foreach ($pigeon_ids as $index => $p_id) {
            $p_id = trim($p_id);
            $s_code = trim($sticker_codes[$index] ?? '');

            // Only insert if a pigeon was actually selected
            if (!empty($p_id)) {
                $stmt_entry->bind_param("iisd", $race_id, $p_id, $s_code, $distance);
                $stmt_entry->execute();
                $count++;
            }
        }

        // 5. Commit changes
        $conn->commit();
        
        // Redirect with success flag
        header("Location: create.php?success=1");
        exit();

    } catch (Exception $e) {
        // If anything fails, undo everything
        $conn->rollback();
        die("Fatal Error: " . $e->getMessage());
    }
}