<?php
// EcoEdu Installation Script
// This script sets up the database and creates demo data

// Check if already installed
if (file_exists('config/installed.lock')) {
    die('EcoEdu is already installed. Delete config/installed.lock to reinstall.');
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Database configuration
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'name' => 'ecoedu_db'
];

if ($_POST) {
    if ($step == 1) {
        // Database configuration step
        $db_config['host'] = $_POST['db_host'] ?? 'localhost';
        $db_config['user'] = $_POST['db_user'] ?? 'root';
        $db_config['pass'] = $_POST['db_pass'] ?? '';
        $db_config['name'] = $_POST['db_name'] ?? 'ecoedu_db';
        
        // Test database connection
        try {
            $pdo = new PDO("mysql:host={$db_config['host']}", $db_config['user'], $db_config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Update config file
            $config_content = "<?php\n";
            $config_content .= "// Database configuration\n";
            $config_content .= "define('DB_HOST', '{$db_config['host']}');\n";
            $config_content .= "define('DB_USER', '{$db_config['user']}');\n";
            $config_content .= "define('DB_PASS', '{$db_config['pass']}');\n";
            $config_content .= "define('DB_NAME', '{$db_config['name']}');\n\n";
            $config_content .= "// Create connection\n";
            $config_content .= "try {\n";
            $config_content .= "    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS);\n";
            $config_content .= "    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
            $config_content .= "    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);\n";
            $config_content .= "} catch(PDOException \$e) {\n";
            $config_content .= "    die(\"Connection failed: \" . \$e->getMessage());\n";
            $config_content .= "}\n\n";
            $config_content .= "// Legacy MySQLi connection for compatibility\n";
            $config_content .= "\$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);\n";
            $config_content .= "if (\$conn->connect_error) {\n";
            $config_content .= "    die(\"Connection failed: \" . \$conn->connect_error);\n";
            $config_content .= "}\n";
            $config_content .= "\$conn->set_charset(\"utf8mb4\");\n";
            $config_content .= "?>";
            
            file_put_contents('config/database.php', $config_content);
            
            $success = 'Database connection successful!';
            $step = 2;
        } catch (Exception $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    } elseif ($step == 2) {
        // Install database schema
        try {
            require_once 'config/database.php';
            
            // Read and execute schema
            $schema = file_get_contents('database/schema.sql');
            
            // Split into individual queries
            $queries = array_filter(array_map('trim', explode(';', $schema)));
            
            foreach ($queries as $query) {
                if (!empty($query) && !preg_match('/^--/', $query)) {
                    $pdo->exec($query);
                }
            }
            
            $success = 'Database schema installed successfully!';
            $step = 3;
        } catch (Exception $e) {
            $error = 'Schema installation failed: ' . $e->getMessage();
        }
    } elseif ($step == 3) {
        // Create admin user
        try {
            require_once 'config/database.php';
            require_once 'includes/functions.php';
            
            $admin_username = $_POST['admin_username'];
            $admin_email = $_POST['admin_email'];
            $admin_password = $_POST['admin_password'];
            $admin_first_name = $_POST['admin_first_name'];
            $admin_last_name = $_POST['admin_last_name'];
            
            // Create admin user
            $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, role, eco_points, level_id) VALUES (?, ?, ?, ?, ?, 'admin', 1000, 3)");
            $stmt->execute([$admin_username, $admin_email, $hashed_password, $admin_first_name, $admin_last_name]);
            
            // Create demo users
            $demo_users = [
                ['student@demo.com', 'demo123', 'Demo', 'Student', 'student', 'EcoEdu High School', '10th Grade', 250],
                ['teacher@demo.com', 'demo123', 'Demo', 'Teacher', 'teacher', 'EcoEdu High School', 'Teacher', 500]
            ];
            
            foreach ($demo_users as $user) {
                $hashed_pass = password_hash($user[1], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role, school_name, grade_level, eco_points, username) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user[0], $hashed_pass, $user[2], $user[3], $user[4], $user[5], $user[6], $user[7], $user[4] . '_demo']);
            }
            
            // Create installation lock file
            file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
            
            $success = 'Installation completed successfully!';
            $step = 4;
        } catch (Exception $e) {
            $error = 'Admin user creation failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoEdu Installation</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); min-height: 100vh; }
        .install-card { max-width: 600px; margin: 50px auto; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { width: 40px; height: 40px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; margin: 0 10px; color: #6c757d; }
        .step.active { background: #28a745; color: white; }
        .step.completed { background: #20c997; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card install-card shadow-lg">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <i class="fas fa-leaf text-success fa-4x mb-3"></i>
                    <h2 class="text-success">EcoEdu Installation</h2>
                    <p class="text-muted">Set up your gamified environmental education platform</p>
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                    <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                    <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                    <div class="step <?php echo $step >= 4 ? 'completed' : ''; ?>">4</div>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                <!-- Step 1: Database Configuration -->
                <h4>Step 1: Database Configuration</h4>
                <p class="text-muted mb-4">Configure your MySQL database connection</p>
                
                <form method="POST">
                    <div class="form-outline mb-3">
                        <input type="text" class="form-control" name="db_host" value="<?php echo $db_config['host']; ?>" required>
                        <label class="form-label">Database Host</label>
                    </div>
                    
                    <div class="form-outline mb-3">
                        <input type="text" class="form-control" name="db_user" value="<?php echo $db_config['user']; ?>" required>
                        <label class="form-label">Database Username</label>
                    </div>
                    
                    <div class="form-outline mb-3">
                        <input type="password" class="form-control" name="db_pass" value="<?php echo $db_config['pass']; ?>">
                        <label class="form-label">Database Password</label>
                    </div>
                    
                    <div class="form-outline mb-4">
                        <input type="text" class="form-control" name="db_name" value="<?php echo $db_config['name']; ?>" required>
                        <label class="form-label">Database Name</label>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-database me-2"></i>Test Connection & Continue
                    </button>
                </form>

                <?php elseif ($step == 2): ?>
                <!-- Step 2: Install Schema -->
                <h4>Step 2: Install Database Schema</h4>
                <p class="text-muted mb-4">Create the required database tables and initial data</p>
                
                <form method="POST">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will create all necessary tables, views, and sample data for EcoEdu.
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-cogs me-2"></i>Install Database Schema
                    </button>
                </form>

                <?php elseif ($step == 3): ?>
                <!-- Step 3: Create Admin User -->
                <h4>Step 3: Create Administrator Account</h4>
                <p class="text-muted mb-4">Set up your admin account to manage the platform</p>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <input type="text" class="form-control" name="admin_first_name" required>
                                <label class="form-label">First Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-outline mb-3">
                                <input type="text" class="form-control" name="admin_last_name" required>
                                <label class="form-label">Last Name</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-outline mb-3">
                        <input type="text" class="form-control" name="admin_username" required>
                        <label class="form-label">Username</label>
                    </div>
                    
                    <div class="form-outline mb-3">
                        <input type="email" class="form-control" name="admin_email" required>
                        <label class="form-label">Email Address</label>
                    </div>
                    
                    <div class="form-outline mb-4">
                        <input type="password" class="form-control" name="admin_password" required minlength="6">
                        <label class="form-label">Password</label>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Demo accounts will also be created:
                        <ul class="mb-0 mt-2">
                            <li><strong>Student:</strong> student@demo.com / demo123</li>
                            <li><strong>Teacher:</strong> teacher@demo.com / demo123</li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-user-shield me-2"></i>Create Admin & Complete Installation
                    </button>
                </form>

                <?php elseif ($step == 4): ?>
                <!-- Step 4: Installation Complete -->
                <div class="text-center">
                    <i class="fas fa-check-circle text-success fa-5x mb-4"></i>
                    <h4 class="text-success">Installation Complete!</h4>
                    <p class="text-muted mb-4">EcoEdu has been successfully installed and configured.</p>
                    
                    <div class="alert alert-success text-start">
                        <h6><i class="fas fa-info-circle me-2"></i>What's Next?</h6>
                        <ul class="mb-0">
                            <li>Access your platform at: <a href="index.php">EcoEdu Home</a></li>
                            <li>Login with your admin account to manage content</li>
                            <li>Try the demo accounts to explore features</li>
                            <li>Customize themes and settings as needed</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-success btn-lg">
                            <i class="fas fa-home me-2"></i>Go to EcoEdu
                        </a>
                        <a href="login.php" class="btn btn-outline-success">
                            <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
</body>
</html>
