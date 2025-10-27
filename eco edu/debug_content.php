<?php
// Debug script to check learning content table
require_once 'config/database.php';

echo "<h2>🔍 Learning Content Debug</h2>";

try {
    // Check if learning_content table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'learning_content'");
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        echo "✅ learning_content table exists<br>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE learning_content");
        $columns = $stmt->fetchAll();
        
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM learning_content");
        $count = $stmt->fetch()['count'];
        echo "<p>📊 Total records: {$count}</p>";
        
        // Show sample records
        if ($count > 0) {
            $stmt = $pdo->query("SELECT id, title, difficulty_level, is_active, created_at FROM learning_content LIMIT 5");
            $samples = $stmt->fetchAll();
            
            echo "<h3>Sample Records:</h3>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Title</th><th>Difficulty</th><th>Active</th><th>Created</th></tr>";
            foreach ($samples as $sample) {
                echo "<tr>";
                echo "<td>{$sample['id']}</td>";
                echo "<td>{$sample['title']}</td>";
                echo "<td>{$sample['difficulty_level']}</td>";
                echo "<td>" . ($sample['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "<td>{$sample['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "❌ learning_content table does not exist<br>";
        echo "<p>You need to create the learning_content table first.</p>";
        
        echo "<h3>Create Table SQL:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
        echo "CREATE TABLE learning_content (
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
);";
        echo "</pre>";
        
        echo "<p><a href='setup_learning_content.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Create Learning Content Table</a></p>";
    }
    
    // Check categories table
    $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
    $categories_exist = $stmt->rowCount() > 0;
    
    if ($categories_exist) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
        $cat_count = $stmt->fetch()['count'];
        echo "<p>✅ categories table exists with {$cat_count} records</p>";
    } else {
        echo "<p>❌ categories table does not exist</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage();
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa; }
table { background: white; }
th { background: #007bff; color: white; padding: 8px; }
td { padding: 8px; }
</style>
