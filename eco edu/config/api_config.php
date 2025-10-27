<?php
// API Configuration - Keep this file secure and out of version control
// Add to .gitignore: config/api_config.php

// Learning Content Management API Configuration
define('LEARNING_API_KEY', 'AIzaSyBwQ2ITdGUTnJwdTB2o2nFghwV5GpQul-Y');
define('LEARNING_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/');

// TinyMCE API Configuration
define('TINYMCE_API_KEY', 'fkvoofjuexic0r8tfienvuu812nxr8mvagnjotvmszzmuqsv');

// Google AI API Endpoints for Learning Content
define('LEARNING_API_ENDPOINTS', [
    'content_generate' => 'models/gemini-pro:generateContent',
    'content_enhance' => 'models/gemini-pro:generateContent',
    'content_translate' => 'models/gemini-pro:generateContent',
    'content_summarize' => 'models/gemini-pro:generateContent',
    'content_quiz' => 'models/gemini-pro:generateContent',
    'content_validate' => 'models/gemini-pro:generateContent'
]);

// API Rate Limits
define('API_RATE_LIMIT_PER_HOUR', 100);
define('API_TIMEOUT_SECONDS', 30);

// Content Generation Settings
define('CONTENT_MAX_LENGTH', 5000);
define('CONTENT_MIN_LENGTH', 100);
define('SUPPORTED_LANGUAGES', ['en', 'es', 'fr', 'de', 'it']);
define('DIFFICULTY_LEVELS', ['beginner', 'intermediate', 'advanced']);

// Security Settings
define('API_ENCRYPTION_KEY', hash('sha256', LEARNING_API_KEY . 'eco_edu_salt'));
define('API_REQUEST_TIMEOUT', 30);
define('MAX_API_RETRIES', 3);

/**
 * Get API headers for Google AI requests
 */
function getApiHeaders() {
    return [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: EcoEdu-LMS/1.0'
    ];
}

/**
 * Validate Google API key format
 */
function validateApiKey($key) {
    return preg_match('/^AIza[0-9A-Za-z_-]{35}$/', $key);
}

/**
 * Log API usage for monitoring
 */
function logApiUsage($endpoint, $response_code, $response_time = null) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'response_code' => $response_code,
        'response_time' => $response_time,
        'user_id' => $_SESSION['user_id'] ?? 'anonymous'
    ];
    
    // Log to file (you can also log to database)
    $log_file = __DIR__ . '/../logs/api_usage.log';
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}
?>
