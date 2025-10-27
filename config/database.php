<?php
// config/database.php
// Returns a PDO instance. Place this file in your project (e.g. /config/database.php).
// It will try to use vlucas/phpdotenv if available (Composer). Otherwise it reads environment variables.

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // If you use Composer and vlucas/phpdotenv, load .env automatically
    require __DIR__ . '/../vendor/autoload.php';
    if (class_exists(\Dotenv\Dotenv::class)) {
        $root = realpath(__DIR__ . '/..');
        $dotenv = \Dotenv\Dotenv::createImmutable($root);
        $dotenv->safeLoad();
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'ecoedu_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host={\$host};port={\$port};dbname={\$db};charset={\$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    throw $e;
}

// Return PDO instance when required
return $pdo;
