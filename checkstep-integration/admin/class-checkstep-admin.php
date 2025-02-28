<?php
/**
 * Admin Interface Handler Class
 */
class CheckStep_Admin {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('CheckStep Integration', 'checkstep-integration'),
            __('CheckStep', 'checkstep-integration'),
            'manage_options',
            'checkstep-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('checkstep_settings', 'checkstep_api_key');
        register_setting('checkstep_settings', 'checkstep_webhook_secret');
        register_setting('checkstep_settings', 'checkstep_appeal_url');

        add_settings_section(
            'checkstep_main_section',
            __('API Configuration', 'checkstep-integration'),
            array($this, 'render_section_info'),
            'checkstep-settings'
        );

        add_settings_field(
            'checkstep_api_key',
            __('API Key', 'checkstep-integration'),
            array($this, 'render_api_key_field'),
            'checkstep-settings',
            'checkstep_main_section'
        );

        add_settings_field(
            'checkstep_webhook_secret',
            __('Webhook Secret', 'checkstep-integration'),
            array($this, 'render_webhook_secret_field'),
            'checkstep-settings',
            'checkstep_main_section'
        );

        add_settings_field(
            'checkstep_appeal_url',
            __('Appeal URL', 'checkstep-integration'),
            array($this, 'render_appeal_url_field'),
            'checkstep-settings',
            'checkstep_main_section'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/settings-page.php';
    }

    /**
     * Render section info
     */
    public function render_section_info() {
        echo '<p>' . __('Configure your CheckStep API credentials and settings below.', 'checkstep-integration') . '</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = get_option('checkstep_api_key');
        ?>
        <input type="password"
               id="checkstep_api_key"
               name="checkstep_api_key"
               value="<?php echo esc_attr($api_key); ?>"
               class="regular-text"
        />
        <?php
    }

    /**
     * Render webhook secret field
     */
    public function render_webhook_secret_field() {
        $webhook_secret = get_option('checkstep_webhook_secret');
        ?>
        <input type="password"
               id="checkstep_webhook_secret"
               name="checkstep_webhook_secret"
               value="<?php echo esc_attr($webhook_secret); ?>"
               class="regular-text"
        />
        <?php
    }

    /**
     * Render appeal URL field
     */
    public function render_appeal_url_field() {
        $appeal_url = get_option('checkstep_appeal_url');
        ?>
        <input type="url"
               id="checkstep_appeal_url"
               name="checkstep_appeal_url"
               value="<?php echo esc_url($appeal_url); ?>"
               class="regular-text"
        />
        <?php
    }
}
