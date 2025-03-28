<?php
require_once 'includes/db.php';

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update task
    $id = $db->escapeString($_POST['id']);
    $title = $db->escapeString($_POST['title']);
    $description = $db->escapeString($_POST['description']);
    $deadline = $db->escapeString($_POST['deadline']);
    
    if (empty($title) || empty($deadline)) {
        die("Title and deadline are required");
    }
    
    $sql = "UPDATE tasks SET title='$title', description='$description', deadline='$deadline' WHERE id=$id";
    
    if ($db->query($sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error updating task: " . $conn->error;
    }
} else {
    // Display edit form
    $id = $db->escapeString($_GET['id']);
    $sql = "SELECT * FROM tasks WHERE id=$id";
    $result = $db->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $task = $result->fetch_assoc();
    } else {
        header("Location: index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - Task Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>Edit Task</h1>
        </div>
    </header>

    <main class="container">
        <section class="task-form">
            <form id="taskForm" action="edit_task.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                
                <div class="form-group">
                    <label for="title">Task Title</label>
                    <input type="text" id="title" name="title" class="form-control" 
                           value="<?php echo htmlspecialchars($task['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" 
                              rows="3"><?php echo htmlspecialchars($task['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="deadline">Deadline</label>
                    <input type="date" id="deadline" name="deadline" class="form-control" 
                           value="<?php echo date('Y-m-d', strtotime($task['deadline'])); ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Task</button>
                <a href="index.php" class="btn btn-danger">Cancel</a>
            </form>
        </section>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>
<?php $db->close(); ?>
