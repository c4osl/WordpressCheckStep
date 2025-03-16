<?php
/**
 * Test queue handler functionality
 */

require_once dirname(__DIR__) . '/includes/class-checkstep-queue-handler.php';
require_once dirname(__DIR__) . '/includes/class-checkstep-content-types.php';
require_once dirname(__DIR__) . '/includes/class-checkstep-api.php';

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

// Mock WordPress functions if not in WordPress environment
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $wp_options;
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

if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

// Initialize global options array
global $wp_options;
$wp_options = array();

echo "Testing queue handler...\n\n";

try {
    $queue_handler = new CheckStep_Queue_Handler();

    // Test queueing content
    echo "Testing content queueing...\n";
    $result = $queue_handler->queue_content('activity', 12345);
    if ($result) {
        echo "✓ Successfully queued activity content\n";

        // Verify queue content
        $queue = get_option(CheckStep_Queue_Handler::QUEUE_OPTION);
        echo "Queue contents:\n";
        print_r($queue);
    } else {
        echo "✗ Failed to queue content\n";
    }

    // Test queue processing
    echo "\nTesting queue processing...\n";
    $queue_handler->process_queue();

    // Verify updated queue status
    $updated_queue = get_option(CheckStep_Queue_Handler::QUEUE_OPTION);
    echo "Updated queue contents:\n";
    print_r($updated_queue);

    echo "\nQueue handler tests completed.\n";

} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>