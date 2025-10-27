<?php
try {
    require_once 'config/database.php';
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

echo "<h2>🔧 Eco Points System Setup</h2>";

echo "<p>This utility ensures that the eco points tracking tables exist and provides a quick way to seed demo data or reset balances.</p>";

try {
    // Ensure point_transactions table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS point_transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        points INT NOT NULL,
        transaction_type ENUM('earned','spent','adjustment') DEFAULT 'earned',
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure user_leaderboard view/table exists (fallback table implementation)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_leaderboard (
        id INT PRIMARY KEY,
        username VARCHAR(50),
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        school_name VARCHAR(150),
        eco_points INT DEFAULT 0,
        level_id INT,
        total_badges INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "<p>✅ Required tables verified.</p>";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['reset_points'])) {
            $pdo->exec("UPDATE users SET eco_points = 0");
            $pdo->exec("DELETE FROM point_transactions");
            $pdo->exec("UPDATE user_leaderboard SET eco_points = 0, total_badges = 0");
            echo "<p>🧹 All eco points reset to 0 and transactions cleared.</p>";
        }
        
        if (isset($_POST['seed_points'])) {
            $stmt = $pdo->query("SELECT id FROM users WHERE role = 'student' ORDER BY id LIMIT 10");
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($students) {
                foreach ($students as $user_id) {
                    $points = rand(50, 250);
                    $pdo->prepare("UPDATE users SET eco_points = eco_points + ? WHERE id = ?")->execute([$points, $user_id]);
                    $pdo->prepare("INSERT INTO point_transactions (user_id, points, transaction_type, description) VALUES (?, ?, 'earned', 'Seeded demo points')")->execute([$user_id, $points]);
                }
                echo "<p>🌱 Seeded eco points for " . count($students) . " students.</p>";
            } else {
                echo "<p>ℹ️ No student accounts found to seed.</p>";
            }
        }
    }

    echo "<hr>";
    echo "<form method='POST' style='display:flex;gap:10px;'>";
    echo "<button type='submit' name='seed_points' class='btn btn-success'>Seed Demo Points</button>";
    echo "<button type='submit' name='reset_points' class='btn btn-danger' onclick=\"return confirm('Reset all eco points and clear transactions?');\">Reset Points</button>";
    echo "</form>";
    
    echo "<p style='margin-top:20px;'><a href='dashboard.php' class='btn btn-primary'>Back to Dashboard</a></p>";

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa; }
h2 { color: #198754; }
a.btn, button.btn { padding: 10px 18px; border-radius: 6px; color: #fff; text-decoration: none; border: none; cursor: pointer; }
.btn-success { background: #28a745; }
.btn-danger { background: #dc3545; }
.btn-primary { background: #0d6efd; display: inline-block; }
form button { min-width: 180px; }
</style>
