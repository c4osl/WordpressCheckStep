<?php
/**
 * CheckStep API Integration Test
 * 
 * This script tests the live integration with CheckStep's API.
 */

require_once dirname(__DIR__) . '/tests/bootstrap-integration.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting CheckStep API Integration Test...\n\n";

// Test content
$test_content = array(
    'id' => 'test-' . time(),
    'type' => 'text',
    'text' => 'This is a test post to verify API integration.',
    'metadata' => array(
        'source' => 'integration_test',
        'timestamp' => date('c')
    )
);

try {
    // Initialize API client
    $api = new CheckStep_API();
    
    echo "Initialized API client successfully\n";
    echo "Submitting test content...\n";
    
    // Submit content
    $submission_result = $api->send_content('text', $test_content);
    
    if ($submission_result) {
        echo "✓ Content submitted successfully\n";
        echo "Content ID: " . $test_content['id'] . "\n";
        
        // Wait briefly for processing
        sleep(2);
        
        // Try to get the decision
        echo "\nRetrieving moderation decision...\n";
        $decision = $api->get_decision($test_content['id']);
        
        if ($decision) {
            echo "✓ Retrieved decision successfully\n";
            echo "Decision details:\n";
            echo json_encode($decision, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "? Decision not yet available (this is normal for async processing)\n";
        }
    } else {
        echo "✗ Failed to submit content\n";
    }
    
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nAPI Integration test completed.\n";