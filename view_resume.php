<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php';

$user_id = $_SESSION['user_id'];
$resume_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch resume data
$resume_stmt = $conn->prepare("
    SELECT r.*, u.FULLNAME, u.EMAIL 
    FROM resumes r
    JOIN users u ON r.USERID = u.USERID
    WHERE r.RESUMEID = ? AND r.USERID = ?
");
$resume_stmt->bind_param("ii", $resume_id, $user_id);
$resume_stmt->execute();
$resume_result = $resume_stmt->get_result();

if ($resume_result->num_rows === 0) {
    header('Location: my_resumes.php');
    exit;
}

$resume = $resume_result->fetch_assoc();

// Parse resume data
function parseResumeData($education_field) {
    $resume_data = [
        'title' => 'AI Specialist',
        'company' => '',
        'summary' => '',
        'phone' => '',
        'linkedin' => '',
        'github' => ''
    ];
    $education = '';
    
    if (!empty($education_field)) {
        if (strpos($education_field, '---RESUME_DATA---') !== false) {
            $parts = explode('---RESUME_DATA---', $education_field);
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
            $education = $education_field;
        }
    }
    
    return ['resume_data' => $resume_data, 'education' => $education];
}

$parsed = parseResumeData($resume['EDUCATION']);
$resume_data = $parsed['resume_data'];
$education = $parsed['education'];

// Fetch experiences
$exp_stmt = $conn->prepare("SELECT * FROM experience WHERE USERID = ? ORDER BY STARTDATE DESC");
$exp_stmt->bind_param("i", $user_id);
$exp_stmt->execute();
$experiences = $exp_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch certificates
$cert_stmt = $conn->prepare("SELECT * FROM certificates WHERE USERID = ? ORDER BY ISSUEDATE DESC");
$cert_stmt->bind_param("i", $user_id);
$cert_stmt->execute();
$certificates = $cert_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch skills
$skills_stmt = $conn->prepare("SELECT * FROM items WHERE USERID = ? AND TYPE = 'skill' ORDER BY ITEMID");
$skills_stmt->bind_param("i", $user_id);
$skills_stmt->execute();
$skills = $skills_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to format date
function formatDate($date) {
    if (empty($date)) return 'Present';
    $d = new DateTime($date);
    return $d->format('M Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($resume_data['title']) ?> - Resume</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #0a2463;
            --accent-blue: #3e92cc;
            --light-blue: #d7e3fc;
            --dark-blue: #001845;
            --text-primary: #212121;
            --text-secondary: #424242;
            --text-light: #757575;
            --white: #ffffff;
            --off-white: #f8f9fa;
            --border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .resume-container {
            max-width: 800px;
            margin: 30px auto;
            background: var(--white);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .resume-header {
            display: flex;
            justify-content: space-between;
            padding: 30px;
            background: var(--primary-blue);
            color: var(--white);
        }

        .resume-title {
            flex: 2;
        }

        .resume-title h1 {
            font-size: 2.2rem;
            margin-bottom: 5px;
        }

        .resume-title p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .resume-contact {
            flex: 1;
            text-align: right;
        }

        .resume-contact p {
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .resume-contact a {
            color: var(--white);
            text-decoration: none;
        }

        .resume-body {
            display: flex;
            padding: 30px;
        }

        .resume-sidebar {
            flex: 1;
            padding-right: 20px;
        }

        .resume-main {
            flex: 2;
            padding-left: 20px;
            border-left: 1px solid #eee;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            color: var(--primary-blue);
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--accent-blue);
        }

        .experience-item, .education-item, .certificate-item {
            margin-bottom: 20px;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .item-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .item-date {
            color: var(--accent-blue);
            font-size: 0.9rem;
        }

        .item-subtitle {
            font-style: italic;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .item-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .skill-tag {
            background: var(--light-blue);
            color: var(--primary-blue);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: var(--white);
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
            padding: 12px 20px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--light-blue);
            transform: translateY(-2px);
        }

        @media print {
            .print-btn, .back-btn {
                display: none;
            }

            body {
                background: none;
            }

            .resume-container {
                box-shadow: none;
                margin: 0;
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .resume-header {
                flex-direction: column;
            }

            .resume-contact {
                text-align: left;
                margin-top: 20px;
            }

            .resume-body {
                flex-direction: column;
            }

            .resume-sidebar {
                padding-right: 0;
                margin-bottom: 30px;
            }

            .resume-main {
                padding-left: 0;
                border-left: none;
                border-top: 1px solid #eee;
                padding-top: 30px;
            }

            .print-btn, .back-btn {
                bottom: 15px;
                right: 15px;
                padding: 10px 15px;
                font-size: 0.9rem;
            }

            .back-btn {
                left: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="resume-container">
        <!-- Header Section -->
        <div class="resume-header">
            <div class="resume-title">
                <h1><?= htmlspecialchars($resume['FULLNAME']) ?></h1>
                <p><?= htmlspecialchars($resume_data['title']) ?></p>
            </div>
            <div class="resume-contact">
                <?php if (!empty($resume['EMAIL'])): ?>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($resume['EMAIL']) ?></p>
                <?php endif; ?>
                <?php if (!empty($resume_data['phone'])): ?>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($resume_data['phone']) ?></p>
                <?php endif; ?>
                <?php if (!empty($resume_data['linkedin'])): ?>
                    <p><i class="fab fa-linkedin"></i> <a href="<?= htmlspecialchars($resume_data['linkedin']) ?>" target="_blank">LinkedIn Profile</a></p>
                <?php endif; ?>
                <?php if (!empty($resume_data['github'])): ?>
                    <p><i class="fab fa-github"></i> <a href="<?= htmlspecialchars($resume_data['github']) ?>" target="_blank">GitHub Profile</a></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Body Section -->
        <div class="resume-body">
            <!-- Sidebar -->
            <div class="resume-sidebar">
                <?php if (!empty($education)): ?>
                    <div class="section">
                        <h3 class="section-title">Education</h3>
                        <div class="education-item">
                            <div class="item-description">
                                <?= nl2br(htmlspecialchars($education)) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($skills)): ?>
                    <div class="section">
                        <h3 class="section-title">Skills</h3>
                        <div class="skills-list">
                            <?php foreach ($skills as $skill): ?>
                                <div class="skill-tag"><?= htmlspecialchars($skill['TITLE']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($certificates)): ?>
                    <div class="section">
                        <h3 class="section-title">Certifications</h3>
                        <?php foreach ($certificates as $cert): ?>
                            <div class="certificate-item">
                                <div class="item-title"><?= htmlspecialchars($cert['NAME']) ?></div>
                                <div class="item-subtitle"><?= htmlspecialchars($cert['ISSUER']) ?></div>
                                <div class="item-date"><?= formatDate($cert['ISSUEDATE']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="resume-main">
                <?php if (!empty($resume_data['summary'])): ?>
                    <div class="section">
                        <h3 class="section-title">Professional Summary</h3>
                        <div class="item-description">
                            <?= nl2br(htmlspecialchars($resume_data['summary'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($experiences)): ?>
                    <div class="section">
                        <h3 class="section-title">Work Experience</h3>
                        <?php foreach ($experiences as $exp): ?>
                            <div class="experience-item">
                                <div class="item-header">
                                    <div class="item-title"><?= htmlspecialchars($exp['ROLE']) ?></div>
                                    <div class="item-date">
                                        <?= formatDate($exp['STARTDATE']) ?> - <?= formatDate($exp['ENDDATE']) ?>
                                    </div>
                                </div>
                                <div class="item-subtitle"><?= htmlspecialchars($exp['ORGANIZATIONNAME']) ?></div>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i>
        Print Resume
    </button>

    <a href="my_resumes.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Back to Resumes
    </a>

   
</body>
</html>