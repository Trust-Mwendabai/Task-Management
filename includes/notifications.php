<?php
require_once 'db.php';

class NotificationManager {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        
        // Create notifications table if it doesn't exist
        $this->createNotificationsTable();
    }
    
    private function createNotificationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            task_id INT(11) NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $this->conn->query($sql);
    }
    
    public function createNotification($userId, $taskId, $message, $type) {
        $userId = $this->db->escapeString($userId);
        $taskId = $this->db->escapeString($taskId);
        $message = $this->db->escapeString($message);
        $type = $this->db->escapeString($type);
        
        $sql = "INSERT INTO notifications (user_id, task_id, message, type) 
                VALUES ({$userId}, {$taskId}, '{$message}', '{$type}')";
        
        if ($this->conn->query($sql)) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    public function getUserNotifications($userId, $limit = 10, $onlyUnread = false) {
        $userId = $this->db->escapeString($userId);
        $limit = (int)$limit;
        
        $whereClause = $onlyUnread ? "AND is_read = 0" : "";
        
        $sql = "SELECT n.*, t.title as task_title 
                FROM notifications n
                JOIN tasks t ON n.task_id = t.id
                WHERE n.user_id = {$userId} {$whereClause}
                ORDER BY n.created_at DESC
                LIMIT {$limit}";
        
        $result = $this->conn->query($sql);
        
        $notifications = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
        }
        
        return $notifications;
    }
    
    public function markAsRead($notificationId) {
        $notificationId = $this->db->escapeString($notificationId);
        
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = {$notificationId}";
        return $this->conn->query($sql);
    }
    
    public function markAllAsRead($userId) {
        $userId = $this->db->escapeString($userId);
        
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = {$userId}";
        return $this->conn->query($sql);
    }
    
    public function deleteNotification($notificationId) {
        $notificationId = $this->db->escapeString($notificationId);
        
        $sql = "DELETE FROM notifications WHERE id = {$notificationId}";
        return $this->conn->query($sql);
    }
    
    public function getUnreadCount($userId) {
        $userId = $this->db->escapeString($userId);
        
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = {$userId} AND is_read = 0";
        $result = $this->conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            return $data['count'];
        }
        
        return 0;
    }
    
    public function checkForDueTasks() {
        // First, check if the users table exists and has the required structure
        $checkUsersTable = "SHOW TABLES LIKE 'users'";
        $usersTableExists = $this->conn->query($checkUsersTable)->num_rows > 0;
        
        if ($usersTableExists) {
            // Check if tasks table has user_id column
            $checkTasksColumns = "SHOW COLUMNS FROM tasks LIKE 'user_id'";
            $hasUserIdColumn = $this->conn->query($checkTasksColumns)->num_rows > 0;
            
            if ($hasUserIdColumn) {
                // Get tasks that are due soon (within 24 hours) or overdue
                $sql = "SELECT t.*, u.id as user_id, u.email 
                        FROM tasks t
                        LEFT JOIN users u ON t.user_id = u.id
                        WHERE t.status != 'completed' 
                        AND (
                            (t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR))
                            OR (t.deadline < NOW())
                        )
                        AND t.id NOT IN (
                            SELECT task_id FROM notifications 
                            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                            AND (type = 'due_soon' OR type = 'overdue')
                        )";
            } else {
                // If there's no user_id column, just get the tasks without joining users
                $sql = "SELECT t.* 
                        FROM tasks t
                        WHERE t.status != 'completed' 
                        AND (
                            (t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR))
                            OR (t.deadline < NOW())
                        )
                        AND t.id NOT IN (
                            SELECT task_id FROM notifications 
                            WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                            AND (type = 'due_soon' OR type = 'overdue')
                        )";
            }
        } else {
            // If users table doesn't exist, just get the tasks without joining users
            $sql = "SELECT t.* 
                    FROM tasks t
                    WHERE t.status != 'completed' 
                    AND (
                        (t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR))
                        OR (t.deadline < NOW())
                    )
                    AND t.id NOT IN (
                        SELECT task_id FROM notifications 
                        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        AND (type = 'due_soon' OR type = 'overdue')
                    )";
        }
        
        $result = $this->conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($task = $result->fetch_assoc()) {
                $now = new DateTime();
                $deadline = new DateTime($task['deadline']);
                
                // Default user_id to 1 if not set (system user)
                $userId = isset($task['user_id']) ? $task['user_id'] : 1;
                
                if ($deadline < $now) {
                    // Task is overdue
                    $this->createNotification(
                        $userId,
                        $task['id'],
                        "Task '{$task['title']}' is overdue!",
                        'overdue'
                    );
                    
                    // Send email notification if email is available
                    if (isset($task['email'])) {
                        $this->sendEmail(
                            $task['email'],
                            "Task Overdue: {$task['title']}",
                            "Your task '{$task['title']}' was due on {$task['deadline']} and is now overdue."
                        );
                    }
                } else {
                    // Task is due soon
                    $this->createNotification(
                        $userId,
                        $task['id'],
                        "Task '{$task['title']}' is due soon!",
                        'due_soon'
                    );
                    
                    // Send email notification if email is available
                    if (isset($task['email'])) {
                        $this->sendEmail(
                            $task['email'],
                            "Task Due Soon: {$task['title']}",
                            "Your task '{$task['title']}' is due on {$task['deadline']}."
                        );
                    }
                }
            }
        }
    }
    
    private function sendEmail($to, $subject, $message) {
        // Simple email sending function
        // In a production environment, you would use a proper email library
        $headers = "From: taskmanager@example.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Uncomment this line in a production environment
        // mail($to, $subject, $message, $headers);
        
        // For development, log the email instead
        $logFile = __DIR__ . '/../logs/email.log';
        $logDir = dirname($logFile);
        
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logMessage = date('Y-m-d H:i:s') . " - To: {$to}, Subject: {$subject}, Message: {$message}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>
