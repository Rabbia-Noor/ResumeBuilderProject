<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php';

// Check if template_id is provided
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 37;
if ($template_id < 37 || $template_id > 43) {
    $template_id = 37; // Default to chronological
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
    $languages = $_POST['languages'] ?? ''; // Added languages field
    
    // Create a comprehensive resume data string
    $resume_data = "title=" . $title . "|phone=" . $phone . "|address=" . $address . "|linkedin=" . $linkedin . 
                  "|github=" . $github . "|summary=" . $summary . "|additional_skills=" . $additional_skills . "|languages=" . $languages;
    
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
$resume_data = ['title' => '', 'phone' => '', 'address' => '', 'linkedin' => '', 'github' => '', 'summary' => '', 'additional_skills' => '', 'languages' => ''];
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

// Fetch experiences (ordered by date)
$exp_stmt = $conn->prepare("SELECT * FROM experience WHERE USERID = ? ORDER BY STARTDATE DESC");
$exp_stmt->bind_param("i", $user_id);
$exp_stmt->execute();
$experiences = $exp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch skills and projects from items table (split into two queries for clarity)
$skills_stmt = $conn->prepare("SELECT TITLE, DESCRIPTION FROM items WHERE USERID = ? AND TYPE = 'skill'");
$skills_stmt->bind_param("i", $user_id);
$skills_stmt->execute();
$skills = $skills_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$projects_stmt = $conn->prepare("SELECT TITLE, DESCRIPTION FROM items WHERE USERID = ? AND TYPE != 'skill'");
$projects_stmt->bind_param("i", $user_id);
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
    <title>Combinational Resume - Resume Builder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --navy-blue: #1a2a44;
            --dark-blue: #0f172a;
            --neon-blue: #00f2fe;
            --neon-purple: #4facfe;
            --plasma-white: rgba(255, 255, 255, 0.9);
            --resume-blue: #00695C;
            --resume-light-blue: #B2DFDB;
            --neon-glow: 0 0 10px rgba(0, 242, 254, 0.7), 0 0 20px rgba(0, 242, 254, 0.5);
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        body {
             background: url('https://img.freepik.com/free-photo/abstract-digital-grid-black-background_53876-97647.jpg?semt=ais_items_boosted&w=740') no-repeat center center fixed;
            background-size: cover;
            background-attachment: fixed;
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

        .resume-container {
            background: white;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border-radius: 15px;
            transition: transform 0.3s ease;
            display: flex;
        }

        .resume-container:hover {
            transform: translateY(-5px);
        }

        .sidebar {
            width: 35%;
            background: var(--resume-blue);
            color: var(--plasma-white);
            padding: 2rem;
            font-size: 0.9rem;
        }

        .sidebar h3 {
            color: var(--resume-light-blue);
            margin: 1rem 0 0.5rem;
            font-size: 1.1rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--resume-light-blue);
            padding-bottom: 0.3rem;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            margin-bottom: 0.5rem;
            padding-left: 0.5rem;
        }

        .sidebar ul li i {
            margin-right: 0.5rem;
        }

        .main-content {
            width: 65%;
            padding: 2rem;
            background: white;
        }

        .name {
            font-size: 2.8rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            color: var(--resume-blue);
            text-align: center;
        }

        .title {
            font-size: 1.3rem;
            margin: 0.6rem 0;
            font-weight: 400;
            opacity: 0.9;
            text-align: center;
            color: #666;
        }

        .contact-info {
            text-align: center;
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .contact-info span {
            margin: 0 0.5rem;
        }

        .section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            color: var(--resume-blue);
            font-size: 1.4rem;
            border-bottom: 3px solid var(--resume-blue);
            padding-bottom: 0.6rem;
            margin-bottom: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 600;
        }

        .experience-item, .education-item {
            margin-bottom: 1.8rem;
        }

        .job-title {
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.4rem;
            color: var(--navy-blue);
        }

        .company {
            font-weight: 500;
            color: #4a5568;
        }

        .date {
            color: #718096;
            font-size: 0.95rem;
            margin-bottom: 0.6rem;
        }

        .job-description {
            margin-top: 0.6rem;
        }

        .job-description ul {
            margin: 0.6rem 0;
            padding-left: 1.8rem;
        }

        .job-description li {
            margin-bottom: 0.4rem;
            color: #2d3748;
        }

        .skills-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .skills-category h4 {
            color: var(--resume-blue);
            margin-bottom: 0.6rem;
            font-size: 1.1rem;
        }

        .skills-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .skills-list li {
            padding: 0.3rem 0;
            font-size: 0.95rem;
            color: #2d3748;
        }

        .certificate-item {
            margin-bottom: 1.2rem;
        }

        .certificate-name {
            font-weight: 600;
            color: var(--navy-blue);
        }

        .certificate-issuer {
            color: #4a5568;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }

            .form-container {
                position: relative;
                top: 0;
            }

            .resume-container {
                flex-direction: column;
            }

            .sidebar, .main-content {
                width: 100%;
            }

            .form-container,
            .resume-container {
                border-radius: 10px;
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
                margin: 0;
                border-radius: 0;
                display: flex;
                flex-direction: row;
                width: 100%;
            }

            .sidebar {
                width: 35%;
                background: var(--resume-blue);
                color: var(--plasma-white);
                padding: 1rem;
                font-size: 0.8rem;
            }

            .main-content {
                width: 65%;
                padding: 1rem;
            }

            .name {
                font-size: 2rem;
            }

            .title {
                font-size: 1rem;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .job-title {
                font-size: 1rem;
            }

            .skills-list li, .job-description li, p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Form Container -->
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
                <div class="form-group">
                    <label for="title">Professional Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($resume_data['title']) ?>" placeholder="e.g., Teacher, Software Developer">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($resume_data['phone']) ?>" placeholder="e.g., +1-999-999-9999">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($resume_data['address']) ?>" placeholder="e.g., 508 Druid Ave, Charlottesville, VA 22902, USA">
                </div>
                
                <div class="form-group">
                    <label for="linkedin">LinkedIn Profile</label>
                    <input type="text" id="linkedin" name="linkedin" value="<?= htmlspecialchars($resume_data['linkedin']) ?>" placeholder="e.g., linkedin.com/in/yourprofile">
                </div>
                
                <div class="form-group">
                    <label for="github">GitHub Profile</label>
                    <input type="text" id="github" name="github" value="<?= htmlspecialchars($resume_data['github']) ?>" placeholder="e.g., github.com/yourusername">
                </div>
                
                <div class="form-group">
                    <label for="summary">Professional Summary</label>
                    <textarea id="summary" name="summary" placeholder="Adaptable professional with 7+ years of experience..."><?= htmlspecialchars($resume_data['summary']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" placeholder="Bachelor of Education in Teachers Education..."><?= htmlspecialchars($education) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="additional_skills">Additional Skills (one per line)</label>
                    <textarea id="additional_skills" name="additional_skills" placeholder="Microsoft Office\nGoogle Suite..."><?= htmlspecialchars($resume_data['additional_skills']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="languages">Languages (one per line)</label>
                    <textarea id="languages" name="languages" placeholder="English\nSpanish..."><?= htmlspecialchars($resume_data['languages']) ?></textarea>
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
            <div class="sidebar">
                <h3>Contact Information</h3>
                <ul>
                    <?php if (!empty($user['EMAIL'])): ?>
                        <li><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['EMAIL']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['phone'])): ?>
                        <li><i class="fas fa-phone"></i> <?= htmlspecialchars($resume_data['phone']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['address'])): ?>
                        <li><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($resume_data['address']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['linkedin'])): ?>
                        <li><i class="fab fa-linkedin"></i> <?= makeLinksClickable($resume_data['linkedin']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['github'])): ?>
                        <li><i class="fab fa-github"></i> <?= makeLinksClickable($resume_data['github']) ?></li>
                    <?php endif; ?>
                </ul>
                <?php if (!empty($resume_data['additional_skills'])): ?>
                    <h3>Additional Skills</h3>
                    <ul>
                        <?php 
                        $additional_skills = array_filter(explode("\n", $resume_data['additional_skills']));
                        foreach ($additional_skills as $skill): 
                        ?>
                            <li><?= htmlspecialchars(trim($skill)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($resume_data['languages'])): ?>
                    <h3>Languages</h3>
                    <ul>
                        <?php 
                        $languages = array_filter(explode("\n", $resume_data['languages']));
                        foreach ($languages as $lang): 
                        ?>
                            <li><?= htmlspecialchars(trim($lang)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div class="main-content">
                <h1 class="name"><?= htmlspecialchars($user['FULLNAME']) ?></h1>
                <?php if (!empty($resume_data['title'])): ?>
                    <p class="title"><?= htmlspecialchars($resume_data['title']) ?></p>
                <?php endif; ?>
                
                <div class="contact-info">
                    <?php 
                    $contact_items = [];
                    if (!empty($user['EMAIL'])) $contact_items[] = htmlspecialchars($user['EMAIL']);
                    if (!empty($resume_data['phone'])) $contact_items[] = htmlspecialchars($resume_data['phone']);
                    if (!empty($resume_data['address'])) $contact_items[] = htmlspecialchars($resume_data['address']);
                    if (!empty($resume_data['linkedin'])) $contact_items[] = makeLinksClickable($resume_data['linkedin']);
                    if (!empty($resume_data['github'])) $contact_items[] = makeLinksClickable($resume_data['github']);
                    echo implode(' | ', $contact_items);
                    ?>
                </div>

                <?php if (!empty($resume_data['summary'])): ?>
                    <div class="section">
                        <h2 class="section-title">Professional Summary</h2>
                        <p><?= nl2br(htmlspecialchars($resume_data['summary'])) ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($skills)): ?>
                    <div class="section">
                        <h2 class="section-title">Skills</h2>
                        <div class="skills-grid">
                            <div class="skills-category">
                                <ul class="skills-list">
                                    <?php foreach ($skills as $skill): ?>
                                        <li><strong><?= htmlspecialchars($skill['TITLE']) ?></strong><?php echo $skill['DESCRIPTION'] ? ": " . htmlspecialchars($skill['DESCRIPTION']) : ''; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="section">
                    <h2 class="section-title">Experience</h2>
                    <?php if (!empty($experiences)): ?>
                        <?php foreach ($experiences as $exp): ?>
                            <div class="experience-item">
                                <h3 class="job-title"><?= htmlspecialchars($exp['ROLE']) ?></h3>
                                <p class="company"><?= htmlspecialchars($exp['ORGANIZATIONNAME']) ?></p>
                                <p class="date">
                                    <?= date('F Y', strtotime($exp['STARTDATE'])) ?> - 
                                    <?= $exp['ENDDATE'] ? date('F Y', strtotime($exp['ENDDATE'])) : 'Present' ?>
                                    <?php if (!empty($exp['ORGANIZATIONNAME']) && strpos($exp['ORGANIZATIONNAME'], 'Greenfield') !== false): ?>
                                        <span style="float: right; color: #777;">Greenfield, IN</span>
                                    <?php endif; ?>
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
                    <?php else: ?>
                        <p>No work experience added yet.</p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($projects)): ?>
                    <div class="section">
                        <h2 class="section-title">Projects</h2>
                        <ul>
                            <?php foreach ($projects as $project): ?>
                                <li><strong><?= htmlspecialchars($project['TITLE']) ?></strong><?php echo $project['DESCRIPTION'] ? ": " . htmlspecialchars($project['DESCRIPTION']) : ''; ?></li>
                            <?php endforeach; ?>
                        </ul>
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