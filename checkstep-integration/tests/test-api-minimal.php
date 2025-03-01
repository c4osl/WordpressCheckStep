<?php
/**
 * Minimal CheckStep API test script
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting minimal CheckStep API test...\n\n";

// Verify API credentials
$api_key = getenv('CHECKSTEP_API_KEY');
if (!$api_key) {
    die("Error: CHECKSTEP_API_KEY environment variable not found\n");
}

echo "✓ API key found\n";

// API configuration
$api_url = 'https://api.checkstep.com/v1';

// Test content
$test_content = json_encode([
    'id' => 'test-' . time(),
    'type' => 'text',
    'content' => 'This is a test message to verify API connectivity.',
    'metadata' => [
        'source' => 'api_test',
        'timestamp' => date('c')
    ]
]);

echo "Attempting to submit content to CheckStep API...\n";
echo "API URL: {$api_url}/content\n";
echo "Test content: {$test_content}\n\n";

// Initialize curl
$ch = curl_init("{$api_url}/content");

// Set curl options
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $test_content,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json'
    ]
]);

// Execute request
echo "Sending request...\n";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "✗ Error: " . curl_error($ch) . "\n";
    curl_close($ch);
    exit(1);
}

curl_close($ch);

echo "HTTP Status Code: {$http_code}\n";
echo "Response body: {$response}\n\n";

if ($http_code === 200) {
    echo "✓ Successfully submitted content to CheckStep\n";
    $response_data = json_decode($response, true);
    if (isset($response_data['id'])) {
        echo "Content ID: {$response_data['id']}\n";
    }
} else {
    echo "✗ Failed to submit content (HTTP {$http_code})\n";
    if ($response) {
        $error_data = json_decode($response, true);
        if (isset($error_data['error'])) {
            echo "Error message: {$error_data['error']}\n";
        }
    }
    exit(1);
}

echo "\nTest completed.\n";
