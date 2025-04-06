<?php
session_start();
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $username = $db->escapeString($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "All fields are required";
    } else {
        $sql = "SELECT * FROM users WHERE username='$username' OR email='$username'";
        $result = $db->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
    }
    
    $db->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TaskMaster</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>Login</h1>
        </div>
    </header>

    <main class="container">
        <div class="auth-container">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            
            <p class="text-center mt-3">
                Don't have an account? <a href="register.php">Register here</a>
            </p>
        </div>
    </main>
</body>
</html>
