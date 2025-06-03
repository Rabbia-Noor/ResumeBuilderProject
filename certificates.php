<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'php/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// Verify database connection
if (!$conn) {
    $message = "Database connection failed: " . mysqli_connect_error();
    $message_type = "error";
} else {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Log form data for debugging
        error_log("Form data: " . print_r($_POST, true));

        // Get and sanitize inputs
        $name = trim($_POST['name'] ?? '');
        $issuer = trim($_POST['issuer'] ?? '');
        $issue_date = $_POST['issue_date'] ?? '';

        // Validation
        if (empty($name)) {
            $message = "Certificate name is required.";
            $message_type = "error";
        } elseif (empty($issuer)) {
            $message = "Issuer is required.";
            $message_type = "error";
        } elseif (empty($issue_date)) {
            $message = "Issue date is required.";
            $message_type = "error";
        } else {
            // Prepare insert statement
            $sql = "INSERT INTO certificates (USERID, NAME, ISSUER, ISSUEDATE) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $message = "Prepare failed: " . $conn->error;
                $message_type = "error";
                error_log("Prepare error: " . $conn->error);
            } else {
                $stmt->bind_param("isss", $user_id, $name, $issuer, $issue_date);
                if ($stmt->execute()) {
                    $message = "Certificate saved successfully." . $stmt->affected_rows;
                    $message_type = "success";
                    error_log("Insert successful: Rows affected = " . $stmt->affected_rows);
                } else {
                    $message = "Error saving certificate: " . $stmt->error;
                    $message_type = "error";
                    error_log("Insert error: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Certificate</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --neon-blue: #00f5ff;
            --navy: #0a0e17;
            --dark-navy: #1a2035;
            --pure-white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--navy);
            background: url(images/pic34.jpg) no-repeat center center fixed;
            background-size: cover;
            color: var(--pure-white);
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        header {
            background-color: var(--dark-navy);
            padding: 1.5rem;
            text-align: center;
            border-bottom: 2px solid var(--neon-blue);
        }

        header h1 {
            font-size: 2rem;
            color: var(--neon-blue);
            margin-bottom: 0.5rem;
        }

        nav {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
        }

        nav a {
            color: var(--pure-white);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        nav a:hover {
            color: var(--neon-blue);
        }

        main {
            background-color: var(--dark-navy);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 245, 255, 0.2);
            margin-top: 1rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        label {
            font-size: 1rem;
            color: var(--neon-blue);
            margin-bottom: 0.3rem;
        }

        input[type="text"],
        input[type="date"] {
            padding: 0.8rem;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--neon-blue);
            border-radius: 4px;
            color: var(--pure-white);
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--pure-white);
            box-shadow: 0 0 8px rgba(0, 245, 255, 0.5);
        }

        input:required + label::after {
            content: " *";
            color: #ff4d4d;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        button {
            background: linear-gradient(135deg, var(--neon-blue), #0066ff);
            color: var(--pure-white);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.3s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 10px rgba(0, 245, 255, 0.5);
        }

        button.back {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }

        .message {
            padding: 1rem;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 1rem;
        }

        .message.success {
            background-color: rgba(0, 245, 255, 0.2);
            color: var(--pure-white);
        }

        .message.error {
            background-color: rgba(255, 0, 0, 0.2);
            color: var(--pure-white);
        }

        @media (max-width: 600px) {
            .container {
                margin: 1rem;
            }

            header h1 {
                font-size: 1.5rem;
            }

            button {
                padding: 0.7rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Add Certificate</h1>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <div class="container">
        <main>
            <?php if ($message): ?>
                <p class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>
            <form action="" method="POST">
                <label for="name">Certificate Name:</label>
                <input type="text" id="name" name="name" placeholder="e.g., Certified Python Developer" required>

                <label for="issuer">Issuer:</label>
                <input type="text" id="issuer" name="issuer" placeholder="e.g., Coursera" required>

                <label for="issue_date">Issue Date:</label>
                <input type="date" id="issue_date" name="issue_date" required>

                <div class="button-group">
                    <button type="submit">Add Certificate</button>
                    <button type="button" class="back" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
                </div>
            </form>
        </main>
        <!-- <?php include 'footer.html'; ?> -->
    </div>
</body>
</html>