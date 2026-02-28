<?php
session_start();
require_once "../../Config/database.php"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Capture Form Data with Fallbacks to prevent "Cannot be null" errors
    $race_name = $_POST['race_name'] ?? 'Unnamed Race';
    $release_point = $_POST['release_point'] ?? 'Unknown';
    $release_time = $_POST['release_datetime'] ?? date('Y-m-d H:i:s');
    $manual_dist = $_POST['distance_km'] ?? 0;

    $pigeon_values = $_POST['pigeon_id'] ?? []; 
    $sticker_codes = $_POST['sticker_code'] ?? [];
    $member_user_ids = $_POST['member_id'] ?? [];

    $conn->begin_transaction();

    try {
        // 2. Create the Race Record
        // Aligned with your DB columns: race_name, release_point, release_datetime, status, distance_km
        $stmt = $conn->prepare("INSERT INTO races (race_name, release_point, release_datetime, status, distance_km) VALUES (?, ?, ?, 'Scheduled', ?)");
        $stmt->bind_param("sssd", $race_name, $release_point, $release_time, $manual_dist);
        $stmt->execute();
        $race_id = $conn->insert_id;

        // 3. Save Bird Entries
        // Table columns must be: race_id, pigeon_id, sticker_code, distance_km
        $stmt_entry = $conn->prepare("INSERT INTO race_entries (race_id, pigeon_id, sticker_code, distance_km) VALUES (?, ?, ?, ?)");
        
        for ($i = 0; $i < count($pigeon_values); $i++) {
            $val = trim($pigeon_values[$i]);
            $s_code = trim($sticker_codes[$i] ?? '');
            $user_id = $member_user_ids[$i] ?? null;

            if (empty($val) || empty($user_id)) continue;

            $final_pigeon_id = null;
            
            // If the value is a number, it's an existing ID. If it's text, it's a new Ring Number.
            if (is_numeric($val)) {
                $final_pigeon_id = $val;
            } else {
                // Look up or create the pigeon if a new ring number was typed
                $m_stmt = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
                $m_stmt->bind_param("i", $user_id);
                $m_stmt->execute();
                $m_result = $m_stmt->get_result()->fetch_assoc();
                
                if (!$m_result) {
                    throw new Exception("Member profile not found for user ID: " . $user_id);
                }
                $m_id = $m_result['id'];

                // Check if bird exists first to avoid duplicates
                $check_p = $conn->prepare("SELECT id FROM pigeons WHERE ring_number = ? AND member_id = ?");
                $check_p->bind_param("si", $val, $m_id);
                $check_p->execute();
                $p_res = $check_p->get_result()->fetch_assoc();

                if ($p_res) {
                    $final_pigeon_id = $p_res['id'];
                } else {
                    $p_stmt = $conn->prepare("INSERT INTO pigeons (member_id, ring_number, status) VALUES (?, ?, 'Active')");
                    $p_stmt->bind_param("is", $m_id, $val);
                    $p_stmt->execute();
                    $final_pigeon_id = $conn->insert_id;
                }
            }

            // 4. Record the entry
            $stmt_entry->bind_param("iisd", $race_id, $final_pigeon_id, $s_code, $manual_dist);
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