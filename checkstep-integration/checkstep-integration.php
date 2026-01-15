<?php
/**
 * Plugin Name: CheckStep Integration for BuddyBoss
 * Plugin URI: https://example.com/checkstep-integration
 * Description: Integrates BuddyBoss with CheckStep's content moderation system
 * Version: 1.0.19
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: checkstep-integration
 * Domain Path: /languages
 *
 * @package CheckStep_Integration
 * @version 1.0.19
 */

// If this file is called directly, abort.
defined('WPINC') || exit;

// Plugin version
define('CHECKSTEP_VERSION', '1.0.19');
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

        // Register activation/deactivation hooks at constructor level (must be here, not in action hooks)
        register_activation_hook(__FILE__, array('CheckStep_Integration', 'activate'));
        register_deactivation_hook(__FILE__, array('CheckStep_Integration', 'deactivate'));

        // Add custom cron schedule early (must be before scheduling)
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));

        // Check if BuddyBoss Platform is active (show warning but don't block)
        $this->check_buddyboss();

        // Initialize admin interface early
        // This ensures admin_menu hooks are registered at the right time
        if (is_admin()) {
            $this->init_admin();
        }

        // Initialize core components immediately (API, content types)
        // Must happen before 'init' hook fires so ingestion has access to them
        $this->init_core_components();
        
        // Initialize ingestion on 'init' action - this is when BuddyBoss hooks are available
        // Using priority 20 to ensure BuddyBoss/bbPress hooks are already registered
        add_action('init', array($this, 'init_ingestion'), 20);
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));

        // Ensure database tables exist (for upgrades)
        add_action('admin_init', array($this, 'maybe_upgrade_database'));

        // Schedule queue processing (run on init after cron_schedules filter is active)
        add_action('init', array($this, 'schedule_queue_processing'), 99);
    }

    /**
     * Schedule queue processing cron job
     *
     * @since 1.0.12
     */
    public function schedule_queue_processing() {
        if (!wp_next_scheduled('checkstep_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'checkstep_process_queue');
            CheckStep_Logger::info('Queue processing cron scheduled');
        }
    }

    /**
     * Check and upgrade database tables if needed.
     * Also ensures tables exist (for cases where activation hook didn't run).
     *
     * @since 1.0.11
     * @since 1.0.15 Also creates queue table if missing
     */
    public function maybe_upgrade_database() {
        global $wpdb;
        
        $current_version = get_option('checkstep_db_version', '0');
        
        // Check if queue table exists
        $queue_table = $wpdb->prefix . 'checkstep_queue';
        $queue_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $queue_table
        )) === $queue_table;
        
        // Create tables if missing or version is old
        if (!$queue_exists || version_compare($current_version, '1.0.15', '<')) {
            $this->create_database_tables();
            update_option('checkstep_db_version', '1.0.15');
        }
    }
    
    /**
     * Create database tables.
     *
     * @since 1.0.15
     */
    private function create_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Queue table
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

        // Logs table
        CheckStep_Logger::create_table();
        
        CheckStep_Logger::info('Database tables created/updated');
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
     * Initialize admin interface early.
     * Called during plugin construction if in admin context.
     */
    public function init_admin() {
        try {
            require_once CHECKSTEP_PLUGIN_DIR . 'admin/class-checkstep-admin.php';
            
            // Only load BuddyBoss tab if BuddyBoss is available
            if (class_exists('BP_Admin_Integration_tab')) {
                require_once CHECKSTEP_PLUGIN_DIR . 'admin/class-checkstep-admin-tab.php';
            }
            
            $this->admin = new CheckStep_Admin();
            CheckStep_Logger::info('Admin interface initialized');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to initialize admin interface', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Initialize core plugin components (API, content types).
     * Called on plugins_loaded.
     *
     * @since 1.0.12
     */
    public function init_core_components() {
        try {
            // Load dependencies
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-api.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-content-types.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-moderation.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-notifications.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-webhook-handler.php';

            // Initialize API component
            try {
                $this->api = new CheckStep_API();
                CheckStep_Logger::info('API component initialized successfully');
            } catch (Exception $e) {
                CheckStep_Logger::error('Failed to initialize API component', array(
                    'error' => $e->getMessage()
                ));
                throw $e;
            }

            // Initialize content types (doesn't require BuddyBoss hooks to be ready)
            $this->content_types = new CheckStep_Content_Types();

            // Initialize moderation, notifications, and webhook handler
            try {
                if (function_exists('bp_get_option')) {
                    $this->moderation = new CheckStep_Moderation($this->api);
                    $this->notifications = new CheckStep_Notifications();
                    new CheckStep_Webhook_Handler();
                }
            } catch (Exception $e) {
                CheckStep_Logger::error('Failed to initialize BuddyBoss components', array(
                    'error' => $e->getMessage()
                ));
            }

            // Register BuddyBoss integration tab on bp_admin_init hook
            add_action('bp_admin_init', array($this, 'register_buddyboss_integration_tab'));

        } catch (Exception $e) {
            CheckStep_Logger::error('Core component initialization failed', array(
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
     * Add custom cron schedules
     *
     * @since 1.0.12
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'checkstep-integration')
        );
        return $schedules;
    }

    /**
     * Initialize ingestion component.
     * Called on 'init' action with priority 20 to ensure BuddyBoss/bbPress hooks are ready.
     *
     * @since 1.0.12
     */
    public function init_ingestion() {
        try {
            // Load ingestion class if not already loaded
            if (!class_exists('CheckStep_Ingestion')) {
                require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-ingestion.php';
            }

            // Initialize ingestion with API and content types
            if ($this->api && $this->content_types) {
                $this->ingestion = new CheckStep_Ingestion($this->api, $this->content_types);
                CheckStep_Logger::info('Ingestion component initialized on init hook');
            } else {
                CheckStep_Logger::warning('Cannot initialize ingestion - API or content_types not ready');
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to initialize ingestion component', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Register BuddyBoss integration tab.
     * Called on bp_admin_init hook as required by BuddyBoss.
     */
    public function register_buddyboss_integration_tab() {
        // Ensure BuddyBoss Platform is available
        if (!function_exists('buddypress')) {
            CheckStep_Logger::warning('BuddyBoss Platform not available for integration tab');
            return;
        }
        
        $bp = buddypress();
        
        // Load the base integration tab class if not already loaded
        $base_class_path = trailingslashit($bp->plugin_dir . 'bp-core/classes') . 'class-bp-admin-integration-tab.php';
        if (file_exists($base_class_path) && !class_exists('BP_Admin_Integration_tab')) {
            require_once $base_class_path;
        }
        
        // Instantiate the CheckStep integration tab
        if (class_exists('BP_Admin_Integration_tab')) {
            // Load our admin tab class if not already loaded
            if (!class_exists('CheckStep_Admin_Tab')) {
                $admin_tab_path = CHECKSTEP_PLUGIN_DIR . 'admin/class-checkstep-admin-tab.php';
                if (file_exists($admin_tab_path)) {
                    require_once $admin_tab_path;
                } else {
                    CheckStep_Logger::error('CheckStep admin tab file not found', array(
                        'path' => $admin_tab_path
                    ));
                    return;
                }
            }
            
            if (class_exists('CheckStep_Admin_Tab')) {
                new CheckStep_Admin_Tab();
                CheckStep_Logger::info('BuddyBoss integration tab registered successfully');
            }
        } else {
            CheckStep_Logger::error('BP_Admin_Integration_tab class not found');
        }
    }

    /**
     * Add settings link to plugins page.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=checkstep-settings'),
            __('Settings', 'checkstep-integration')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Display admin notice for missing BuddyBoss.
     */
    public function buddyboss_missing_notice() {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('CheckStep Integration:', 'checkstep-integration'); ?></strong>
                <?php _e('BuddyBoss Platform is not installed. The settings page is available, but content moderation features require BuddyBoss to function.', 'checkstep-integration'); ?>
            </p>
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

            // Queue table
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

            // Logs table
            CheckStep_Logger::create_table();

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