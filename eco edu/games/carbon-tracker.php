<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: ../login.php');
    exit;
}

// Handle carbon footprint tracking
$message = '';
$message_type = '';

if ($_POST) {
    if (isset($_POST['action']) && $_POST['action'] === 'log_activity') {
        $activity_type = sanitizeInput($_POST['activity_type']);
        $amount = floatval($_POST['amount']);
        $date = date('Y-m-d');
        
        // Carbon emission factors (kg CO2 per unit)
        $emission_factors = [
            'car_km' => 0.21,      // kg CO2 per km
            'bus_km' => 0.089,     // kg CO2 per km
            'flight_km' => 0.255,  // kg CO2 per km
            'electricity_kwh' => 0.5, // kg CO2 per kWh
            'gas_m3' => 2.0,       // kg CO2 per m³
            'waste_kg' => 0.5      // kg CO2 per kg
        ];
        
        $carbon_footprint = $amount * ($emission_factors[$activity_type] ?? 0);
        
        try {
            // Save to game results
            $game_data = json_encode([
                'activity_type' => $activity_type,
                'amount' => $amount,
                'carbon_footprint' => $carbon_footprint,
                'date' => $date
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO game_results (user_id, game_type, score, points_earned, game_data) 
                VALUES (?, 'carbon_tracker', ?, ?, ?)
            ");
            
            // Award points based on low carbon activities
            $points = max(5, 50 - intval($carbon_footprint * 10));
            $stmt->execute([$user['id'], $carbon_footprint, $points, $game_data]);
            
            addEcoPoints($user['id'], $points, "Carbon footprint tracking");
            
            $message = "Activity logged! Carbon footprint: {$carbon_footprint} kg CO2. +{$points} eco-points earned.";
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error logging activity. Please try again.';
            $message_type = 'error';
        }
    }
}

// Get user's carbon tracking history
try {
    $stmt = $pdo->prepare("
        SELECT * FROM game_results 
        WHERE user_id = ? AND game_type = 'carbon_tracker' 
        ORDER BY played_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $carbon_history = $stmt->fetchAll();
} catch (Exception $e) {
    $carbon_history = [];
}

// Calculate weekly carbon footprint
$weekly_carbon = 0;
$weekly_start = date('Y-m-d', strtotime('-7 days'));
foreach ($carbon_history as $entry) {
    if ($entry['played_at'] >= $weekly_start) {
        $weekly_carbon += $entry['score'];
    }
}

// Carbon footprint activities
$activities = [
    'car_km' => [
        'name' => 'Car Travel',
        'unit' => 'kilometers',
        'icon' => 'fas fa-car',
        'color' => 'danger',
        'factor' => 0.21
    ],
    'bus_km' => [
        'name' => 'Bus Travel',
        'unit' => 'kilometers',
        'icon' => 'fas fa-bus',
        'color' => 'warning',
        'factor' => 0.089
    ],
    'flight_km' => [
        'name' => 'Flight',
        'unit' => 'kilometers',
        'icon' => 'fas fa-plane',
        'color' => 'danger',
        'factor' => 0.255
    ],
    'electricity_kwh' => [
        'name' => 'Electricity Usage',
        'unit' => 'kWh',
        'icon' => 'fas fa-bolt',
        'color' => 'info',
        'factor' => 0.5
    ],
    'gas_m3' => [
        'name' => 'Natural Gas',
        'unit' => 'm³',
        'icon' => 'fas fa-fire',
        'color' => 'primary',
        'factor' => 2.0
    ],
    'waste_kg' => [
        'name' => 'Waste Generated',
        'unit' => 'kg',
        'icon' => 'fas fa-trash',
        'color' => 'secondary',
        'factor' => 0.5
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carbon Tracker - EcoEdu Games</title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        .carbon-card {
            transition: all 0.3s ease;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .carbon-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .carbon-meter {
            background: linear-gradient(90deg, #28a745 0%, #ffc107 50%, #dc3545 100%);
            height: 20px;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .carbon-indicator {
            position: absolute;
            top: -5px;
            width: 4px;
            height: 30px;
            background: #000;
            border-radius: 2px;
        }
        
        .activity-form {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 20px;
        }
        
        .carbon-stat {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .carbon-history-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body data-theme="light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-success fixed-top">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="../index.php">
                <i class="fas fa-leaf me-2"></i>EcoEdu
            </a>
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="../games.php">
                            <i class="fas fa-gamepad me-1"></i>Games
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-mdb-toggle="dropdown">
                            <?php 
                            $profile_image_path = '../uploads/profiles/' . ($user['profile_image'] ?? 'default-avatar.png');
                            if ($user['profile_image'] && $user['profile_image'] !== 'default-avatar.png' && file_exists($profile_image_path)): 
                            ?>
                                <img src="<?php echo $profile_image_path; ?>" alt="Profile" class="navbar-profile-img me-2">
                            <?php else: ?>
                                <i class="fas fa-user me-2"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($user['first_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5 pt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="text-center">
                    <h1 class="display-4 text-success mb-3">
                        <i class="fas fa-leaf me-3"></i>
                        Carbon Footprint Tracker
                    </h1>
                    <p class="lead text-muted">Track and reduce your daily carbon emissions!</p>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-mdb-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Carbon Stats -->
        <div class="row mb-5">
            <div class="col-md-4 mb-3">
                <div class="carbon-stat bg-primary text-white">
                    <i class="fas fa-calendar-week fa-2x mb-2"></i>
                    <h4><?php echo number_format($weekly_carbon, 2); ?> kg</h4>
                    <p class="mb-0">This Week's CO₂</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="carbon-stat bg-success text-white">
                    <i class="fas fa-target fa-2x mb-2"></i>
                    <h4>50 kg</h4>
                    <p class="mb-0">Weekly Target</p>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="carbon-stat bg-<?php echo $weekly_carbon <= 50 ? 'success' : 'warning'; ?> text-white">
                    <i class="fas fa-<?php echo $weekly_carbon <= 50 ? 'check' : 'exclamation'; ?> fa-2x mb-2"></i>
                    <h4><?php echo $weekly_carbon <= 50 ? 'On Track!' : 'Over Target'; ?></h4>
                    <p class="mb-0">Status</p>
                </div>
            </div>
        </div>

        <!-- Carbon Meter -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-tachometer-alt me-2"></i>Weekly Carbon Meter</h5>
                        <div class="carbon-meter mt-3">
                            <div class="carbon-indicator" style="left: <?php echo min(100, ($weekly_carbon / 100) * 100); ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-success">Low (0-25 kg)</small>
                            <small class="text-warning">Medium (25-75 kg)</small>
                            <small class="text-danger">High (75+ kg)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Logger -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="activity-form">
                    <h4 class="mb-4"><i class="fas fa-plus-circle me-2"></i>Log New Activity</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="log_activity">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="activity_type" class="form-label">Activity Type</label>
                                <select class="form-select" id="activity_type" name="activity_type" required onchange="updateActivityInfo()">
                                    <option value="">Select an activity...</option>
                                    <?php foreach ($activities as $key => $activity): ?>
                                    <option value="<?php echo $key; ?>" data-factor="<?php echo $activity['factor']; ?>" data-unit="<?php echo $activity['unit']; ?>">
                                        <?php echo $activity['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="amount" class="form-label">Amount <span id="unit-display"></span></label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.1" min="0" required onchange="calculateCarbon()">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">CO₂ Estimate</label>
                                <div class="form-control bg-light" id="carbon-estimate">0 kg</div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Log Activity
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Activity Categories -->
        <div class="row mb-5">
            <div class="col-12">
                <h4 class="mb-4"><i class="fas fa-list me-2"></i>Activity Categories</h4>
            </div>
            <?php foreach ($activities as $key => $activity): ?>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card carbon-card border-<?php echo $activity['color']; ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <i class="<?php echo $activity['icon']; ?> fa-2x text-<?php echo $activity['color']; ?> me-3"></i>
                            <div>
                                <h6 class="mb-0"><?php echo $activity['name']; ?></h6>
                                <small class="text-muted"><?php echo $activity['factor']; ?> kg CO₂ per <?php echo $activity['unit']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($carbon_history)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-leaf fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No activities logged yet!</h5>
                            <p class="text-muted">Start tracking your carbon footprint above.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($carbon_history as $entry): ?>
                        <?php $data = json_decode($entry['game_data'], true); ?>
                        <?php $activity = $activities[$data['activity_type']] ?? ['name' => $data['activity_type'], 'icon' => 'fas fa-leaf', 'color' => 'success']; ?>
                        <div class="carbon-history-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="<?php echo $activity['icon']; ?> fa-lg text-<?php echo $activity['color']; ?> me-3"></i>
                                    <div>
                                        <h6 class="mb-0"><?php echo $activity['name']; ?></h6>
                                        <small class="text-muted">
                                            <?php echo $data['amount']; ?> <?php echo $activity['unit'] ?? 'units'; ?> • 
                                            <?php echo date('M j, Y g:i A', strtotime($entry['played_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $entry['score'] <= 5 ? 'success' : ($entry['score'] <= 15 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($entry['score'], 2); ?> kg CO₂
                                    </span>
                                    <div><small class="text-success">+<?php echo $entry['points_earned']; ?> points</small></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MDBootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <script>
        const activities = <?php echo json_encode($activities); ?>;
        
        function updateActivityInfo() {
            const select = document.getElementById('activity_type');
            const unitDisplay = document.getElementById('unit-display');
            const amountInput = document.getElementById('amount');
            
            if (select.value) {
                const activity = activities[select.value];
                unitDisplay.textContent = `(${activity.unit})`;
                amountInput.placeholder = `Enter ${activity.unit}`;
                calculateCarbon();
            } else {
                unitDisplay.textContent = '';
                amountInput.placeholder = '';
                document.getElementById('carbon-estimate').textContent = '0 kg';
            }
        }
        
        function calculateCarbon() {
            const select = document.getElementById('activity_type');
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const carbonEstimate = document.getElementById('carbon-estimate');
            
            if (select.value && amount > 0) {
                const activity = activities[select.value];
                const carbon = (amount * activity.factor).toFixed(2);
                carbonEstimate.textContent = `${carbon} kg`;
            } else {
                carbonEstimate.textContent = '0 kg';
            }
        }
    </script>
    
    <style>
        .navbar-profile-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .navbar-profile-img:hover {
            border-color: rgba(255, 255, 255, 0.8);
            transform: scale(1.1);
        }
    </style>
</body>
</html>
