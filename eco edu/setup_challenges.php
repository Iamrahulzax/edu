<?php
// Setup script to ensure challenge tables exist
try {
    require_once 'config/database.php';
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "<br>Please check your database configuration.");
}

echo "<h2>🌱 Setting up Challenge Tables...</h2>";

try {
    // Create challenge_categories table
    $sql = "CREATE TABLE IF NOT EXISTS challenge_categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(50) DEFAULT 'fas fa-leaf',
        color VARCHAR(20) DEFAULT '#28a745',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✅ challenge_categories table created/verified<br>";

    // Create eco_challenges table
    $sql = "CREATE TABLE IF NOT EXISTS eco_challenges (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category_id INT,
        title VARCHAR(200) NOT NULL,
        description TEXT NOT NULL,
        instructions TEXT,
        ecopoints_reward INT DEFAULT 50,
        difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        estimated_time VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES challenge_categories(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql);
    echo "✅ eco_challenges table created/verified<br>";

    // Create challenge_submissions table
    $sql = "CREATE TABLE IF NOT EXISTS challenge_submissions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        challenge_id INT NOT NULL,
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        photo_path VARCHAR(500),
        video_path VARCHAR(500),
        description TEXT,
        location VARCHAR(200),
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_feedback TEXT,
        approved_by INT,
        approved_at TIMESTAMP NULL,
        ecopoints_awarded INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (challenge_id) REFERENCES eco_challenges(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql);
    echo "✅ challenge_submissions table created/verified<br>";

    // Check if categories exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM challenge_categories");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        echo "<br>📝 Inserting default categories...<br>";
        
        $categories = [
            ['Tree Plantation', 'Plant trees and contribute to reforestation', 'fas fa-tree', '#28a745'],
            ['Plastic Reduction', 'Reduce plastic usage and promote alternatives', 'fas fa-recycle', '#17a2b8'],
            ['Clean-Up Drive', 'Participate in environmental cleanup activities', 'fas fa-broom', '#ffc107'],
            ['Recycling Action', 'Collect and recycle waste materials', 'fas fa-sync-alt', '#6f42c1'],
            ['Water Conservation', 'Promote water saving and awareness', 'fas fa-tint', '#007bff'],
            ['Energy Saving', 'Implement energy conservation practices', 'fas fa-bolt', '#fd7e14'],
            ['Wildlife Protection', 'Support local wildlife and biodiversity', 'fas fa-paw', '#20c997'],
            ['Sustainable Transport', 'Use eco-friendly transportation methods', 'fas fa-bicycle', '#6c757d']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO challenge_categories (name, description, icon, color) VALUES (?, ?, ?, ?)");
        foreach ($categories as $cat) {
            $stmt->execute($cat);
            echo "✅ Added category: {$cat[0]}<br>";
        }
    } else {
        echo "✅ Found {$count} existing categories<br>";
    }

    // Check if sample challenges exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM eco_challenges");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        echo "<br>📝 Adding sample challenges...<br>";
        
        // Get category IDs
        $stmt = $pdo->query("SELECT id, name FROM challenge_categories");
        $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $sample_challenges = [
            [
                'category' => 'Tree Plantation',
                'title' => 'Track Your Plant Growth Journey',
                'description' => 'Plant a seed and document its growth journey with weekly photos over 30 days to learn about plant life cycles and environmental care.',
                'instructions' => "1. Choose a plant seed (bean, sunflower, or herb)\n2. Plant it in a small pot with good soil\n3. Place in a sunny location near a window\n4. Water regularly (check soil moisture daily)\n5. Take a photo every week showing growth progress\n6. Measure and record plant height weekly\n7. Document any changes in leaves, stems, or flowers\n8. Submit final photo collage showing 30-day growth journey\n9. Include a short description of what you learned about plant care",
                'ecopoints' => 150,
                'difficulty' => 'medium',
                'time' => '30 days (5 minutes daily care)'
            ],
            [
                'category' => 'Plastic Reduction',
                'title' => 'Plastic-Free Week Challenge',
                'description' => 'Go plastic-free for one week and document alternatives used to reduce plastic waste.',
                'instructions' => "1. Identify all plastic items you normally use\n2. Find eco-friendly alternatives for each item\n3. Document your plastic-free journey with daily photos\n4. Keep a log of challenges and solutions\n5. Share your experience and tips learned",
                'ecopoints' => 120,
                'difficulty' => 'medium',
                'time' => '1 week'
            ],
            [
                'category' => 'Clean-Up Drive',
                'title' => 'Neighborhood Cleanup Initiative',
                'description' => 'Organize or participate in a local area cleanup drive to improve your community environment.',
                'instructions' => "1. Choose a public area that needs cleaning\n2. Gather cleaning supplies and safety equipment\n3. Take before photos of the area\n4. Clean up litter and organize waste properly\n5. Take after photos showing the improvement\n6. Properly dispose of collected waste",
                'ecopoints' => 100,
                'difficulty' => 'easy',
                'time' => '2-3 hours'
            ]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO eco_challenges (category_id, title, description, instructions, ecopoints_reward, difficulty_level, estimated_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($sample_challenges as $challenge) {
            $category_id = array_search($challenge['category'], $categories);
            if ($category_id) {
                $stmt->execute([
                    $category_id,
                    $challenge['title'],
                    $challenge['description'],
                    $challenge['instructions'],
                    $challenge['ecopoints'],
                    $challenge['difficulty'],
                    $challenge['time']
                ]);
                echo "✅ Added challenge: {$challenge['title']}<br>";
            }
        }
    } else {
        echo "✅ Found {$count} existing challenges<br>";
    }

    echo "<br>🎉 <strong>Setup Complete!</strong><br>";
    echo "<a href='admin/challenges.php' class='btn btn-success'>Go to Challenge Management</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Please check your database connection and try again.";
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa; }
.btn { padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px; }
</style>
