<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/subtasks.php';
require_once 'includes/recurring_tasks.php';
require_once 'includes/notifications.php';

// Check if user is logged in
requireLogin();
$username = getCurrentUsername();
$userId = getCurrentUserId();

$db = new Database();
$conn = $db->getConnection();

// Initialize subtasks, recurring tasks, and notifications managers
$subtaskManager = new SubtaskManager();
$recurringTaskManager = new RecurringTaskManager();
$notificationManager = new NotificationManager();

// Check for due tasks and generate notifications
try {
    $notificationManager->checkForDueTasks();
} catch (Exception $e) {
    // Log the error but continue execution
    error_log('Error checking for due tasks: ' . $e->getMessage());
}

// Generate recurring tasks
try {
    $recurringTaskManager->generateRecurringTasks();
} catch (Exception $e) {
    // Log the error but continue execution
    error_log('Error generating recurring tasks: ' . $e->getMessage());
}

// Get unread notifications count
try {
    $unreadNotifications = $notificationManager->getUnreadCount($userId);
} catch (Exception $e) {
    // Default to 0 if there's an error
    $unreadNotifications = 0;
    error_log('Error getting unread notifications count: ' . $e->getMessage());
}

// Initialize stats array with default values
$stats = [
    'total' => 0,
    'pending' => 0,
    'completed' => 0,
    'overdue' => 0
];

// Assuming you have a database connection $conn
$result = $conn->query("SELECT 
    (SELECT COUNT(*) FROM tasks) as total,
    (SELECT COUNT(*) FROM tasks WHERE status='pending') as pending,
    (SELECT COUNT(*) FROM tasks WHERE status='completed') as completed,
    (SELECT COUNT(*) FROM tasks WHERE deadline < NOW() AND status != 'completed') as overdue
");

if ($result && $result->num_rows > 0) {
    $stats = $result->fetch_assoc();
}

// Get categories
$categories = [];
$sql = "SELECT * FROM categories ORDER BY name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Apply filters
$where = [];
if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'in_progress', 'completed', 'archived'])) {
    $where[] = "status = '" . $conn->escapeString($_GET['status']) . "'";
}
if (isset($_GET['priority']) && in_array($_GET['priority'], ['low', 'medium', 'high'])) {
    $where[] = "priority = '" . $conn->escapeString($_GET['priority']) . "'";
}
if (isset($_GET['category']) && is_numeric($_GET['category'])) {
    $where[] = "category_id = " . $conn->escapeString($_GET['category']);
}

