<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php'; // Ensure this path is correct for your database connection

// Check if template_id is provided
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 7;

// Handle form submission for saving resume
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resume'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get all form data
    $title = $_POST['title'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $linkedin = $_POST['linkedin'] ?? '';
    $website = $_POST['website'] ?? '';
    $portfolio = $_POST['portfolio'] ?? '';
    $social_media = $_POST['social_media'] ?? '';
    $personal_statement = $_POST['personal_statement'] ?? '';
    $creative_skills = $_POST['creative_skills'] ?? '';
    $education = $_POST['education'] ?? '';
    $creative_projects = $_POST['creative_projects'] ?? '';
    $awards = $_POST['awards'] ?? '';
    $mentorship_workshops = $_POST['mentorship_workshops'] ?? '';
    $languages = $_POST['languages'] ?? '';
    $references = $_POST['references'] ?? '';
    
    // Create a comprehensive resume data string
    $resume_data = "title=" . $title . "|phone=" . $phone . "|address=" . $address . "|linkedin=" . $linkedin .
                   "|website=" . $website . "|portfolio=" . $portfolio . "|social_media=" . $social_media .
                   "|personal_statement=" . $personal_statement . "|creative_skills=" . $creative_skills .
                   "|creative_projects=" . $creative_projects . "|awards=" . $awards .
                   "|mentorship_workshops=" . $mentorship_workshops . "|languages=" . $languages .
                   "|references=" . $references;
    
    // Insert into resumes table
    $stmt = $conn->prepare("INSERT INTO resumes (USERID, TEMPLATEID, EDUCATION) VALUES (?, ?, ?)");
    $full_resume_data = $education . "---RESUME_DATA---" . $resume_data;
    $stmt->bind_param("iis", $user_id, $template_id, $full_resume_data);
    
    if ($stmt->execute()) {
        $success_message = "Creative Resume saved successfully!";
        $resume_id = $conn->insert_id;
        header("Location: " . $_SERVER['PHP_SELF'] . "?template_id=" . $template_id . "&saved=1");
        exit;
    } else {
        $error_message = "Error saving resume: " . $conn->error;
    }
}

