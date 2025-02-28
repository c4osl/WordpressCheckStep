<?php
/**
 * CheckStep Admin Integration Tab
 *
 * Implements the BuddyBoss Platform integration tab for CheckStep configuration.
 * Provides settings fields for API credentials, moderation preferences, and
 * notification settings within the BuddyBoss admin interface.
 *
 * @package CheckStep_Integration
 * @subpackage Admin
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Class CheckStep_Admin_Tab
 *
 * Extends BuddyBoss's integration tab system to provide a dedicated configuration
 * interface for CheckStep settings. Handles the registration and display of settings
 * fields, section management, and asset enqueuing.
 *
 * @since 1.0.0
 */
class CheckStep_Admin_Tab extends BP_Admin_Integration_Tab {

    /**
     * Initialize the admin tab.
     *
     * Sets up the tab properties and hooks for the CheckStep integration settings.
     *
     * @since 1.0.0
     * @param string $tab_path Tab identifier path
     * @param string $tab_name Display name of the tab
     * @param array  $tab_args Additional tab configuration arguments
     */
    public function initialize($tab_path, $tab_name, $tab_args = array()) {
        $this->tab = $tab_path;
        $this->tab_name = $tab_name;
        $this->tab_args = $tab_args;

        $this->setup_hooks();
    }

    /**
     * Setup hooks for the admin tab.
     *
     * Registers necessary WordPress hooks for settings and asset management.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function setup_hooks() {
        // Register settings on admin_init
        add_action('admin_init', array($this, 'register_fields'));

        // Add custom admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register admin settings.
     *
     * Creates sections and fields for the CheckStep integration settings.
     *
     * @since 1.0.0
     */
    public function register_fields() {
        $sections = array(
            'checkstep_api_settings_section' => array(
                'title'    => __('CheckStep API Settings', 'checkstep-integration'),
                'callback' => array($this, 'api_settings_section'),
                'fields'   => array(
                    'checkstep_api_key' => array(
                        'title'    => __('API Key', 'checkstep-integration'),
                        'callback' => array($this, 'api_key_field'),
                        'sanitize' => 'sanitize_text_field',
                    ),
                    'checkstep_api_url' => array(
                        'title'    => __('API URL', 'checkstep-integration'),
                        'callback' => array($this, 'api_url_field'),
                        'sanitize' => 'esc_url_raw',
                    ),
                ),
            ),
            'checkstep_moderation_settings_section' => array(
                'title'    => __('Moderation Settings', 'checkstep-integration'),
                'callback' => array($this, 'moderation_settings_section'),
                'fields'   => array(
                    'checkstep_auto_moderation' => array(
                        'title'    => __('Auto-moderation', 'checkstep-integration'),
                        'callback' => array($this, 'auto_moderation_field'),
                        'sanitize' => 'intval',
                    ),
                    'checkstep_notification_level' => array(
                        'title'    => __('Notification Level', 'checkstep-integration'),
                        'callback' => array($this, 'notification_level_field'),
                        'sanitize' => 'sanitize_text_field',
                    ),
                ),
            ),
        );

        foreach ($sections as $section_id => $section) {
            // Add section
            bp_add_settings_section(
                $this->tab,
                $section_id,
                $section['title'],
                $section['callback']
            );

            // Add fields
            foreach ($section['fields'] as $field_id => $field) {
                bp_add_settings_field(
                    $field_id,
                    $field['title'],
                    $field['callback'],
                    $this->tab,
                    $section_id,
                    array(
                        'sanitize_callback' => $field['sanitize'],
                    )
                );
            }
        }
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * Loads the necessary CSS and JavaScript files for the admin interface.
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
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
        if (!is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

        return 'bp-integrations' === $page && $this->tab === $tab;
    }

    /**
     * API Settings section description.
     *
     * Outputs the descriptive text for the API settings section.
     *
     * @since 1.0.0
     */
    public function api_settings_section() {
        ?>
        <p><?php _e('Configure your CheckStep API credentials and settings.', 'checkstep-integration'); ?></p>
        <?php
    }

    /**
     * API Key field.
     *
     * Renders the input field for the CheckStep API key with proper security measures.
     *
     * @since 1.0.0
     */
    public function api_key_field() {
        $value = bp_get_option('checkstep_api_key', '');
        ?>
        <input type="password" 
               name="checkstep_api_key" 
               id="checkstep_api_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Enter your CheckStep API key.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * API URL field.
     *
     * Renders the input field for the CheckStep API endpoint URL.
     *
     * @since 1.0.0
     */
    public function api_url_field() {
        $value = bp_get_option('checkstep_api_url', 'https://api.checkstep.com/v1');
        ?>
        <input type="url" 
               name="checkstep_api_url" 
               id="checkstep_api_url" 
               value="<?php echo esc_url($value); ?>" 
               class="regular-text" />
        <p class="description">
            <?php _e('Enter the CheckStep API endpoint URL.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Moderation Settings section description.
     *
     * Outputs the descriptive text for the moderation settings section.
     *
     * @since 1.0.0
     */
    public function moderation_settings_section() {
        ?>
        <p><?php _e('Configure how content moderation should be handled.', 'checkstep-integration'); ?></p>
        <?php
    }

    /**
     * Auto-moderation field.
     *
     * Renders the checkbox for enabling automatic content moderation.
     *
     * @since 1.0.0
     */
    public function auto_moderation_field() {
        $value = bp_get_option('checkstep_auto_moderation', 0);
        ?>
        <input type="checkbox" 
               name="checkstep_auto_moderation" 
               id="checkstep_auto_moderation" 
               value="1" 
               <?php checked($value, 1); ?> />
        <label for="checkstep_auto_moderation">
            <?php _e('Enable automatic content moderation', 'checkstep-integration'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, content will be automatically moderated based on CheckStep\'s recommendations.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Notification level field.
     *
     * Renders the dropdown for selecting moderation notification levels.
     *
     * @since 1.0.0
     */
    public function notification_level_field() {
        $value = bp_get_option('checkstep_notification_level', 'moderate');
        $options = array(
            'all'      => __('All Issues', 'checkstep-integration'),
            'moderate' => __('Moderate and Severe Issues', 'checkstep-integration'),
            'severe'   => __('Severe Issues Only', 'checkstep-integration'),
        );
        ?>
        <select name="checkstep_notification_level" id="checkstep_notification_level">
            <?php foreach ($options as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" 
                        <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Choose which content moderation issues should trigger notifications.', 'checkstep-integration'); ?>
        </p>
        <?php
    }
}