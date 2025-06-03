<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php';

// Check if template_id is provided
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 40;
if ($template_id < 37 || $template_id > 43) {
    $template_id = 40; // Default to targeted resume
}

// Handle form submission for saving resume
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resume'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get all form data
    $title = $_POST['title'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $linkedin = $_POST['linkedin'] ?? '';
    $github = $_POST['github'] ?? '';
    $summary = $_POST['summary'] ?? '';
    $education = $_POST['education'] ?? '';
    $additional_skills = $_POST['additional_skills'] ?? '';
    $languages = $_POST['languages'] ?? '';
    $target_company = $_POST['target_company'] ?? '';
    $target_position = $_POST['target_position'] ?? '';
    $company_values = $_POST['company_values'] ?? '';
    $references = $_POST['references'] ?? ''; // New references field
    
    // Create a comprehensive resume data string
    $resume_data = "title=" . $title . "|phone=" . $phone . "|address=" . $address . "|linkedin=" . $linkedin . 
                  "|github=" . $github . "|summary=" . $summary . "|additional_skills=" . $additional_skills . 
                  "|languages=" . $languages . "|target_company=" . $target_company . "|target_position=" . $target_position .
                  "|company_values=" . $company_values . "|references=" . $references;
    
    // Insert into resumes table
    $stmt = $conn->prepare("INSERT INTO resumes (USERID, TEMPLATEID, EDUCATION) VALUES (?, ?, ?)");
    $full_resume_data = $education . "---RESUME_DATA---" . $resume_data;
    $stmt->bind_param("iis", $user_id, $template_id, $full_resume_data);
    
    if ($stmt->execute()) {
        $success_message = "Resume saved successfully!";
        $resume_id = $conn->insert_id;
        header("Location: " . $_SERVER['PHP_SELF'] . "?template_id=" . $template_id . "&saved=1");
        exit;
    } else {
        $error_message = "Error saving resume: " . $conn->error;
    }
}

// Check if we just saved
if (isset($_GET['saved'])) {
    $success_message = "Resume saved successfully!";
}

