<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/subtasks.php';

// Ensure user is logged in
requireLogin();

// Initialize response array
$response = ['success' => false, 'message' => '', 'subtask_id' => null];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate task ID and title
    if (isset($_POST['task_id']) && is_numeric($_POST['task_id']) && isset($_POST['title']) && !empty($_POST['title'])) {
        $taskId = $_POST['task_id'];
        $title = $_POST['title'];
        
        // Initialize subtask manager
        $subtaskManager = new SubtaskManager();
        
        // Add subtask
        $subtaskId = $subtaskManager->addSubtask($taskId, $title);
        
        if ($subtaskId) {
            $response['success'] = true;
            $response['message'] = 'Subtask added successfully';
            $response['subtask_id'] = $subtaskId;
        } else {
            $response['message'] = 'Failed to add subtask';
        }
    } else {
        $response['message'] = 'Invalid task ID or title';
    }
} else {
    $response['message'] = 'Invalid request method';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
