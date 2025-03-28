<?php
require_once 'includes/db.php';

$db = new Database();
$conn = $db->getConnection();

// Fetch all tasks
$sql = "SELECT * FROM tasks ORDER BY created_at DESC";
$result = $db->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>Task Management System</h1>
        </div>
    </header>

    <main class="container">
        <!-- Add Task Form -->
        <section class="task-form">
            <h2>Add New Task</h2>
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
                    <label for="deadline">Deadline</label>
                    <input type="date" id="deadline" name="deadline" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Task</button>
            </form>
        </section>

        <!-- Task List -->
        <section class="task-list">
            <h2>Your Tasks</h2>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($task = $result->fetch_assoc()): ?>
                    <div class="task-item">
                        <div class="task-info">
                            <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                            <p><?php echo htmlspecialchars($task['description']); ?></p>
                            <div class="task-meta">
                                <span>Deadline: <?php echo date('Y-m-d', strtotime($task['deadline'])); ?></span>
                                <span class="status-badge status-<?php echo $task['status']; ?>">
                                    <?php echo ucfirst($task['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="task-actions">
                            <button class="btn btn-success toggle-status" 
                                    data-task-id="<?php echo $task['id']; ?>"
                                    data-status="<?php echo $task['status']; ?>">
                                <?php echo $task['status'] === 'pending' ? 'Mark Complete' : 'Mark Pending'; ?>
                            </button>
                            <a href="edit_task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary">Edit</a>
                            <a href="delete_task.php?id=<?php echo $task['id']; ?>" class="btn btn-danger delete-task">Delete</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No tasks found. Add your first task above!</p>
            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>
<?php $db->close(); ?>
