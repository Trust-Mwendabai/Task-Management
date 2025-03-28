<?php
require_once 'includes/db.php';

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $id = $db->escapeString($_GET['id']);
    
    $sql = "DELETE FROM tasks WHERE id=$id";
    
    if ($db->query($sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error deleting task: " . $conn->error;
    }
    
    $db->close();
} else {
    header("Location: index.php");
    exit();
}
?>
