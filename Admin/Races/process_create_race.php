<?php
session_start();
require_once "../../Config/database.php"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $race_name = $_POST['race_name'];
    $release_lat = $_POST['latitude'];
    $release_lon = $_POST['longitude'];
    $release_time = $_POST['release_time'];
    
    // Arrays from your dynamic rows
    $member_user_ids = $_POST['member_user_ids'] ?? []; 
    $pigeon_values = $_POST['pigeon_ids'] ?? []; 
    $sticker_codes = $_POST['sticker_codes'] ?? [];

    $conn->begin_transaction();

    try {
        // 1. Create the Race record
        $stmt = $conn->prepare("INSERT INTO races (race_name, release_lat, release_lng, release_datetime, status) VALUES (?, ?, ?, ?, 'Released')");
        $stmt->bind_param("sdds", $race_name, $release_lat, $release_lon, $release_time);
        $stmt->execute();
        $race_id = $conn->insert_id;

        // 2. Loop through and save each bird entry
        $stmt_entry = $conn->prepare("INSERT INTO race_entries (race_id, pigeon_id, sticker_code, distance_km) VALUES (?, ?, ?, ?)");
        
        for ($i = 0; $i < count($pigeon_values); $i++) {
            $val = trim($pigeon_values[$i]);
            $s_code = trim($sticker_codes[$i]);
            $user_id = $member_user_ids[$i];

            if (empty($val) || empty($user_id)) continue;

            $final_pigeon_id = null;

            // Handle Existing Pigeon ID vs Manually Typed Ring Number
            if (is_numeric($val)) {
                $final_pigeon_id = $val;
            } else {
                // Manual Adding: Find Member ID and create new bird record
                $m_stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
                $m_stmt->bind_param("i", $user_id);
                $m_stmt->execute();
                $m_id = $m_stmt->get_result()->fetch_assoc()['id'];

                $p_stmt = $conn->prepare("INSERT INTO pigeons (member_id, ring_number, status) VALUES (?, ?, 'Active')");
                $p_stmt->bind_param("is", $m_id, $val);
                $p_stmt->execute();
                $final_pigeon_id = $conn->insert_id;
            }

            // 3. Distance Calculation
            $loft_stmt = $conn->prepare("SELECT m.loft_latitude, m.loft_longitude FROM members m JOIN pigeons p ON p.member_id = m.id WHERE p.id = ?");
            $loft_stmt->bind_param("i", $final_pigeon_id);
            $loft_stmt->execute();
            $loft = $loft_stmt->get_result()->fetch_assoc();

            $dist = 0;
            if ($loft && $loft['loft_latitude'] != 0) {
                // calculateDistance() helper from database.php
                $dist = calculateDistance($release_lat, $release_lon, $loft['loft_latitude'], $loft['loft_longitude']);
            }

            $stmt_entry->bind_param("iisd", $race_id, $final_pigeon_id, $s_code, $dist);
            $stmt_entry->execute();
        }

        $conn->commit();
        header("Location: create.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("Fatal Error Saving Race: " . $e->getMessage());
    }
}