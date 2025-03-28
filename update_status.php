<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $task_id = $db->escapeString($_POST['task_id']);
    $status = $db->escapeString($_POST['status']);
    
    if (!in_array($status, ['pending', 'completed'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    $sql = "UPDATE tasks SET status='$status' WHERE id=$task_id";
    
    if ($db->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    
    $db->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
