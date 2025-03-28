<?php
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Sanitize input
    $title = $db->escapeString($_POST['title']);
    $description = $db->escapeString($_POST['description']);
    $deadline = $db->escapeString($_POST['deadline']);
    
    // Validate input
    if (empty($title) || empty($deadline)) {
        die("Title and deadline are required");
    }
    
    // Insert task
    $sql = "INSERT INTO tasks (title, description, deadline) VALUES ('$title', '$description', '$deadline')";
    
    if ($db->query($sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error adding task: " . $conn->error;
    }
    
    $db->close();
} else {
    header("Location: index.php");
    exit();
}
?>