// Check if we just saved
if (isset($_GET['saved'])) {
    $success_message = "Creative Resume saved successfully!";
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
$resume_data = [
    'title' => '', 'phone' => '', 'address' => '', 'linkedin' => '', 'website' => '',
    'portfolio' => '', 'social_media' => '', 'personal_statement' => '', 'creative_skills' => '',
    'creative_projects' => '', 'awards' => '', 'mentorship_workshops' => '', 'languages' => '', 'references' => ''
];
$education = '';
$resume_stmt = $conn->prepare("SELECT EDUCATION FROM resumes WHERE USERID = ? AND TEMPLATEID = ? ORDER BY GENERATEDAT DESC LIMIT 1");
$resume_stmt->bind_param("ii", $user_id, $template_id);
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
function makeLinks($text) {
    if (empty($text)) return '';
    
    if (strpos($text, 'linkedin.com') !== false || strpos($text, 'LinkedIn') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: #ff6f61; text-decoration: none; border-bottom: 1px dotted #ff6f61;">' . htmlspecialchars($text) . '</a>';
    }
    
    if (preg_match('/^https?:\/\//', $text)) {
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: #ff6f61; text-decoration: none; border-bottom: 1px dotted #ff6f61;">' . htmlspecialchars($text) . '</a>';
    }
    
    return htmlspecialchars($text);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creative Resume</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --creative-indigo:rgb(7, 73, 68);
            --creative-coral: #ff6f61;
            --creative-lime: #a3e635;
            --creative-dark: #1a1a3d;
            --creative-light: #f8f9fa;
            --creative-accent: #ffd166;
            --creative-gray: #6c757d;
            --creative-white: #ffffff;
            --shadow-creative: 0 8px 20px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        body {
            background: linear-gradient(145deg, var(--creative-indigo) 0%, var(--creative-coral) 100%);
            font-family: 'Montserrat', sans-serif;
            background: url('https://img.freepik.com/free-photo/abstract-digital-grid-black-background_53876-97647.jpg?semt=ais_items_boosted&w=740') no-repeat center center fixed;
            background-size: cover;
            font-size: 11pt;
            line-height: 1.8;
            margin: 0;
            padding: 2rem;
            color: var(--creative-dark);
        }

        .container {
            max-width: 10in;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            background: rgb(192, 181, 190);
            padding: 2.5rem;
            border-radius: 25px;
            box-shadow: var(--shadow-creative);
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 111, 97, 0.1) 0%, transparent 70%);
            transform: rotate(45deg);
            pointer-events: none;
        }

        .form-container {
            background: var(--creative-dark);
            color: var(--creative-white);
            padding: 2.5rem;
            border-radius: 20px;
            width: 360px;
            box-shadow: var(--shadow-creative);
            height: fit-content;
            position: sticky;
            top: 2rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-container:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }

        .form-title {
            color: var(--creative-lime);
            margin-bottom: 2rem;
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 600;
            color: var(--creative-lime);
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Montserrat', sans-serif;
            background: rgba(255, 255, 255, 0.12);
            color: var(--creative-white);
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--creative-lime);
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 0 0 4px rgba(163, 230, 53, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .category-title {
            color: var(--creative-coral);
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            margin-top: 2rem;
            padding-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            position: relative;
        }

        .category-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--creative-accent);
            border-radius: 2px;
        }

        .btn {
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            margin: 0.6rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .btn-primary {
            background: var(--creative-coral);
            color: var(--creative-white);
            box-shadow: var(--shadow-creative);
        }

        .btn-primary:hover {
            background: var(--creative-lime);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .btn-success {
            background: var(--creative-indigo);
            color: var(--creative-white);
            box-shadow: var(--shadow-creative);
        }

        .btn-success:hover {
            background: var(--creative-accent);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .alert {
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.2rem;
            border-left: 5px solid var(--creative-accent);
            background: rgba(255, 255, 255, 0.12);
            color: var(--creative-white);
            font-size: 0.95rem;
        }

        .alert-success {
            border-left-color: var(--creative-lime);
        }

        .alert-error {
            border-left-color: var(--creative-coral);
        }

        .resume-container {
            background: var(--creative-light);
            padding: 0;
            font-size: 11pt;
            border-radius: 20px;
            box-shadow: var(--shadow-creative);
            transition: transform 0.3s ease;
        }

        .resume-container:hover {
            transform: translateY(-8px);
        }

        .resume-header {
            background: linear-gradient(135deg, var(--creative-indigo) 0%, var(--creative-coral) 100%);
            color: var(--creative-white);
            padding: 3rem 2.5rem;
            text-align: center;
            position: relative;
            border-radius: 20px 20px 0 0;
            overflow: hidden;
        }

        .resume-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M10,90 Q50,10 90,90" stroke="rgba(255,255,255,0.15)" stroke-width="3" fill="none"/></svg>');
            opacity: 0.4;
        }

        .resume-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            font-weight: 700;
            margin: 0 0 0.6rem 0;
            position: relative;
            z-index: 1;
            text-shadow: 0 3px 6px rgba(0, 0, 0, 0.2);
        }

        .resume-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.4rem;
            font-weight: 400;
            color: var(--creative-accent);
            margin-bottom: 1.2rem;
            position: relative;
            z-index: 1;
        }

        .resume-contact {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.8rem;
            position: relative;
            z-index: 1;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            transition: background 0.3s ease, transform 0.3s ease;
        }

        .contact-item:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        .contact-item i {
            color: var(--creative-lime);
            width: 18px;
        }

        .resume-body {
            padding: 0;
        }

        .resume-section {
            padding: 2.5rem;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .resume-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--creative-dark);
            margin: 0 0 1.2rem 0;
            padding-bottom: 0.6rem;
            border-bottom: 4px solid var(--creative-coral);
            text-transform: uppercase;
            letter-spacing: 1.8px;
            position: relative;
        }

        .section-title::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 50%;
            width: 12px;
            height: 12px;
            background: var(--creative-lime);
            border-radius: 50%;
        }

        .resume-item {
            margin-bottom: 1.8rem;
            padding: 1.2rem;
            background: var(--creative-white);
            border-radius: 12px;
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.06);
            transition: transform 0.3s ease;
        }

        .resume-item:hover {
            transform: translateX(8px);
        }

        .resume-item:last-child {
            margin-bottom: 0;
        }

        .item-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 1.2rem;
            color: var(--creative-dark);
            margin-bottom: 0.4rem;
        }

        .item-subtitle {
            font-style: italic;
            color: var(--creative-gray);
            margin-bottom: 0.4rem;
        }

        .item-date {
            font-size: 0.95rem;
            color: var(--creative-coral);
            font-weight: 600;
            margin-bottom: 0.6rem;
        }

        .item-description {
            text-align: justify;
            color: var(--creative-dark);
            line-height: 1.8;
        }

        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
        }

        .skill-category {
            background: var(--creative-white);
            padding: 1.2rem;
            border-radius: 12px;
            border-left: 5px solid var(--creative-coral);
            transition: transform 0.3s ease;
        }

        .skill-category:hover {
            transform: translateY(-5px);
        }

        .skill-category h4 {
            color: var(--creative-dark);
            margin: 0 0 0.6rem 0;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .project-item {
            padding: 1.2rem;
            background: var(--creative-white);
            border-radius: 12px;
            margin-bottom: 1.2rem;
            border-left: 5px solid var(--creative-coral);
            transition: transform 0.3s ease;
        }

        .project-item:hover {
            transform: translateX(8px);
        }

        .project-item:last-child {
            margin-bottom: 0;
        }

        @media print {
            body {
                background: none;
                background-color: white;
                padding: 0;
                margin: 0;
                font-size: 10pt;
            }

            .form-container {
                display: none;
            }

            .container {
                grid-template-columns: 1fr;
                max-width: 8.5in;
                gap: 0;
                background: white;
                padding: 0;
                box-shadow: none;
            }

            .resume-container {
                box-shadow: none;
                border-radius: 0;
            }

            .resume-header {
                background: var(--creative-indigo) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .section-title {
                color: var(--creative-dark) !important;
                border-bottom-color: var(--creative-coral) !important;
            }

            .resume-item,
            .skill-category,
            .project-item {
                box-shadow: none;
                transform: none;
            }
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                padding: 1.5rem;
                gap: 1.5rem;
            }

            .form-container {
                position: relative;
                top: 0;
                padding: 2rem;
                width: auto;
            }

            .resume-container {
                font-size: 10pt;
            }

            .resume-header {
                padding: 2rem 1.5rem;
            }

            .resume-name {
                font-size: 2.2rem;
            }

            .resume-contact {
                flex-direction: column;
                gap: 0.8rem;
            }

            .resume-section {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title"><i class="fas fa-palette"></i> Creative Resume Builder</h2>
            
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
                <div class="category-title"><i class="fas fa-user"></i> Personal Information</div>
                <div class="form-group">
                    <label for="title">Professional Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($resume_data['title']) ?>" placeholder="e.g., Graphic Designer, Creative Director">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($resume_data['phone']) ?>" placeholder="e.g., +1 (555) 123-4567">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($resume_data['address']) ?>" placeholder="e.g., 123 Creative St, City">
                </div>
                
                <div class="form-group">
                    <label for="linkedin">LinkedIn Profile</label>
                    <input type="text" id="linkedin" name="linkedin" value="<?= htmlspecialchars($resume_data['linkedin']) ?>" placeholder="e.g., linkedin.com/in/yourprofile">
                </div>
                
                <div class="form-group">
                    <label for="website">Personal Website</label>
                    <input type="text" id="website" name="website" value="<?= htmlspecialchars($resume_data['website']) ?>" placeholder="e.g., www.yourwebsite.com">
                </div>
                
                <div class="form-group">
                    <label for="portfolio">Portfolio Link</label>
                    <input type="text" id="portfolio" name="portfolio" value="<?= htmlspecialchars($resume_data['portfolio']) ?>" placeholder="e.g., behance.net/yourportfolio">
                </div>
                
                <div class="form-group">
                    <label for="social_media">Social Media Handle</label>
                    <input type="text" id="social_media" name="social_media" value="<?= htmlspecialchars($resume_data['social_media']) ?>" placeholder="e.g., @yourhandle on Instagram">
                </div>
                
                <div class="category-title"><i class="fas fa-star"></i> About You</div>
                <div class="form-group">
                    <label for="personal_statement">Personal Statement</label>
                    <textarea id="personal_statement" name="personal_statement" placeholder="Describe your creative vision and passion..."><?= htmlspecialchars($resume_data['personal_statement']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="creative_skills">Creative Skills</label>
                    <textarea id="creative_skills" name="creative_skills" placeholder="List your skills, e.g., Adobe Suite, UI/UX, Animation..."><?= htmlspecialchars($resume_data['creative_skills']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-palette"></i> Creative Work</div>
                <div class="form-group">
                    <label for="creative_projects">Creative Projects</label>
                    <textarea id="creative_projects" name="creative_projects" placeholder="Showcase your key projects or portfolio pieces..."><?= htmlspecialchars($resume_data['creative_projects']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-chalkboard-teacher"></i> Mentorship & Workshops</div>
                <div class="form-group">
                    <label for="mentorship_workshops">Mentorship or Workshops</label>
                    <textarea id="mentorship_workshops" name="mentorship_workshops" placeholder="Describe any creative workshops or mentorship roles..."><?= htmlspecialchars($resume_data['mentorship_workshops']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-trophy"></i> Achievements</div>
                <div class="form-group">
                    <label for="awards">Awards & Recognition</label>
                    <textarea id="awards" name="awards" placeholder="List your creative awards or honors..."><?= htmlspecialchars($resume_data['awards']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-graduation-cap"></i> Education</div>
                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" placeholder="B.F.A. in Design, University Name, Year"><?= htmlspecialchars($education) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-globe"></i> Additional Info</div>
                <div class="form-group">
                    <label for="languages">Languages</label>
                    <textarea id="languages" name="languages" placeholder="List languages and proficiency levels..."><?= htmlspecialchars($resume_data['languages']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="references">References</label>
                    <textarea id="references" name="references" placeholder="Available upon request or list contacts..."><?= htmlspecialchars($resume_data['references']) ?></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 2.5rem;">
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
                <h1 class="resume-name"><?= htmlspecialchars($user['FULLNAME']) ?></h1>
                <?php if (!empty($resume_data['title'])): ?>
                    <div class="resume-title"><?= htmlspecialchars($resume_data['title']) ?></div>
                <?php endif; ?>
                <div class="resume-contact">
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
                    <?php if (!empty($resume_data['website'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-globe"></i>
                            <span><?= makeLinks($resume_data['website']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['linkedin'])): ?>
                        <div class="contact-item">
                            <i class="fab fa-linkedin"></i>
                            <span><?= makeLinks($resume_data['linkedin']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['portfolio'])): ?>
                        <div class="contact-item">
                            <i class="fas fa-briefcase"></i>
                            <span><?= makeLinks($resume_data['portfolio']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['social_media'])): ?>
                        <div class="contact-item">
                            <i class="fab fa-instagram"></i>
                            <span><?= htmlspecialchars($resume_data['social_media']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="resume-body">
                <?php if (!empty($resume_data['personal_statement'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Personal Statement</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['personal_statement'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['creative_skills'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Creative Skills</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['creative_skills'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($education)): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Education</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($education)) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($experiences)): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Professional Experience</h2>
                        <?php foreach ($experiences as $exp): ?>
                            <div class="resume-item">
                                <div class="item-title"><?= htmlspecialchars($exp['ROLE']) ?></div>
                                <div class="item-subtitle"><?= htmlspecialchars($exp['ORGANIZATIONNAME']) ?></div>
                                <div class="item-date">
                                    <?= htmlspecialchars(date('F Y', strtotime($exp['STARTDATE']))) ?> - 
                                    <?= $exp['ENDDATE'] ? htmlspecialchars(date('F Y', strtotime($exp['ENDDATE']))) : 'Present' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['creative_projects'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Creative Projects</h2>
                        <div class="project-item"><?= nl2br(htmlspecialchars($resume_data['creative_projects'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['mentorship_workshops'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Mentorship & Workshops</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['mentorship_workshops'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['awards'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Awards & Recognition</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['awards'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($skills)): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Additional Skills</h2>
                        <div class="skills-grid">
                            <?php foreach ($skills as $skill): ?>
                                <div class="skill-category">
                                    <h4><?= htmlspecialchars($skill['TITLE']) ?></h4>
                                    <div class="item-description"><?= nl2br(htmlspecialchars($skill['DESCRIPTION'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($certificates)): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Certificates</h2>
                        <?php foreach ($certificates as $cert): ?>
                            <div class="resume-item">
                                <div class="item-title"><?= htmlspecialchars($cert['NAME']) ?></div>
                                <div class="item-subtitle"><?= htmlspecialchars($cert['ISSUER']) ?></div>
                                <div class="item-date"><?= htmlspecialchars(date('F Y', strtotime($cert['ISSUEDATE']))) ?></div>
                                <?php if (!empty($cert['DESCRIPTION'])): ?>
                                    <div class="item-description"><?= nl2br(htmlspecialchars($cert['DESCRIPTION'])) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['languages'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">Languages</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['languages'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['references'])): ?>
                    <div class="resume-section">
                        <h2 class="section-title">References</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['references'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>