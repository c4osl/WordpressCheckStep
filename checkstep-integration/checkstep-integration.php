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
if (!defined('WPINC')) {
    // Load stubs if we're not in WordPress environment
    require_once __DIR__ . '/includes/wordpress-stubs.php';
}

// Plugin version
define('CHECKSTEP_VERSION', '1.0.0');
define('CHECKSTEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHECKSTEP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load core classes
require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-logger.php';

/**
 * Class CheckStep_Integration
 * 
 * Main plugin class that handles integration with BuddyBoss Platform.
 * Manages plugin lifecycle, component initialization, and BuddyBoss integration setup.
 *
 * @since 1.0.0
 * @package CheckStep_Integration
 */
class CheckStep_Integration extends BP_Integration {

    /**
     * Instance of this class.
     *
     * @since 1.0.0
     * @access private
     * @var CheckStep_Integration
     */
    private static $instance = null;

    /**
     * Initialize the plugin.
     *
     * Sets up the integration with BuddyBoss platform and configures required plugins.
     *
     * @since 1.0.0
     * @access private
     */
    private function __construct() {
        try {
            // Initialize logger early
            CheckStep_Logger::init();

            $this->start(
                'checkstep',
                __('CheckStep', 'checkstep-integration'),
                'checkstep',
                array(
                    'required_plugin' => array(
                        array(
                            'name'    => 'BuddyBoss Platform',
                            'version' => '1.0.0',
                            'slug'    => 'buddyboss-platform/bp-loader.php',
                        ),
                    ),
                )
            );

            // Initialize components after required plugins check
            add_action('plugins_loaded', array($this, 'init_components'));

        } catch (Exception $e) {
            CheckStep_Logger::error('Plugin initialization failed', array(
                'error' => $e->getMessage()
            ));
            add_action('admin_notices', function() use ($e) {
                ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html(sprintf(
                        __('CheckStep Integration initialization failed: %s', 'checkstep-integration'),
                        $e->getMessage()
                    )); ?></p>
                </div>
                <?php
            });
        }
    }

    /**
     * Get plugin instance.
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @since 1.0.0
     * @return CheckStep_Integration Main plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin components.
     *
     * Loads and initializes all plugin components after verifying requirements.
     *
     * @since 1.0.0
     */
    public function init_components() {
        try {
            if (!$this->check_requirements()) {
                return;
            }

            // Load dependencies
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-api.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-content-types.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-ingestion.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-moderation.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'includes/class-checkstep-notifications.php';
            require_once CHECKSTEP_PLUGIN_DIR . 'admin/class-checkstep-admin.php';

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
                CheckStep_Logger::info('All components initialized successfully');
            } catch (Exception $e) {
                CheckStep_Logger::error('Failed to initialize components', array(
                    'error' => $e->getMessage()
                ));
                throw $e;
            }

            // Setup admin integration tab
            $this->setup_admin_integration_tab();

            // Register activation/deactivation hooks
            register_activation_hook(__FILE__, array($this, 'activate'));
            register_deactivation_hook(__FILE__, array($this, 'deactivate'));

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
     * Check if required plugins are active.
     *
     * Verifies that BuddyBoss Platform is installed and activated.
     *
     * @since 1.0.0
     * @access private
     * @return bool True if requirements are met, false otherwise
     */
    private function check_requirements() {
        if (!class_exists('BuddyBoss_Platform')) {
            CheckStep_Logger::warning('BuddyBoss Platform not found');
            add_action('admin_notices', array($this, 'buddyboss_missing_notice'));
            return false;
        }
        return true;
    }

    /**
     * Setup admin integration tab.
     *
     * Initializes the CheckStep settings tab in BuddyBoss Platform settings.
     *
     * @since 1.0.0
     */
    public function setup_admin_integration_tab() {
        try {
            require_once CHECKSTEP_PLUGIN_DIR . 'admin/class-checkstep-admin-tab.php';

            new CheckStep_Admin_Tab(
                "bp-{$this->id}",
                $this->name,
                array(
                    'root_path'       => $this->path,
                    'root_url'        => $this->url,
                    'required_plugin' => $this->required_plugin,
                )
            );
            CheckStep_Logger::info('Admin integration tab setup complete');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to setup admin integration tab', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Plugin activation.
     *
     * Creates necessary database tables and schedules recurring tasks.
     *
     * @since 1.0.0
     */
    public function activate() {
        try {
            // Schedule WP-Cron events
            if (!wp_next_scheduled('checkstep_process_queue')) {
                wp_schedule_event(time(), 'every_5_minutes', 'checkstep_process_queue');
            }

            // Create necessary database tables
            $this->create_tables();

            CheckStep_Logger::info('Plugin activated successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Plugin activation failed', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Plugin deactivation.
     *
     * Cleans up scheduled tasks and performs any necessary cleanup.
     *
     * @since 1.0.0
     */
    public function deactivate() {
        try {
            // Clear scheduled events
            wp_clear_scheduled_hook('checkstep_process_queue');
            CheckStep_Logger::info('Plugin deactivated successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Plugin deactivation failed', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Create plugin tables.
     *
     * Sets up custom database tables required by the plugin.
     *
     * @since 1.0.0
     * @access private
     */
    private function create_tables() {
        try {
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

            CheckStep_Logger::info('Database tables created successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to create database tables', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Admin notice for missing BuddyBoss.
     *
     * Displays an admin notice when BuddyBoss Platform is not installed.
     *
     * @since 1.0.0
     */
    public function buddyboss_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('CheckStep Integration requires BuddyBoss Platform to be installed and activated.', 'checkstep-integration'); ?></p>
        </div>
        <?php
    }
}

/**
 * Initialize the plugin.
 *
 * Returns the main instance of CheckStep_Integration.
 *
 * @since 1.0.0
 * @return CheckStep_Integration
 */
function checkstep_integration_init() {
    try {
        return CheckStep_Integration::get_instance();
    } catch (Exception $e) {
        CheckStep_Logger::error('Failed to initialize plugin', array(
            'error' => $e->getMessage()
        ));
        return null;
    }
}

// Start the plugin
checkstep_integration_init();