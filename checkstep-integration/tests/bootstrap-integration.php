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

// Mock CheckStep_Logger if not available
if (!class_exists('CheckStep_Logger')) {
    class CheckStep_Logger {
        public static function debug($message, $context = array()) {
            echo sprintf("[Debug] %s: %s\n", $message, json_encode($context));
        }

        public static function info($message, $context = array()) {
            echo sprintf("[Info] %s: %s\n", $message, json_encode($context));
        }

        public static function warning($message, $context = array()) {
            echo sprintf("[Warning] %s: %s\n", $message, json_encode($context));
        }

        public static function error($message, $context = array()) {
            echo sprintf("[Error] %s: %s\n", $message, json_encode($context));
        }
    }
}

// Mock WordPress functions needed by API class
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $wp_options;
        if ($option === 'checkstep_api_key') {
            return getenv('CHECKSTEP_API_KEY');
        }
        if ($option === 'checkstep_webhook_secret') {
            return getenv('CHECKSTEP_WEBHOOK_SECRET');
        }
        if ($option === 'checkstep_api_url') {
            return 'https://api.checkstep.com/api/v2/';
        }
        return isset($wp_options[$option]) ? $wp_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        global $wp_options;
        $wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook) {
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook) {
        return true;
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '') {
        return 'https://example.com' . $path;
    }
}

// Mock BuddyBoss moderation functions
if (!function_exists('bp_moderation_hide')) {
    function bp_moderation_hide($args) {
        CheckStep_Logger::info('Mock: Content hidden via BuddyBoss moderation', $args);
        return true;
    }
}

if (!function_exists('bp_moderation_update_status')) {
    function bp_moderation_update_status($args) {
        CheckStep_Logger::info('Mock: User status updated via BuddyBoss moderation', $args);
        return true;
    }
}

if (!function_exists('bp_notifications_add_notification')) {
    function bp_notifications_add_notification($args) {
        CheckStep_Logger::info('Mock: BuddyBoss notification sent', $args);
        return true;
    }
}

if (!function_exists('bp_core_current_time')) {
    function bp_core_current_time() {
        return date('Y-m-d H:i:s');
    }
}

// Mock BuddyBoss moderation type constants
if (!class_exists('BP_Moderation_Posts')) {
    class BP_Moderation_Posts {
        public static $moderation_type = 'post';
    }
}

if (!class_exists('BP_Moderation_Activity')) {
    class BP_Moderation_Activity {
        public static $moderation_type = 'activity';
    }
}

if (!class_exists('BP_Moderation_Media')) {
    class BP_Moderation_Media {
        public static $moderation_type = 'media';
    }
}

if (!class_exists('BP_Moderation_Video')) {
    class BP_Moderation_Video {
        public static $moderation_type = 'video';
    }
}

if (!class_exists('BP_Moderation_Document')) {
    class BP_Moderation_Document {
        public static $moderation_type = 'document';
    }
}

// Mock WordPress HTTP API functions
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        CheckStep_Logger::debug('Mock API POST request', array(
            'url' => $url,
            'body' => isset($args['body']) ? json_decode($args['body'], true) : null
        ));

        // Simulate successful API response
        return array(
            'response' => array('code' => 202),
            'body' => json_encode(array(
                'status' => 'accepted',
                'message' => 'Content accepted for moderation',
                'content_id' => 'test-' . time()
            ))
        );
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args) {
        CheckStep_Logger::debug('Mock API GET request', array('url' => $url));

        // Simulate successful API response
        return array(
            'response' => array('code' => 200),
            'body' => json_encode(array(
                'status' => 'completed',
                'decision' => 'accept',
                'content_id' => 'test-' . time(),
                'decision_id' => 'decision-' . time()
            ))
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 500;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return false;
    }
}

// Initialize global options array
global $wp_options;
$wp_options = array();

// Required files
$plugin_root = dirname(__DIR__);
$api_file = $plugin_root . '/includes/class-checkstep-api.php';

if (!file_exists($api_file)) {
    die("API class file not found at: {$api_file}\n");
}

require_once $api_file;
echo "API class loaded successfully.\n\n";