<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php'; // Ensure this path is correct for your database connection

// Check if template_id is provided
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 6;

// Handle form submission for saving resume
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resume'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get all form data
    $title = $_POST['title'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $linkedin = $_POST['linkedin'] ?? '';
    $website = $_POST['website'] ?? '';
    $orcid = $_POST['orcid'] ?? '';
    $research_interests = $_POST['research_interests'] ?? '';
    $education = $_POST['education'] ?? '';
    $publications = $_POST['publications'] ?? '';
    $conferences = $_POST['conferences'] ?? '';
    $awards = $_POST['awards'] ?? '';
    $grants = $_POST['grants'] ?? '';
    $teaching_experience = $_POST['teaching_experience'] ?? '';
    $research_experience = $_POST['research_experience'] ?? '';
    $languages = $_POST['languages'] ?? '';
    $professional_memberships = $_POST['professional_memberships'] ?? '';
    $references = $_POST['references'] ?? '';
    
    // Create a comprehensive resume data string
    $resume_data = "title=" . $title . "|phone=" . $phone . "|address=" . $address . "|linkedin=" . $linkedin .
                    "|website=" . $website . "|orcid=" . $orcid . "|research_interests=" . $research_interests .
                    "|publications=" . $publications . "|conferences=" . $conferences . "|awards=" . $awards .
                    "|grants=" . $grants . "|teaching_experience=" . $teaching_experience . "|research_experience=" . $research_experience .
                    "|languages=" . $languages . "|professional_memberships=" . $professional_memberships . "|references=" . $references;
    
    // Insert into resumes table
    $stmt = $conn->prepare("INSERT INTO resumes (USERID, TEMPLATEID, EDUCATION) VALUES (?, ?, ?)");
    $full_resume_data = $education . "---RESUME_DATA---" . $resume_data;
    $stmt->bind_param("iis", $user_id, $template_id, $full_resume_data);
    
    if ($stmt->execute()) {
        $success_message = "Academic CV saved successfully!";
        $resume_id = $conn->insert_id;
        header("Location: " . $_SERVER['PHP_SELF'] . "?template_id=" . $template_id . "&saved=1");
        exit;
    } else {
        $error_message = "Error saving CV: " . $conn->error;
    }
}

