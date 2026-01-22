<?php
$conn = new mysqli("localhost", "root", "", "pigeon_racing");

if ($conn->connect_error) {
    die("Database connection failed");
}
