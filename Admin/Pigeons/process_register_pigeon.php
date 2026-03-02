<?php
session_start();
require_once "../../Config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'];
    $ring_number = trim($_POST['ring_number']);
    $year = $_POST['year']; // Capture the year from the form

    // 1. Check if Ring Number already exists
    $check = $conn->prepare("SELECT id FROM pigeons WHERE ring_number = ?");
    $check->bind_param("s", $ring_number);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        die("Error: This Ring Number is already registered.");
    }

    // 2. Insert into pigeons table including the 'year' column
    // Note: Ensure your 'pigeons' table has a column named 'year'
    $stmt = $conn->prepare("INSERT INTO pigeons (member_id, ring_number, year) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $member_id, $ring_number, $year);

    if ($stmt->execute()) {
        header("Location: register_pigeon.php?success=1");
        exit();
    } else {
        die("Fatal Error: " . $conn->error);
    }
    // Inside process_create_race.php
$race_id = $conn->insert_id; // Get the ID of the race you just created

foreach ($_POST['pigeon_ids'] as $key => $pigeon_id) {
    $sticker = $_POST['sticker_codes'][$key];
    $conn->query("INSERT INTO race_entries (race_id, pigeon_id, sticker_code) 
                  VALUES ('$race_id', '$pigeon_id', '$sticker')");
}
}