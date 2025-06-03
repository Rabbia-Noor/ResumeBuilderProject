<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php';

// Check database connection
if ($conn->connect_error) {
    $error = "Database connection failed: " . $conn->connect_error;
} else {
    // Fetch templates with TEMPLATEID 37 to 43
    $stmt = $conn->prepare("SELECT * FROM templates WHERE TEMPLATEID BETWEEN 37 AND 43");
    if (!$stmt) {
        $error = "Failed to prepare query: " . $conn->error;
    } else {
        $stmt->execute();
        $result = $stmt->get_result();
        $templates = $result->fetch_all(MYSQLI_ASSOC);
        if (empty($templates)) {
            $error = "No templates found in the database. Please ensure the templates table is populated with TEMPLATEID 37 to 43.";
        }
    }
}

$template_images = [
    'https://images.unsplash.com/photo-1626785774573-4b799315345d?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60',
    'https://images.unsplash.com/photo-1531545514256-b1400bc00f31?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60',
    'https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60',
    'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=60'
];

// Map TEMPLATEID to template files
$template_files = [
    37 => 'template1.php',
    38 => 'template2.php',
    39 => 'template3.php',
    40 => 'template4.php',
    41 => 'template5.php',
    42 => 'template6.php',
    43 => 'template7.php'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Template - Resume Builder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --deep-space: #0a0a1a;
            --neon-blue: #00f2fe;
            --neon-purple: #4facfe;
            --neon-border: rgba(0, 242, 254, 0.3);
            --neon-glow: 0 0 15px rgba(0, 242, 254, 0.7);
            --neon-text-glow: 0 0 10px rgba(0, 242, 254, 0.9);
            --plasma-white: rgba(255, 255, 255, 0.9);
        }

        body {
            background: radial-gradient(ellipse at bottom, #0a0a1a 0%, #000000 100%);
            color: var(--plasma-white);
            font-family: 'Orbitron', 'Segoe UI', sans-serif;
            min-height: 100vh;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 3rem 1.5rem;
        }

        h1 {
            text-align: center;
            font-size: 2.8rem;
            margin-bottom: 3rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            background: linear-gradient(90deg, var(--neon-blue), var(--neon-purple));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: var(--neon-text-glow);
            position: relative;
        }

        h1::after {
            content: '';
            display: block;
            width: 150px;
            height: 3px;
            background: linear-gradient(90deg, var(--neon-blue), var(--neon-purple));
            margin: 1rem auto 0;
            box-shadow: var(--neon-glow);
        }

        .template-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2.5rem;
            margin-bottom: 4rem;
        }

        .template-item {
            background: rgba(10, 10, 26, 0.7);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--neon-border);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            backdrop-filter: blur(5px);
        }

        .template-item::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 12px;
            padding: 1px;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s;
        }

        .template-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 242, 254, 0.2);
        }

        .template-item:hover::before {
            opacity: 1;
        }

        .template-img-container {
            position: relative;
            height: 150px;
            overflow: hidden;
        }

        .template-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s;
            filter: grayscale(30%) contrast(110%);
        }

        .template-item:hover .template-img {
            transform: scale(1.05);
            filter: grayscale(0%) contrast(120%);
        }

        .template-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: var(--neon-blue);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 1px;
            border: 1px solid var(--neon-blue);
            box-shadow: var(--neon-glow);
            text-transform: uppercase;
        }

        .template-content {
            padding: 1.8rem;
        }

        .template-item h3 {
            margin: 0 0 1.2rem 0;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--plasma-white);
        }

        .template-features {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem 0;
        }

        .template-features li {
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .template-features i {
            margin-right: 0.7rem;
            color: var(--neon-blue);
            font-size: 0.9rem;
            text-shadow: var(--neon-text-glow);
        }

        .neon-btn-sm {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.5rem;
            background: rgba(0, 242, 254, 0.1);
            color: var(--neon-blue);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 1px;
            transition: all 0.4s;
            border: 1px solid var(--neon-blue);
            box-shadow: 0 0 10px rgba(0, 242, 254, 0.3);
            position: relative;
            overflow: hidden;
        }

        .neon-btn-sm::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 242, 254, 0.4), transparent);
            transition: 0.5s;
        }

        .neon-btn-sm:hover {
            background: rgba(0, 242, 254, 0.2);
            box-shadow: 0 0 20px rgba(0, 242, 254, 0.6);
            color: var(--plasma-white);
            text-shadow: var(--neon-text-glow);
        }

        .neon-btn-sm:hover::before {
            left: 100%;
        }

        .neon-btn-sm i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-secondary {
            display: flex;
            align-items: center;
            justify-content: center;
            width: fit-content;
            margin: 3rem auto 0;
            padding: 0.8rem 2rem;
            background: transparent;
            color: var(--neon-blue);
            border: 1px solid var(--neon-blue);
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 1px;
            transition: all 0.4s;
            text-decoration: none;
            box-shadow: 0 0 10px rgba(0, 242, 254, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(0, 242, 254, 0.1);
            box-shadow: 0 0 20px rgba(0, 242, 254, 0.6);
            color: var(--plasma-white);
        }

        .error-message {
            text-align: center;
            color: #ff4d4d;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .template-list {
                grid-template-columns: 1fr;
            }

            .neon-btn-sm {
                padding: 0.6rem 1.2rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Select Your Template</h1>
        <?php if (isset($error)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($templates)): ?>
            <div class="template-list">
                <?php foreach ($templates as $index => $template): ?>
                    <?php
                    // Determine the target template file based on TEMPLATEID
                    $template_file = isset($template_files[$template['TEMPLATEID']]) && file_exists($template_files[$template['TEMPLATEID']])
                        ? $template_files[$template['TEMPLATEID']]
                        : '#';
                    ?>
                    <div class="template-item">
                        <div class="template-img-container">
                            <img src="<?= $template_images[$index % count($template_images)] ?>" 
                                 alt="<?= htmlspecialchars($template['TEMPLATENAME']) ?>" 
                                 class="template-img">
                            <span class="template-badge">NEW</span>
                        </div>
                        <div class="template-content">
                            <h3><?= htmlspecialchars($template['TEMPLATENAME']) ?></h3>
                            <ul class="template-features">
                                <li><i class="fas fa-bolt"></i> ATS-optimized format</li>
                                <li><i class="fas fa-star"></i> Premium design</li>
                                <li><i class="fas fa-cog"></i> Easy customization</li>
                            </ul>
                            <a href="<?= $template_file ?>?template_id=<?= $template['TEMPLATEID'] ?>" 
                               class="neon-btn-sm" 
                               <?= $template_file === '#' ? 'onclick="alert(\'Template file not found. Please contact support.\'); return false;"' : '' ?>>
                                <i class="fas fa-chevron-right"></i> Use Template
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="dashboard.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
       
    </div>
</body>
</html>