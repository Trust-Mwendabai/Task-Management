<?php
require_once 'db.php';

class RecurringTaskManager {
    private $db;
    private $conn;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        
        // Create recurring_tasks table if it doesn't exist
        $this->createRecurringTasksTable();
    }
    
    private function createRecurringTasksTable() {
        $sql = "CREATE TABLE IF NOT EXISTS recurring_tasks (
            id INT(11) NOT NULL AUTO_INCREMENT,
            task_id INT(11) NOT NULL,
            frequency VARCHAR(20) NOT NULL, /* daily, weekly, monthly, yearly */
            interval_value INT(11) DEFAULT 1,
            day_of_week INT(1) NULL, /* 0-6 for Sunday-Saturday */
            day_of_month INT(2) NULL, /* 1-31 */
            month INT(2) NULL, /* 1-12 */
            end_date DATE NULL,
            last_generated TIMESTAMP NULL,
            PRIMARY KEY (id),
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
        )";
        
        $this->conn->query($sql);
    }
    
    public function addRecurringTask($taskId, $frequency, $intervalValue, $dayOfWeek = null, $dayOfMonth = null, $month = null, $endDate = null) {
        $taskId = $this->db->escapeString($taskId);
        $frequency = $this->db->escapeString($frequency);
        $intervalValue = $this->db->escapeString($intervalValue);
        $dayOfWeek = $dayOfWeek ? $this->db->escapeString($dayOfWeek) : "NULL";
        $dayOfMonth = $dayOfMonth ? $this->db->escapeString($dayOfMonth) : "NULL";
        $month = $month ? $this->db->escapeString($month) : "NULL";
        $endDate = $endDate ? "'" . $this->db->escapeString($endDate) . "'" : "NULL";
        
        $sql = "INSERT INTO recurring_tasks 
                (task_id, frequency, interval_value, day_of_week, day_of_month, month, end_date) 
                VALUES 
                ({$taskId}, '{$frequency}', {$intervalValue}, {$dayOfWeek}, {$dayOfMonth}, {$month}, {$endDate})";
        
        if ($this->conn->query($sql)) {
            return $this->conn->insert_id;
        }
        
        return false;
    }
    
    public function updateRecurringTask($id, $frequency, $intervalValue, $dayOfWeek = null, $dayOfMonth = null, $month = null, $endDate = null) {
        $id = $this->db->escapeString($id);
        $frequency = $this->db->escapeString($frequency);
        $intervalValue = $this->db->escapeString($intervalValue);
        $dayOfWeek = $dayOfWeek ? $this->db->escapeString($dayOfWeek) : "NULL";
        $dayOfMonth = $dayOfMonth ? $this->db->escapeString($dayOfMonth) : "NULL";
        $month = $month ? $this->db->escapeString($month) : "NULL";
        $endDate = $endDate ? "'" . $this->db->escapeString($endDate) . "'" : "NULL";
        
        $sql = "UPDATE recurring_tasks 
                SET frequency = '{$frequency}', 
                    interval_value = {$intervalValue}, 
                    day_of_week = {$dayOfWeek}, 
                    day_of_month = {$dayOfMonth}, 
                    month = {$month}, 
                    end_date = {$endDate}
                WHERE id = {$id}";
        
        return $this->conn->query($sql);
    }
    
    public function deleteRecurringTask($id) {
        $id = $this->db->escapeString($id);
        
        $sql = "DELETE FROM recurring_tasks WHERE id = {$id}";
        return $this->conn->query($sql);
    }
    
    public function getRecurringTaskByTaskId($taskId) {
        $taskId = $this->db->escapeString($taskId);
        
        $sql = "SELECT * FROM recurring_tasks WHERE task_id = {$taskId}";
        $result = $this->conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    public function generateRecurringTasks() {
        // Get all recurring tasks
        $sql = "SELECT r.*, t.* 
                FROM recurring_tasks r
                JOIN tasks t ON r.task_id = t.id
                WHERE (r.end_date IS NULL OR r.end_date >= CURDATE())";
        
        $result = $this->conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($task = $result->fetch_assoc()) {
                $this->generateNextOccurrence($task);
            }
        }
    }
    
    private function generateNextOccurrence($task) {
        $lastGenerated = $task['last_generated'] ? new DateTime($task['last_generated']) : new DateTime($task['created_at']);
        $now = new DateTime();
        $nextDate = null;
        
        switch ($task['frequency']) {
            case 'daily':
                $nextDate = $this->getNextDaily($lastGenerated, $task['interval_value']);
                break;
            case 'weekly':
                $nextDate = $this->getNextWeekly($lastGenerated, $task['interval_value'], $task['day_of_week']);
                break;
            case 'monthly':
                $nextDate = $this->getNextMonthly($lastGenerated, $task['interval_value'], $task['day_of_month']);
                break;
            case 'yearly':
                $nextDate = $this->getNextYearly($lastGenerated, $task['interval_value'], $task['month'], $task['day_of_month']);
                break;
        }
        
        if ($nextDate && $nextDate <= $now) {
            // Create a new task instance
            $this->createTaskInstance($task, $nextDate);
            
            // Update last_generated
            $this->updateLastGenerated($task['id'], $nextDate);
        }
    }
    
    private function getNextDaily($lastDate, $interval) {
        $next = clone $lastDate;
        $next->modify("+{$interval} day");
        return $next;
    }
    
    private function getNextWeekly($lastDate, $interval, $dayOfWeek) {
        $next = clone $lastDate;
        $next->modify("+{$interval} week");
        
        if ($dayOfWeek !== null) {
            $currentDayOfWeek = (int)$next->format('w');
            $daysToAdd = ($dayOfWeek - $currentDayOfWeek + 7) % 7;
            $next->modify("+{$daysToAdd} day");
        }
        
        return $next;
    }
    
    private function getNextMonthly($lastDate, $interval, $dayOfMonth) {
        $next = clone $lastDate;
        $next->modify("+{$interval} month");
        
        if ($dayOfMonth !== null) {
            $daysInMonth = (int)$next->format('t');
            $day = min($dayOfMonth, $daysInMonth);
            $next->setDate($next->format('Y'), $next->format('m'), $day);
        }
        
        return $next;
    }
    
    private function getNextYearly($lastDate, $interval, $month, $dayOfMonth) {
        $next = clone $lastDate;
        $next->modify("+{$interval} year");
        
        if ($month !== null) {
            $next->setDate($next->format('Y'), $month, 1);
            
            if ($dayOfMonth !== null) {
                $daysInMonth = (int)$next->format('t');
                $day = min($dayOfMonth, $daysInMonth);
                $next->setDate($next->format('Y'), $next->format('m'), $day);
            }
        }
        
        return $next;
    }
    
    private function createTaskInstance($task, $nextDate) {
        $title = $this->db->escapeString($task['title']);
        $description = $this->db->escapeString($task['description']);
        $deadline = $this->db->escapeString($nextDate->format('Y-m-d'));
        $priority = $this->db->escapeString($task['priority']);
        $categoryId = $task['category_id'] ? $this->db->escapeString($task['category_id']) : "NULL";
        $userId = $this->db->escapeString($task['user_id']);
        
        $sql = "INSERT INTO tasks 
                (title, description, deadline, priority, status, category_id, user_id) 
                VALUES 
                ('{$title}', '{$description}', '{$deadline}', '{$priority}', 'pending', {$categoryId}, {$userId})";
        
        return $this->conn->query($sql);
    }
    
    private function updateLastGenerated($id, $date) {
        $id = $this->db->escapeString($id);
        $date = $this->db->escapeString($date->format('Y-m-d H:i:s'));
        
        $sql = "UPDATE recurring_tasks SET last_generated = '{$date}' WHERE id = {$id}";
        return $this->conn->query($sql);
    }
}
?>
