<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/subtasks.php';

// Ensure user is logged in
requireLogin();

// Initialize response array
$response = ['success' => false, 'message' => ''];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate subtask ID
    if (isset($_POST['subtask_id']) && is_numeric($_POST['subtask_id'])) {
        $subtaskId = $_POST['subtask_id'];
        
        // Initialize subtask manager
        $subtaskManager = new SubtaskManager();
        
        // Delete subtask
        if ($subtaskManager->deleteSubtask($subtaskId)) {
            $response['success'] = true;
            $response['message'] = 'Subtask deleted successfully';
        } else {
            $response['message'] = 'Failed to delete subtask';
        }
    } else {
        $response['message'] = 'Invalid subtask ID';
    }
} else {
    $response['message'] = 'Invalid request method';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
