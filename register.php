<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'php/db.php';

    // Get and sanitize user inputs
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic input validation
    if (empty($fullname) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already exists
        $check_query = "SELECT EMAIL FROM users WHERE EMAIL = ?";
        if ($check_stmt = $conn->prepare($check_query)) {
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error = "Email already registered.";
            } else {
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                // Insert new user
                $query = "INSERT INTO users (FULLNAME, EMAIL, PASSWORDHASH, CREATEDAT) VALUES (?, ?, ?, NOW())";
                if ($stmt = $conn->prepare($query)) {
                    $stmt->bind_param("sss", $fullname, $email, $password_hash);
                    if ($stmt->execute()) {
                        // Set session and redirect to dashboard
                        $_SESSION['user_id'] = $conn->insert_id;
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Registration failed: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database query error.";
                }
            }
            $check_stmt->close();
        } else {
            $error = "Database query error.";
        }
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Register | ResumeSphere</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, rgba(0, 31, 63, 0.7) 0%, rgba(0, 48, 135, 0.7) 25%, rgba(0, 71, 171, 0.7) 50%, rgba(0, 102, 204, 0.7) 75%, rgba(153, 194, 255, 0.7) 100%),
                        url('images/pic16.avif') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            z-index: 0;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(25px);
            border-radius: 25px;
            box-shadow: 0 30px 80px rgba(0, 31, 63, 0.2);
            width: 100%;
            max-width: 800px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            min-height: 500px;
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(153, 194, 255, 0.3);
        }

        .welcome-section {
            background: linear-gradient(135deg, #001f3f 0%, rgb(1, 21, 57) 50%, rgb(3, 20, 45) 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, transparent 100%);
            border-radius: 25px 0 0 25px;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-section h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ffffff 0%, #99c2ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .welcome-section p {
            font-size: 1.2rem;
            opacity: 0.95;
            line-height: 1.7;
            margin-bottom: 35px;
            font-weight: 300;
        }

        .features {
            list-style: none;
            text-align: left;
        }

        .features li {
            padding: 10px 0;
            display: flex;
            align-items: center;
            opacity: 0.9;
            font-size: 1rem;
        }

        .features li::before {
            content: '🔷';
            margin-right: 12px;
            font-size: 1.3rem;
        }

        .features li:nth-child(2)::before {
            content: '💎';
        }

        .features li:nth-child(3)::before {
            content: '🛡️';
        }

        .features li:nth-child(4)::before {
            content: '⭐';
        }

        .login-section {
            background: linear-gradient(135deg, #f5faff 0%, #e6f0ff 50%, #f0f7ff 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #001f3f;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #001f3f 0%, #003087 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            color: #334155;
            font-size: 1rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #001f3f;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #99c2ff;
            border-radius: 15px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            color: #001f3f;
            font-weight: 500;
        }

        .form-group input:focus {
            outline: none;
            border-color: #003087;
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 4px rgba(0, 48, 135, 0.1);
        }

        .form-group input:hover {
            border-color: #4d8cff;
            background: rgba(255, 255, 255, 1);
        }

        .form-group input::placeholder {
            color: #64748b;
            font-weight: 400;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #001f3f 0%, #003087 50%, #0047ab 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 8px 25px rgba(0, 48, 135, 0.3);
        }

        .login-btn:hover {
            box-shadow: 0 12px 35px rgba(0, 48, 135, 0.4);
            transform: translateY(-1px);
        }

        .error-message {
            background: linear-gradient(135deg, #e6f0ff 0%, #99c2ff 100%);
            border: 2px solid #4d8cff;
            color: #001f3f;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 500;
            display: none;
        }

        .signup-link {
            text-align: center;
            margin-top: 25px;
            color: #334155;
            font-size: 0.95rem;
        }

        .signup-link a {
            color: #001f3f;
            text-decoration: none;
            font-weight: 600;
        }

        .signup-link a:hover {
            color: #003087;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                max-width: 420px;
                min-height: auto;
            }
            
            .welcome-section {
                padding: 35px 30px;
            }
            
            .login-section {
                padding: 35px 30px;
            }
            
            .welcome-section h1 {
                font-size: 2.2rem;
            }
            
            .login-header h2 {
                font-size: 1.8rem;
            }
        }

        .company-brand {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
        }

        .decorative-circle {
            position: absolute;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -50px;
            right: -50px;
        }

        .decorative-circle-2 {
            position: absolute;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            bottom: 20px;
            left: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #99c2ff;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <div class="company-brand">📄 ResumeSphere</div>
            <div class="decorative-circle"></div>
            <div class="decorative-circle-2"></div>
            
            <div class="welcome-content">
                <h1>Join ResumeSphere!</h1>
                <p>Create your account and unlock a world of seamless productivity and professional resume building.</p>
                <ul class="features">
                    <li>Premium resume-building experience</li>
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
                <h2>Create Account</h2>
                <p>Start your premium resume journey</p>
            </div>

            <!-- PHP error message integration -->
            <?php if (isset($error)): ?>
                <div class="error-message" style="display: block;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" min="4" placeholder="Create a secure password" required>
                </div>

                <button type="submit" class="login-btn">
                    Create Account
                </button>
            </form>

            <div class="signup-link">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>