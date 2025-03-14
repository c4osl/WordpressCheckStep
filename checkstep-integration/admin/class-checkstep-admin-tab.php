<?php
/**
 * CheckStep Admin Integration Tab
 *
 * Implements the BuddyBoss Platform integration tab for CheckStep configuration.
 *
 * @package CheckStep_Integration
 * @subpackage Admin
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Include parent class if not already included
if (!class_exists('BP_Admin_Integration_Tab')) {
    require_once buddypress()->plugin_dir . 'bp-core/classes/class-bp-admin-integration-tab.php';
}

/**
 * Class CheckStep_Admin_Tab
 *
 * Extends BuddyBoss's integration tab system to provide a dedicated configuration
 * interface for CheckStep settings.
 *
 * @since 1.0.0
 */
class CheckStep_Admin_Tab extends BP_Admin_Integration_Tab {

    /**
     * Initialize the admin tab.
     *
     * @since 1.0.0
     */
    public function initialize() {
        parent::initialize();

        $this->tab = 'checkstep_integration';
        $this->tab_name = __('CheckStep', 'checkstep-integration');
        $this->tab_args = array(
            'title'    => __('CheckStep Integration', 'checkstep-integration'),
            'priority' => 50,
        );

        add_action('bp_loaded', array($this, 'setup_hooks'));
    }

    /**
     * Setup hooks for the admin tab.
     *
     * @since 1.0.0
     * @access public
     */
    public function setup_hooks() {
        try {
            // Register settings on admin_init
            add_action('admin_init', array($this, 'register_fields'));

            // Add custom admin scripts and styles
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

            CheckStep_Logger::debug('Admin tab hooks initialized');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to setup admin tab hooks', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Register admin settings.
     *
     * @since 1.0.0
     */
    public function register_fields() {
        try {
            // Add section for API settings
            bp_core_register_section(
                $this->tab,
                'checkstep_api_settings',
                __('API Settings', 'checkstep-integration'),
                array($this, 'api_settings_section_callback')
            );

            // Register API Key field
            bp_core_register_setting(
                $this->tab,
                'checkstep_api_key',
                array(
                    'title'    => __('API Key', 'checkstep-integration'),
                    'callback' => array($this, 'api_key_field_callback'),
                    'section'  => 'checkstep_api_settings'
                )
            );

            // Register Webhook Secret field
            bp_core_register_setting(
                $this->tab,
                'checkstep_webhook_secret',
                array(
                    'title'    => __('Webhook Secret', 'checkstep-integration'),
                    'callback' => array($this, 'webhook_secret_field_callback'),
                    'section'  => 'checkstep_api_settings'
                )
            );

            CheckStep_Logger::info('Settings fields registered successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to register settings fields', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * API Settings section callback.
     */
    public function api_settings_section_callback() {
        ?>
        <p><?php _e('Configure your CheckStep API credentials. You can find these in your CheckStep dashboard.', 'checkstep-integration'); ?></p>
        <?php
    }

    /**
     * API Key field callback.
     */
    public function api_key_field_callback() {
        $value = bp_get_option('checkstep_api_key', '');
        ?>
        <input type="password" 
               name="checkstep_api_key" 
               id="checkstep_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <button type="button" class="button button-secondary toggle-api-key">
            <?php _e('Show/Hide', 'checkstep-integration'); ?>
        </button>
        <p class="description">
            <?php _e('Enter your CheckStep API key. This is required for content moderation.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Webhook Secret field callback.
     */
    public function webhook_secret_field_callback() {
        $value = bp_get_option('checkstep_webhook_secret', '');
        ?>
        <input type="password" 
               name="checkstep_webhook_secret" 
               id="checkstep_webhook_secret" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <button type="button" class="button button-secondary toggle-webhook-secret">
            <?php _e('Show/Hide', 'checkstep-integration'); ?>
        </button>
        <p class="description">
            <?php _e('Enter your CheckStep webhook secret. This is used to verify incoming webhook requests.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * Loads the necessary CSS and JavaScript files for the admin interface.
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        try {
            if ($this->is_current_tab()) {
                wp_enqueue_style(
                    'checkstep-admin',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
                    array(),
                    CHECKSTEP_VERSION
                );

                wp_enqueue_script(
                    'checkstep-admin',
                    plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
                    array('jquery'),
                    CHECKSTEP_VERSION,
                    true
                );

                CheckStep_Logger::debug('Admin assets enqueued successfully');
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to enqueue admin assets', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Check if current page is this tab.
     *
     * Determines if the current admin page corresponds to this integration tab.
     *
     * @since 1.0.0
     * @access protected
     * @return bool True if current page is this tab
     */
    protected function is_current_tab() {
        try {
            if (!is_admin()) {
                return false;
            }

            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

            $is_current = 'bp-integrations' === $page && $this->tab === $tab;

            CheckStep_Logger::debug('Tab check performed', array(
                'is_current' => $is_current,
                'page' => $page,
                'tab' => $tab
            ));

            return $is_current;
        } catch (Exception $e) {
            CheckStep_Logger::error('Error checking current tab', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * API Settings section description.
     *
     * Outputs the descriptive text for the API settings section.
     *
     * @since 1.0.0
     */
   

    /**
     * API Key field.
     *
     * Renders the input field for the CheckStep API key with proper security measures.
     *
     * @since 1.0.0
     */
   

    /**
     * API URL field.
     *
     * Renders the input field for the CheckStep API endpoint URL.
     *
     * @since 1.0.0
     */
   

    /**
     * Moderation Settings section description.
     *
     * Outputs the descriptive text for the moderation settings section.
     *
     * @since 1.0.0
     */
    

    /**
     * Auto-moderation field.
     *
     * Renders the checkbox for enabling automatic content moderation.
     *
     * @since 1.0.0
     */
    

    /**
     * Notification level field.
     *
     * Renders the dropdown for selecting moderation notification levels.
     *
     * @since 1.0.0
     */
    

}