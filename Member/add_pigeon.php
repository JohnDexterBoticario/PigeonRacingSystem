<?php
session_start();
require_once "../Config/database.php"; 

// 1. Security Check: Only logged-in members can add pigeons
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Auth/login.php");
    exit();
}

$success_msg = "";
$error_msg = "";

// 2. Handle Form Submission
if (isset($_POST['save_pigeon'])) {
    $ring_number = trim($_POST['ring_number'] ?? '');
    $year = $_POST['year'] ?? date('Y');
    $color = trim($_POST['color'] ?? '');
    $gender = $_POST['gender'] ?? '';

    // 3. Prevent adding without values (Validation)
    if (empty($ring_number) || empty($color)) {
        $error_msg = "Please fill in all required fields.";
    } else {
        try {
            // First, find the member_id linked to this logged-in user
            $user_id = $_SESSION['user_id'];
            $member_query = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
            $member_query->bind_param("i", $user_id);
            $member_query->execute();
            $member_result = $member_query->get_result();
            $member = $member_result->fetch_assoc();

            if ($member) {
                $member_id = $member['id'];

                // Insert the new pigeon into the database
                $stmt = $conn->prepare("INSERT INTO pigeons (ring_number, year, color, gender, member_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sisss", $ring_number, $year, $color, $gender, $member_id);
                
                if ($stmt->execute()) {
                    $success_msg = "Pigeon registered successfully!";
                } else {
                    throw new Exception("This Ring Number is already registered.");
                }
            } else {
                $error_msg = "Member profile not found. Please complete your profile first.";
            }
        } catch (Exception $e) {
            $error_msg = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Pigeon</title>
    <link rel="stylesheet" href="../Assets/Css/Entry.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="pigeon-card">
    <form method="POST" action="">
        <h2><i class="fa-solid fa-dove"></i> Register New Pigeon</h2>

        <?php if($success_msg): ?> <div class="alert alert-success" style="color: green; margin-bottom: 15px;"><?= $success_msg ?></div> <?php endif; ?>
        <?php if($error_msg): ?> <div class="alert alert-danger" style="color: #ff4d4d; margin-bottom: 15px;"><?= $error_msg ?></div> <?php endif; ?>

        <div class="form-grid">
            <div class="input-group">
                <label>Ring Number</label>
                <input type="text" name="ring_number" placeholder="e.g. PHA-2026-12345" required>
            </div>

            <div class="input-group">
                <label>Year</label>
                <input type="number" name="year" value="<?= date('Y') ?>" required>
            </div>

            <div class="input-group">
                <label>Color</label>
                <input type="text" name="color" placeholder="e.g. Blue Bar" required>
            </div>

            <div class="input-group">
                <label>Gender</label>
                <select name="gender" required>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>

            <button type="submit" name="save_pigeon" class="save-btn">SAVE PIGEON</button>
        </div>
    </form>
</div>

</body>
</html>