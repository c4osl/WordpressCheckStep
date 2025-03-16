<?php
/**
 * Test webhook handler functionality
 */

require_once dirname(__DIR__) . '/tests/bootstrap-integration.php';
require_once dirname(__DIR__) . '/includes/class-checkstep-webhook-handler.php';

echo "Testing webhook handler...\n\n";

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

// Test decision taken payload - No Action
$no_action_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'no_action',
    'reason' => 'Content meets community guidelines'
);

// Test decision taken payload - Hide Action
$hide_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'hide',
    'reason' => 'Contains inappropriate content'
);

// Test decision taken payload - Ban User
$ban_user_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '1',  // User ID
    'action' => 'ban_user',
    'reason' => 'Multiple violations of community guidelines'
);

// Test decision taken payload - Appeal Upheld
$upheld_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'upheld',
    'reason' => 'Appeal review complete - original decision stands'
);

// Test decision taken payload - Appeal Overturned
$overturn_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'overturn',
    'reason' => 'Appeal review complete - decision overturned'
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
    $request->set_header('X-CheckStep-Signature', hash_hmac('sha256', json_encode($no_action_payload), getenv('CHECKSTEP_WEBHOOK_SECRET')));
    $request->set_body(json_encode($no_action_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test hide content decision
    echo "\nTesting hide content decision...\n";
    $request->set_header('X-CheckStep-Signature', hash_hmac('sha256', json_encode($hide_payload), getenv('CHECKSTEP_WEBHOOK_SECRET')));
    $request->set_body(json_encode($hide_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test ban user decision
    echo "\nTesting ban user decision...\n";
    $request->set_header('X-CheckStep-Signature', hash_hmac('sha256', json_encode($ban_user_payload), getenv('CHECKSTEP_WEBHOOK_SECRET')));
    $request->set_body(json_encode($ban_user_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test appeal upheld decision
    echo "\nTesting appeal upheld decision...\n";
    $request->set_header('X-CheckStep-Signature', hash_hmac('sha256', json_encode($upheld_payload), getenv('CHECKSTEP_WEBHOOK_SECRET')));
    $request->set_body(json_encode($upheld_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test appeal overturned decision
    echo "\nTesting appeal overturned decision...\n";
    $request->set_header('X-CheckStep-Signature', hash_hmac('sha256', json_encode($overturn_payload), getenv('CHECKSTEP_WEBHOOK_SECRET')));
    $request->set_body(json_encode($overturn_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    // Test incident closure
    echo "\nTesting incident closed event...\n";
    $request->set_header('X-CheckStep-Signature', hash_hmac('sha256', json_encode($incident_payload), getenv('CHECKSTEP_WEBHOOK_SECRET')));
    $request->set_body(json_encode($incident_payload));
    $response = $handler->handle_webhook($request);
    echo "Response:\n";
    print_r($response);

    echo "\nAll webhook handler tests completed successfully.\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
}

?>