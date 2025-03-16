<?php
/**
 * Admin settings page template
 *
 * @package CheckStep_Integration
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php
    // Show settings update message
    if (isset($_GET['settings-updated'])) {
        add_settings_error(
            'checkstep_messages',
            'checkstep_message',
            __('Settings Saved', 'checkstep-integration'),
            'updated'
        );
    }
    settings_errors('checkstep_messages');
    ?>

    <div class="card">
        <h2><?php _e('About CheckStep Integration', 'checkstep-integration'); ?></h2>
        <p>
            <?php _e('This plugin integrates your BuddyBoss-powered website with CheckStep\'s content moderation system. It helps maintain a safe and respectful community by automatically monitoring user-generated content.', 'checkstep-integration'); ?>
        </p>
    </div>

    <form action="options.php" method="post" id="checkstep-settings-form">
        <?php
        settings_fields('checkstep_settings');
        do_settings_sections('checkstep-settings');
        ?>

        <div class="card">
            <h3><?php _e('API Connection Status', 'checkstep-integration'); ?></h3>
            <?php
            $api_key = get_option('checkstep_api_key');
            if ($api_key) {
                echo '<div class="notice notice-success inline"><p>';
                _e('API key is configured. The plugin is ready to moderate content.', 'checkstep-integration');
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-warning inline"><p>';
                _e('Please configure your CheckStep API key to enable content moderation.', 'checkstep-integration');
                echo '</p></div>';
            }
            ?>
            <p>
                <button type="button" class="button button-secondary" id="test-checkstep-connection">
                    <?php _e('Test Connection', 'checkstep-integration'); ?>
                </button>
                <span class="checkstep-status"></span>
            </p>
        </div>

        <div class="card">
            <h3><?php _e('Webhook Configuration', 'checkstep-integration'); ?></h3>
            <p><?php _e('Configure this webhook endpoint in your CheckStep dashboard:', 'checkstep-integration'); ?></p>
            <code><?php echo esc_url(get_rest_url(null, 'checkstep/v1/decisions')); ?></code>
        </div>

        <div class="card">
            <h3><?php _e('Content Types Being Monitored', 'checkstep-integration'); ?></h3>
            <ul class="ul-disc">
                <li><?php _e('Blog Posts', 'checkstep-integration'); ?></li>
                <li><?php _e('Forum Posts', 'checkstep-integration'); ?></li>
                <li><?php _e('User Profiles', 'checkstep-integration'); ?></li>
                <li><?php _e('Media Attachments', 'checkstep-integration'); ?></li>
            </ul>
        </div>

        <div class="card">
            <h3><?php _e('Queue Status', 'checkstep-integration'); ?></h3>
            <?php
            global $wpdb;
            $pending_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}checkstep_queue WHERE status = 'pending'"
            );
            ?>
            <div class="queue-status">
                <p>
                    <span class="queue-status-count"><?php echo intval($pending_count); ?></span>
                    <?php _e('items in queue', 'checkstep-integration'); ?>
                </p>
                <p class="queue-last-processed">
                    <?php 
                    $last_processed = get_option('checkstep_last_queue_process');
                    if ($last_processed) {
                        printf(
                            __('Last processed: %s', 'checkstep-integration'),
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_processed))
                        );
                    }
                    ?>
                </p>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>

    <div class="card">
        <h3><?php _e('Need Help?', 'checkstep-integration'); ?></h3>
        <p>
            <?php _e('For support and documentation, please visit:', 'checkstep-integration'); ?>
            <a href="https://docs.checkstep.com" target="_blank">docs.checkstep.com</a>
        </p>
    </div>
</div>