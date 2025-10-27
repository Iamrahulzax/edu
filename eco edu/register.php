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
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $school_name = sanitizeInput($_POST['school_name']);
    $grade_level = sanitizeInput($_POST['grade_level']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $result = registerUser($username, $email, $password, $first_name, $last_name, $school_name, $grade_level);
        if ($result['success']) {
            $success = 'Registration successful! You can now log in.';
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
    <title>Register - EcoEdu</title>
    
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

    <!-- Register Section -->
    <section class="py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); min-height: 100vh;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card shadow-lg border-0" style="border-radius: 20px;">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <i class="fas fa-user-plus text-success fa-3x mb-3"></i>
                                <h2 class="fw-bold text-success">Join EcoEdu!</h2>
                                <p class="text-muted">Start your environmental learning journey today</p>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                    <div class="mt-2">
                                        <a href="login.php" class="btn btn-sm btn-success">Login Now</a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="needs-validation" novalidate>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-outline mb-4">
                                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                            <label class="form-label" for="first_name">First Name *</label>
                                            <div class="invalid-feedback">
                                                Please enter your first name.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-outline mb-4">
                                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                            <label class="form-label" for="last_name">Last Name *</label>
                                            <div class="invalid-feedback">
                                                Please enter your last name.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-outline mb-4">
                                    <input type="text" id="username" name="username" class="form-control" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                    <label class="form-label" for="username">Username *</label>
                                    <div class="invalid-feedback">
                                        Please choose a username.
                                    </div>
                                </div>

                                <div class="form-outline mb-4">
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                    <label class="form-label" for="email">Email Address *</label>
                                    <div class="invalid-feedback">
                                        Please enter a valid email address.
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-outline mb-4">
                                            <input type="password" id="password" name="password" class="form-control" required minlength="6">
                                            <label class="form-label" for="password">Password *</label>
                                            <div class="invalid-feedback">
                                                Password must be at least 6 characters long.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-outline mb-4">
                                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                            <label class="form-label" for="confirm_password">Confirm Password *</label>
                                            <div class="invalid-feedback">
                                                Please confirm your password.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-outline mb-4">
                                    <input type="text" id="school_name" name="school_name" class="form-control" value="<?php echo isset($_POST['school_name']) ? htmlspecialchars($_POST['school_name']) : ''; ?>">
                                    <label class="form-label" for="school_name">School Name</label>
                                </div>

                                <div class="form-outline mb-4">
                                    <select class="form-select" id="grade_level" name="grade_level">
                                        <option value="">Select Grade Level</option>
                                        <option value="1st Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '1st Grade') ? 'selected' : ''; ?>>1st Grade</option>
                                        <option value="2nd Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '2nd Grade') ? 'selected' : ''; ?>>2nd Grade</option>
                                        <option value="3rd Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '3rd Grade') ? 'selected' : ''; ?>>3rd Grade</option>
                                        <option value="4th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '4th Grade') ? 'selected' : ''; ?>>4th Grade</option>
                                        <option value="5th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '5th Grade') ? 'selected' : ''; ?>>5th Grade</option>
                                        <option value="6th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '6th Grade') ? 'selected' : ''; ?>>6th Grade</option>
                                        <option value="7th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '7th Grade') ? 'selected' : ''; ?>>7th Grade</option>
                                        <option value="8th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '8th Grade') ? 'selected' : ''; ?>>8th Grade</option>
                                        <option value="9th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '9th Grade') ? 'selected' : ''; ?>>9th Grade</option>
                                        <option value="10th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '10th Grade') ? 'selected' : ''; ?>>10th Grade</option>
                                        <option value="11th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '11th Grade') ? 'selected' : ''; ?>>11th Grade</option>
                                        <option value="12th Grade" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === '12th Grade') ? 'selected' : ''; ?>>12th Grade</option>
                                        <option value="College" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === 'College') ? 'selected' : ''; ?>>College</option>
                                        <option value="Teacher" <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] === 'Teacher') ? 'selected' : ''; ?>>Teacher</option>
                                    </select>
                                    <label for="grade_level" class="form-label select-label">Grade Level</label>
                                </div>

                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" value="" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" class="text-success">Terms of Service</a> and <a href="#" class="text-success">Privacy Policy</a>
                                    </label>
                                    <div class="invalid-feedback">
                                        You must agree to the terms and conditions.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success btn-lg w-100 mb-3">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>

                                <div class="text-center">
                                    <p class="mb-0">Already have an account? 
                                        <a href="login.php" class="text-success fw-bold">Sign in here</a>
                                    </p>
                                </div>
                            </form>
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
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
