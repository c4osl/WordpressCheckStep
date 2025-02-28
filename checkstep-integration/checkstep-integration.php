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

/**
 * Class CheckStep_Integration
 * 
 * Main plugin class that handles integration with BuddyBoss Platform
 */
class CheckStep_Integration extends BP_Integration {

    /**
     * Instance of this class.
     *
     * @var CheckStep_Integration
     */
    private static $instance = null;

    /**
     * Initialize the plugin.
     */
    private function __construct() {
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
     * Initialize plugin components.
     */
    public function init_components() {
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

        // Initialize components
        $this->api = new CheckStep_API();
        $this->content_types = new CheckStep_Content_Types();
        $this->ingestion = new CheckStep_Ingestion($this->api, $this->content_types);
        $this->moderation = new CheckStep_Moderation($this->api);
        $this->notifications = new CheckStep_Notifications();
        $this->admin = new CheckStep_Admin();

        // Setup admin integration tab
        $this->setup_admin_integration_tab();

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Check if required plugins are active.
     *
     * @return bool
     */
    private function check_requirements() {
        if (!class_exists('BuddyBoss_Platform')) {
            add_action('admin_notices', array($this, 'buddyboss_missing_notice'));
            return false;
        }
        return true;
    }

    /**
     * Setup admin integration tab.
     */
    public function setup_admin_integration_tab() {
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
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Schedule WP-Cron events
        if (!wp_next_scheduled('checkstep_process_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'checkstep_process_queue');
        }

        // Create necessary database tables
        $this->create_tables();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('checkstep_process_queue');
    }

    /**
     * Create plugin tables.
     */
    private function create_tables() {
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
    }

    /**
     * Admin notice for missing BuddyBoss.
     */
    public function buddyboss_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('CheckStep Integration requires BuddyBoss Platform to be installed and activated.', 'checkstep-integration'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
function checkstep_integration_init() {
    return CheckStep_Integration::get_instance();
}

// Start the plugin
checkstep_integration_init();