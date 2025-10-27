<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
$user_logged_in = isset($_SESSION['user_id']);
$user_data = null;
if ($user_logged_in) {
    $user_data = getUserById($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoEdu - Gamified Environmental Education</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-theme="light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-success fixed-top">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="index.php">
                <i class="fas fa-leaf me-2"></i>EcoEdu
            </a>
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#about">About</a>
                    </li>
                    <?php if ($user_logged_in): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="register.php">
                                <i class="fas fa-user-plus me-1"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <button class="btn btn-sm btn-outline-light ms-2" onclick="toggleTheme()">
                            <i class="fas fa-moon" id="theme-icon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center min-vh-100 pt-5">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-success mb-4">
                        Learn. Play. Save the Planet.
                    </h1>
                    <p class="lead mb-4">
                        Join thousands of students in our gamified environmental education platform. 
                        Earn eco-points, complete challenges, and make a real difference!
                    </p>
                    <div class="d-flex gap-3 mb-4">
                        <?php if (!$user_logged_in): ?>
                            <a href="register.php" class="btn btn-success btn-lg">
                                <i class="fas fa-rocket me-2"></i>Get Started
                            </a>
                            <a href="login.php" class="btn btn-outline-success btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                        <?php else: ?>
                            <a href="dashboard.php" class="btn btn-success btn-lg">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stats -->
                    <div class="row text-center">
                        <div class="col-4">
                            <h3 class="text-success fw-bold">1000+</h3>
                            <small class="text-muted">Students</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-success fw-bold">50+</h3>
                            <small class="text-muted">Schools</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-success fw-bold">100K+</h3>
                            <small class="text-muted">Eco Points</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <i class="fas fa-globe-americas text-success" style="font-size: 20rem; opacity: 0.1;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose EcoEdu?</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-gamepad text-success fa-3x mb-3"></i>
                            <h5>Gamified Learning</h5>
                            <p>Earn points, unlock badges, and compete with friends while learning about the environment.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-tasks text-success fa-3x mb-3"></i>
                            <h5>Real Challenges</h5>
                            <p>Complete real-world eco-friendly tasks and make a genuine impact on the environment.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-trophy text-success fa-3x mb-3"></i>
                            <h5>Competitions</h5>
                            <p>Participate in school-wide competitions and see how your school ranks globally.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">About EcoEdu</h2>
                    <p class="lead">
                        EcoEdu is revolutionizing environmental education by making it interactive, 
                        engaging, and rewarding. Our platform combines the best of gamification 
                        with practical environmental learning.
                    </p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Interactive quizzes and lessons</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Real-world eco challenges</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>School-wide competitions</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Progress tracking and analytics</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="bg-success text-white p-4 rounded">
                        <h4>Join the Movement</h4>
                        <p>Be part of the next generation of environmental champions. Start your eco-journey today!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-leaf me-2"></i>EcoEdu</h5>
                    <p>Making environmental education fun and engaging for everyone.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 EcoEdu. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
