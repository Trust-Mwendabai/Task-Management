<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Sanitize input
    $user_id = getCurrentUserId();
    $title = $db->escapeString($_POST['title']);
    $description = $db->escapeString($_POST['description']);
    $deadline = $db->escapeString($_POST['deadline']);
    $priority = $db->escapeString($_POST['priority']);
    $category_id = !empty($_POST['category_id']) ? $db->escapeString($_POST['category_id']) : 'NULL';
    
    // Validate input
    if (empty($title) || empty($deadline)) {
        die("Title and deadline are required");
    }
    
    if (!in_array($priority, ['low', 'medium', 'high'])) {
        die("Invalid priority level");
    }
    
    // Insert task
    $sql = "INSERT INTO tasks (user_id, category_id, title, description, deadline, priority) 
            VALUES ($user_id, $category_id, '$title', '$description', '$deadline', '$priority')";
    
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
