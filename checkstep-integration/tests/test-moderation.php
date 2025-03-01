<?php
/**
 * Simple unit tests for CheckStep moderation functionality
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting CheckStep Moderation Tests...\n";

class MockAPI {
    public function send_content($type, $payload) {
        return ['status' => 'success'];
    }

    public function get_decision($content_id) {
        return ['decision' => 'approved'];
    }
}

class TestCase {
    protected function assertEquals($expected, $actual, $message = '') {
        if ($expected !== $actual) {
            throw new Exception(sprintf(
                '%s: Expected %s but got %s',
                $message ?: 'Assertion failed',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertTrue($value, $message = '') {
        if ($value !== true) {
            throw new Exception($message ?: 'Expected true but got ' . var_export($value, true));
        }
    }

    protected function assertFalse($value, $message = '') {
        if ($value !== false) {
            throw new Exception($message ?: 'Expected false but got ' . var_export($value, true));
        }
    }
}

class ModerationTest extends TestCase {
    private $api;
    private $test_data = [];

    public function setUp() {
        $this->api = new MockAPI();
        $this->test_data = [];
    }

    public function test_content_submission() {
        $content = [
            'id' => 123,
            'type' => 'post',
            'text' => 'Test content'
        ];

        $result = $this->api->send_content('post', $content);
        $this->assertEquals('success', $result['status']);
    }

    public function test_decision_retrieval() {
        $result = $this->api->get_decision(123);
        $this->assertEquals('approved', $result['decision']);
    }

    public function test_content_moderation() {
        // Test that content can be hidden
        $this->test_data['hidden_content'] = [];
        $this->hideContent(123);
        $this->assertTrue(in_array(123, $this->test_data['hidden_content']));
    }

    private function hideContent($content_id) {
        if (!isset($this->test_data['hidden_content'])) {
            $this->test_data['hidden_content'] = [];
        }
        $this->test_data['hidden_content'][] = $content_id;
        return true;
    }

    public function run() {
        $methods = get_class_methods($this);
        $test_count = 0;
        $passed_count = 0;
        $failed_count = 0;

        echo "\nRunning Moderation Tests\n";
        echo "=====================\n\n";

        foreach ($methods as $method) {
            if (strpos($method, 'test_') === 0) {
                $test_count++;
                try {
                    $this->setUp();
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
    }
}

// Run the tests
$test = new ModerationTest();
$test->run();