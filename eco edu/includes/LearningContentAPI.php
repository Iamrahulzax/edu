<?php
require_once __DIR__ . '/../config/api_config.php';

/**
 * Learning Content API Service
 * Handles all API interactions for content management
 */
class LearningContentAPI {
    private $apiKey;
    private $baseUrl;
    private $timeout;
    
    public function __construct() {
        $this->apiKey = LEARNING_API_KEY;
        $this->baseUrl = LEARNING_API_BASE_URL;
        $this->timeout = API_REQUEST_TIMEOUT;
        
        if (!validateApiKey($this->apiKey)) {
            throw new Exception('Invalid API key format');
        }
    }
    
    /**
     * Generate educational content using Google AI
     */
    public function generateContent($topic, $difficulty = 'intermediate', $length = 1000) {
        $endpoint = 'models/gemini-pro:generateContent';
        
        $prompt = "Create educational content about '{$topic}' for {$difficulty} level learners. " .
                 "The content should be approximately {$length} words long. " .
                 "Include practical examples and make it engaging for environmental education. " .
                 "Format the content with proper headings and structure.";
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048
            ]
        ];
        
        return $this->makeApiRequest($endpoint, $data);
    }
    
    /**
     * Enhance existing content using Google AI
     */
    public function enhanceContent($content, $enhancements = []) {
        $endpoint = 'models/gemini-pro:generateContent';
        
        $prompt = "Please enhance the following educational content by improving readability, " .
                 "adding practical examples, ensuring factual accuracy, and making it more engaging " .
                 "for environmental education. Keep the same topic and difficulty level but make it better:\n\n" .
                 $content;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.5,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048
            ]
        ];
        
        return $this->makeApiRequest($endpoint, $data);
    }
    
    /**
     * Generate quiz questions from content using Google AI
     */
    public function generateQuiz($content, $questionCount = 5, $difficulty = 'intermediate') {
        $endpoint = 'models/gemini-pro:generateContent';
        
        $prompt = "Based on the following educational content, create {$questionCount} quiz questions " .
                 "for {$difficulty} level students. Include a mix of multiple choice, true/false, and short answer questions. " .
                 "Format the response as JSON with this structure: " .
                 '{"questions": [{"question": "...", "type": "multiple_choice", "options": ["A", "B", "C", "D"], "correct_answer": "A"}]}' .
                 "\n\nContent:\n" . $content;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048
            ]
        ];
        
        return $this->makeApiRequest($endpoint, $data);
    }
    
    /**
     * Translate content to different languages
     */
    public function translateContent($content, $targetLanguage = 'es') {
        if (!in_array($targetLanguage, SUPPORTED_LANGUAGES)) {
            throw new Exception('Unsupported language: ' . $targetLanguage);
        }
        
        $endpoint = 'content/translate';
        $data = [
            'content' => $content,
            'target_language' => $targetLanguage,
            'preserve_formatting' => true,
            'context' => 'educational'
        ];
        
        return $this->makeApiRequest($endpoint, $data);
    }
    
    /**
     * Summarize long content
     */
    public function summarizeContent($content, $maxLength = 500) {
        $endpoint = 'content/summarize';
        $data = [
            'content' => $content,
            'max_length' => $maxLength,
            'preserve_key_points' => true,
            'format' => 'bullet_points'
        ];
        
        return $this->makeApiRequest($endpoint, $data);
    }
    
    /**
     * Validate content for accuracy and appropriateness
     */
    public function validateContent($content) {
        $endpoint = 'content/validate';
        $data = [
            'content' => $content,
            'checks' => [
                'fact_accuracy' => true,
                'age_appropriateness' => true,
                'educational_value' => true,
                'plagiarism' => true
            ]
        ];
        
        return $this->makeApiRequest($endpoint, $data);
    }
    
    /**
     * Make API request with error handling and retry logic for Google AI
     */
    private function makeApiRequest($endpoint, $data, $retries = 0) {
        // Add API key as URL parameter for Google AI
        $url = $this->baseUrl . $endpoint . '?key=' . $this->apiKey;
        $startTime = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => getApiHeaders(),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = microtime(true) - $startTime;
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log API usage
        logApiUsage($endpoint, $httpCode, $responseTime);
        
        // Handle cURL errors
        if ($error) {
            if ($retries < MAX_API_RETRIES) {
                sleep(pow(2, $retries)); // Exponential backoff
                return $this->makeApiRequest($endpoint, $data, $retries + 1);
            }
            throw new Exception('API request failed: ' . $error);
        }
        
        // Handle HTTP errors
        if ($httpCode >= 400) {
            if ($httpCode == 429 && $retries < MAX_API_RETRIES) {
                sleep(5); // Rate limit delay
                return $this->makeApiRequest($endpoint, $data, $retries + 1);
            }
            
            $errorMessage = $this->getErrorMessage($httpCode, $response);
            throw new Exception($errorMessage);
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        return $decodedResponse;
    }
    
    /**
     * Get user-friendly error messages
     */
    private function getErrorMessage($httpCode, $response) {
        $messages = [
            400 => 'Bad request - Invalid parameters',
            401 => 'Unauthorized - Invalid API key',
            403 => 'Forbidden - Access denied',
            404 => 'Not found - Endpoint does not exist',
            429 => 'Rate limit exceeded - Please try again later',
            500 => 'Internal server error - Please try again later',
            503 => 'Service unavailable - Please try again later'
        ];
        
        if (isset($messages[$httpCode])) {
            return $messages[$httpCode];
        }
        
        // Try to extract error from response
        $decoded = json_decode($response, true);
        if ($decoded && isset($decoded['error'])) {
            return $decoded['error'];
        }
        
        return 'API request failed with code: ' . $httpCode;
    }
    
    /**
     * Check API health and connectivity using Google AI
     */
    public function checkApiHealth() {
        try {
            $startTime = microtime(true);
            
            // Test with a simple content generation request
            $endpoint = 'models/gemini-pro:generateContent';
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Say "API is working" in one sentence.']
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 10
                ]
            ];
            
            $response = $this->makeApiRequest($endpoint, $data);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Check if we got a valid response
            if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                return [
                    'status' => 'healthy',
                    'response_time' => $responseTime . 'ms',
                    'version' => 'Gemini Pro',
                    'test_response' => trim($response['candidates'][0]['content']['parts'][0]['text'])
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Invalid response format from API'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get API usage statistics
     */
    public function getUsageStats() {
        $logFile = __DIR__ . '/../logs/api_usage.log';
        if (!file_exists($logFile)) {
            return ['total_requests' => 0, 'today_requests' => 0];
        }
        
        $logs = file($logFile, FILE_IGNORE_NEW_LINES);
        $today = date('Y-m-d');
        $todayCount = 0;
        
        foreach ($logs as $log) {
            $entry = json_decode($log, true);
            if ($entry && strpos($entry['timestamp'], $today) === 0) {
                $todayCount++;
            }
        }
        
        return [
            'total_requests' => count($logs),
            'today_requests' => $todayCount,
            'rate_limit' => API_RATE_LIMIT_PER_HOUR,
            'remaining_today' => max(0, API_RATE_LIMIT_PER_HOUR - $todayCount)
        ];
    }
}
?>
