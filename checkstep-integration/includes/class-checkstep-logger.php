<?php
/**
 * CheckStep Logger Class
 *
 * Provides centralized logging functionality for the CheckStep integration.
 * Implements consistent error logging and debugging capabilities that integrate
 * with WordPress's error logging system.
 *
 * @package CheckStep_Integration
 * @subpackage Logging
 * @since 1.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Class CheckStep_Logger
 *
 * Implements logging functionality with support for different severity levels
 * and contextual information.
 *
 * @since 1.0.0
 */
class CheckStep_Logger {
    /**
     * Log levels
     *
     * @since 1.0.0
     * @var array
     */
    private static $levels = array(
        'error'   => 1,
        'warning' => 2,
        'info'    => 3,
        'debug'   => 4
    );

    /**
     * Current log level
     *
     * @since 1.0.0
     * @var string
     */
    private static $current_level = 'warning';

    /**
     * Initialize logger
     *
     * Sets up the logging system with the configured log level.
     *
     * @since 1.0.0
     */
    public static function init() {
        self::$current_level = get_option('checkstep_log_level', 'warning');
    }

    /**
     * Log an error message
     *
     * Records critical errors that require immediate attention.
     *
     * @since 1.0.0
     * @param string $message Error message
     * @param array  $context Additional contextual data
     */
    public static function error($message, $context = array()) {
        self::log('error', $message, $context);
    }

    /**
     * Log a warning message
     *
     * Records potentially problematic situations that don't prevent operation.
     *
     * @since 1.0.0
     * @param string $message Warning message
     * @param array  $context Additional contextual data
     */
    public static function warning($message, $context = array()) {
        self::log('warning', $message, $context);
    }

    /**
     * Log an info message
     *
     * Records general operational information.
     *
     * @since 1.0.0
     * @param string $message Info message
     * @param array  $context Additional contextual data
     */
    public static function info($message, $context = array()) {
        self::log('info', $message, $context);
    }

    /**
     * Log a debug message
     *
     * Records detailed debugging information.
     *
     * @since 1.0.0
     * @param string $message Debug message
     * @param array  $context Additional contextual data
     */
    public static function debug($message, $context = array()) {
        self::log('debug', $message, $context);
    }

    /**
     * Internal logging method
     *
     * Handles the actual logging of messages based on configured level.
     *
     * @since 1.0.0
     * @access private
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Additional contextual data
     */
    private static function log($level, $message, $context = array()) {
        if (!isset(self::$levels[$level]) || !isset(self::$levels[self::$current_level])) {
            return;
        }

        if (self::$levels[$level] > self::$levels[self::$current_level]) {
            return;
        }

        $log_entry = sprintf(
            '[CheckStep %s] %s%s',
            strtoupper($level),
            $message,
            empty($context) ? '' : ' | Context: ' . json_encode($context)
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_entry);
        }

        /**
         * Fires after a log entry is created
         *
         * @since 1.0.0
         * @param string $level     Log level
         * @param string $message   Log message
         * @param array  $context   Contextual data
         * @param string $log_entry Complete log entry
         */
        do_action('checkstep_logged_message', $level, $message, $context, $log_entry);
    }
}
