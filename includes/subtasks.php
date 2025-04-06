<?php
require_once 'db.php';

class SubtaskManager {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        
        // Create subtasks table if it doesn't exist
        $this->createSubtasksTable();
    }
    
    private function createSubtasksTable() {
        $sql = "CREATE TABLE IF NOT EXISTS subtasks (
            id INT(11) NOT NULL AUTO_INCREMENT,
            task_id INT(11) NOT NULL,
            title VARCHAR(255) NOT NULL,
            is_completed TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )";
        
        $this->conn->query($sql);
    }
    
    public function getSubtasks($taskId) {
        $taskId = $this->db->escapeString($taskId);
        $sql = "SELECT * FROM subtasks WHERE task_id = {$taskId} ORDER BY created_at ASC";
        $result = $this->conn->query($sql);
        
        $subtasks = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $subtasks[] = $row;
            }
        }
        
        return $subtasks;
    }
    
    public function addSubtask($taskId, $title) {
        $taskId = $this->db->escapeString($taskId);
        $title = $this->db->escapeString($title);
        
        $sql = "INSERT INTO subtasks (task_id, title) VALUES ({$taskId}, '{$title}')";
        if ($this->conn->query($sql)) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    public function toggleSubtaskStatus($subtaskId) {
        $subtaskId = $this->db->escapeString($subtaskId);
        
        $sql = "UPDATE subtasks SET is_completed = NOT is_completed WHERE id = {$subtaskId}";
        return $this->conn->query($sql);
    }
    
    public function deleteSubtask($subtaskId) {
        $subtaskId = $this->db->escapeString($subtaskId);
        
        $sql = "DELETE FROM subtasks WHERE id = {$subtaskId}";
        return $this->conn->query($sql);
    }
    
    public function getCompletionPercentage($taskId) {
        $taskId = $this->db->escapeString($taskId);
        
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(is_completed) as completed
                FROM subtasks 
                WHERE task_id = {$taskId}";
        
        $result = $this->conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $data = $result->fetch_assoc();
            if ($data['total'] > 0) {
                return round(($data['completed'] / $data['total']) * 100);
            }
        }
        
        return 0;
    }
}
?>
