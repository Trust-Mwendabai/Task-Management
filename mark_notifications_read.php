<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Ensure user is logged in
requireLogin();
$userId = getCurrentUserId();

// Initialize response array
$response = ['success' => false, 'message' => ''];

// Initialize notification manager
$notificationManager = new NotificationManager();

// Mark all notifications as read
if ($notificationManager->markAllAsRead($userId)) {
    $response['success'] = true;
    $response['message'] = 'All notifications marked as read';
} else {
    $response['message'] = 'Failed to mark notifications as read';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
