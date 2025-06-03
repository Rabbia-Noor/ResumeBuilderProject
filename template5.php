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
    $template_id = 38; // Default to functional (or adjust to 5 for federal)
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
    $job_series = $_POST['job_series'] ?? '';
    $grade_level = $_POST['grade_level'] ?? '';
    
    // Create a comprehensive resume data string
    $resume_data = "title=" . $title . "|phone=" . $phone . "|address=" . $address . "|linkedin=" . $linkedin .
                    "|github=" . $github . "|summary=" . $summary . "|technical_skills=" . $technical_skills .
                    "|troubleshooting_skills=" . $troubleshooting_skills . "|analytical_skills=" . $analytical_skills .
                    "|qualification_summary=" . $qualification_summary . "|job_series=" . $job_series .
                    "|grade_level=" . $grade_level;
    
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
                'technical_skills' => '', 'troubleshooting_skills' => '', 'analytical_skills' => '', 
                'qualification_summary' => '', 'job_series' => '', 'grade_level' => ''];
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
    <title>Federal Resume - Resume Builder</title>
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
        }

        body {
           background: url('https://img.freepik.com/free-photo/abstract-digital-grid-black-background_53876-97647.jpg?semt=ais_items_boosted&w=740') no-repeat center center fixed;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            background-color: #fff; /* Fallback color */
            color: #000;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            margin: 0;
            padding: 1in;
        }

        .container {
            max-width: 8.5in;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1in;
            background: rgba(255, 255, 255, 0.95); /* Semi-transparent white to ensure readability */
            padding: 1rem;
            border-radius: 10px;
        }

        .form-container {
            background: var(--dark-blue);
            color: var(--plasma-white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 1in;
        }

        .form-title {
            color: var(--neon-blue);
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--plasma-white);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.05);
            color: var(--plasma-white);
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .category-title {
            color: var(--neon-purple);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            margin-top: 1.2rem;
            border-bottom: 2px solid var(--neon-blue);
            padding-bottom: 0.2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-purple));
            color: var(--dark-blue);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
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
            padding: 0;
            font-size: 12pt;
            border: 1px solid #ccc;
        }

        .resume-header {
            margin-bottom: 0.5in;
            border-bottom: 2pt solid #000;
            padding-bottom: 0.25in;
        }

        .name {
            font-size: 14pt;
            font-weight: bold;
            margin: 0;
            text-align: center;
        }

        .contact-info {
            text-align: center;
            margin-top: 0.25in;
        }

        .contact-item {
            margin-bottom: 0.25rem;
        }

        .section {
            margin-bottom: 0.5in;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1pt solid #000;
            margin-bottom: 0.25in;
        }

        .job-info-item, .experience-item, .education-item, .certificate-item {
            margin-bottom: 0.25in;
        }

        .job-title {
            font-weight: bold;
            font-size: 12pt;
        }

        .company, .date, .certificate-issuer {
            font-size: 11pt;
            color: #333;
        }

        .description {
            margin-top: 0.25rem;
            text-align: justify;
        }

        /* Print Specific Styles */
        @media print {
            body {
                background-image: none; /* Remove background image for print */
                background-color: white;
                padding: 0;
                margin: 0;
                font-size: 12pt;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .form-container {
                display: none;
            }

            .container {
                grid-template-columns: 1fr;
                max-width: 8.5in;
                gap: 0;
                background: white; /* Ensure no background overlay */
                padding: 0;
            }

            .resume-container {
                padding: 0;
                margin: 0;
                border: none;
            }
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                background: white; /* Ensure readability on smaller screens */
            }

            .form-container {
                position: relative;
                top: 0;
            }

            .resume-container {
                padding: 0.5rem;
            }

            .name, .contact-info {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Federal Resume Information</h2>
            
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
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($resume_data['title']) ?>" placeholder="e.g., IT Specialist">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($resume_data['phone']) ?>" placeholder="e.g., (555) 555-5555">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($resume_data['address']) ?>" placeholder="e.g., 123 Main St, Springfield, IL">
                </div>
                
                <div class="form-group">
                    <label for="linkedin">LinkedIn Profile</label>
                    <input type="text" id="linkedin" name="linkedin" value="<?= htmlspecialchars($resume_data['linkedin']) ?>" placeholder="e.g., linkedin.com/in/yourprofile">
                </div>
                
                <div class="form-group">
                    <label for="github">GitHub Profile</label>
                    <input type="text" id="github" name="github" value="<?= htmlspecialchars($resume_data['github']) ?>" placeholder="e.g., github.com/yourusername">
                </div>
                
                <div class="category-title">Job Information</div>
                <div class="form-group">
                    <label for="job_series">Job Series</label>
                    <input type="text" id="job_series" name="job_series" value="<?= htmlspecialchars($resume_data['job_series']) ?>" placeholder="e.g., 2210 - Information Technology Management">
                </div>
                
                <div class="form-group">
                    <label for="grade_level">Grade Level</label>
                    <input type="text" id="grade_level" name="grade_level" value="<?= htmlspecialchars($resume_data['grade_level']) ?>" placeholder="e.g., GS-12">
                </div>
                
                <div class="category-title">Summary</div>
                <div class="form-group">
                    <label for="summary">Career Objective</label>
                    <textarea id="summary" name="summary" placeholder="Describe your career objective for the federal position..."><?= htmlspecialchars($resume_data['summary']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="qualification_summary">Summary of Qualifications</label>
                    <textarea id="qualification_summary" name="qualification_summary" placeholder="List key qualifications relevant to the federal position..."><?= htmlspecialchars($resume_data['qualification_summary']) ?></textarea>
                </div>
                
                <div class="category-title">Professional Skills</div>
                <div class="form-group">
                    <label for="technical_skills">Technical Skills</label>
                    <textarea id="technical_skills" name="technical_skills" placeholder="Detail technical proficiencies relevant to the job series..."><?= htmlspecialchars($resume_data['technical_skills']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="troubleshooting_skills">Troubleshooting Skills</label>
                    <textarea id="troubleshooting_skills" name="troubleshooting_skills" placeholder="Describe troubleshooting experience..."><?= htmlspecialchars($resume_data['troubleshooting_skills']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="analytical_skills">Analytical Skills</label>
                    <textarea id="analytical_skills" name="analytical_skills" placeholder="Highlight analytical skills and problem-solving abilities..."><?= htmlspecialchars($resume_data['analytical_skills']) ?></textarea>
                </div>
                
                <div class="category-title">Education</div>
                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" placeholder="Bachelor of Science, Computer Science\nUniversity Name, City, State\nGraduation Date"><?= htmlspecialchars($education) ?></textarea>
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
            <div class="resume-header">
                <h1 class="name"><?= htmlspecialchars($user['FULLNAME']) ?></h1>
                <div class="contact-info">
                    <?php if (!empty($resume_data['address'])): ?>
                        <div class="contact-item"><?= htmlspecialchars($resume_data['address']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['phone'])): ?>
                        <div class="contact-item"><?= htmlspecialchars($resume_data['phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($user['EMAIL'])): ?>
                        <div class="contact-item"><?= htmlspecialchars($user['EMAIL']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['linkedin'])): ?>
                        <div class="contact-item"><?= makeLinksClickable($resume_data['linkedin']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['github'])): ?>
                        <div class="contact-item"><?= makeLinksClickable($resume_data['github']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($resume_data['job_series']) || !empty($resume_data['grade_level'])): ?>
                <div class="section">
                    <h2 class="section-title">Job Information</h2>
                    <?php if (!empty($resume_data['job_series'])): ?>
                        <div class="job-info-item">
                            <strong>Job Series:</strong> <?= htmlspecialchars($resume_data['job_series']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['grade_level'])): ?>
                        <div class="job-info-item">
                            <strong>Grade Level:</strong> <?= htmlspecialchars($resume_data['grade_level']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($resume_data['summary'])): ?>
                <div class="section">
                    <h2 class="section-title">Career Objective</h2>
                    <p class="description"><?= nl2br(htmlspecialchars($resume_data['summary'])) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($resume_data['qualification_summary'])): ?>
                <div class="section">
                    <h2 class="section-title">Summary of Qualifications</h2>
                    <ul>
                        <?php 
                        $qualifications = array_filter(explode("\n", $resume_data['qualification_summary']));
                        foreach ($qualifications as $qualification): 
                        ?>
                            <li><?= htmlspecialchars(trim($qualification)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($resume_data['technical_skills']) || !empty($resume_data['troubleshooting_skills']) || !empty($resume_data['analytical_skills'])): ?>
                <div class="section">
                    <h2 class="section-title">Professional Skills</h2>
                    <?php if (!empty($resume_data['technical_skills'])): ?>
                        <div class="skills-category">
                            <h3>Technical Skills</h3>
                            <ul>
                                <?php 
                                $technical = array_filter(explode("\n", $resume_data['technical_skills']));
                                foreach ($technical as $skill): 
                                ?>
                                    <li><?= htmlspecialchars(trim($skill)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($resume_data['troubleshooting_skills'])): ?>
                        <div class="skills-category">
                            <h3>Troubleshooting Skills</h3>
                            <ul>
                                <?php 
                                $troubleshooting = array_filter(explode("\n", $resume_data['troubleshooting_skills']));
                                foreach ($troubleshooting as $skill): 
                                ?>
                                    <li><?= htmlspecialchars(trim($skill)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($resume_data['analytical_skills'])): ?>
                        <div class="skills-category">
                            <h3>Analytical Skills</h3>
                            <ul>
                                <?php 
                                $analytical = array_filter(explode("\n", $resume_data['analytical_skills']));
                                foreach ($analytical as $skill): 
                                ?>
                                    <li><?= htmlspecialchars(trim($skill)) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($experiences)): ?>
                <div class="section">
                    <h2 class="section-title">Work Experience</h2>
                    <?php foreach ($experiences as $exp): ?>
                        <div class="experience-item">
                            <div class="job-title"><?= htmlspecialchars($exp['ROLE']) ?></div>
                            <p class="company"><?= htmlspecialchars($exp['ORGANIZATIONNAME']) ?>, 
                                <?php if (!empty($exp['ORGANIZATIONNAME']) && strpos($exp['ORGANIZATIONNAME'], 'Greenfield') !== false): ?>
                                    Greenfield, IN
                                <?php else: ?>
                                    Wheaton, IL
                                <?php endif; ?>
                            </p>
                            <p class="date"><?= date('m/Y', strtotime($exp['STARTDATE'])) ?> - 
                                <?= $exp['ENDDATE'] ? date('m/Y', strtotime($exp['ENDDATE'])) : 'Present' ?>
                            </p>
                            <?php if (!empty($exp['DESCRIPTION'])): ?>
                                <p class="description"><?= nl2br(htmlspecialchars($exp['DESCRIPTION'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
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
                    <h2 class="section-title">Certifications</h2>
                    <?php foreach ($certificates as $cert): ?>
                        <div class="certificate-item">
                            <p class="certificate-name"><?= htmlspecialchars($cert['NAME']) ?></p>
                            <?php if (!empty($cert['ISSUER'])): ?>
                                <p class="certificate-issuer"><?= htmlspecialchars($cert['ISSUER']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($cert['ISSUEDATE'])): ?>
                                <p class="date"><?= date('F Y', strtotime($cert['ISSUEDATE'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>