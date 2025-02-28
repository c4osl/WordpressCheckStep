<?php
/**
 * Base test case for CheckStep Integration tests
 *
 * @package CheckStep_Integration
 */

/**
 * Class Base_Test_Case
 */
class Base_Test_Case {

    /**
     * Set up test environment before each test
     */
    protected function setUp() {
        // Reset any static properties
        BP_Moderation_Abstract::$moderation = array();

        // Reset any global state
        $_GET = array();
        $_POST = array();
        $_SERVER = array();
    }

    /**
     * Create a mock REST request
     *
     * @param array $params Request parameters
     * @param array $headers Request headers
     * @param string $body Request body
     * @return WP_REST_Request
     */
    protected function create_request($params = array(), $headers = array(), $body = '') {
        $request = new WP_REST_Request();
        $request->set_params($params);
        $request->set_headers($headers);
        $request->set_body($body);
        return $request;
    }

    /**
     * Assert that a value equals an expected value
     *
     * @param mixed $expected Expected value
     * @param mixed $actual Actual value
     * @param string $message Optional assertion message
     */
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

    /**
     * Assert that a value is true
     *
     * @param mixed $value Value to test
     * @param string $message Optional assertion message
     */
    protected function assertTrue($value, $message = '') {
        if ($value !== true) {
            throw new Exception($message ?: 'Expected true but got ' . var_export($value, true));
        }
    }

    /**
     * Assert that a value is an instance of a class
     *
     * @param string $class Expected class name
     * @param mixed $object Object to test
     * @param string $message Optional assertion message
     */
    protected function assertInstanceOf($class, $object, $message = '') {
        if (!($object instanceof $class)) {
            throw new Exception(sprintf(
                '%s: Expected instance of %s but got %s',
                $message ?: 'Assertion failed',
                $class,
                get_class($object)
            ));
        }
    }

    /**
     * Assert that a value is a WP_Error with specific code
     *
     * @param mixed $actual The value to test
     * @param string $code Expected error code
     * @param string $message Optional assertion message
     */
    protected function assertWPError($actual, $code, $message = '') {
        if (!($actual instanceof WP_Error)) {
            throw new Exception($message ?: 'Expected WP_Error but got ' . get_class($actual));
        }
        $this->assertEquals($code, $actual->get_error_codes()[0], $message);
    }
}