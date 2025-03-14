<?php
/**
 * Test webhook handler functionality
 */

require_once dirname(__DIR__) . '/includes/class-checkstep-webhook-handler.php';

// Mock BuddyBoss moderation types
class BP_Moderation_Posts {
    public static $moderation_type = 'post';
}

class BP_Moderation_Activity {
    public static $moderation_type = 'activity';
}

class BP_Moderation_Media {
    public static $moderation_type = 'media';
}

class BP_Moderation_Video {
    public static $moderation_type = 'video';
}

class BP_Moderation_Document {
    public static $moderation_type = 'document';
}

// Mock BuddyBoss functions if not in WordPress environment
if (!function_exists('bp_moderation_hide')) {
    function bp_moderation_hide($args) {
        echo sprintf("[Mock BuddyBoss] Hiding content: %s\n", json_encode($args));
        return true;
    }
}

if (!function_exists('bp_activity_get')) {
    function bp_activity_get($activity_id) {
        return false;
    }
}

if (!function_exists('bp_get_media')) {
    function bp_get_media($media_id) {
        return $media_id === '12345' ? (object)array('id' => $media_id) : false;
    }
}

if (!function_exists('bp_get_video')) {
    function bp_get_video($video_id) {
        return false;
    }
}

if (!function_exists('bp_get_document')) {
    function bp_get_document($doc_id) {
        return false;
    }
}

// Mock WP_REST_Request class if not available
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $body;
        private $headers = array();

        public function __construct($method, $route) {}

        public function set_header($name, $value) {
            $this->headers[$name] = $value;
        }

        public function get_header($name) {
            return isset($this->headers[$name]) ? $this->headers[$name] : null;
        }

        public function set_body($body) {
            $this->body = $body;
        }

        public function get_body() {
            return $this->body;
        }

        public function get_json_params() {
            return json_decode($this->body, true);
        }
    }
}

// Mock BuddyBoss notification function
if (!function_exists('bp_notifications_add_notification')) {
    function bp_notifications_add_notification($args) {
        echo sprintf("[Mock BuddyBoss] Adding notification: %s\n", json_encode($args));
        return true;
    }
}

if (!function_exists('bp_core_current_time')) {
    function bp_core_current_time() {
        return date('Y-m-d H:i:s');
    }
}

echo "Testing webhook handler...\n\n";

// Test decision taken payload - No Action
$no_action_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'no_action',
    'reason' => 'Content meets community guidelines'
);

// Test decision taken payload - Hide Action
$decision_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'hide',
    'reason' => 'Contains inappropriate content'
);

// Test decision taken payload - Appeal Upheld
$upheld_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'upheld',
    'reason' => 'Appeal review complete - original decision stands'
);

// Test incident closed payload
$incident_payload = array(
    'event_type' => 'incident_closed',
    'incident_id' => 'inc_123',
    'content_id' => '12345',
    'resolution' => 'Content violates community guidelines'
);

try {
    // Create webhook handler
    $handler = new CheckStep_Webhook_Handler();

    // Test no action decision
    echo "Testing no action decision...\n";
    $request = new WP_REST_Request('POST', '/checkstep/v1/decisions');
    $request->set_header('X-CheckStep-Signature', 'test-signature');
    $request->set_body(json_encode($no_action_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test moderation decision
    echo "\nTesting hide content decision...\n";
    $request->set_body(json_encode($decision_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test appeal upheld decision
    echo "\nTesting appeal upheld decision...\n";
    $request->set_body(json_encode($upheld_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test incident closure
    echo "\nTesting incident closed event...\n";
    $request->set_body(json_encode($incident_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    echo "\nTest completed successfully.\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
}

?>