<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    $username_email = sanitizeInput($_POST['username_email']);
    $password = $_POST['password'];
    
    if (empty($username_email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = loginUser($username_email, $password);
        if ($result['success']) {
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['username'] = $result['user']['username'];
            $_SESSION['role'] = $result['user']['role'];
            
            // Redirect based on role
            if ($result['user']['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EcoEdu</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-theme="light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-success">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="index.php">
                <i class="fas fa-leaf me-2"></i>EcoEdu
            </a>
            <div class="ms-auto">
                <button class="btn btn-sm btn-outline-light" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="min-vh-100 d-flex align-items-center" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow-lg border-0" style="border-radius: 20px;">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <i class="fas fa-leaf text-success fa-3x mb-3"></i>
                                <h2 class="fw-bold text-success">Welcome Back!</h2>
                                <p class="text-muted">Sign in to continue your eco-journey</p>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="needs-validation" novalidate>
                                <div class="form-outline mb-4">
                                    <input type="text" id="username_email" name="username_email" class="form-control form-control-lg" required>
                                    <label class="form-label" for="username_email">Username or Email</label>
                                    <div class="invalid-feedback">
                                        Please enter your username or email.
                                    </div>
                                </div>

                                <div class="form-outline mb-4">
                                    <input type="password" id="password" name="password" class="form-control form-control-lg" required>
                                    <label class="form-label" for="password">Password</label>
                                    <div class="invalid-feedback">
                                        Please enter your password.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="" id="remember_me">
                                        <label class="form-check-label" for="remember_me">
                                            Remember me
                                        </label>
                                    </div>
                                    <a href="forgot-password.php" class="text-success">Forgot password?</a>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100 mb-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>

                                <div class="text-center">
                                    <p class="mb-0">Don't have an account? 
                                        <a href="register.php" class="text-success fw-bold">Sign up here</a>
                                    </p>
                                </div>
                            </form>

                            <!-- Demo Accounts -->
                            <div class="mt-4 pt-4 border-top">
                                <h6 class="text-center text-muted mb-3">Demo Accounts</h6>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Student</small>
                                        <code>student@demo.com</code><br>
                                        <code>demo123</code>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Teacher</small>
                                        <code>teacher@demo.com</code><br>
                                        <code>demo123</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
</body>
</html>
