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

    <form action="options.php" method="post">
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
            <p>
                <?php printf(
                    __('There are currently %d items waiting to be processed.', 'checkstep-integration'),
                    $pending_count
                ); ?>
            </p>
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

    <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            margin-top: 20px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2, .card h3 {
            margin-top: 0;
        }
        .ul-disc {
            list-style: disc inside;
            margin-left: 20px;
        }
        code {
            display: block;
            padding: 10px;
            background: #f0f0f1;
            margin: 10px 0;
        }
    </style>
</div>