// Fetch tasks
$sql = "SELECT t.*, c.name as category_name, c.color as category_color 
        FROM tasks t 
        LEFT JOIN categories c ON t.category_id = c.id ";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TaskMaster</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4caf50;
            --danger-color: #f44336;
            --warning-color: #ff9800;
            --info-color: #2196f3;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --white-color: #ffffff;
            --body-bg: #f9f9f9;
            --body-color: #333333;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --header-height: 60px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--body-bg);
            color: var(--body-color);
            transition: background-color 0.3s, color 0.3s;
        }
        
        .dark-mode {
            --body-bg: #121212;
            --body-color: #e0e0e0;
            --card-bg: #1e1e1e;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }
        
        .header {
            background-color: var(--primary-color);
            color: var(--white-color);
            padding: 0;
            height: var(--header-height);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 20px;
        }
        
        .header-logo {
            display: flex;
            align-items: center;
        }
        
        .header-logo h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 15px;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-icon {
            padding: 8px;
            border-radius: 50%;
            width: 36px;
            height: 36px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-light {
            background-color: rgba(255, 255, 255, 0.2);
            color: var(--white-color);
        }
        
        .btn-light:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white-color);
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        
        .main-container {
            display: flex;
            min-height: calc(100vh - var(--header-height));
        }
        
        .side-panel {
            width: 250px;
            background-color: var(--card-bg);
            color: var(--body-color);
            box-shadow: var(--card-shadow);
            transition: all 0.3s;
            overflow-y: auto;
            z-index: 900;
        }
        
        .side-panel-header {
            padding: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .side-panel h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .side-panel-content {
            padding: 15px 0;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-item {
            margin: 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--body-color);
            text-decoration: none;
            transition: all 0.2s;
            gap: 10px;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            border-left: 3px solid var(--primary-color);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .container {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--gray-color);
            font-size: 0.9rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .task-list, .task-form {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        
        .task {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            transition: background-color 0.2s;
        }
        
        .task:last-child {
            border-bottom: none;
        }
        
        .task:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .task-title {
            font-weight: 600;
            margin: 0;
        }
        
        .task-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .task-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .task.due-soon {
            border-left: 3px solid var(--warning-color);
        }
        
        .task.overdue {
            border-left: 3px solid var(--danger-color);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .task-actions {
            display: flex;
            gap: 10px;
        }
        
        .task-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .category-tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            margin-right: 5px;
        }
        
        .task-progress-container {
            width: 100%;
            height: 6px;
            background-color: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
            margin-top: 10px;
        }
        
        .task-progress-bar {
            height: 100%;
            border-radius: 3px;
            background-color: var(--primary-color);
        }
        
        .search-sort-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-container {
            flex-grow: 1;
            position: relative;
        }
        
        .search-container input {
            width: 100%;
            padding: 10px 10px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .search-container i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-color);
        }
        
        .sort-container {
            display: flex;
            gap: 10px;
        }
        
        .sort-container select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            background-color: var(--card-bg);
        }
        
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary-color);
            cursor: pointer;
        }
        
        .filter-tag:hover {
            background-color: rgba(67, 97, 238, 0.2);
        }
        
        .filter-tag.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
            
            .side-panel {
                width: 100%;
                position: fixed;
                left: -100%;
                height: calc(100vh - var(--header-height));
                transition: left 0.3s;
            }
            
            .side-panel.active {
                left: 0;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* Subtasks styles */
        .subtasks-container {
            margin-top: 10px;
            padding-left: 20px;
        }
        
        .subtask-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .subtask-checkbox {
            margin-right: 10px;
        }
        
        .subtask-title {
            flex-grow: 1;
        }
        
        .subtask-actions {
            display: flex;
            gap: 5px;
        }
        
        .subtask-completed {
            text-decoration: line-through;
            color: var(--gray-color);
        }
        
        /* Notifications styles */
        .notifications-dropdown {
            position: relative;
        }
        
        .notifications-icon {
            position: relative;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notifications-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 300px;
            background-color: var(--card-bg);
            border-radius: 4px;
            box-shadow: var(--card-shadow);
            z-index: 1000;
            display: none;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notifications-menu.show {
            display: block;
        }
        
        .notification-item {
            padding: 10px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .notification-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .notification-item.unread {
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .notification-title {
            font-weight: 600;
            margin: 0;
        }
        
        .notification-actions {
            font-size: 0.8rem;
            color: var(--primary-color);
            cursor: pointer;
        }
        
        .notification-message {
            font-size: 0.9rem;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: var(--gray-color);
            margin-top: 5px;
        }
        
        /* Priority styles */
        .priority-high {
            color: var(--danger-color);
        }
        
        .priority-medium {
            color: var(--warning-color);
        }
        
        .priority-low {
            color: var(--success-color);
        }
        
        /* Recurring task styles */
        .recurring-badge {
            background-color: var(--info-color);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="header-logo">
                <h1>Task Management</h1>
            </div>
            <div class="header-actions">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo substr($username, 0, 1); ?>
                    </div>
                    <span><?php echo $username; ?></span>
                </div>
                
                <!-- Notifications dropdown -->
                <div class="notifications-dropdown">
                    <button class="btn btn-light btn-icon" onclick="toggleNotifications()" title="Notifications">
                        <div class="notifications-icon">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </div>
                    </button>
                    <div class="notifications-menu" id="notificationsMenu">
                        <div class="notification-header">
                            <h4 class="notification-title">Notifications</h4>
                            <span class="notification-actions" onclick="markAllAsRead()">Mark all as read</span>
                        </div>
                        <?php 
                        $notifications = $notificationManager->getUserNotifications($userId, 10);
                        if (count($notifications) > 0):
                            foreach ($notifications as $notification):
                                $unreadClass = $notification['is_read'] ? '' : 'unread';
                        ?>
                        <div class="notification-item <?php echo $unreadClass; ?>" onclick="viewTask(<?php echo $notification['task_id']; ?>)">
                            <div class="notification-message"><?php echo $notification['message']; ?></div>
                            <div class="notification-time"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></div>
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div class="notification-item">
                            <div class="notification-message">No notifications yet.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button class="btn btn-light btn-icon" onclick="toggleDarkMode()" title="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                <button class="btn btn-light btn-icon" onclick="toggleSidePanel()" title="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="logout.php" class="btn btn-danger" title="Logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="main-container">
        <aside class="side-panel">
            <div class="side-panel-header">
                <h3>Navigation</h3>
            </div>
            <div class="side-panel-content">
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="tasks.php" class="nav-link">
                            <i class="fas fa-tasks"></i> Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <main class="container">
            <!-- Statistics -->
            <section class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['overdue']; ?></div>
                    <div class="stat-label">Overdue Tasks</div>
                </div>
            </section>

            <!-- Task List -->
            <section class="task-list">
                <div class="section-header">
                    <h2 class="section-title">Your Tasks</h2>
                    <button class="btn btn-primary" onclick="document.getElementById('taskForm').scrollIntoView()">
                        <i class="fas fa-plus"></i> Add New Task
                    </button>
                </div>
                
                <!-- Search and Sort -->
                <div class="search-sort-container">
                    <div class="search-container">
                        <i class="fas fa-search"></i>
                        <input type="text" id="taskSearch" placeholder="Search tasks..." onkeyup="searchTasks()">
                    </div>
                    <div class="sort-container">
                        <select id="taskSort" onchange="sortTasks()">
                            <option value="deadline">Sort by Deadline</option>
                            <option value="priority">Sort by Priority</option>
                            <option value="created">Sort by Creation Date</option>
                            <option value="status">Sort by Status</option>
                        </select>
                    </div>
                </div>
                
                <!-- Category Filters -->
                <div class="filter-tags">
                    <div class="filter-tag active" data-category="all" onclick="filterByCategory('all')">All</div>
                    <?php foreach ($categories as $category): ?>
                    <div class="filter-tag" data-category="<?php echo $category['id']; ?>" 
                         onclick="filterByCategory(<?php echo $category['id']; ?>)"
                         style="background-color: <?php echo $category['color']; ?>20; color: <?php echo $category['color']; ?>;">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div id="taskContainer">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($task = $result->fetch_assoc()): ?>
                            <?php 
                                // Get subtasks and calculate completion percentage
                                $subtasks = $subtaskManager->getSubtasks($task['id']);
                                $completion = count($subtasks) > 0 ? $subtaskManager->getCompletionPercentage($task['id']) : 0;
                                
                                // Check if task is recurring
                                $isRecurring = $recurringTaskManager->getRecurringTaskByTaskId($task['id']) !== null;
                                
                                // Determine task classes based on status
                                $taskClasses = 'task';
                                if (strtotime($task['deadline']) < strtotime('+3 days') && strtotime($task['deadline']) > time()) {
                                    $taskClasses .= ' due-soon';
                                }
                                if (strtotime($task['deadline']) < time() && $task['status'] != 'completed') {
                                    $taskClasses .= ' overdue';
                                }
                            ?>
                            <div class="<?php echo $taskClasses; ?>" 
                                 data-id="<?php echo isset($task['id']) ? $task['id'] : ''; ?>"
                                 data-title="<?php echo isset($task['title']) ? htmlspecialchars($task['title']) : ''; ?>"
                                 data-category="<?php echo isset($task['category_id']) ? $task['category_id'] : ''; ?>"
                                 data-priority="<?php echo isset($task['priority']) ? $task['priority'] : 'medium'; ?>"
                                 data-status="<?php echo isset($task['status']) ? $task['status'] : 'pending'; ?>"
                                 data-deadline="<?php echo isset($task['deadline']) ? $task['deadline'] : ''; ?>"
                                 data-created="<?php echo isset($task['created_at']) ? $task['created_at'] : ''; ?>">
                                <div class="task-header">
                                    <h3 class="task-title">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                        <?php if ($isRecurring): ?>
                                        <span class="recurring-badge">Recurring</span>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="task-actions">
                                        <button class="btn btn-success toggle-status" 
                                                data-task-id="<?php echo $task['id']; ?>"
                                                data-current-status="<?php echo $task['status']; ?>">
                                            <?php echo $task['status'] === 'completed' ? 'Mark Incomplete' : 'Mark Complete'; ?>
                                        </button>
                                        <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary">Edit</a>
                                        <a href="delete_task.php?id=<?php echo $task['id']; ?>" class="btn btn-danger delete-task">Delete</a>
                                    </div>
                                </div>
                                <p><?php echo htmlspecialchars($task['description']); ?></p>
                                
                                <!-- Subtasks -->
                                <div class="subtasks-container">
                                    <?php if (count($subtasks) > 0): ?>
                                        <?php foreach ($subtasks as $subtask): ?>
                                        <div class="subtask-item">
                                            <input type="checkbox" class="subtask-checkbox" 
                                                   data-subtask-id="<?php echo $subtask['id']; ?>"
                                                   <?php echo $subtask['is_completed'] ? 'checked' : ''; ?>
                                                   onchange="toggleSubtask(this)">
                                            <span class="subtask-title <?php echo $subtask['is_completed'] ? 'subtask-completed' : ''; ?>">
                                                <?php echo htmlspecialchars($subtask['title']); ?>
                                            </span>
                                            <div class="subtask-actions">
                                                <button class="btn btn-sm btn-danger" onclick="deleteSubtask(<?php echo $subtask['id']; ?>)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <div class="subtask-form">
                                        <div class="input-group">
                                            <input type="text" class="form-control subtask-input" placeholder="Add subtask...">
                                            <button class="btn btn-primary" onclick="addSubtask(<?php echo $task['id']; ?>, this)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Task Progress Bar -->
                                <div class="task-progress-container">
                                    <div class="task-progress-bar" style="width: <?php echo $completion; ?>%"></div>
                                </div>
                                
                                <div class="task-meta">
                                    <?php if ($task['category_name']): ?>
                                        <span class="category-tag" style="background-color: <?php echo $task['category_color']; ?>">
                                            <?php echo htmlspecialchars($task['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="task-meta-item">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
                                    </div>
                                    <div class="task-meta-item priority-<?php echo isset($task['priority']) ? $task['priority'] : 'medium'; ?>">
                                        <i class="fas fa-flag"></i>
                                        <?php echo ucfirst(isset($task['priority']) ? $task['priority'] : 'medium'); ?>
                                    </div>
                                    <div class="task-meta-item">
                                        <i class="fas fa-tasks"></i>
                                        <?php echo ucfirst($task['status']); ?>
                                    </div>
                                    <div class="task-meta-item">
                                        <i class="fas fa-chart-pie"></i>
                                        <?php echo $completion; ?>% Complete
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No tasks found. Add your first task above!</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Add Task Form -->
            <section class="task-form">
                <div class="section-header">
                    <h2 class="section-title">Add New Task</h2>
                </div>
                <form id="taskForm" action="add_task.php" method="POST">
                    <div class="form-group">
                        <label for="title">Task Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="deadline">Deadline</label>
                        <input type="date" id="deadline" name="deadline" class="form-control" required>
                    </div>
                    
                    <!-- Recurring Task Options -->
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="isRecurring" name="is_recurring" onchange="toggleRecurringOptions()"> 
                            Make this a recurring task
                        </label>
                    </div>
                    
                    <div id="recurringOptions" style="display: none;">
                        <div class="form-group">
                            <label for="frequency">Frequency</label>
                            <select id="frequency" name="frequency" class="form-control" onchange="updateRecurringFields()">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="interval">Repeat every</label>
                            <div class="input-group">
                                <input type="number" id="interval" name="interval" class="form-control" min="1" value="1">
                                <span class="input-group-text" id="intervalLabel">day(s)</span>
                            </div>
                        </div>
                        
                        <div class="form-group" id="dayOfWeekGroup" style="display: none;">
                            <label for="dayOfWeek">Day of week</label>
                            <select id="dayOfWeek" name="day_of_week" class="form-control">
                                <option value="0">Sunday</option>
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="dayOfMonthGroup" style="display: none;">
                            <label for="dayOfMonth">Day of month</label>
                            <select id="dayOfMonth" name="day_of_month" class="form-control">
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="monthGroup" style="display: none;">
                            <label for="month">Month</label>
                            <select id="month" name="month" class="form-control">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="endDate">End date (optional)</label>
                            <input type="date" id="endDate" name="end_date" class="form-control">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </form>
            </section>
        </main>
    </div>

    <script>
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            // Save preference to localStorage
            const isDarkMode = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDarkMode);
        }
        
        function toggleSidePanel() {
            document.querySelector('.side-panel').classList.toggle('active');
        }
        
        // Search functionality
        function searchTasks() {
            const searchTerm = document.getElementById('taskSearch').value.toLowerCase();
            const tasks = document.querySelectorAll('#taskContainer .task');
            
            tasks.forEach(task => {
                const title = task.getAttribute('data-title').toLowerCase();
                const description = task.querySelector('p').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    task.style.display = '';
                } else {
                    task.style.display = 'none';
                }
            });
        }
        
        // Sort functionality
        function sortTasks() {
            const sortBy = document.getElementById('taskSort').value;
            const taskContainer = document.getElementById('taskContainer');
            const tasks = Array.from(taskContainer.querySelectorAll('.task'));
            
            tasks.sort((a, b) => {
                switch(sortBy) {
                    case 'deadline':
                        return new Date(a.getAttribute('data-deadline')) - new Date(b.getAttribute('data-deadline'));
                    case 'priority':
                        const priorityOrder = { 'high': 1, 'medium': 2, 'low': 3 };
                        return priorityOrder[a.getAttribute('data-priority')] - priorityOrder[b.getAttribute('data-priority')];
                    case 'created':
                        return new Date(a.getAttribute('data-created')) - new Date(b.getAttribute('data-created'));
                    case 'status':
                        const statusOrder = { 'pending': 1, 'in_progress': 2, 'completed': 3 };
                        return statusOrder[a.getAttribute('data-status')] - statusOrder[b.getAttribute('data-status')];
                    default:
                        return 0;
                }
            });
            
            tasks.forEach(task => {
                taskContainer.appendChild(task);
            });
        }
        
        // Filter by category
        function filterByCategory(categoryId) {
            const tasks = document.querySelectorAll('#taskContainer .task');
            const filterTags = document.querySelectorAll('.filter-tag');
            
            // Update active filter tag
            filterTags.forEach(tag => {
                if (tag.getAttribute('data-category') == categoryId) {
                    tag.classList.add('active');
                } else {
                    tag.classList.remove('active');
                }
            });
            
            tasks.forEach(task => {
                if (categoryId === 'all' || task.getAttribute('data-category') == categoryId) {
                    task.style.display = '';
                } else {
                    task.style.display = 'none';
                }
            });
        }
        
        // Notifications functions
        function toggleNotifications() {
            document.getElementById('notificationsMenu').classList.toggle('show');
        }
        
        function markAllAsRead() {
            fetch('mark_notifications_read.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item').forEach(item => {
                        item.classList.remove('unread');
                    });
                    document.querySelector('.notification-badge').style.display = 'none';
                }
            });
        }
        
        function viewTask(taskId) {
            // Redirect to task detail or scroll to task
            const taskElement = document.querySelector(`.task[data-id="${taskId}"]`);
            if (taskElement) {
                taskElement.scrollIntoView({ behavior: 'smooth' });
                taskElement.classList.add('highlight');
                setTimeout(() => {
                    taskElement.classList.remove('highlight');
                }, 2000);
            }
        }
        
        // Subtask functions
        function toggleSubtask(checkbox) {
            const subtaskId = checkbox.getAttribute('data-subtask-id');
            const titleElement = checkbox.nextElementSibling;
            
            fetch('toggle_subtask.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `subtask_id=${subtaskId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (checkbox.checked) {
                        titleElement.classList.add('subtask-completed');
                    } else {
                        titleElement.classList.remove('subtask-completed');
                    }
                    
                    // Update progress bar
                    const taskElement = checkbox.closest('.task');
                    const taskId = taskElement.getAttribute('data-id');
                    updateTaskProgress(taskId);
                }
            });
        }
        
        function addSubtask(taskId, button) {
            const inputElement = button.previousElementSibling;
            const title = inputElement.value.trim();
            
            if (title) {
                fetch('add_subtask.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `task_id=${taskId}&title=${encodeURIComponent(title)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const subtasksContainer = button.closest('.subtasks-container');
                        const subtaskForm = button.closest('.subtask-form');
                        
                        // Create new subtask element
                        const subtaskItem = document.createElement('div');
                        subtaskItem.className = 'subtask-item';
                        subtaskItem.innerHTML = `
                            <input type="checkbox" class="subtask-checkbox" 
                                   data-subtask-id="${data.subtask_id}"
                                   onchange="toggleSubtask(this)">
                            <span class="subtask-title">${title}</span>
                            <div class="subtask-actions">
                                <button class="btn btn-sm btn-danger" onclick="deleteSubtask(${data.subtask_id})">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        `;
                        
                        // Insert before the form
                        subtasksContainer.insertBefore(subtaskItem, subtaskForm);
                        
                        // Clear input
                        inputElement.value = '';
                        
                        // Update progress bar
                        updateTaskProgress(taskId);
                    }
                });
            }
        }
        
        function deleteSubtask(subtaskId) {
            if (confirm('Are you sure you want to delete this subtask?')) {
                fetch('delete_subtask.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `subtask_id=${subtaskId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const subtaskElement = document.querySelector(`[data-subtask-id="${subtaskId}"]`).closest('.subtask-item');
                        const taskElement = subtaskElement.closest('.task');
                        const taskId = taskElement.getAttribute('data-id');
                        
                        subtaskElement.remove();
                        
                        // Update progress bar
                        updateTaskProgress(taskId);
                    }
                });
            }
        }
        
        function updateTaskProgress(taskId) {
            fetch(`get_task_progress.php?task_id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const taskElement = document.querySelector(`.task[data-id="${taskId}"]`);
                        const progressBar = taskElement.querySelector('.task-progress-bar');
                        const progressText = taskElement.querySelector('.task-meta-item:last-child');
                        
                        progressBar.style.width = `${data.completion}%`;
                        progressText.innerHTML = `<i class="fas fa-chart-pie"></i> ${data.completion}% Complete`;
                    }
                });
        }
        
        // Recurring task functions
        function toggleRecurringOptions() {
            const isRecurring = document.getElementById('isRecurring').checked;
            document.getElementById('recurringOptions').style.display = isRecurring ? 'block' : 'none';
        }
        
        function updateRecurringFields() {
            const frequency = document.getElementById('frequency').value;
            const intervalLabel = document.getElementById('intervalLabel');
            
            // Hide all specific frequency fields
            document.getElementById('dayOfWeekGroup').style.display = 'none';
            document.getElementById('dayOfMonthGroup').style.display = 'none';
            document.getElementById('monthGroup').style.display = 'none';
            
            // Show relevant fields based on frequency
            switch (frequency) {
                case 'daily':
                    intervalLabel.textContent = 'day(s)';
                    break;
                case 'weekly':
                    intervalLabel.textContent = 'week(s)';
                    document.getElementById('dayOfWeekGroup').style.display = 'block';
                    break;
                case 'monthly':
                    intervalLabel.textContent = 'month(s)';
                    document.getElementById('dayOfMonthGroup').style.display = 'block';
                    break;
                case 'yearly':
                    intervalLabel.textContent = 'year(s)';
                    document.getElementById('monthGroup').style.display = 'block';
                    document.getElementById('dayOfMonthGroup').style.display = 'block';
                    break;
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize default sort
            sortTasks();
            
            // Close notifications menu when clicking outside
            document.addEventListener('click', function(event) {
                const notificationsMenu = document.getElementById('notificationsMenu');
                const notificationsButton = document.querySelector('.notifications-dropdown .btn');
                
                if (!notificationsButton.contains(event.target) && !notificationsMenu.contains(event.target)) {
                    notificationsMenu.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
<?php $db->close(); ?>
