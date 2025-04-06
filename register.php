<?php
require_once 'includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $username = $db->escapeString($_POST['username']);
    $email = $db->escapeString($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Check if username or email already exists
        $check_sql = "SELECT id FROM users WHERE username='$username' OR email='$email'";
        $result = $db->query($check_sql);
        
        if ($result && $result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $sql = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
            
            if ($db->query($sql)) {
                $success = "Registration successful! Please login.";
            } else {
                $error = "Error creating account: " . $conn->error;
            }
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
    <title>Register - TaskMaster</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <h1>Create Account</h1>
        </div>
    </header>

    <main class="container">
        <div class="auth-container">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <p><a href="login.php">Click here to login</a></p>
                </div>
            <?php else: ?>
                <form action="register.php" method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>
                
                <p class="text-center mt-3">
                    Already have an account? <a href="login.php">Login here</a>
                </p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
