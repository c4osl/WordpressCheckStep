<?php
/**
 * Tests for CheckStep_Moderation class
 *
 * @package CheckStep_Integration
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
}

// Load dependencies
require_once TEST_ROOT . '/class-base-test-case.php';
require_once PLUGIN_ROOT . '/includes/wordpress-stubs.php';
require_once PLUGIN_ROOT . '/includes/class-checkstep-api.php';
require_once PLUGIN_ROOT . '/includes/class-checkstep-moderation.php';

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
        $api = new CheckStep_API();
        $this->moderation = new CheckStep_Moderation($api);
    }

    /**
     * Run all tests
     */
    public function run() {
        $this->setUp();

        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (strpos($method, 'test_') === 0) {
                try {
                    $this->$method();
                    echo "✓ {$method}\n";
                } catch (Exception $e) {
                    echo "✗ {$method}: {$e->getMessage()}\n";
                }
            }
        }
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
$test = new Test_CheckStep_Moderation();
$test->run();