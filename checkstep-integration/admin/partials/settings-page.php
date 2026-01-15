<?php
/**
 * Admin settings page template with tabs
 *
 * @package CheckStep_Integration
 * @since 1.0.0
 * @since 1.0.11 Added Logs tab
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
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

    <nav class="nav-tab-wrapper">
        <a href="?page=checkstep-settings&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Settings', 'checkstep-integration'); ?>
        </a>
        <a href="?page=checkstep-settings&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Logs', 'checkstep-integration'); ?>
        </a>
    </nav>

    <div class="tab-content" style="margin-top: 20px;">
        <?php if ($active_tab === 'settings'): ?>
            <!-- Settings Tab -->
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
                        <li><?php _e('Forum Topics & Replies', 'checkstep-integration'); ?></li>
                        <li><?php _e('BuddyBoss Activity Posts', 'checkstep-integration'); ?></li>
                        <li><?php _e('User Profiles', 'checkstep-integration'); ?></li>
                        <li><?php _e('Media Attachments (Images & Videos)', 'checkstep-integration'); ?></li>
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

        <?php elseif ($active_tab === 'logs'): ?>
            <!-- Logs Tab -->
            <?php
            $log_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
            $log_type = isset($_GET['log_type']) ? sanitize_text_field($_GET['log_type']) : '';
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $per_page = 50;

            $args = array(
                'level' => $log_level,
                'log_type' => $log_type,
                'search' => $search,
                'limit' => $per_page,
                'offset' => ($paged - 1) * $per_page
            );

            $logs = CheckStep_Logger::get_logs($args);
            $total_logs = CheckStep_Logger::get_log_count($args);
            $stats = CheckStep_Logger::get_stats();
            $total_pages = ceil($total_logs / $per_page);
            ?>

            <div class="card">
                <h2><?php _e('Log Statistics', 'checkstep-integration'); ?></h2>
                <div class="log-stats" style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>
                        <strong><?php _e('Total Logs:', 'checkstep-integration'); ?></strong>
                        <?php echo intval($stats['total']); ?>
                    </div>
                    <div>
                        <strong><?php _e('Errors:', 'checkstep-integration'); ?></strong>
                        <span style="color: #dc3232;"><?php echo isset($stats['by_level']['error']) ? intval($stats['by_level']['error']->count) : 0; ?></span>
                    </div>
                    <div>
                        <strong><?php _e('Warnings:', 'checkstep-integration'); ?></strong>
                        <span style="color: #ffb900;"><?php echo isset($stats['by_level']['warning']) ? intval($stats['by_level']['warning']->count) : 0; ?></span>
                    </div>
                    <div>
                        <strong><?php _e('Hook Triggers:', 'checkstep-integration'); ?></strong>
                        <?php echo isset($stats['by_type']['hook_trigger']) ? intval($stats['by_type']['hook_trigger']->count) : 0; ?>
                    </div>
                    <div>
                        <strong><?php _e('API Calls:', 'checkstep-integration'); ?></strong>
                        <?php echo isset($stats['by_type']['api_call']) ? intval($stats['by_type']['api_call']->count) : 0; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($stats['recent_hooks'])): ?>
            <div class="card">
                <h3><?php _e('Recent Hook Activity', 'checkstep-integration'); ?></h3>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th><?php _e('Hook Name', 'checkstep-integration'); ?></th>
                            <th><?php _e('Times Fired', 'checkstep-integration'); ?></th>
                            <th><?php _e('Last Fired', 'checkstep-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['recent_hooks'] as $hook): ?>
                        <tr>
                            <td><code><?php echo esc_html($hook->hook_name); ?></code></td>
                            <td><?php echo intval($hook->count); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($hook->last_fired))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="card">
                <h3><?php _e('Log Filters', 'checkstep-integration'); ?></h3>
                <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="page" value="checkstep-settings">
                    <input type="hidden" name="tab" value="logs">
                    
                    <div>
                        <label for="log-level"><?php _e('Level:', 'checkstep-integration'); ?></label><br>
                        <select name="level" id="log-level">
                            <option value=""><?php _e('All Levels', 'checkstep-integration'); ?></option>
                            <option value="error" <?php selected($log_level, 'error'); ?>><?php _e('Error', 'checkstep-integration'); ?></option>
                            <option value="warning" <?php selected($log_level, 'warning'); ?>><?php _e('Warning', 'checkstep-integration'); ?></option>
                            <option value="info" <?php selected($log_level, 'info'); ?>><?php _e('Info', 'checkstep-integration'); ?></option>
                            <option value="debug" <?php selected($log_level, 'debug'); ?>><?php _e('Debug', 'checkstep-integration'); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="log-type"><?php _e('Type:', 'checkstep-integration'); ?></label><br>
                        <select name="log_type" id="log-type">
                            <option value=""><?php _e('All Types', 'checkstep-integration'); ?></option>
                            <option value="hook_trigger" <?php selected($log_type, 'hook_trigger'); ?>><?php _e('Hook Triggers', 'checkstep-integration'); ?></option>
                            <option value="api_call" <?php selected($log_type, 'api_call'); ?>><?php _e('API Calls', 'checkstep-integration'); ?></option>
                            <option value="api_response" <?php selected($log_type, 'api_response'); ?>><?php _e('API Responses', 'checkstep-integration'); ?></option>
                            <option value="general" <?php selected($log_type, 'general'); ?>><?php _e('General', 'checkstep-integration'); ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="log-search"><?php _e('Search:', 'checkstep-integration'); ?></label><br>
                        <input type="text" name="s" id="log-search" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search logs...', 'checkstep-integration'); ?>">
                    </div>
                    
                    <div>
                        <button type="submit" class="button"><?php _e('Filter', 'checkstep-integration'); ?></button>
                        <a href="?page=checkstep-settings&tab=logs" class="button"><?php _e('Reset', 'checkstep-integration'); ?></a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;"><?php _e('Log Entries', 'checkstep-integration'); ?></h3>
                    <div>
                        <button type="button" class="button" id="refresh-logs" onclick="location.reload();">
                            <?php _e('Refresh', 'checkstep-integration'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="clear-logs" style="color: #dc3232;">
                            <?php _e('Clear All Logs', 'checkstep-integration'); ?>
                        </button>
                    </div>
                </div>

                <?php if (empty($logs)): ?>
                    <p><?php _e('No logs found.', 'checkstep-integration'); ?></p>
                <?php else: ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th style="width: 150px;"><?php _e('Time', 'checkstep-integration'); ?></th>
                                <th style="width: 80px;"><?php _e('Level', 'checkstep-integration'); ?></th>
                                <th style="width: 100px;"><?php _e('Type', 'checkstep-integration'); ?></th>
                                <th><?php _e('Message', 'checkstep-integration'); ?></th>
                                <th style="width: 150px;"><?php _e('Hook/Details', 'checkstep-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                                <td>
                                    <?php
                                    $level_colors = array(
                                        'error' => '#dc3232',
                                        'warning' => '#ffb900',
                                        'info' => '#0073aa',
                                        'debug' => '#666'
                                    );
                                    $color = isset($level_colors[$log->level]) ? $level_colors[$log->level] : '#666';
                                    ?>
                                    <span style="color: <?php echo esc_attr($color); ?>; font-weight: bold;">
                                        <?php echo esc_html(strtoupper($log->level)); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="log-type-badge" style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <?php echo esc_html($log->log_type); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log->message); ?></td>
                                <td>
                                    <?php if ($log->hook_name): ?>
                                        <code style="font-size: 11px;"><?php echo esc_html($log->hook_name); ?></code>
                                    <?php elseif ($log->context): ?>
                                        <button type="button" class="button button-small toggle-context" data-context="<?php echo esc_attr($log->context); ?>">
                                            <?php _e('View', 'checkstep-integration'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($log->context && !$log->hook_name): ?>
                            <tr class="context-row" style="display: none;">
                                <td colspan="5">
                                    <pre style="background: #f5f5f5; padding: 10px; margin: 0; overflow-x: auto; font-size: 11px;"><?php 
                                        echo esc_html(json_encode(json_decode($log->context), JSON_PRETTY_PRINT)); 
                                    ?></pre>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(__('%d items', 'checkstep-integration'), $total_logs); ?>
                            </span>
                            <span class="pagination-links">
                                <?php if ($paged > 1): ?>
                                    <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1)); ?>">
                                        &lsaquo;
                                    </a>
                                <?php endif; ?>
                                <span class="paging-input">
                                    <?php echo $paged; ?> / <?php echo $total_pages; ?>
                                </span>
                                <?php if ($paged < $total_pages): ?>
                                    <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $paged + 1)); ?>">
                                        &rsaquo;
                                    </a>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Toggle context details
                $('.toggle-context').on('click', function() {
                    $(this).closest('tr').next('.context-row').toggle();
                });

                // Clear logs
                $('#clear-logs').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to clear all logs? This action cannot be undone.', 'checkstep-integration'); ?>')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'checkstep_clear_logs',
                                nonce: '<?php echo wp_create_nonce('checkstep-admin'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert(response.data.message || '<?php _e('Failed to clear logs', 'checkstep-integration'); ?>');
                                }
                            },
                            error: function() {
                                alert('<?php _e('Failed to clear logs', 'checkstep-integration'); ?>');
                            }
                        });
                    }
                });
            });
            </script>
        <?php endif; ?>
    </div>
</div>
