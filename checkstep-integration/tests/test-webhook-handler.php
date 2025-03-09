<?php
/**
 * Test webhook handler functionality
 */

require_once dirname(__DIR__) . '/includes/class-checkstep-webhook-handler.php';

echo "Testing webhook handler...\n\n";

// Test decision taken payload
$decision_payload = array(
    'event_type' => 'decision_taken',
    'content_id' => '12345',
    'action' => 'hide',
    'reason' => 'Contains inappropriate content'
);

// Test incident closed payload
$incident_payload = array(
    'event_type' => 'incident_closed',
    'incident_id' => 'inc_123',
    'content_id' => '12345',
    'resolution' => 'Content violates community guidelines'
);

// Create webhook handler
$handler = new CheckStep_Webhook_Handler();

// Create mock request
$request = new WP_REST_Request('POST', '/checkstep/v1/decisions');
$request->set_header('X-CheckStep-Signature', 'test-signature');
$request->set_body(json_encode($decision_payload));

// Test signature verification
echo "Testing signature verification...\n";
$verify_result = $handler->verify_webhook_signature($request);
var_dump($verify_result);

// Test webhook handling
echo "\nTesting decision taken event...\n";
$response = $handler->handle_webhook($request);
var_dump($response);

// Test incident closure
$request->set_body(json_encode($incident_payload));
echo "\nTesting incident closed event...\n";
$response = $handler->handle_webhook($request);
var_dump($response);

echo "\nTest completed.\n";
