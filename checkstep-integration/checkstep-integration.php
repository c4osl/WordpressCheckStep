<?php
/**
 * Plugin Name: CheckStep Integration for BuddyBoss
 * Plugin URI: https://example.com/checkstep-integration
 * Description: Integrates BuddyBoss with CheckStep's content moderation system
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: checkstep-integration
 * Domain Path: /languages
 *
 * @package CheckStep_Integration
 * @version 1.0.0
 */

// If this file is called directly, abort.
defined('WPINC') || exit;

// Plugin version
define('CHECKSTEP_VERSION', '1.0.0');
define('CHECKSTEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHECKSTEP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load core classes
require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-logger.php';

/**
 * The main plugin class.
 *
 * @since 1.0.0
 */
class CheckStep_Integration {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     * @var CheckStep_Integration
     */
    private static $instance = null;

    /**
     * Initialize the plugin.
     */
    private function __construct() {
        // Initialize logger early
        CheckStep_Logger::init();

        // Check if BuddyBoss Platform is active
        if (!$this->check_buddyboss()) {
            return;
        }

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
    }

    /**
     * Get plugin instance.
     *
     * @return CheckStep_Integration
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if BuddyBoss Platform is active.
     *
     * @return bool
     */
    private function check_buddyboss() {
        if (!function_exists('bp_get_option')) {
            add_action('admin_notices', array($this, 'buddyboss_missing_notice'));
            return false;
        }
        return true;
    }

    /**
     * Initialize plugin components.
     */
    public function init_components() {
        try {
            // Load dependencies
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-api.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-content-types.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-ingestion.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-moderation.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-notifications.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-webhook-handler.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'admin/class-checkstep-admin.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'admin/class-checkstep-admin-tab.php';

            // Initialize components with error handling
            try {
                $this->api = new CheckStep_API();
                CheckStep_Logger::info('API component initialized successfully');
            } catch (Exception $e) {
                CheckStep_Logger::error('Failed to initialize API component', array(
                    'error' => $e->getMessage()
                ));
                throw $e;
            }

            try {
                $this->content_types = new CheckStep_Content_Types();
                $this->ingestion = new CheckStep_Ingestion($this->api, $this->content_types);
                $this->moderation = new CheckStep_Moderation($this->api);
                $this->notifications = new CheckStep_Notifications();
                $this->admin = new CheckStep_Admin();
                new CheckStep_Admin_Tab(); // from edited
                new CheckStep_Webhook_Handler(); //from edited
                CheckStep_Logger::info('All components initialized successfully');
            } catch (Exception $e) {
                CheckStep_Logger::error('Failed to initialize components', array(
                    'error' => $e->getMessage()
                ));
                throw $e;
            }

            // Register activation/deactivation hooks (moved here from edited)
            register_activation_hook(__FILE__, array('CheckStep_Integration', 'activate'));
            register_deactivation_hook(__FILE__, array('CheckStep_Integration', 'deactivate'));


        } catch (Exception $e) {
            CheckStep_Logger::error('Component initialization failed', array(
                'error' => $e->getMessage()
            ));
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html(sprintf(
                        __('CheckStep Integration component initialization failed: %s', 'checkstep-integration'),
                        $e->getMessage()
                    )); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * Display admin notice for missing BuddyBoss.
     */
    public function buddyboss_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('CheckStep Integration requires BuddyBoss Platform to be installed and activated.', 'checkstep-integration'); ?></p>
        </div>
        <?php
    }

    /**
     * Plugin activation.
     */
    public static function activate() {
        try {
            // Schedule WP-Cron events
            if (!wp_next_scheduled('checkstep_process_queue')) {
                wp_schedule_event(time(), 'every_5_minutes', 'checkstep_process_queue');
            }

            // Create necessary database tables
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}checkstep_queue (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                content_type varchar(50) NOT NULL,
                content_id bigint(20) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                processed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY content_type_id (content_type, content_id),
                KEY status (status)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            CheckStep_Logger::info('Plugin activated successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Plugin activation failed', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Plugin deactivation.
     */
    public static function deactivate() {
        try {
            wp_clear_scheduled_hook('checkstep_process_queue');
            CheckStep_Logger::info('Plugin deactivated successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Plugin deactivation failed', array(
                'error' => $e->getMessage()
            ));
        }
    }
}

// Initialize the plugin
function checkstep_integration_init() {
    return CheckStep_Integration::get_instance();
}

add_action('plugins_loaded', 'checkstep_integration_init');