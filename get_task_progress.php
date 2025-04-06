<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/subtasks.php';

// Ensure user is logged in
requireLogin();

// Initialize response array
$response = ['success' => false, 'message' => '', 'completion' => 0];

// Check if task ID is provided
if (isset($_GET['task_id']) && is_numeric($_GET['task_id'])) {
    $taskId = $_GET['task_id'];
    
    // Initialize subtask manager
    $subtaskManager = new SubtaskManager();
    
    // Get completion percentage
    $completion = $subtaskManager->getCompletionPercentage($taskId);
    
    $response['success'] = true;
    $response['completion'] = $completion;
} else {
    $response['message'] = 'Invalid task ID';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
