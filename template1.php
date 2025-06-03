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
    
    // Create a comprehensive resume data string
    $resume_data = "title=" . $title . "|phone=" . $phone . "|address=" . $address . "|linkedin=" . $linkedin . 
                  "|github=" . $github . "|summary=" . $summary . "|additional_skills=" . $additional_skills;
    
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
$resume_data = ['title' => '', 'phone' => '', 'address' => '', 'linkedin' => '', 'github' => '', 'summary' => '', 'additional_skills' => ''];
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
    
    // Check if it's a LinkedIn URL
    if (strpos($text, 'linkedin.com') !== false || strpos($text, 'LinkedIn') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: inherit; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    // Check if it's a GitHub URL
    if (strpos($text, 'github.com') !== false || strpos($text, 'GitHub') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: inherit; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    // For other URLs
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
    <title>Chronological Resume - Resume Builder</title>
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
    color: var(-- plasma-white);
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
}

.resume-container:hover {
    transform: translateY(-5px);
}

.resume-header {
    background: linear-gradient(135deg, var(--resume-blue), var(--resume-light-blue));
    color: var(--plasma-white);
    padding: 2.5rem;
    position: relative;
}

.header-content {
    position: relative;
    z-index: 1;
}

.name {
    font-size: 2.8rem;
    margin: 0;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.title {
    font-size: 1.3rem;
    margin: 0.6rem 0 0;
    font-weight: 400;
    opacity: 0.9;
}

.contact-info {
    margin-top: 1.8rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1.2rem;
}

.contact-item {
    display: flex;
    align-items: center;
    font-size: 0.95rem;
}

.contact-item i {
    margin-right: 0.6rem;
    color: var(--neon-blue);
}

.contact-item a {
    color: var(--plasma-white);
    text-decoration: underline;
    transition: color 0.3s ease;
}

.contact-item a:hover {
    color: var(--neon-blue);
}

.resume-body {
    padding: 2.5rem;
}

.section {
    margin-bottom: 2.5rem;
}

.section-title {
    color: var(--resume-blue);
    font-size: 1.4rem;
    border-bottom: 3px solid var(--neon-blue);
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
    grid-template-columns: 1fr 1fr;
    gap: 2.5rem;
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

    .skills-grid {
        grid-template-columns: 1fr;
    }

    .form-container,
    .resume-container {
        border-radius: 10px;
    }
}

@media print {
    .form-container {
        display: none;
    }

    .container {
        grid-template-columns: 1fr;
    }

    body {
        background: white;
        padding: 0;
    }

    .resume-container {
        box-shadow: none;
        margin: 0;
        border-radius: 0;
    }

    .btn-success {
        border: 2px solid var(--neon-blue);
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
                    <input type="number" id="phone" name="phone" max=13 value="<?= htmlspecialchars($resume_data['phone']) ?>" placeholder="e.g., +1-999-999-9999">
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
                    <textarea id="summary" name="summary" placeholder="Adaptable professional with 7+ years of experience and a proven knowledge of curriculum development, adaptive teaching methods, classroom management, education administration, student assessment, and lectures. Aiming to leverage my skills to successfully fill the School Teacher role."><?= htmlspecialchars($resume_data['summary']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" placeholder="Bachelor of Education in Teachers Education&#10;Franklin College - Franklin, IN&#10;May 2014"><?= htmlspecialchars($education) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="additional_skills">Additional Skills (one per line)</label>
                    <textarea id="additional_skills" name="additional_skills" placeholder="Microsoft Office&#10;Google Suite&#10;Project Management&#10;Communication&#10;Teaching"><?= htmlspecialchars($resume_data['additional_skills']) ?></textarea>
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
                <div class="header-content">
                    <h1 class="name"><?= htmlspecialchars($user['FULLNAME']) ?></h1>
                    <?php if (!empty($resume_data['title'])): ?>
                        <p class="title"><?= htmlspecialchars($resume_data['title']) ?></p>
                    <?php endif; ?>
                    
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
            </div>
            
            <div class="resume-body">
                <?php if (!empty($resume_data['summary'])): ?>
                    <div class="section">
                        <h2 class="section-title">Professional Summary</h2>
                        <p><?= nl2br(htmlspecialchars($resume_data['summary'])) ?></p>
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
                                        // Convert bullet points to proper list format
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
                
                <?php if (!empty($education)): ?>
                    <div class="section">
                        <h2 class="section-title">Education</h2>
                        <div class="education-item">
                            <?= nl2br(htmlspecialchars($education)) ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($skills) || !empty($resume_data['additional_skills'])): ?>
                    <div class="section">
                        <h2 class="section-title">Skills</h2>
                        <div class="skills-grid">
                            <?php if (!empty($skills)): ?>
                                <div class="skills-category">
                                    <h4>Technical Skills</h4>
                                    <ul class="skills-list">
                                        <?php foreach ($skills as $skill): ?>
                                            <li><?= htmlspecialchars($skill['TITLE']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($resume_data['additional_skills'])): ?>
                                <div class="skills-category">
                                    <h4>Additional Skills</h4>
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