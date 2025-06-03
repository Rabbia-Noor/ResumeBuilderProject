<?php
// Start the session
session_start();

// Include the database connection file
include 'php/db.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the login page if not logged in
    header("Location: login.php");
    exit();
}

// Fetch the user's details
$userid = $_SESSION['user_id'];
$query_user = "SELECT FULLNAME FROM users WHERE USERID = ?";
$stmt_user = $conn->prepare($query_user);
$stmt_user->bind_param("i", $userid);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user_data = $result_user->fetch_assoc();

// Fetch dashboard statistics
$query_stats = [
    "resumes" => "SELECT COUNT(*) AS count FROM resumes WHERE USERID = ?",
    "certificates" => "SELECT COUNT(*) AS count FROM certificates WHERE USERID = ?",
    "experience" => "SELECT COUNT(*) AS count FROM experience WHERE USERID = ?"
];

$stats = [];
foreach ($query_stats as $key => $query) {
    $stmt_stat = $conn->prepare($query);
    $stmt_stat->bind_param("i", $userid);
    $stmt_stat->execute();
    $result_stat = $stmt_stat->get_result();
    $stats[$key] = $result_stat->fetch_assoc()['count'];
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="css/style_dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Left Sidebar -->
        <div class="sidebar">
            <div class="logo-section">
                <div class="logo">Resume Sphere</div>
                <div class="logo-subtitle">Professional Dashboard</div>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">👤</div>
                <div class="user-name"><?php echo htmlspecialchars($user_data['FULLNAME']); ?></div>
                <div class="user-role">Professional</div>
            </div>
            
            <div class="navigation">
                <div class="nav-section">
                    <div class="nav-section-title">Create</div>
                    <a href="resume_builder.php" class="nav-item active">
                        <span class="nav-item-icon">📝</span>
                        Build Resume
                    </a>
                    <a href="templates.php" class="nav-item">
                        <span class="nav-item-icon">🎨</span>
                        Templates
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Manage</div>
                    <a href="my_resumes.php" class="nav-item">
                        <span class="nav-item-icon">📋</span>
                        My Resumes
                    </a>
                    <a href="certificate.php" class="nav-item">
                        <span class="nav-item-icon">🏆</span>
                        Certificates
                    </a>
                    <a href="experience.php" class="nav-item">
                        <span class="nav-item-icon">💼</span>
                        Experience
                    </a>
                </div>
            </div>
            
            <div class="logout-section">
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title">Dashboard</h1>
                <p class="header-subtitle">Manage your professional profile and career documents</p>
            </div>
            
            <div class="content-area">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['resumes']; ?></div>
                        <div class="stat-label">Total Resumes</div>
                    </div>
                   
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['certificates']; ?></div>
                        <div class="stat-label">Certificates</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['experience']; ?></div>
                        <div class="stat-label">Experience Records</div>
                    </div>
                </div>
                
                <div class="welcome-section">
                    <span class="welcome-icon">🚀</span>
                    <h2 class="welcome-title">Welcome to Your Professional Hub</h2>
                    <p class="welcome-text">
                        Create stunning resumes, manage your professional experience, and showcase your certificates all in one place. 
                        Start building your career profile today with our comprehensive tools and templates.
                    </p>
                </div>
                
                <div class="quick-actions">
                    <a href="resume_builder.php" class="action-card">
                        <span class="action-icon">📝</span>
                        <div class="action-title">Create New Resume</div>
                        <div class="action-description">Build a professional resume with our intuitive builder</div>
                    </a>
                    
                    <a href="templates.php" class="action-card">
                        <span class="action-icon">🎨</span>
                        <div class="action-title">Browse Templates</div>
                        <div class="action-description">Choose from professionally designed templates</div>
                    </a>
                    
                    <a href="certificate.php" class="action-card">
                        <span class="action-icon">🏆</span>
                        <div class="action-title">Add Certificate</div>
                        <div class="action-description">Upload and manage your professional certificates</div>
                    </a>
                    
                    <a href="experience.php" class="action-card">
                        <span class="action-icon">💼</span>
                        <div class="action-title">Update Experience</div>
                        <div class="action-description">Add your latest work experience and skills</div>
                    </a>
                </div>
            </div>
        </div>
        
    </div>
</body>
</html>