// Check if we just saved
if (isset($_GET['saved'])) {
    $success_message = "Academic CV saved successfully!";
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
$resume_data = ['title' => '', 'phone' => '', 'address' => '', 'linkedin' => '', 'website' => '', 'orcid' => '',
                'research_interests' => '', 'publications' => '', 'conferences' => '', 'awards' => '', 'grants' => '',
                'teaching_experience' => '', 'research_experience' => '', 'languages' => '', 'professional_memberships' => '', 'references' => ''];
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
function makeLinksClickable($text) {
    if (empty($text)) return '';
    
    if (strpos($text, 'linkedin.com') !== false || strpos($text, 'LinkedIn') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: #2c5282; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    if (strpos($text, 'orcid.org') !== false || strpos($text, 'ORCID') !== false) {
        if (!preg_match('/^https?:\/\//', $text)) {
            $text = 'https://orcid.org/' . ltrim($text, '/');
        }
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: #2c5282; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    if (preg_match('/^https?:\/\//', $text)) {
        return '<a href="' . htmlspecialchars($text) . '" target="_blank" style="color: #2c5282; text-decoration: underline;">' . htmlspecialchars($text) . '</a>';
    }
    
    return htmlspecialchars($text);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic CV - Resume Builder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --academic-navy: rgb(4, 19, 62);
            --academic-blue: rgb(21, 89, 97);
            --academic-light-blue: rgb(14, 38, 66);
            --academic-gold: #d97706;
            --academic-light-gold: #f59e0b;
            --academic-gray: #374151;
            --academic-light-gray: #6b7280;
            --academic-white: #ffffff;
            --academic-cream: #fefce8;
            --academic-border: #d1d5db;
            --shadow-elegant: 0 4px 12px rgba(30, 58, 138, 0.15);
            --shadow-soft: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        body {
            background: url('https://img.freepik.com/free-photo/abstract-digital-grid-black-background_53876-97647.jpg?semt=ais_items_boosted&w=740') no-repeat center center fixed;
            background-size: cover;
            background-position: center, center;
            background-attachment: fixed, fixed;
            background-repeat: no-repeat, no-repeat;
            background-blend-mode: overlay;
            color: var(--academic-gray);
            font-family: 'Georgia', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.6;
            margin: 0;
            padding: 1rem;
        }

        .container {
            max-width: 10in;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
            background: rgba(255, 255, 255, 0.96);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: var(--shadow-elegant);
            backdrop-filter: blur(10px);
        }

        .form-container {
            background: linear-gradient(135deg, var(--academic-navy) 0%, var(--academic-blue) 100%);
            color: var(--academic-white);
            padding: 2rem;
            border-radius: 15px;
            width: 350px;
            box-shadow: var(--shadow-elegant);
            height: fit-content;
            position: sticky;
            top: 1rem;
        }

        .form-title {
            color: var(--academic-light-gold);
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--academic-cream);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.1);
            color: var(--academic-white);
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--academic-light-gold);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .category-title {
            color: var(--academic-light-gold);
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            margin-top: 1.5rem;
            border-bottom: 2px solid var(--academic-light-gold);
            padding-bottom: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn {
            padding: 0.8rem 1.8rem;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            margin: 0.5rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--academic-gold), var(--academic-light-gold));
            color: var(--academic-white);
            box-shadow: 0 4px 15px rgba(217, 119, 6, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(217, 119, 6, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--academic-light-gold);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--academic-cream);
            border-left-color: #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--academic-cream);
            border-left-color: #ef4444;
        }

        /* CV Specific Styles */
        .cv-container {
            background: var(--academic-white);
            padding: 0;
            font-size: 11pt;
            border: 1px solid var(--academic-border);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .cv-header {
            background: linear-gradient(135deg, var(--academic-navy) 0%, var(--academic-blue) 100%);
            color: var(--academic-white);
            padding: 2rem 2rem 1.5rem 2rem;
            text-align: center;
            position: relative;
        }

        .cv-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="academic-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23academic-pattern)"/></svg>');
            opacity: 0.3;
        }

        .cv-name {
            font-size: 2.2rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .cv-title {
            font-size: 1.2rem;
            font-weight: 400;
            color: var(--academic-light-gold);
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .cv-contact {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .contact-item i {
            color: var(--academic-light-gold);
            width: 16px;
        }

        .cv-body {
            padding: 0;
        }

        .cv-section {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--academic-border);
        }

        .cv-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--academic-navy);
            margin: 0 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--academic-gold);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .cv-item {
            margin-bottom: 1.5rem;
        }

        .cv-item:last-child {
            margin-bottom: 0;
        }

        .item-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--academic-navy);
            margin-bottom: 0.3rem;
        }

        .item-subtitle {
            font-style: italic;
            color: var(--academic-light-gray);
            margin-bottom: 0.3rem;
        }

        .item-date {
            font-size: 0.9rem;
            color: var(--academic-gold);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .item-description {
            text-align: justify;
            color: var(--academic-gray);
            line-height: 1.6;
        }

        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .skill-category {
            background: var(--academic-cream);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--academic-gold);
        }

        .skill-category h4 {
            color: var(--academic-navy);
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .publication-item {
            padding: 1rem;
            background: var(--academic-cream);
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--academic-blue);
        }

        .publication-item:last-child {
            margin-bottom: 0;
        }

        /* Print Specific Styles */
        @media print {
            body {
                background-image: none;
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

            .cv-container {
                border: none;
                box-shadow: none;
            }

            .cv-header {
                background: var(--academic-navy) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .section-title {
                color: var(--academic-navy) !important;
                border-bottom-color: var(--academic-gold) !important;
            }
        }

        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
                padding: 1rem;
                gap: 1rem;
            }

            .form-container {
                position: relative;
                top: 0;
                padding: 1.5rem;
            }

            .cv-container {
                font-size: 10pt;
            }

            .cv-header {
                padding: 1.5rem 1rem;
            }

            .cv-name {
                font-size: 1.8rem;
            }

            .cv-contact {
                flex-direction: column;
                gap: 0.5rem;
            }

            .cv-section {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title"><i class="fas fa-graduation-cap"></i> Academic CV Information</h2>
            
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
            
            <form method="POST" id="cvForm">
                <div class="category-title"><i class="fas fa-user"></i> Personal Information</div>
                <div class="form-group">
                    <label for="title">Academic Title</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($resume_data['title']) ?>" placeholder="e.g., Assistant Professor, Research Scientist">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="number" id="phone" name="phone" value="<?= htmlspecialchars($resume_data['phone']) ?>" placeholder="e.g., +1 (555) 123-4567">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= htmlspecialchars($resume_data['address']) ?>" placeholder="e.g., 123 University Ave, City, State">
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
                    <label for="orcid">ORCID ID</label>
                    <input type="text" id="orcid" name="orcid" value="<?= htmlspecialchars($resume_data['orcid']) ?>" placeholder="e.g., 0000-0000-0000-0000">
                </div>
                
                <div class="category-title"><i class="fas fa-microscope"></i> Research</div>
                <div class="form-group">
                    <label for="research_interests">Research Interests</label>
                    <textarea id="research_interests" name="research_interests" placeholder="List your research interests and areas of expertise..."><?= htmlspecialchars($resume_data['research_interests']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="research_experience">Research Experience</label>
                    <textarea id="research_experience" name="research_experience" placeholder="Detail your research experience, projects, and roles..."><?= htmlspecialchars($resume_data['research_experience']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-book"></i> Publications & Presentations</div>
                <div class="form-group">
                    <label for="publications">Publications</label>
                    <textarea id="publications" name="publications" placeholder="List your publications in standard academic format..."><?= htmlspecialchars($resume_data['publications']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="conferences">Conferences & Presentations</label>
                    <textarea id="conferences" name="conferences" placeholder="List conference presentations, talks, and posters..."><?= htmlspecialchars($resume_data['conferences']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-chalkboard-teacher"></i> Teaching</div>
                <div class="form-group">
                    <label for="teaching_experience">Teaching Experience</label>
                    <textarea id="teaching_experience" name="teaching_experience" placeholder="Detail your teaching experience, courses taught, and responsibilities..."><?= htmlspecialchars($resume_data['teaching_experience']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-trophy"></i> Awards & Grants</div>
                <div class="form-group">
                    <label for="awards">Awards & Honors</label>
                    <textarea id="awards" name="awards" placeholder="List academic awards, honors, and recognitions..."><?= htmlspecialchars($resume_data['awards']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="grants">Grants & Funding</label>
                    <textarea id="grants" name="grants" placeholder="List research grants, fellowships, and funding received..."><?= htmlspecialchars($resume_data['grants']) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-graduation-cap"></i> Education</div>
                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" placeholder="Ph.D. in Field, University Name, Year
M.S. in Field, University Name, Year
B.S. in Field, University Name, Year"><?= htmlspecialchars($education) ?></textarea>
                </div>
                
                <div class="category-title"><i class="fas fa-globe"></i> Additional Information</div>
                <div class="form-group">
                    <label for="languages">Languages</label>
                    <textarea id="languages" name="languages" placeholder="List languages and proficiency levels..."><?= htmlspecialchars($resume_data['languages']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="professional_memberships">Professional Memberships</label>
                    <textarea id="professional_memberships" name="professional_memberships" placeholder="List professional societies and organizations..."><?= htmlspecialchars($resume_data['professional_memberships']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="references">References</label>
                    <textarea id="references" name="references" placeholder="Available upon request or list 3-4 academic references..."><?= htmlspecialchars($resume_data['references']) ?></textarea>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" name="save_resume" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save CV
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-success">
                        <i class="fas fa-print"></i> Print CV
                    </button>
                </div>
            </form>
        </div>
        
        <div class="cv-container" id="cvPreview">
            <div class="cv-header">
                <h1 class="cv-name"><?= htmlspecialchars($user['FULLNAME']) ?></h1>
                <?php if (!empty($resume_data['title'])): ?>
                    <div class="cv-title"><?= htmlspecialchars($resume_data['title']) ?></div>
                <?php endif; ?>
                <div class="cv-contact">
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
                            <span><?= makeLinksClickable($resume_data['website']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['linkedin'])): ?>
                        <div class="contact-item">
                            <i class="fab fa-linkedin"></i>
                            <span><?= makeLinksClickable($resume_data['linkedin']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($resume_data['orcid'])): ?>
                        <div class="contact-item">
                            <i class="fab fa-orcid"></i>
                            <span><?= makeLinksClickable($resume_data['orcid']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="cv-body">
                <?php if (!empty($resume_data['research_interests'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Research Interests</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['research_interests'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($education)): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Education</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($education)) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($experiences)): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Professional Experience</h2>
                        <?php foreach ($experiences as $exp): ?>
                            <div class="cv-item">
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

                <?php if (!empty($resume_data['research_experience'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Research Experience</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['research_experience'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['teaching_experience'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Teaching Experience</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['teaching_experience'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['publications'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Publications</h2>
                        <div class="publication-item"><?= nl2br(htmlspecialchars($resume_data['publications'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['conferences'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Conferences & Presentations</h2>
                        <div class="publication-item"><?= nl2br(htmlspecialchars($resume_data['conferences'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['awards'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Awards & Honors</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['awards'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['grants'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Grants & Funding</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['grants'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($skills)): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Skills</h2>
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
                    <div class="cv-section">
                        <h2 class="section-title">Certificates</h2>
                        <?php foreach ($certificates as $cert): ?>
                            <div class="cv-item">
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
                    <div class="cv-section">
                        <h2 class="section-title">Languages</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['languages'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['professional_memberships'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">Professional Memberships</h2>
                        <div class="item-description"> nl2br(htmlspecialchars($resume_data['professional_memberships'])) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resume_data['references'])): ?>
                    <div class="cv-section">
                        <h2 class="section-title">References</h2>
                        <div class="item-description"><?= nl2br(htmlspecialchars($resume_data['references'])) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>