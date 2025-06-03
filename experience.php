<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'php/db.php';

$message = '';
$message_type = ''; // success or error

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $type = trim($_POST['type']);
    $organization_name = trim($_POST['organization_name']);
    $role = trim($_POST['role']);
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

    // Input validation
    if (empty($type) || empty($organization_name) || empty($role) || empty($start_date)) {
        $message = "All required fields must be filled.";
        $message_type = 'error';
    } else {
        // Validate user exists
        $query = "SELECT COUNT(*) AS count FROM users WHERE USERID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] == 0) {
            $message = "Invalid user. Please ensure you are logged in.";
            $message_type = 'error';
        } else {
            // Insert into experience table
            $sql = "INSERT INTO experience (userid, type, organizationname, role, startdate, enddate) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $message = "Database error: " . $conn->error;
                $message_type = 'error';
            } else {
                $stmt->bind_param("isssss", $user_id, $type, $organization_name, $role, $start_date, $end_date);

                if ($stmt->execute()) {
                    $message = "Experience added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding experience: " . $stmt->error;
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Experience</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --neon-blue: #00f5ff;
            --navy: #0a0e17;
            --pure-white: #ffffff;
            --dark-navy: #1a2035;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--navy);
            background: url('https://img.freepik.com/free-vector/gradient-dark-dynamic-lines-background_23-2148995950.jpg?semt=ais_hybrid&w=740') no-repeat center center fixed;
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
            padding: 1rem;
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
            box-shadow: 0 0 10px rgba(0, 245, 255, 0.2);
            margin-top: 1rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
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
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--pure-white);
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
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
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
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Add Experience</h1>
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
                <label for="type">Type (e.g., Job, Internship):</label>
                <input type="text" id="type" name="type" required>

                <label for="organization_name">Organization Name:</label>
                <input type="text" id="organization_name" name="organization_name" required>

                <label for="role">Role:</label>
                <input type="text" id="role" name="role" required>

                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" max="2025-06-30" required>

                <label for="end_date">End Date (optional):</label>
                <input type="date" id="end_date" name="end_date">

                <button type="submit">Add Experience</button>
            </form>
        </main>
    </div>
</body>
</html>