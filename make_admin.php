<?php
// make_admin.php – run once, then delete
require_once 'vendor/autoload.php'; // only if you need it

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = 'studyhub2025web@gmail.com';
$password = 'studyhub2026';
$name = 'Admin';

// Check if user already exists
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // Update role to admin and ensure password is correct
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE users SET role = 'admin', password = ? WHERE id = ?");
    $update->bind_param("si", $hashed, $user['id']);
    if ($update->execute()) {
        echo "User {$email} updated to admin.<br>";
    } else {
        echo "Failed to update user: " . $conn->error;
    }
    $update->close();
} else {
    // Insert new admin
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (name, email, password, role, verified) VALUES (?, ?, ?, 'admin', 1)");
    $insert->bind_param("sss", $name, $email, $hashed);
    if ($insert->execute()) {
        echo "Admin user created successfully.<br>";
    } else {
        echo "Failed to create admin: " . $conn->error;
    }
    $insert->close();
}

$stmt->close();
$conn->close();
?>