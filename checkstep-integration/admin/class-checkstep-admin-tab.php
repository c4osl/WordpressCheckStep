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

/**
 * Class CheckStep_Admin_Tab
 */
class CheckStep_Admin_Tab extends BP_Admin_Integration_Tab {

    /**
     * Initialize the admin tab
     */
    public function initialize() {
        $this->tab_label = __('CheckStep', 'checkstep-integration');
        $this->tab_name  = 'checkstep-integration';
        $this->tab_order = 55;
    }

    /**
     * Register settings fields
     */
    public function register_fields() {
        // API Settings Section
        $this->add_section(
            'checkstep_api_settings',
            __('API Configuration', 'checkstep-integration'),
            array($this, 'api_settings_info')
        );

        // API Key Field
        $this->add_field(
            'checkstep_api_key',
            __('API Key', 'checkstep-integration'),
            array($this, 'render_api_key_field'),
            'checkstep_api_settings'
        );

        // Webhook Secret Field
        $this->add_field(
            'checkstep_webhook_secret',
            __('Webhook Secret', 'checkstep-integration'),
            array($this, 'render_webhook_secret_field'),
            'checkstep_api_settings'
        );

        // Queue Settings Section
        $this->add_section(
            'checkstep_queue_settings',
            __('Queue Configuration', 'checkstep-integration'),
            array($this, 'queue_settings_info')
        );

        // Queue Processing Interval
        $this->add_field(
            'checkstep_queue_interval',
            __('Processing Interval', 'checkstep-integration'),
            array($this, 'render_queue_interval_field'),
            'checkstep_queue_settings'
        );

        // Notification Settings Section
        $this->add_section(
            'checkstep_notification_settings',
            __('Notification Settings', 'checkstep-integration'),
            array($this, 'notification_settings_info')
        );

        // Enable Email Notifications
        $this->add_field(
            'checkstep_enable_email_notifications',
            __('Email Notifications', 'checkstep-integration'),
            array($this, 'render_email_notifications_field'),
            'checkstep_notification_settings'
        );
    }

    /**
     * API Settings section information
     */
    public function api_settings_info() {
        ?>
        <p>
            <?php _e('Configure your CheckStep API credentials and endpoint settings.', 'checkstep-integration'); ?>
            <a href="https://docs.checkstep.com/api" target="_blank">
                <?php _e('Learn More', 'checkstep-integration'); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Queue Settings section information
     */
    public function queue_settings_info() {
        ?>
        <p>
            <?php _e('Configure how CheckStep processes the content moderation queue.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Notification Settings section information
     */
    public function notification_settings_info() {
        ?>
        <p>
            <?php _e('Configure how users are notified about moderation decisions.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $value = bp_get_option('checkstep_api_key', '');
        ?>
        <input type="password"
               id="checkstep_api_key"
               name="checkstep_api_key"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
        />
        <button type="button" class="button button-secondary toggle-field-visibility">
            <?php _e('Show/Hide', 'checkstep-integration'); ?>
        </button>
        <p class="description">
            <?php _e('Enter your CheckStep API key. This is required for content moderation.', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render webhook secret field
     */
    public function render_webhook_secret_field() {
        $value = bp_get_option('checkstep_webhook_secret', '');
        ?>
        <input type="password"
               id="checkstep_webhook_secret"
               name="checkstep_webhook_secret"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
        />
        <button type="button" class="button button-secondary toggle-field-visibility">
            <?php _e('Show/Hide', 'checkstep-integration'); ?>
        </button>
        <p class="description">
            <?php _e('Enter your CheckStep webhook secret. This verifies incoming webhook requests.', 'checkstep-integration'); ?>
        </p>
        <div class="webhook-url-info">
            <p>
                <?php _e('Configure this webhook URL in your CheckStep dashboard:', 'checkstep-integration'); ?>
                <code><?php echo esc_url(get_rest_url(null, 'checkstep/v1/decisions')); ?></code>
            </p>
        </div>
        <?php
    }

    /**
     * Render queue interval field
     */
    public function render_queue_interval_field() {
        $value = bp_get_option('checkstep_queue_interval', '5');
        ?>
        <select id="checkstep_queue_interval" name="checkstep_queue_interval">
            <option value="1" <?php selected($value, '1'); ?>>
                <?php _e('Every minute', 'checkstep-integration'); ?>
            </option>
            <option value="5" <?php selected($value, '5'); ?>>
                <?php _e('Every 5 minutes', 'checkstep-integration'); ?>
            </option>
            <option value="15" <?php selected($value, '15'); ?>>
                <?php _e('Every 15 minutes', 'checkstep-integration'); ?>
            </option>
            <option value="30" <?php selected($value, '30'); ?>>
                <?php _e('Every 30 minutes', 'checkstep-integration'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('How often should the moderation queue be processed?', 'checkstep-integration'); ?>
        </p>
        <?php
    }

    /**
     * Render email notifications field
     */
    public function render_email_notifications_field() {
        $value = bp_get_option('checkstep_enable_email_notifications', '1');
        ?>
        <input type="checkbox"
               id="checkstep_enable_email_notifications"
               name="checkstep_enable_email_notifications"
               value="1"
               <?php checked($value, '1'); ?>
        />
        <label for="checkstep_enable_email_notifications">
            <?php _e('Send email notifications for moderation decisions', 'checkstep-integration'); ?>
        </label>
        <p class="description">
            <?php _e('Users will receive email notifications when their content is moderated.', 'checkstep-integration'); ?>
        </p>
        <?php
    }
}

// Initialize the admin tab
new CheckStep_Admin_Tab();

?>