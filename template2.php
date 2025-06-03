<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php'; // Ensure this path is correct for your database connection

// Check if template_id is provided
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 38;
if ($template_id < 37 || $template_id > 43) {
    $template_id = 38; // Default to functional
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
    $technical_skills = $_POST['technical_skills'] ?? '';
    $troubleshooting_skills = $_POST['troubleshooting_skills'] ?? '';
    $analytical_skills = $_POST['analytical_skills'] ?? '';
    $qualification_summary = $_POST['qualification_summary'] ?? '';
    
    // Create a comprehensive resume data string
    $resume_data = "title=" . $title . "|phone=" . $phone . "|address=" . $address . "|linkedin=" . $linkedin .
                    "|github=" . $github . "|summary=" . $summary . "|technical_skills=" . $technical_skills .
                    "|troubleshooting_skills=" . $troubleshooting_skills . "|analytical_skills=" . $analytical_skills .
                    "|qualification_summary=" . $qualification_summary;
    
    // Insert into resumes table
    $stmt = $conn->prepare("INSERT INTO resumes (USERID, TEMPLATEID, EDUCATION) VALUES (?, ?, ?)");
    $full_resume_data = $education . "---RESUME_DATA---" . $resume_data;
    $stmt->bind_param("iis", $user_id, $template_id, $full_resume_data);
    
    if ($stmt->execute()) {
        $success_message = "Resume saved successfully!";
        $resume_id = $conn->insert_id;
        // Redirect to refresh the page with updated data
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

// Fetch user data
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM users WHERE USERID = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch experiences (ordered by date)
$exp_stmt = $conn->prepare("SELECT * FROM experience WHERE USERID = ? ORDER BY STARTDATE DESC");
$exp_stmt->bind_param("i", $user_id);
$exp_stmt->execute();
$experiences = $exp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch latest resume data
$resume_data = ['title' => '', 'phone' => '', 'address' => '', 'linkedin' => '', 'github' => '', 'summary' => '',
                'technical_skills' => '', 'troubleshooting_skills' => '', 'analytical_skills' => '', 'qualification_summary' => ''];
$education = '';
$resume_stmt = $conn->prepare("SELECT EDUCATION FROM resumes WHERE USERID = ? ORDER BY GENERATEDAT DESC LIMIT 1");
$resume_stmt->bind_param("i", $user_id);
$resume_stmt->execute();
$resume_result = $resume_stmt->get_result();
if ($resume_result->num_rows > 0) {
    $stored_data = $resume_result->fetch_assoc()['EDUCATION'];
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

// Fetch skills from items table
$skills_stmt = $conn->prepare("SELECT * FROM items WHERE USERID = ? AND TYPE = 'skill'");
$skills_stmt->bind_param("i", $user_id);
$skills_stmt->execute();
$skills = $skills_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: inherit; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    if (strpos($text, 'github.com') !== false || strpos($text, 'GitHub') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: inherit; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    if (preg_match('/^https?:\/\//', $text)) {
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: inherit; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    return htmlspecialchars($text);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Functional Resume - Resume Builder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy-blue: #1a2a44;
            --dark-blue: #0f172a;
            --neon-blue: #00f2fe;
            --neon-purple: #4facfe;
            --plasma-white: rgba(255, 255, 255, 0.9);
            --resume-blue: #2c3e50;
            --resume-light-blue: #3b5998;
            --neon-glow: 0 0 10px rgba(0, 242, 254, 0.7), 0 0 20px rgba(0, 242, 254, 0.5);
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            --sidebar-blue: #005c99; /* Matching the blue from the image */
        }

        body {
            background: linear-gradient(to bottom, #e6e9f0, #f5f5f5);
            background: url('https://img.freepik.com/premium-photo/business-data-financial-figures-visualiser-graphic_31965-23939.jpg?semt=ais_hybrid&w=740') no-repeat center center fixed;
            background-size: cover;
            color: #1a202c;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.7;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2.5rem;
        }

        .form-container {
            background: var(--dark-blue);
            color: var(--plasma-white);
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 30px;
            transition: transform 0.3s ease;
        }

        .form-container:hover {
            transform: translateY(-5px);
        }

        .form-title {
            color: var(--neon-blue);
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 500;
            color: var(--plasma-white);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.05);
            color: var(--plasma-white);
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: var(--neon-glow);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .category-title {
            color: var(--neon-purple);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
            border-bottom: 2px solid var(--neon-blue);
            padding-bottom: 0.3rem;
        }

        .btn {
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            margin: 0.6rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            color: var(--dark-blue);
            box-shadow: var(--neon-glow);
        }

        .btn-success {
            background: #28a745;
            color: white;
            border: 2px solid var(--neon-blue);
            box-shadow: var(--neon-glow);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 242, 254, 0.4);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: var(--neon-glow);
        }

        .alert {
            padding: 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.2rem;
            background: rgba(255, 255, 255, 0.1);
            color: var(--plasma-white);
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid var(--neon-blue);
        }

        .alert-error {
            background: rgba(248, 215, 218, 0.2);
            border: 1px solid var(--neon-purple);
        }

        /* Resume Specific Styles */
        .resume-container {
            background: white;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            transition: transform 0.3s ease;
            display: flex; /* Use flexbox for layout */
        }

        .resume-container:hover {
            transform: translateY(-5px);
        }

        .resume-sidebar {
            background: var(--navy-blue); /* Darker blue for the sidebar */
            color: var(--plasma-white);
            padding: 1rem;
            flex: 1; /* Takes up 1 part of the flexible space */
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Align content to the left */
        }

        .resume-main-content {
            padding: 2rem;
            flex: 2; /* Takes up 2 parts of the flexible space */
        }

        .name {
            font-size: 2rem; /* Slightly smaller to fit better */
            margin: 0;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--neon-blue); /* Neon blue for the name */
            margin-bottom: 1.5rem;
        }

        .contact-info {
            font-size: 0.95rem;
            width: 100%; /* Ensure contact info takes full width of sidebar */
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem; /* Increased margin for readability */
        }

        .contact-item i {
            margin-right: 0.8rem; /* Increased margin */
            color: var(--neon-blue); /* Neon blue icons */
            font-size: 1.1em;
        }

        .contact-item a {
            color: var(--plasma-white);
            text-decoration: underline;
            transition: color 0.3s ease;
        }

        .contact-item a:hover {
            color: var(--neon-blue);
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            color: var(--resume-blue);
            font-size: 1.4rem;
            border-bottom: 2px solid var(--resume-blue);
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .skills-category {
            margin-bottom: 1.5rem;
        }

        .skills-category h3 {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.6rem;
            color: var(--navy-blue);
            text-transform: uppercase;
        }

        .skills-description ul {
            margin: 0.6rem 0;
            padding-left: 1.8rem;
        }

        .skills-description li {
            margin-bottom: 0.4rem;
            color: #2d3748;
        }

        .experience-item, .education-item {
            margin-bottom: 1rem;
        }

        .job-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #2d3748;
        }

        .company {
            font-weight: 500;
            color: #718096;
            font-size: 0.95rem;
        }

        .date {
            color: #718096;
            font-size: 0.95rem;
        }

        /* Print Specific Styles */
        @media print {
            body {
                padding: 0;
                margin: 0;
                background: white;
                font-size: 10pt; /* Adjust font size for print */
                -webkit-print-color-adjust: exact; /* For better background color printing */
                print-color-adjust: exact;
            }

            .form-container {
                display: none; /* Hide the form when printing */
            }

            .container {
                grid-template-columns: 1fr; /* Single column layout for print */
                max-width: 100%;
                margin: 0;
                gap: 0;
            }

            .resume-container {
                box-shadow: none;
                border-radius: 0;
                display: flex; /* Keep flexbox for print */
                flex-direction: row; /* Ensure side by side */
                width: 100%;
                margin: 0;
            }

            .resume-sidebar {
                padding: 1.5cm; /* Adjust padding for print */
                background: var(--navy-blue); /* Keep background for print */
                color: var(--plasma-white); /* Keep text color for print */
                flex: 1;
                max-width: 30%; /* Allocate space for sidebar */
                box-sizing: border-box;
            }

            .resume-main-content {
                padding: 1.5cm; /* Adjust padding for print */
                flex: 2;
                box-sizing: border-box;
            }

            .name {
                color: var(--neon-blue) !important; /* Force color for print */
                font-size: 2.2rem; /* Adjust font size for print */
            }

            .contact-item i {
                color: var(--neon-blue) !important; /* Force color for print */
            }

            .contact-item a {
                color: var(--plasma-white) !important; /* Force color for print */
            }

            .section-title {
                border-color: var(--resume-blue) !important; /* Force border color for print */
                color: var(--resume-blue) !important; /* Force title color for print */
            }

            .skills-category h3 {
                color: var(--navy-blue) !important; /* Force color for print */
            }
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }

            .form-container {
                position: relative;
                top: 0;
            }

            .form-container,
            .resume-container {
                border-radius: 10px;
            }

            .resume-container {
                flex-direction: column; /* Stack sections on small screens */
            }

            .resume-sidebar,
            .resume-main-content {
                flex: none; /* Remove flex properties */
                width: 100%; /* Take full width */
                padding: 1.5rem; /* Adjust padding */
            }

            .name {
                font-size: 2rem;
                text-align: center;
                margin-bottom: 1rem;
            }

            .contact-info {
                text-align: center; /* Center contact info on small screens */
            }

            .contact-item {
                justify-content: center; /* Center icons and text */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Resume Information</h2>
            
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
                <div class="category-title">Personal Information</div>
                <div class="form-group">
                    <label for="title">Professional Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($resume_data['title']) ?>" placeholder="e.g., Programmer">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="number" id="phone" name="phone" max="13" value="<?= htmlspecialchars($resume_data['phone']) ?>" placeholder="e.g., (555) 555-5555">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($resume_data['address']) ?>" placeholder="e.g., Lombard, IL 60148">
                </div>
                
                <div class="form-group">
                    <label for="linkedin">LinkedIn Profile</label>
                    <input type="text" id="linkedin" name="linkedin" value="<?= htmlspecialchars($resume_data['linkedin']) ?>" placeholder="e.g., linkedin.com/in/yourprofile">
                </div>
                
                <div class="form-group">
                    <label for="github">GitHub Profile</label>
                    <input type="text" id="github" name="github" value="<?= htmlspecialchars($resume_data['github']) ?>" placeholder="e.g., github.com/yourusername">
                </div>
                
                <div class="category-title">Summary</div>
                <div class="form-group">
                    <label for="summary">Summary Statement</label>
                    <textarea id="summary" name="summary" placeholder="Motivated Programmer with impressive background spent in the Artificial Intelligence industry..."><?= htmlspecialchars($resume_data['summary']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="qualification_summary">Summary of Qualifications</label>
                    <textarea id="qualification_summary" name="qualification_summary" placeholder="Proven experience in sending two to three e-mails..."><?= htmlspecialchars($resume_data['qualification_summary']) ?></textarea>
                </div>
                
                <div class="category-title">Professional Skills</div>
                <div class="form-group">
                    <label for="technical_skills">Testing and Debugging</label>
                    <textarea id="technical_skills" name="technical_skills" placeholder="Worked closely with 15+ software development and testing team members..."><?= htmlspecialchars($resume_data['technical_skills']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="troubleshooting_skills">Troubleshooting Skills</label>
                    <textarea id="troubleshooting_skills" name="troubleshooting_skills" placeholder="Participated in the design, debug and troubleshoot applications..."><?= htmlspecialchars($resume_data['troubleshooting_skills']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="analytical_skills">Analytical Skills</label>
                    <textarea id="analytical_skills" name="analytical_skills" placeholder="Programmed data mapping engine for legacy systems..."><?= htmlspecialchars($resume_data['analytical_skills']) ?></textarea>
                </div>
                
                <div class="category-title">Education</div>
                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" placeholder="Bachelor of Science, Computer Programming
Elmhurst University, 06/2018 - Elmhurst, IL"><?= htmlspecialchars($education) ?></textarea>
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
        
        <div class="resume-container" id="resumePreview">
            <div class="resume-sidebar">
                <h1 class="name"><?= htmlspecialchars($user['FULLNAME']) ?></h1>
                <?php if (!empty($resume_data['title'])): ?>
                    <p style="color: var(--plasma-white); font-size: 1.1rem; margin-top: -1rem; margin-bottom: 1.5rem;"><?= htmlspecialchars($resume_data['title']) ?></p>
                <?php endif; ?>
                
                <div class="contact-info">
                    <?php if (!empty($resume_data['address'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($resume_data['address']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($resume_data['phone'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($resume_data['phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['EMAIL'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($user['EMAIL']) ?></span>
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
            
            <div class="resume-main-content">
                <?php if (!empty($resume_data['summary'])): ?>
                    <div class="section">
                        <h2 class="section-title">Summary Statement</h2>
                        <p><?= nl2br(htmlspecialchars($resume_data['summary'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($resume_data['qualification_summary'])): ?>
                    <div class="section">
                        <h2 class="section-title">Summary of Qualifications</h2>
                        <div class="skills-description">
                            <ul>
                                <?php 
                                $qualifications = array_filter(explode("\n", $resume_data['qualification_summary']));
                                foreach ($qualifications as $qualification): 
                                ?>
                                    <li><?= htmlspecialchars(trim($qualification)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($resume_data['technical_skills']) || !empty($resume_data['troubleshooting_skills']) || !empty($resume_data['analytical_skills']) || !empty($experiences)): ?>
                    <div class="section">
                        <h2 class="section-title">Professional Skills</h2>
                        <?php if (!empty($resume_data['technical_skills'])): ?>
                            <div class="skills-category">
                                <h3>Testing and Debugging</h3>
                                <div class="skills-description">
                                    <ul>
                                        <?php 
                                        $technical = array_filter(explode("\n", $resume_data['technical_skills']));
                                        foreach ($technical as $skill): 
                                        ?>
                                            <li><?= htmlspecialchars(trim($skill)) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($resume_data['troubleshooting_skills'])): ?>
                            <div class="skills-category">
                                <h3>Troubleshooting Skills</h3>
                                <div class="skills-description">
                                    <ul>
                                        <?php 
                                        $troubleshooting = array_filter(explode("\n", $resume_data['troubleshooting_skills']));
                                        foreach ($troubleshooting as $skill): 
                                        ?>
                                            <li><?= htmlspecialchars(trim($skill)) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($resume_data['analytical_skills'])): ?>
                            <div class="skills-category">
                                <h3>Analytical Skills</h3>
                                <div class="skills-description">
                                    <ul>
                                        <?php 
                                        $analytical = array_filter(explode("\n", $resume_data['analytical_skills']));
                                        foreach ($analytical as $skill): 
                                        ?>
                                            <li><?= htmlspecialchars(trim($skill)) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($experiences)): ?>
                            <?php
                            $skill_categories = [
                                'Management' => [],
                                'Technical' => [],
                                'Communication' => []
                            ];
                            foreach ($experiences as $exp) {
                                // Check if DESCRIPTION exists to avoid undefined key error
                                $description = isset($exp['DESCRIPTION']) ? strtolower($exp['DESCRIPTION']) : '';
                                if (strpos($description, 'manage') !== false || strpos($description, 'lead') !== false) {
                                    $skill_categories['Management'][] = $exp;
                                }
                                if (strpos($description, 'develop') !== false || strpos($description, 'program') !== false) {
                                    $skill_categories['Technical'][] = $exp;
                                }
                                if (strpos($description, 'communicate') !== false || strpos($description, 'present') !== false) {
                                    $skill_categories['Communication'][] = $exp;
                                }
                            }
                            ?>
                            <?php foreach ($skill_categories as $category => $exps): ?>
                                <?php if (!empty($exps)): ?>
                                    <div class="skills-category">
                                        <h3><?= htmlspecialchars($category) ?></h3>
                                        <div class="skills-description">
                                            <ul>
                                                <?php foreach ($exps as $exp): ?>
                                                    <li>
                                                        <?= htmlspecialchars($exp['ROLE']) ?> at <?= htmlspecialchars($exp['ORGANIZATIONNAME']) ?>
                                                        (<?= date('Y', strtotime($exp['STARTDATE'])) ?> - 
                                                        <?= $exp['ENDDATE'] ? date('Y', strtotime($exp['ENDDATE'])) : 'Present' ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="section">
                    <h2 class="section-title">Work History</h2>
                    <?php if (!empty($experiences)): ?>
                        <?php foreach ($experiences as $exp): ?>
                            <div class="experience-item">
                                <div class="job-title"><?= htmlspecialchars($exp['ROLE']) ?>, <?= date('m/Y', strtotime($exp['STARTDATE'])) ?> - 
                                    <?= $exp['ENDDATE'] ? date('m/Y', strtotime($exp['ENDDATE'])) : 'Current' ?>
                                </div>
                                <p class="company"><?= htmlspecialchars($exp['ORGANIZATIONNAME']) ?>, 
                                    <?php if (!empty($exp['ORGANIZATIONNAME']) && strpos($exp['ORGANIZATIONNAME'], 'Greenfield') !== false): ?>
                                        Greenfield, IN
                                    <?php else: ?>
                                        Wheaton, IL
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No work experience added yet.</p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($education)): ?>
                    <div class="section">
                        <h2 class="section-title">Education</h2>
                        <div class="education-item">
                            <?= nl2br(htmlspecialchars($education)) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($certificates)): ?>
                    <div class="section">
                        <h2 class="section-title">Certifications/Licenses</h2>
                        <?php foreach ($certificates as $cert): ?>
                            <div class="certificate-item">
                                <p class="certificate-name"><?= htmlspecialchars($cert['NAME']) ?></p>
                                <?php if (!empty($cert['ISSUER'])): ?>
                                    <p class="certificate-issuer"><?= htmlspecialchars($cert['ISSUER']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($cert['ISSUEDATE'])): ?>
                                    <p class="date"><?= date('F Y', strtotime($cert['ISSUEDATE'])) ?> to <?= date('F Y', strtotime($cert['ISSUEDATE'] . ' +5 years')) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</body>
</html>