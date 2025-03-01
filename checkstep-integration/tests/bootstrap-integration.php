<?php
/**
 * Bootstrap file for integration tests
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify API credentials
$required_env = array('CHECKSTEP_API_KEY', 'CHECKSTEP_WEBHOOK_SECRET');
foreach ($required_env as $env) {
    if (!getenv($env)) {
        die("Missing required environment variable: {$env}\n");
    }
}

echo "API credentials verified.\n";

// Set API credentials from environment variables
putenv('CHECKSTEP_API_KEY=' . getenv('CHECKSTEP_API_KEY'));
putenv('CHECKSTEP_WEBHOOK_SECRET=' . getenv('CHECKSTEP_WEBHOOK_SECRET'));

// Required files
$plugin_root = dirname(__DIR__);
$api_file = $plugin_root . '/includes/class-checkstep-api.php';

if (!file_exists($api_file)) {
    die("API class file not found at: {$api_file}\n");
}

require_once $api_file;
echo "API class loaded successfully.\n\n";