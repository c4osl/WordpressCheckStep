<?php
/**
 * CheckStep Logger Class
 *
 * Provides centralized logging functionality for the CheckStep integration.
 * Stores logs in the database for admin viewing and integrates with
 * WordPress's error logging system.
 *
 * @package CheckStep_Integration
 * @subpackage Logging
 * @since 1.0.0
 * @since 1.0.11 Added database storage for logs and admin viewer
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Class CheckStep_Logger
 *
 * Implements logging functionality with support for different severity levels
 * and contextual information. Stores logs in database for admin viewing.
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
    private static $current_level = 'info';

    /**
     * Maximum number of logs to retain
     *
     * @since 1.0.11
     * @var int
     */
    private static $max_logs = 1000;

    /**
     * Initialize logger
     *
     * Sets up the logging system with the configured log level.
     *
     * @since 1.0.0
     */
    public static function init() {
        if (function_exists('get_option')) {
            self::$current_level = get_option('checkstep_log_level', 'info');
        }
    }

    /**
     * Log a hook trigger event
     *
     * Records when a WordPress/BuddyBoss hook fires.
     *
     * @since 1.0.11
     * @param string $hook_name  Name of the hook that fired
     * @param array  $context    Additional contextual data (IDs, parameters, etc.)
     */
    public static function hook($hook_name, $context = array()) {
        $context['hook'] = $hook_name;
        $context['type'] = 'hook_trigger';
        self::log('info', "Hook fired: {$hook_name}", $context);
    }

    /**
     * Log an API call
     *
     * Records API calls to CheckStep.
     *
     * @since 1.0.11
     * @param string $endpoint   API endpoint called
     * @param string $method     HTTP method (GET, POST, etc.)
     * @param array  $context    Additional contextual data
     */
    public static function api($endpoint, $method = 'POST', $context = array()) {
        $context['endpoint'] = $endpoint;
        $context['method'] = $method;
        $context['type'] = 'api_call';
        self::log('info', "API call: {$method} {$endpoint}", $context);
    }

    /**
     * Log an API response
     *
     * Records API responses from CheckStep.
     *
     * @since 1.0.11
     * @param string $endpoint     API endpoint called
     * @param int    $status_code  HTTP status code
     * @param array  $context      Additional contextual data
     */
    public static function api_response($endpoint, $status_code, $context = array()) {
        $context['endpoint'] = $endpoint;
        $context['status_code'] = $status_code;
        $context['type'] = 'api_response';
        $level = ($status_code >= 200 && $status_code < 300) ? 'info' : 'error';
        self::log($level, "API response: {$status_code} from {$endpoint}", $context);
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
     * Handles the actual logging of messages to both database and error log.
     *
     * @since 1.0.0
     * @since 1.0.11 Added database storage
     * @since 1.0.15 Removed legacy level gate - now controlled by enabled_log_levels setting
     * @access private
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Additional contextual data
     */
    private static function log($level, $message, $context = array()) {
        if (!isset(self::$levels[$level])) {
            return;
        }

        $log_entry = sprintf(
            '[CheckStep %s] %s%s',
            strtoupper($level),
            $message,
            empty($context) ? '' : ' | Context: ' . json_encode($context)
        );

        // Store in database if WordPress is available (level filtering happens in store_log)
        if (function_exists('get_option') && defined('CHECKSTEP_PLUGIN_DIR')) {
            self::store_log($level, $message, $context);
        }

        // If WordPress functions are available, use WP's error logging for errors/warnings
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
            // Only log errors and warnings to error_log to avoid flooding
            if (in_array($level, array('error', 'warning'))) {
                error_log($log_entry);
            }
        } else if (!function_exists('get_option')) {
            // In test environment, output to stdout
            echo $log_entry . "\n";
        }

        // Fire action if WordPress is available
        if (function_exists('do_action')) {
            do_action('checkstep_logged_message', $level, $message, $context, $log_entry);
        }
    }

    /**
     * Store log entry in database
     *
     * @since 1.0.11
     * @since 1.0.15 Added enabled log levels check
     * @access private
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Additional contextual data
     */
    private static function store_log($level, $message, $context = array()) {
        global $wpdb;

        if (!$wpdb) {
            return;
        }
        
        // Check if this log level is enabled
        $enabled_levels = get_option('checkstep_enabled_log_levels', array('error', 'warning', 'info'));
        if (!is_array($enabled_levels)) {
            $enabled_levels = array('error', 'warning', 'info');
        }
        
        if (!in_array($level, $enabled_levels)) {
            return;
        }

        $table_name = $wpdb->prefix . 'checkstep_logs';

        // Check if table exists, create if not
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            // Try to create the table
            if (!self::create_table()) {
                return;
            }
        }

        // Extract special context fields
        $log_type = isset($context['type']) ? $context['type'] : 'general';
        $hook_name = isset($context['hook']) ? $context['hook'] : null;
        $content_type = isset($context['content_type']) ? $context['content_type'] : null;
        $content_id = isset($context['content_id']) ? intval($context['content_id']) : null;

        // Remove special fields from context to avoid duplication
        unset($context['type']);

        $result = $wpdb->insert(
            $table_name,
            array(
                'level' => $level,
                'message' => $message,
                'context' => !empty($context) ? json_encode($context) : null,
                'log_type' => $log_type,
                'hook_name' => $hook_name,
                'content_type' => $content_type,
                'content_id' => $content_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );

        // Cleanup old logs periodically
        if (rand(1, 100) === 1) {
            self::cleanup_old_logs();
        }
    }

    /**
     * Cleanup old logs to prevent table from growing too large
     *
     * @since 1.0.11
     * @access private
     */
    private static function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'checkstep_logs';

        // Delete logs older than 30 days or exceeding max count
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )
        );

        // Keep only the most recent logs if exceeding max
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        if ($count > self::$max_logs) {
            $delete_count = $count - self::$max_logs;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table_name} ORDER BY created_at ASC LIMIT %d",
                    $delete_count
                )
            );
        }
    }

    /**
     * Get logs from database
     *
     * @since 1.0.11
     * @param array $args Query arguments
     * @return array Array of log entries
     */
    public static function get_logs($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'checkstep_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array();
        }

        $defaults = array(
            'level' => '',
            'log_type' => '',
            'search' => '',
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $values = array();

        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $values[] = $args['level'];
        }

        if (!empty($args['log_type'])) {
            $where[] = 'log_type = %s';
            $values[] = $args['log_type'];
        }

        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR context LIKE %s OR hook_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $orderby = in_array($args['orderby'], array('id', 'level', 'created_at', 'log_type')) ? $args['orderby'] : 'created_at';

        $sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = intval($args['limit']);
        $values[] = intval($args['offset']);

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get log count
     *
     * @since 1.0.11
     * @param array $args Query arguments
     * @return int Count of logs
     */
    public static function get_log_count($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'checkstep_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return 0;
        }

        $where = array('1=1');
        $values = array();

        if (!empty($args['level'])) {
            $where[] = 'level = %s';
            $values[] = $args['level'];
        }

        if (!empty($args['log_type'])) {
            $where[] = 'log_type = %s';
            $values[] = $args['log_type'];
        }

        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR context LIKE %s OR hook_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return intval($wpdb->get_var($sql));
    }

    /**
     * Clear all logs
     *
     * @since 1.0.11
     * @return bool Success status
     */
    public static function clear_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'checkstep_logs';

        return $wpdb->query("TRUNCATE TABLE {$table_name}") !== false;
    }

    /**
     * Get log statistics
     *
     * @since 1.0.11
     * @return array Statistics about logs
     */
    public static function get_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'checkstep_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return array(
                'total' => 0,
                'by_level' => array(),
                'by_type' => array(),
                'recent_hooks' => array(),
                'recent_api_calls' => array()
            );
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        $by_level = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM {$table_name} GROUP BY level",
            OBJECT_K
        );

        $by_type = $wpdb->get_results(
            "SELECT log_type, COUNT(*) as count FROM {$table_name} GROUP BY log_type",
            OBJECT_K
        );

        $recent_hooks = $wpdb->get_results(
            "SELECT hook_name, COUNT(*) as count, MAX(created_at) as last_fired 
             FROM {$table_name} 
             WHERE log_type = 'hook_trigger' AND hook_name IS NOT NULL 
             GROUP BY hook_name 
             ORDER BY last_fired DESC 
             LIMIT 10"
        );

        $recent_api_calls = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE log_type IN ('api_call', 'api_response') 
             ORDER BY created_at DESC 
             LIMIT 10"
        );

        return array(
            'total' => intval($total),
            'by_level' => $by_level,
            'by_type' => $by_type,
            'recent_hooks' => $recent_hooks,
            'recent_api_calls' => $recent_api_calls
        );
    }

    /**
     * Create logs table
     *
     * @since 1.0.11
     * @return bool Success status
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'checkstep_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext DEFAULT NULL,
            log_type varchar(50) DEFAULT 'general',
            hook_name varchar(100) DEFAULT NULL,
            content_type varchar(50) DEFAULT NULL,
            content_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY log_type (log_type),
            KEY hook_name (hook_name),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }
}
