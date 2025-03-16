<?php
/**
 * CheckStep Moderation Queue Dashboard
 *
 * @package CheckStep_Integration
 * @subpackage Admin
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

global $wpdb;
?>

<div class="wrap checkstep-dashboard">
    <h1><?php _e('CheckStep Moderation Queue', 'checkstep-integration'); ?></h1>

    <!-- Queue Statistics -->
    <div class="queue-stats">
        <div class="stat-box">
            <h3><?php _e('Pending Review', 'checkstep-integration'); ?></h3>
            <div class="stat-value">
                <?php
                $pending_count = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}checkstep_queue WHERE status = 'pending'"
                );
                echo intval($pending_count);
                ?>
            </div>
        </div>
        <div class="stat-box">
            <h3><?php _e('Under Moderation', 'checkstep-integration'); ?></h3>
            <div class="stat-value">
                <?php
                $processing_count = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}checkstep_queue WHERE status = 'processing'"
                );
                echo intval($processing_count);
                ?>
            </div>
        </div>
        <div class="stat-box">
            <h3><?php _e('Completed Today', 'checkstep-integration'); ?></h3>
            <div class="stat-value">
                <?php
                $completed_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}checkstep_queue 
                     WHERE status = 'completed' 
                     AND processed_at >= %s",
                    date('Y-m-d 00:00:00')
                ));
                echo intval($completed_count);
                ?>
            </div>
        </div>
    </div>

    <!-- Queue Filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" id="filter-queue-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
                <select name="status" id="filter-by-status">
                    <option value=""><?php _e('All Statuses', 'checkstep-integration'); ?></option>
                    <option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>>
                        <?php _e('Pending', 'checkstep-integration'); ?>
                    </option>
                    <option value="processing" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'processing'); ?>>
                        <?php _e('Processing', 'checkstep-integration'); ?>
                    </option>
                    <option value="completed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'completed'); ?>>
                        <?php _e('Completed', 'checkstep-integration'); ?>
                    </option>
                </select>
                <select name="content_type" id="filter-by-type">
                    <option value=""><?php _e('All Content Types', 'checkstep-integration'); ?></option>
                    <option value="post" <?php selected(isset($_GET['content_type']) ? $_GET['content_type'] : '', 'post'); ?>>
                        <?php _e('Posts', 'checkstep-integration'); ?>
                    </option>
                    <option value="activity" <?php selected(isset($_GET['content_type']) ? $_GET['content_type'] : '', 'activity'); ?>>
                        <?php _e('Activity', 'checkstep-integration'); ?>
                    </option>
                    <option value="media" <?php selected(isset($_GET['content_type']) ? $_GET['content_type'] : '', 'media'); ?>>
                        <?php _e('Media', 'checkstep-integration'); ?>
                    </option>
                </select>
                <?php submit_button(__('Filter', 'checkstep-integration'), 'secondary', 'filter_action', false); ?>
            </form>
        </div>
    </div>

    <!-- Queue Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php _e('ID', 'checkstep-integration'); ?></th>
                <th scope="col"><?php _e('Content Type', 'checkstep-integration'); ?></th>
                <th scope="col"><?php _e('Content', 'checkstep-integration'); ?></th>
                <th scope="col"><?php _e('Author', 'checkstep-integration'); ?></th>
                <th scope="col"><?php _e('Status', 'checkstep-integration'); ?></th>
                <th scope="col"><?php _e('Submitted', 'checkstep-integration'); ?></th>
                <th scope="col"><?php _e('Actions', 'checkstep-integration'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $items_per_page = 20;
            $offset = ($page - 1) * $items_per_page;

            // Build query conditions
            $where = array('1=1');
            $values = array();

            if (!empty($_GET['status'])) {
                $where[] = 'status = %s';
                $values[] = sanitize_text_field($_GET['status']);
            }

            if (!empty($_GET['content_type'])) {
                $where[] = 'content_type = %s';
                $values[] = sanitize_text_field($_GET['content_type']);
            }

            $where_clause = implode(' AND ', $where);

            // Get total items for pagination
            $total_items = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}checkstep_queue WHERE $where_clause",
                    $values
                )
            );

            // Get queue items
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}checkstep_queue 
                     WHERE $where_clause 
                     ORDER BY created_at DESC 
                     LIMIT %d OFFSET %d",
                    array_merge($values, array($items_per_page, $offset))
                )
            );

            if ($items) {
                foreach ($items as $item) {
                    $author_id = get_post_field('post_author', $item->content_id);
                    $author = get_userdata($author_id);
                    $content_preview = '';

                    switch ($item->content_type) {
                        case 'post':
                            $post = get_post($item->content_id);
                            $content_preview = wp_trim_words($post->post_content, 10);
                            break;
                        case 'activity':
                            if (function_exists('bp_activity_get_specific')) {
                                $activity = bp_activity_get_specific(array('activity_ids' => array($item->content_id)));
                                if (!empty($activity['activities'])) {
                                    $content_preview = wp_trim_words($activity['activities'][0]->content, 10);
                                }
                            }
                            break;
                        case 'media':
                            if (function_exists('bp_get_media')) {
                                $media = bp_get_media($item->content_id);
                                if ($media) {
                                    $content_preview = $media->title;
                                }
                            }
                            break;
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html($item->id); ?></td>
                        <td><?php echo esc_html($item->content_type); ?></td>
                        <td><?php echo esc_html($content_preview); ?></td>
                        <td><?php echo $author ? esc_html($author->display_name) : __('Unknown', 'checkstep-integration'); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($item->status); ?>">
                                <?php echo esc_html(ucfirst($item->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(human_time_diff(strtotime($item->created_at), current_time('timestamp'))); ?> ago</td>
                        <td>
                            <?php if ($item->status === 'pending'): ?>
                                <button type="button" 
                                        class="button button-small queue-action-button approve"
                                        data-item-id="<?php echo esc_attr($item->id); ?>"
                                        data-action="approve">
                                    <?php _e('Approve', 'checkstep-integration'); ?>
                                </button>
                                <button type="button"
                                        class="button button-small queue-action-button reject"
                                        data-item-id="<?php echo esc_attr($item->id); ?>"
                                        data-action="reject">
                                    <?php _e('Reject', 'checkstep-integration'); ?>
                                </button>
                            <?php elseif ($item->status === 'rejected'): ?>
                                <button type="button"
                                        class="button button-small queue-action-button requeue"
                                        data-item-id="<?php echo esc_attr($item->id); ?>"
                                        data-action="requeue">
                                    <?php _e('Requeue', 'checkstep-integration'); ?>
                                </button>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=checkstep-queue&action=view&id=' . $item->id)); ?>" 
                               class="button button-small">
                                <?php _e('View Details', 'checkstep-integration'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="7"><?php _e('No items found in queue.', 'checkstep-integration'); ?></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php
    $total_pages = ceil($total_items / $items_per_page);
    if ($total_pages > 1) {
        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $page
        ));
        echo '</div>';
        echo '</div>';
    }
    ?>
</div>