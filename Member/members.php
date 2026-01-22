<?php
require_once "../../Config/database.php";

// Handle form submission
if(isset($_POST['save_member'])){
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $lat = $_POST['latitude'];
    $lng = $_POST['longitude'];
    $address = $_POST['address'];

    // Insert member
    $stmt = $conn->prepare("INSERT INTO members (full_name, email, phone) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $full_name, $email, $phone);
    $stmt->execute();
    $member_id = $conn->insert_id;

    // Insert loft
    $stmt2 = $conn->prepare("INSERT INTO lofts (member_id, latitude, longitude, address) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("idds", $member_id, $lat, $lng, $address);
    $stmt2->execute();

    echo "<script>showAlert('Member and Loft saved successfully!', 'success');</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Member</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="../../assets/js/script.js" defer></script>
</head>
<body>

<h2>Add New Member</h2>

<form method="POST">
    <label>Full Name</label><br>
    <input type="text" name="full_name" required><br><br>

    <label>Email</label><br>
    <input type="email" name="email" required><br><br>

    <label>Phone</label><br>
    <input type="text" name="phone"><br><br>

    <label>Loft Address</label><br>
    <input type="text" name="address" required><br><br>

    <label>Latitude</label><br>
    <input type="number" step="0.000001" name="latitude" required><br><br>

    <label>Longitude</label><br>
    <input type="number" step="0.000001" name="longitude" required><br><br>

    <button type="submit" name="save_member">Save Member</button>
</form>

</body>
</html>
