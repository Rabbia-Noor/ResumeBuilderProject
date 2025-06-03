<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page after a brief delay
header("Refresh: 2; url=login.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout</title>
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
            color: var(--pure-white);
            line-height: 1.6;
             background: url('https://img.freepik.com/free-photo/abstract-digital-grid-black-background_53876-97647.jpg?semt=ais_items_boosted&w=740') no-repeat center center fixed;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 14, 23, 0.7);
            z-index: 1;
        }

        .container, header, main {
            position: relative;
            z-index: 2;
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

        main {
            background-color: var(--dark-navy);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 245, 255, 0.2);
            margin-top: 1rem;
            text-align: center;
        }

        .message {
            padding: 1rem;
            border-radius: 4px;
            text-align: center;
            margin-bottom: 1rem;
            background-color: rgba(0, 245, 255, 0.2);
            color: var(--pure-white);
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
        <h1>Logout</h1>
    </header>
    <div class="container">
        <main>
            <p class="message">You have been logged out successfully. Redirecting to login page...</p>
            <button onclick="window.location.href='login.php'">Go to Login</button>
        </main>
    </div>
</body>
</html>