<?php
// Setup script for learning content table
require_once 'config/database.php';

echo "<h2>🚀 Setting up Learning Content Table...</h2>";

try {
    // Create categories table first if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(50) DEFAULT 'fas fa-book',
        color VARCHAR(20) DEFAULT '#007bff',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✅ categories table created/verified<br>";

    // Insert default categories if none exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $categories = [
            ['Environmental Science', 'Learn about ecosystems, climate change, and environmental protection', 'fas fa-leaf', '#28a745'],
            ['Sustainability', 'Discover sustainable practices and green technologies', 'fas fa-recycle', '#17a2b8'],
            ['Conservation', 'Understand wildlife and natural resource conservation', 'fas fa-tree', '#20c997'],
            ['Climate Change', 'Study climate science and global warming impacts', 'fas fa-thermometer-half', '#dc3545'],
            ['Renewable Energy', 'Explore solar, wind, and other clean energy sources', 'fas fa-bolt', '#ffc107'],
            ['Ecology', 'Learn about relationships between organisms and environment', 'fas fa-seedling', '#6f42c1']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon, color) VALUES (?, ?, ?, ?)");
        foreach ($categories as $cat) {
            $stmt->execute($cat);
        }
        echo "✅ Added 6 default categories<br>";
    }

    // Create learning_content table
    $sql = "CREATE TABLE IF NOT EXISTS learning_content (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        category_id INT,
        difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        estimated_time INT DEFAULT 10,
        points_reward INT DEFAULT 20,
        is_active BOOLEAN DEFAULT TRUE,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql);
    echo "✅ learning_content table created/verified<br>";

    // Check if sample content exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM learning_content");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        echo "<br>📝 Adding sample learning content...<br>";
        
        // Get category IDs
        $stmt = $pdo->query("SELECT id, name FROM categories");
        $categories = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $sample_content = [
            [
                'category' => 'Environmental Science',
                'title' => 'Introduction to Ecosystems',
                'content' => '<h2>What is an Ecosystem?</h2><p>An ecosystem is a community of living organisms interacting with their physical environment. It includes both biotic (living) and abiotic (non-living) components.</p><h3>Components of an Ecosystem</h3><ul><li><strong>Producers:</strong> Plants that make their own food through photosynthesis</li><li><strong>Primary Consumers:</strong> Herbivores that eat plants</li><li><strong>Secondary Consumers:</strong> Carnivores that eat herbivores</li><li><strong>Decomposers:</strong> Organisms that break down dead matter</li></ul><p>Understanding ecosystems helps us protect biodiversity and maintain environmental balance.</p>',
                'difficulty' => 'easy',
                'time' => 15,
                'points' => 25
            ],
            [
                'category' => 'Climate Change',
                'title' => 'Understanding Global Warming',
                'content' => '<h2>Global Warming Explained</h2><p>Global warming refers to the long-term increase in Earth\'s average surface temperature due to human activities and natural factors.</p><h3>Main Causes</h3><ul><li>Greenhouse gas emissions (CO2, methane, nitrous oxide)</li><li>Deforestation</li><li>Industrial processes</li><li>Transportation</li></ul><h3>Effects</h3><ul><li>Rising sea levels</li><li>Extreme weather events</li><li>Arctic ice melting</li><li>Changes in precipitation patterns</li></ul><p>Taking action now is crucial to mitigate these effects.</p>',
                'difficulty' => 'medium',
                'time' => 20,
                'points' => 30
            ],
            [
                'category' => 'Renewable Energy',
                'title' => 'Solar Energy Basics',
                'content' => '<h2>Harnessing the Power of the Sun</h2><p>Solar energy is one of the most abundant and clean sources of renewable energy available on Earth.</p><h3>How Solar Panels Work</h3><ol><li>Solar panels contain photovoltaic cells</li><li>Sunlight hits the cells and creates an electric field</li><li>This generates direct current (DC) electricity</li><li>An inverter converts DC to alternating current (AC)</li><li>AC electricity powers your home or feeds into the grid</li></ol><h3>Benefits</h3><ul><li>Reduces electricity bills</li><li>Environmentally friendly</li><li>Low maintenance</li><li>Increases property value</li></ul>',
                'difficulty' => 'easy',
                'time' => 12,
                'points' => 20
            ]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO learning_content (title, content, category_id, difficulty_level, estimated_time, points_reward, created_by) VALUES (?, ?, ?, ?, ?, ?, 1)");
        
        foreach ($sample_content as $content) {
            $category_id = array_search($content['category'], $categories);
            if ($category_id) {
                $stmt->execute([
                    $content['title'],
                    $content['content'],
                    $category_id,
                    $content['difficulty'],
                    $content['time'],
                    $content['points']
                ]);
                echo "✅ Added: {$content['title']}<br>";
            }
        }
    } else {
        echo "✅ Found {$count} existing learning content items<br>";
    }

    echo "<br>🎉 <strong>Setup Complete!</strong><br>";
    echo "<p>✅ Categories table ready<br>";
    echo "✅ Learning content table ready<br>";
    echo "✅ Sample content added</p>";
    echo "<a href='admin/content.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Go to Content Management</a>";
    echo "<a href='debug_content.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Debug Info</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Please check your database connection and try again.";
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa; }
a { display: inline-block; margin-top: 10px; }
</style>
