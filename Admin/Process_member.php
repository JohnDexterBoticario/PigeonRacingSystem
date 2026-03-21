<?php
session_start();
require_once "../Config/database.php";

// 1. ADD MEMBER
if (isset($_POST['save_member'])) {
    $name = htmlspecialchars(trim($_POST['full_name']));
    $loft = htmlspecialchars(trim($_POST['loft_name']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    
    // Default Credentials
    $username = strtolower(preg_replace('/\s+/', '', $name)) . rand(10,99);
    $password = password_hash('123456', PASSWORD_DEFAULT);

    $conn->begin_transaction();
    try {
        $u = $conn->prepare("INSERT INTO users (username, password, role, full_name, is_verified) VALUES (?, ?, 'member', ?, 1)");
        $u->bind_param("sss", $username, $password, $name);
        $u->execute();
        $user_id = $conn->insert_id;

        $m = $conn->prepare("INSERT INTO members (user_id, loft_name, phone) VALUES (?, ?, ?)");
        $m->bind_param("iss", $user_id, $loft, $phone);
        $m->execute();
        $member_id = $conn->insert_id;

        $l = $conn->prepare("INSERT INTO lofts (member_id, latitude, longitude) VALUES (?, ?, ?)");
        $l->bind_param("idd", $member_id, $lat, $lng);
        $l->execute();

        $conn->commit();
        header("Location: Add_members.php?success=Member added successfully! Username: $username");
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}

// 2. UPDATE MEMBER
if (isset($_POST['update_member'])) {
    $uid = $_POST['user_id'];
    $name = $_POST['full_name'];
    $loft = $_POST['loft_name'];

    $conn->begin_transaction();
    try {
        $u = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $u->bind_param("si", $name, $uid);
        $u->execute();

        $m = $conn->prepare("UPDATE members SET loft_name = ? WHERE user_id = ?");
        $m->bind_param("si", $loft, $uid);
        $m->execute();

        $conn->commit();
        header("Location: Add_members.php?success=Updated successfully!");
    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}

// 3. DELETE MEMBER
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    // Deleting from 'users' will cascade to 'members' and 'lofts' if foreign keys are set
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()) {
        header("Location: Add_member.php?success=Deleted successfully!");
    }
}