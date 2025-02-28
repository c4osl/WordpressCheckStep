<?php
/**
 * Tests for CheckStep_Moderation class
 *
 * @package CheckStep_Integration
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting CheckStep Moderation Tests...\n";

// Define paths explicitly
define('TEST_ROOT', dirname(__FILE__));
define('PLUGIN_ROOT', dirname(TEST_ROOT));

// Verify file paths
$required_files = array(
    TEST_ROOT . '/class-base-test-case.php',
    PLUGIN_ROOT . '/includes/wordpress-stubs.php',
    PLUGIN_ROOT . '/includes/class-checkstep-api.php',
    PLUGIN_ROOT . '/includes/class-checkstep-moderation.php',
);

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Required file not found: {$file}\n");
    }
    echo "Found required file: {$file}\n";
}

// Load dependencies
require_once TEST_ROOT . '/class-base-test-case.php';
require_once PLUGIN_ROOT . '/includes/wordpress-stubs.php';
require_once PLUGIN_ROOT . '/includes/class-checkstep-api.php';
require_once PLUGIN_ROOT . '/includes/class-checkstep-moderation.php';

echo "Dependencies loaded successfully.\n\n";

/**
 * Class Test_CheckStep_Moderation
 */
class Test_CheckStep_Moderation extends Base_Test_Case {

    /**
     * @var CheckStep_Moderation
     */
    private $moderation;

    /**
     * Set up each test
     */
    public function setUp() {
        parent::setUp();

        echo "Setting up test environment...\n";

        try {
            // Set up test configuration
            update_stub_option('checkstep_api_key', 'test-api-key');
            update_stub_option('checkstep_webhook_secret', 'test-secret');
            update_stub_option('checkstep_api_url', 'https://api.checkstep.com/v1/');

            $api = new CheckStep_API();
            $this->moderation = new CheckStep_Moderation($api);
            echo "Test environment initialized successfully.\n";
        } catch (Exception $e) {
            echo "Failed to initialize test environment: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Run all tests
     */
    public function run() {
        ob_start();
        try {
            $this->setUp();

            $methods = get_class_methods($this);
            $test_count = 0;
            $passed_count = 0;
            $failed_count = 0;

            echo "\nRunning CheckStep Moderation Tests\n";
            echo "================================\n\n";

            foreach ($methods as $method) {
                if (strpos($method, 'test_') === 0) {
                    $test_count++;
                    try {
                        echo "Running {$method}...\n";
                        $this->$method();
                        echo "✓ {$method}\n";
                        $passed_count++;
                    } catch (Exception $e) {
                        echo "✗ {$method}: {$e->getMessage()}\n";
                        $failed_count++;
                    }
                }
            }

            echo "\nTest Results\n";
            echo "============\n";
            echo "Total Tests: {$test_count}\n";
            echo "Passed: {$passed_count}\n";
            echo "Failed: {$failed_count}\n\n";

        } catch (Exception $e) {
            echo "Test execution failed: " . $e->getMessage() . "\n";
        }

        ob_end_flush();
    }

    /**
     * Test webhook verification with missing signature
     */
    public function test_verify_webhook_missing_signature() {
        $request = $this->create_request();
        $result = $this->moderation->verify_webhook($request);
        $this->assertWPError($result, 'missing_signature');
    }

    /**
     * Test webhook verification with missing secret
     */
    public function test_verify_webhook_missing_secret() {
        $request = $this->create_request(
            array(),
            array('X-CheckStep-Signature' => 'test-sig')
        );

        $result = $this->moderation->verify_webhook($request);
        $this->assertWPError($result, 'missing_secret');
    }

    /**
     * Test webhook verification with invalid signature
     */
    public function test_verify_webhook_invalid_signature() {
        // Set webhook secret
        update_stub_option('checkstep_webhook_secret', 'test-secret');

        $request = $this->create_request(
            array(),
            array('X-CheckStep-Signature' => 'invalid-sig'),
            'test-payload'
        );

        $result = $this->moderation->verify_webhook($request);
        $this->assertWPError($result, 'invalid_signature');
    }

    /**
     * Test webhook verification with valid signature
     */
    public function test_verify_webhook_valid_signature() {
        $secret = 'test-secret';
        $payload = 'test-payload';
        $signature = hash_hmac('sha256', $payload, $secret);

        // Set webhook secret
        update_stub_option('checkstep_webhook_secret', $secret);

        $request = $this->create_request(
            array(),
            array('X-CheckStep-Signature' => $signature),
            $payload
        );

        $result = $this->moderation->verify_webhook($request);
        $this->assertTrue($result);
    }

    /**
     * Test handling webhook with valid decision data
     */
    public function test_handle_decision_webhook() {
        $decision_data = array(
            'decision_id' => 'test-decision',
            'content_id' => 123,
            'action' => 'hide',
            'reason' => 'test reason'
        );

        $request = $this->create_request($decision_data);

        $response = $this->moderation->handle_decision_webhook($request);

        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals(202, $response->get_status());
        $this->assertEquals('queued', $response->get_data()['status']);
        $this->assertEquals('test-decision', $response->get_data()['decision_id']);
    }

    /**
     * Test handling moderation decisions
     */
    public function test_handle_moderation_decision() {
        // Test delete action
        $this->moderation->handle_moderation_decision(array(
            'decision_id' => 'test-delete',
            'content_id' => 123,
            'action' => 'delete',
            'reason' => ''
        ));

        // Test hide action
        $this->moderation->handle_moderation_decision(array(
            'decision_id' => 'test-hide',
            'content_id' => 124,
            'action' => 'hide',
            'reason' => ''
        ));

        // Test warn action
        $this->moderation->handle_moderation_decision(array(
            'decision_id' => 'test-warn',
            'content_id' => 125,
            'action' => 'warn',
            'reason' => 'test warning'
        ));

        // Test ban action
        $this->moderation->handle_moderation_decision(array(
            'decision_id' => 'test-ban',
            'content_id' => 126,
            'action' => 'ban_user',
            'reason' => ''
        ));

        // Verify actions through stub functions
        $this->assertTrue(stub_post_was_deleted(123));
        $this->assertTrue(stub_post_was_hidden(124));
        $this->assertEquals('test warning', stub_get_content_warning(125));
        $this->assertTrue(stub_user_was_banned(stub_get_post_author(126)));
    }
}

// Run the tests
echo "Instantiating test class...\n";
$test = new Test_CheckStep_Moderation();
echo "Running tests...\n";
$test->run();