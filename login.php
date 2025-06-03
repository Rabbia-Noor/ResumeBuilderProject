<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'php/db.php';

    // Get and sanitize user inputs
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Prepare and execute the SQL query
    $query = "SELECT USERID, PASSWORDHASH FROM users WHERE EMAIL = ?";
    if ($stmt = $conn->prepare($query)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if the user exists
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stored_password = $user['PASSWORDHASH'];

            // Check if the stored password is hashed (starts with $2y$ for bcrypt)
            if (password_verify($password, $stored_password)) {
                // Password is hashed and matches
                $_SESSION['user_id'] = $user['USERID'];
                header('Location: dashboard.php');
                exit;
            } elseif ($password === $stored_password) {
                // Password is plain text and matches, update to hashed version
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET PASSWORDHASH = ? WHERE USERID = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("si", $hashed_password, $user['USERID']);
                $update_stmt->execute();
                $update_stmt->close();

                // Set session and redirect
                $_SESSION['user_id'] = $user['USERID'];
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User does not exist.";
        }

        $stmt->close();
    } else {
        $error = "Database query error.";
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title>Welcome - Login</title>
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <div class="company-brand">ResumeSphere</div>
            <div class="decorative-circle"></div>
            <div class="decorative-circle-2"></div>
            
            <div class="welcome-content">
                <h1>Welcome Back!</h1>
                <p>Your trusted digital workspace awaits. Experience the power of seamless productivity and innovation.</p>
                <ul class="features">
                    <li>Premium blue experience</li>
                    <li>Crystal clear interface</li>
                    <li>Secure & reliable</li>
                    <li>Professional grade</li>
                </ul>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">50K+</div>
                        <div class="stat-label">Happy Users</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">99.9%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-section">
            <div class="login-header">
                <h2>Sign In</h2>
                <p>Access your premium account</p>
            </div>

            <!-- PHP error message integration -->
            <?php if (isset($error)): ?>
                <div class="error-message" style="display: block;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="login-btn">
                    Access Dashboard
                </button>
            </form>

            <div class="signup-link">
                New user? <a href="register.php">Create account</a>
            </div>
        </div>
       
    </div>
    
</body>
</html>