// Fetch user and resume data with a JOIN
$user_id = $_SESSION['user_id'];
$user_resume_stmt = $conn->prepare("
    SELECT u.*, r.EDUCATION 
    FROM users u 
    LEFT JOIN resumes r ON u.USERID = r.USERID 
    WHERE u.USERID = ? 
    ORDER BY r.GENERATEDAT DESC LIMIT 1
");
$user_resume_stmt->bind_param("i", $user_id);
$user_resume_stmt->execute();
$user_resume_result = $user_resume_stmt->get_result();
$user_resume_data = $user_resume_result->fetch_assoc();

// Extract user and resume data
$user = [
    'FULLNAME' => $user_resume_data['FULLNAME'],
    'EMAIL' => $user_resume_data['EMAIL'],
    'USERID' => $user_resume_data['USERID']
];
$resume_data = [
    'title' => '', 'phone' => '', 'address' => '', 'linkedin' => '', 
    'github' => '', 'summary' => '', 'additional_skills' => '', 
    'languages' => '', 'target_company' => '', 'target_position' => '',
    'company_values' => '', 'references' => ''
];
$education = '';
if (!empty($user_resume_data['EDUCATION'])) {
    $stored_data = $user_resume_data['EDUCATION'];
    if (strpos($stored_data, '---RESUME_DATA---') !== false) {
        $parts = explode('---RESUME_DATA---', $stored_data);
        $education = trim($parts[0]);
        if (isset($parts[1])) {
            $data_parts = explode('|', $parts[1]);
            foreach ($data_parts as $part) {
                $key_value = explode('=', $part, 2);
                if (count($key_value) === 2) {
                    $resume_data[$key_value[0]] = $key_value[1];
                }
            }
        }
    } else {
        $education = $stored_data;
    }
}

// Fetch experiences (ordered by relevance to target position)
$exp_stmt = $conn->prepare("SELECT * FROM experience WHERE USERID = ? ORDER BY 
    CASE WHEN ROLE LIKE ? THEN 0 ELSE 1 END, STARTDATE DESC");
$target_position_like = '%' . $resume_data['target_position'] . '%';
$exp_stmt->bind_param("is", $user_id, $target_position_like);
$exp_stmt->execute();
$experiences = $exp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch skills and projects from items table (prioritize those matching target)
$skills_stmt = $conn->prepare("SELECT TITLE, DESCRIPTION FROM items WHERE USERID = ? AND TYPE = 'skill' 
    ORDER BY CASE WHEN TITLE LIKE ? THEN 0 ELSE 1 END");
$target_skills_like = '%' . $resume_data['target_position'] . '%';
$skills_stmt->bind_param("is", $user_id, $target_skills_like);
$skills_stmt->execute();
$skills = $skills_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$projects_stmt = $conn->prepare("SELECT TITLE, DESCRIPTION FROM items WHERE USERID = ? AND TYPE != 'skill' 
    ORDER BY CASE WHEN TITLE LIKE ? THEN 0 ELSE 1 END");
$projects_stmt->bind_param("is", $user_id, $target_position_like);
$projects_stmt->execute();
$projects = $projects_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch certificates
$cert_stmt = $conn->prepare("SELECT * FROM certificates WHERE USERID = ?");
$cert_stmt->bind_param("i", $user_id);
$cert_stmt->execute();
$certificates = $cert_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to make links clickable
function makeLinksClickable($text) {
    if (empty($text)) return '';
    
    if (strpos($text, 'linkedin.com') !== false || strpos($text, 'LinkedIn') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" class="text-teal-600 hover:underline">' . htmlspecialchars($text) . '</a>';
    }
    
    if (strpos($text, 'github.com') !== false || strpos($text, 'GitHub') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" class="text-teal-600 hover:underline">' . htmlspecialchars($text) . '</a>';
    }
    
    if (preg_match('/^https?:\/\//', $text)) {
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" class="text-teal-600 hover:underline">' . htmlspecialchars($text) . '</a>';
    }
    
    return htmlspecialchars($text);
}

// Current date and time
$date_time = date('g:i A T, l, F j, Y'); // e.g., 09:50 PM PKT, Wednesday, May 28, 2025
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Targeted Resume - Resume Builder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

        :root {
            --primary-bg: #F9FAFB;       /* Light gray background */
            --primary-text: #1F2937;     /* Dark gray text */
            --accent-teal: #0D9488;      /* Teal for highlights */
            --accent-teal-dark: #0F766E; /* Darker teal for hover */
            --sidebar-bg: #111827;       /* Dark gray-blue for sidebar */
            --sidebar-text: #F3F4F6;     /* Light gray for sidebar text */
            --highlight-bg: #E5E7EB;     /* Light gray for highlights */
            --border-color: #D1D5DB;     /* Medium gray for borders */
            --shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            --border-radius: 6px;
        }

        body {
            background: linear-gradient(to bottom, #f3f4f6, #e5e7eb);
            background: url('https://img.freepik.com/free-photo/abstract-digital-grid-black-background_53876-97647.jpg?semt=ais_items_boosted&w=740') no-repeat center center fixed;
            background-size: cover;
            color: var(--primary-text);
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .form-container {
            background: var(--primary-bg);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .form-title {
            color: var(--accent-teal);
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.8rem;
            font-weight: 700;
            border-bottom: 2px solid var(--accent-teal);
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--primary-text);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            font-family: inherit;
            background: white;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-teal);
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            margin: 0.5rem 0;
        }

        .btn-primary {
            background-color: var(--accent-teal);
            color: white;
        }

        .btn-success {
            background-color: var(--accent-teal-dark);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            border-left: 4px solid #EF4444;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left: 4px solid #10B981;
            color: #059669;
        }

        .resume-container {
            background: var(--primary-bg);
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .resume-header {
            background: linear-gradient(135deg, var(--sidebar-bg), #1F2937);
            color: var(--sidebar-text);
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .resume-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-teal);
        }

        .name {
            font-size: 2.5rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .title {
            font-size: 1.2rem;
            margin: 0.5rem 0;
            font-weight: 400;
        }

        .date-time {
            font-size: 0.9rem;
            color: #D1D5DB;
            margin-top: 0.5rem;
        }

        .target-info {
            background: var(--highlight-bg);
            color: var(--primary-text);
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--accent-teal);
        }

        .target-company {
            font-size: 1.3rem;
            margin: 0;
            color: var(--accent-teal);
        }

        .target-position {
            font-size: 1.1rem;
            margin: 0.3rem 0 0;
            font-weight: 500;
        }

        .resume-body {
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .sidebar {
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .main-content {
            padding-left: 1.5rem;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            color: var(--accent-teal);
            font-size: 1.3rem;
            border-bottom: 2px solid var(--accent-teal);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .sidebar .section-title {
            color: var(--sidebar-text);
            border-bottom: 2px solid var(--accent-teal);
        }

        .contact-info {
            margin-bottom: 1.5rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.7rem;
        }

        .contact-item i {
            width: 24px;
            color: var(--accent-teal);
            margin-right: 0.7rem;
        }

        .skills-list, .languages-list {
            list-style: none;
            padding: 0;
        }

        .skills-list li, .languages-list li {
            margin-bottom: 0.7rem;
            position: relative;
            padding-left: 1.5rem;
        }

        .skills-list li::before, .languages-list li::before {
            content: '•';
            color: var(--accent-teal);
            position: absolute;
            left: 0;
            font-weight: bold;
        }

        .experience-item, .certificate-item, .project-item {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .job-title, .cert-title, .project-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-text);
            margin-bottom: 0.3rem;
        }

        .company, .issuer {
            font-weight: 500;
            color: var(--accent-teal);
            margin-bottom: 0.3rem;
        }

        .date {
            color: #6B7280;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-style: italic;
        }

        .job-description {
            margin-top: 0.5rem;
        }

        .job-description ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }

        .job-description li {
            margin-bottom: 0.3rem;
        }

        .alignment-statement {
            background: var(--highlight-bg);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--accent-teal);
        }

        .company-values {
            font-style: italic;
            color: var(--primary-text);
        }

        .references p {
            margin: 0.5rem 0;
            padding: 0.5rem;
            background: white;
            border-left: 4px solid var(--accent-teal);
            border-radius: var(--border-radius);
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }

            .form-container {
                position: static;
                margin-bottom: 2rem;
            }

            .resume-body {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: none;
                padding-right: 0;
                border-bottom: 2px solid var(--highlight-bg);
                padding-bottom: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .main-content {
                padding-left: 0;
            }

            .name {
                font-size: 2rem;
            }
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .container {
                grid-template-columns: 1fr;
                max-width: 100%;
            }

            .form-container {
                display: none;
            }

            .resume-container {
                box-shadow: none;
                border-radius: 0;
            }

            .sidebar {
                background: none;
                color: var(--primary-text);
                box-shadow: none;
            }

            .sidebar .section-title {
                color: var(--accent-teal);
            }

            .name {
                font-size: 1.8rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .job-title, .cert-title, .project-title {
                font-size: 1rem;
            }

            .skills-list li, .languages-list li, .job-description li, p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Form Container -->
        <div class="form-container">
            <h2 class="form-title">Targeted Resume Information</h2>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="resumeForm">
                <div class="form-group">
                    <label for="target_company">Target Company</label>
                    <input type="text" id="target_company" name="target_company" 
                           value="<?= htmlspecialchars($resume_data['target_company']) ?>" 
                           placeholder="e.g., Google, Amazon, Tesla">
                </div>
                
                <div class="form-group">
                    <label for="target_position">Target Position</label>
                    <input type="text" id="target_position" name="target_position" 
                           value="<?= htmlspecialchars($resume_data['target_position']) ?>" 
                           placeholder="e.g., Software Engineer, Marketing Manager">
                </div>
                
                <div class="form-group">
                    <label for="company_values">How You Align with Company Values</label>
                    <textarea id="company_values" name="company_values" 
                              placeholder="Explain how your values and skills align with the company's mission..."><?= htmlspecialchars($resume_data['company_values']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="title">Professional Title</label>
                    <input type="text" id="title" name="title" 
                           value="<?= htmlspecialchars($resume_data['title']) ?>" 
                           placeholder="e.g., Software Developer, Marketing Specialist">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" 
                           value="<?= htmlspecialchars($resume_data['phone']) ?>" 
                           placeholder="e.g., (123) 456-7890">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" 
                           value="<?= htmlspecialchars($resume_data['address']) ?>" 
                           placeholder="e.g., 123 Main St, City, State ZIP">
                </div>
                
                <div class="form-group">
                    <label for="linkedin">LinkedIn Profile</label>
                    <input type="text" id="linkedin" name="linkedin" 
                           value="<?= htmlspecialchars($resume_data['linkedin']) ?>" 
                           placeholder="e.g., linkedin.com/in/yourname">
                </div>
                
                <div class="form-group">
                    <label for="github">GitHub Profile</label>
                    <input type="text" id="github" name="github" 
                           value="<?= htmlspecialchars($resume_data['github']) ?>" 
                           placeholder="e.g., github.com/yourusername">
                </div>
                
                <div class="form-group">
                    <label for="summary">Professional Summary (Tailored for Target)</label>
                    <textarea id="summary" name="summary" 
                              placeholder="Experienced professional with skills specifically valuable to [Target Company]..."><?= htmlspecialchars($resume_data['summary']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" 
                              placeholder="Bachelor of Science in Computer Science, University of..."><?= htmlspecialchars($education) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="additional_skills">Key Skills (Highlight relevant ones)</label>
                    <textarea id="additional_skills" name="additional_skills" 
                              placeholder="JavaScript\nReact\nProject Management..."><?= htmlspecialchars($resume_data['additional_skills']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="languages">Languages</label>
                    <textarea id="languages" name="languages" 
                              placeholder="English (Fluent)\nSpanish (Intermediate)..."><?= htmlspecialchars($resume_data['languages']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="references">References (one per line, format: Name\nTitle, Company\nPhone\nEmail)</label>
                    <textarea id="references" name="references" 
                              placeholder="John Doe\nSenior Engineer, Tech Corp\n+1-555-123-4567\njohn.doe@techcorp.com"><?= htmlspecialchars($resume_data['references']) ?></textarea>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" name="save_resume" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Resume
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-success">
                        <i class="fas fa-print"></i> Print Resume
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Resume Preview -->
        <div class="resume-container" id="resumePreview">
            <div class="resume-header">
                <h1 class="name"><?= htmlspecialchars($user['FULLNAME']) ?: '[Full Name]' ?></h1>
                <?php if (!empty($resume_data['title'])): ?>
                    <p class="title"><?= htmlspecialchars($resume_data['title']) ?></p>
                <?php endif; ?>
                <div class="date-time">Generated: <?= $date_time ?></div>
            </div>
            
            <?php if (!empty($resume_data['target_company']) || !empty($resume_data['target_position'])): ?>
                <div class="target-info">
                    <?php if (!empty($resume_data['target_company'])): ?>
                        <p class="target-company">Application for <?= htmlspecialchars($resume_data['target_company']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['target_position'])): ?>
                        <p class="target-position">Position: <?= htmlspecialchars($resume_data['target_position']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="resume-body">
                <div class="sidebar">
                    <div class="section">
                        <h2 class="section-title">Contact</h2>
                        <div class="contact-info">
                            <?php if (!empty($user['EMAIL'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?= htmlspecialchars($user['EMAIL']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($resume_data['phone'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?= htmlspecialchars($resume_data['phone']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($resume_data['address'])): ?>
                                <div class="contact-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars($resume_data['address']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($resume_data['linkedin'])): ?>
                                <div class="contact-item">
                                    <i class="fab fa-linkedin"></i>
                                    <span><?= makeLinksClickable($resume_data['linkedin']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($resume_data['github'])): ?>
                                <div class="contact-item">
                                    <i class="fab fa-github"></i>
                                    <span><?= makeLinksClickable($resume_data['github']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($skills)): ?>
                        <div class="section">
                            <h2 class="section-title">Key Skills</h2>
                            <ul class="skills-list">
                                <?php foreach ($skills as $skill): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($skill['TITLE']) ?></strong>
                                        <?php if ($skill['DESCRIPTION']): ?>
                                            <br><span style="font-size: 0.9em;"><?= htmlspecialchars($skill['DESCRIPTION']) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($resume_data['additional_skills'])): ?>
                        <div class="section">
                            <h2 class="section-title">Additional Skills</h2>
                            <ul class="skills-list">
                                <?php 
                                $additional_skills = array_filter(explode("\n", $resume_data['additional_skills']));
                                foreach ($additional_skills as $skill): 
                                ?>
                                    <li><?= htmlspecialchars(trim($skill)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($resume_data['languages'])): ?>
                        <div class="section">
                            <h2 class="section-title">Languages</h2>
                            <ul class="languages-list">
                                <?php 
                                $languages = array_filter(explode("\n", $resume_data['languages']));
                                foreach ($languages as $lang): 
                                ?>
                                    <li><?= htmlspecialchars(trim($lang)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="main-content">
                    <?php if (!empty($resume_data['company_values']) || !empty($resume_data['summary'])): ?>
                        <div class="section">
                            <h2 class="section-title">Professional Profile</h2>
                            <?php if (!empty($resume_data['company_values'])): ?>
                                <div class="alignment-statement">
                                    <p><strong>Alignment with <?= htmlspecialchars($resume_data['target_company']) ?>:</strong></p>
                                    <p class="company-values"><?= nl2br(htmlspecialchars($resume_data['company_values'])) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($resume_data['summary'])): ?>
                                <p><?= nl2br(htmlspecialchars($resume_data['summary'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($education)): ?>
                        <div class="section">
                            <h2 class="section-title">Education</h2>
                            <div class="experience-item">
                                <?= nl2br(htmlspecialchars($education)) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($experiences)): ?>
                        <div class="section">
                            <h2 class="section-title">Relevant Experience</h2>
                            <?php foreach ($experiences as $exp): ?>
                                <div class="experience-item">
                                    <h3 class="job-title"><?= htmlspecialchars($exp['ROLE']) ?></h3>
                                    <p class="company"><?= htmlspecialchars($exp['ORGANIZATIONNAME']) ?></p>
                                    <p class="date">
                                        <?= date('F Y', strtotime($exp['STARTDATE'])) ?> - 
                                        <?= $exp['ENDDATE'] ? date('F Y', strtotime($exp['ENDDATE'])) : 'Present' ?>
                                    </p>
                                    <?php if (!empty($exp['DESCRIPTION'])): ?>
                                        <div class="job-description">
                                            <?php
                                            $description = $exp['DESCRIPTION'];
                                            if (strpos($description, '•') !== false || strpos($description, '-') !== false) {
                                                $lines = explode("\n", $description);
                                                echo "<ul>";
                                                foreach ($lines as $line) {
                                                    $line = trim($line);
                                                    if (!empty($line)) {
                                                        $line = ltrim($line, '•-');
                                                        echo "<li>" . htmlspecialchars(trim($line)) . "</li>";
                                                    }
                                                }
                                                echo "</ul>";
                                            } else {
                                                echo nl2br(htmlspecialchars($description));
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($projects)): ?>
                        <div class="section">
                            <h2 class="section-title">Key Projects</h2>
                            <?php foreach ($projects as $project): ?>
                                <div class="project-item">
                                    <h3 class="project-title"><?= htmlspecialchars($project['TITLE']) ?></h3>
                                    <?php if ($project['DESCRIPTION']): ?>
                                        <p><?= htmlspecialchars($project['DESCRIPTION']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($certificates)): ?>
                        <div class="section">
                            <h2 class="section-title">Certifications</h2>
                            <?php foreach ($certificates as $cert): ?>
                                <div class="certificate-item">
                                    <p class="cert-title"><?= htmlspecialchars($cert['NAME']) ?></p>
                                    <?php if (!empty($cert['ISSUER'])): ?>
                                        <p class="issuer"><?= htmlspecialchars($cert['ISSUER']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($cert['ISSUEDATE'])): ?>
                                        <p class="date">Issued: <?= date('F Y', strtotime($cert['ISSUEDATE'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($resume_data['references'])): ?>
                        <div class="section references">
                            <h2 class="section-title">References</h2>
                            <?php 
                            $ref_lines = array_filter(explode("\n", $resume_data['references']));
                            $ref_data = array_chunk($ref_lines, 4); // Assuming 4 lines per reference (Name, Title, Phone, Email)
                            foreach ($ref_data as $ref): 
                                if (count($ref) >= 4): 
                            ?>
                                <p>
                                    <strong><?= htmlspecialchars(trim($ref[0])) ?></strong><br>
                                    <?= htmlspecialchars(trim($ref[1])) ?><br>
                                    Phone: <?= htmlspecialchars(trim($ref[2])) ?><br>
                                    Email: <?= htmlspecialchars(trim($ref[3])) ?>
                                </p>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>