<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'php/db.php';

$user_id = $_SESSION['user_id'];

// Fetch user information
$user_stmt = $conn->prepare("SELECT FULLNAME, EMAIL FROM users WHERE USERID = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Fetch all resumes with related data using JOIN queries
$resumes_stmt = $conn->prepare("
    SELECT 
        r.RESUMEID,
        r.TEMPLATEID,
        r.EDUCATION,
        r.GENERATEDAT,
        COUNT(DISTINCT e.EXPERIENCEID) as experience_count,
        COUNT(DISTINCT c.CERTIFICATEID) as certificate_count,
        COUNT(DISTINCT i.ITEMID) as skills_count
    FROM resumes r
    LEFT JOIN experience e ON r.USERID = e.USERID
    LEFT JOIN certificates c ON r.USERID = c.USERID  
    LEFT JOIN items i ON r.USERID = i.USERID
    WHERE r.USERID = ?
    GROUP BY r.RESUMEID, r.TEMPLATEID, r.EDUCATION, r.GENERATEDAT
    ORDER BY r.GENERATEDAT DESC
");
$resumes_stmt->bind_param("i", $user_id);
$resumes_stmt->execute();
$resumes = $resumes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Function to parse resume data
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

// Function to map template_id to template file
function getTemplateFile($template_id) {
    $template_map = [
        37 => 'template1.php',
        38 => 'template2.php',
        39 => 'template3.php',
        40 => 'template4.php',
        41 => 'template5.php',
        42 => 'template6.php',
        43 => 'template7.php'
    ];
    return isset($template_map[$template_id]) ? $template_map[$template_id] : 'targeted_resume.php';
}

// Handle resume deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resume'])) {
    $resume_id = intval($_POST['resume_id']);
    $delete_stmt = $conn->prepare("DELETE FROM resumes 
    WHERE RESUMEID = ? AND USERID = ?");


    $delete_stmt->bind_param("ii", $resume_id, $user_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['success_message'] = "Resume deleted successfully!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['error_message'] = "Error deleting resume.";
    }
}

// Handle confirmation step
$confirmation_resume_id = isset($_GET['confirm_delete']) ? intval($_GET['confirm_delete']) : null;

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Resumes - Resume Builder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: rgb(19, 112, 112);
            --accent-blue: #3e92cc;
            --light-blue: #d7e3fc;
            --dark-blue: #001845;
            --hover-blue: rgb(8, 38, 81);
            --hover-accent: rgb(7, 69, 96);
            --success-green: #2e7d32;
            --danger-red: #c62828;
            --text-primary: #212121;
            --text-secondary: #424242;
            --text-light: #757575;
            --white: #ffffff;
            --off-white: #f8f9fa;
            --border-radius: 12px;
            --shadow-sm: 0 2px 6px rgba(0,0,0,0.1);
            --shadow-md: 0 6px 12px rgba(0,0,0,0.15);
            --shadow-lg: 0 10px 20px rgba(0,0,0,0.2);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: url('images/pic30.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
            padding: 20px;
            position: relative;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 8rem;
            font-weight: 900;
            color: rgba(0, 0, 0, 0.1);
            text-transform: uppercase;
            pointer-events: none;
            z-index: 0;
            white-space: nowrap;
        }

        .header {
            background: url('images/pic1.jpg');
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            position: relative;
            overflow: hidden;
            color: var(--white);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgb(11, 71, 114), #5c9eff, rgb(14, 84, 138));
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }

        .header-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .user-info {
            display: inline-flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.15);
            padding: 15px 25px;
            border-radius: 50px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent-blue), var(--hover-accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .user-details h3 {
            color: var(--white);
            margin-bottom: 5px;
        }

        .user-details p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .resume-count {
            background: var(--white);
            padding: 15px 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--accent-blue);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .count-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-blue);
        }

        .count-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .create-btn {
            background: linear-gradient(135deg, var(--accent-blue), var(--hover-accent));
            color: var(--white);
            padding: 15px 30px;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border-radius: 30px;
            box-shadow: var(--shadow-md);
        }

        .create-btn:hover {
            background: linear-gradient(135deg, var(--hover-blue), var(--hover-accent));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-sm);
        }

        .alert-success {
            background: rgb(203, 219, 213);
            color: var(--success-green);
            border: 1px solid rgb(33, 114, 123);
        }

        .alert-error {
            background: rgb(180, 152, 156);
            color: var(--danger-red);
            border: 1px solid #ffcdd2;
        }

        .resumes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .resume-card {
            background: rgba(2, 29, 63, 0.64);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            position: relative;
            max-height: 500px;
            border: 1px solid rgba(112, 172, 187, 0.93);
        }

        .resume-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .resume-image {
            height: 180px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .template-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--white);
            color: var(--primary-blue);
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
            z-index: 1;
        }

        .resume-header {
            padding: 20px;
            position: relative;
        }

        .resume-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: rgb(247, 247, 247);
            margin-bottom: 8px;
        }

        .resume-company {
            color: rgb(247, 247, 247);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .resume-date {
            color: var(--text-light);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .resume-body {
            padding: 0 20px 20px;
        }

        .resume-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 12px;
            background: var(--light-blue);
            border-radius: 8px;
            transition: var(--transition);
        }

        .stat-item:hover {
            background: var(--accent-blue);
            color: var(--white);
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-blue);
            display: block;
        }

        .stat-item:hover .stat-number {
            color: var(--white);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-item:hover .stat-label {
            color: var(--white);
        }

        .resume-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), var(--hover-accent));
            color: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--hover-blue), var(--hover-accent));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--accent-blue);
            border: 1px solid var(--accent-blue);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: var(--light-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: var(--white);
            color: var(--danger-red);
            border: 1px solid var(--danger-red);
            box-shadow: var(--shadow-sm);
        }

        .btn-danger:hover {
            background: #ffebee;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            grid-column: 1 / -1;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--text-light);
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .empty-description {
            color: var(--text-secondary);
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .confirmation-box {
            background: #ffebee;
            border: 1px solid var(--danger-red);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .confirmation-box p {
            color: var(--danger-red);
            margin-bottom: 15px;
        }

        .confirmation-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header-title {
                font-size: 2rem;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .resumes-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
            }

            .watermark {
                font-size: 4rem;
            }
        }

        @media (max-width: 480px) {
            .resume-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .resume-actions {
                flex-direction: column;
            }
            
            .confirmation-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }

            .watermark {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="watermark">My Resumes</div>
    
    <div class="container">
        <!-- Header Section -->
        <div class="header">
           
            <h1 class="header-title">My Resumes</h1>
            <p class="header-subtitle">Manage and create professional resumes tailored for your career</p>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['FULLNAME'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($user['FULLNAME']) ?></h3>
                    <p><?= htmlspecialchars($user['EMAIL']) ?></p>
                </div>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="actions-bar">
            <div class="resume-count">
                <span class="count-number"><?= count($resumes) ?></span>
                <span class="count-label">Total Resumes</span>
            </div>
            
            <a href="templates.php" class="create-btn">
                <i class="fas fa-plus"></i>
                Create New Resume
            </a>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Delete Confirmation -->
        <?php if ($confirmation_resume_id): ?>
            <?php
            // Fetch resume details for confirmation
            $resume_stmt = $conn->prepare("SELECT EDUCATION FROM resumes WHERE RESUMEID = ? AND USERID = ?");
            $resume_stmt->bind_param("ii", $confirmation_resume_id, $user_id);
            $resume_stmt->execute();
            $resume_result = $resume_stmt->get_result();
            $resume = $resume_result->fetch_assoc();
            if ($resume):
                $parsed = parseResumeData($resume['EDUCATION']);
                $resume_data = $parsed['resume_data'];
            ?>
                <div class="confirmation-box">
                    <p>Are you sure you want to delete the resume titled "<?= htmlspecialchars($resume_data['title']) ?>"? This action cannot be undone.</p>
                    <div class="confirmation-actions">
                        <form method="POST">
                            <input type="hidden" name="delete_resume" value="1">
                            <input type="hidden" name="resume_id" value="<?= $confirmation_resume_id ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i>
                                Confirm Delete
                            </button>
                        </form>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Resume not found or you do not have permission to delete it.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Resumes Grid -->
        <?php if (empty($resumes)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h2 class="empty-title">No Resumes Yet</h2>
                <p class="empty-description">
                    Start building your professional resume today! Create targeted resumes for different companies and positions.
                </p>
                <a href="targeted_resume.php" class="create-btn">
                    <i class="fas fa-plus"></i>
                    Create Your First Resume
                </a>
            </div>
        <?php else: ?>
            <div class="resumes-grid">
                <?php 
                $background_images = [
                    'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1521791136064-7986c2920216?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1497366811353-6870744d04b2?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80',
                    'https://images.unsplash.com/photo-1522071820081-009f0129c71c?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'
                ];
                
                foreach ($resumes as $index => $resume): 
                    $parsed = parseResumeData($resume['EDUCATION']);
                    $resume_data = $parsed['resume_data'];
                    $education = $parsed['education'];
                    $created_date = new DateTime($resume['GENERATEDAT']);
                    $template_file = getTemplateFile($resume['TEMPLATEID']);
                    $bg_image = $background_images[$index % count($background_images)];
                ?>
                    <div class="resume-card">
                        <div class="resume-image" style="background-image: url('<?= $bg_image ?>')">
                            <div class="template-badge">
                                Template <?= $resume['TEMPLATEID'] ?>
                            </div>
                        </div>
                        
                        <div class="resume-header">
                            <h3 class="resume-title">
                                <?= htmlspecialchars($resume_data['title']) ?>
                            </h3>
                            
                            <?php if (!empty($resume_data['company'])): ?>
                                <div class="resume-company">
                                    <i class="fas fa-building"></i>
                                    <?= htmlspecialchars($resume_data['company']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="resume-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?= $created_date->format('M d, Y') ?>
                            </div>
                        </div>
                        
                        <div class="resume-body">
                            <div class="resume-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= $resume['experience_count'] ?></span>
                                    <span class="stat-label">Experience</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $resume['skills_count'] ?></span>
                                    <span class="stat-label">Skills</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $resume['certificate_count'] ?></span>
                                    <span class="stat-label">Certificates</span>
                                </div>
                            </div>
                            
                            <div class="resume-actions">
                                <a href="<?= $template_file ?>?template_id=<?= $resume['TEMPLATEID'] ?>&resume_id=<?= $resume['RESUMEID'] ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </a>
                                
                                <a href="view_resume.php?id=<?= $resume['RESUMEID'] ?>" 
                                   class="btn btn-secondary">
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>
                                
                                <a href="<?= $_SERVER['PHP_SELF'] ?>?confirm_delete=<?= $resume['RESUMEID'] ?>" 
                                   class="btn btn-danger">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>