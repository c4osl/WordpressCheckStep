<?php
/**
 * Notifications Handler Class
 *
 * Manages BuddyBoss notifications and messages for moderation actions.
 * Provides user feedback for content moderation decisions through
 * the BuddyBoss notification system and private messages.
 *
 * @package CheckStep_Integration
 * @subpackage Notifications
 * @since 1.0.0
 */

/**
 * Class CheckStep_Notifications
 *
 * Handles the creation and delivery of moderation-related notifications
 * to users through BuddyBoss platform's notification system.
 *
 * @since 1.0.0
 */
class CheckStep_Notifications {
    /**
     * Constructor
     *
     * Sets up notification hooks for moderation decisions.
     *
     * @since 1.0.0
     */
    public function __construct() {
        try {
            add_filter('bp_notifications_get_registered_components', array($this, 'register_notification_component'));
            add_filter('bp_notifications_get_notifications_for_user', array($this, 'format_notification'), 10, 8);
            add_action('checkstep_decision_handled', array($this, 'send_notification'));
            CheckStep_Logger::info('Notification hooks initialized');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to initialize notification hooks', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Register CheckStep as a BuddyBoss notification component
     *
     * This is required for BuddyBoss to recognize and display CheckStep notifications.
     *
     * @since 1.0.21
     * @param array $components Registered notification components
     * @return array Modified components array
     */
    public function register_notification_component($components) {
        if (!in_array('checkstep', $components)) {
            $components[] = 'checkstep';
        }
        return $components;
    }

    /**
     * Format notification for display
     *
     * Provides the notification text and link for BuddyBoss to display.
     * This filter is called for every notification - we only modify our own.
     *
     * @since 1.0.21
     * @param string $content           The notification content (deprecated, pass through for non-checkstep)
     * @param int    $item_id           The item ID
     * @param int    $secondary_item_id Secondary item ID
     * @param int    $total_items       Total number of notifications
     * @param string $format            Output format (string or object)
     * @param string $component_action  The component action name (use this for checks)
     * @param string $component_name    The component name
     * @param int    $notification_id   The notification ID
     * @return string|array Formatted notification or original content
     */
    public function format_notification($content, $item_id, $secondary_item_id, $total_items, $format, $component_action, $component_name, $notification_id) {
        if ($component_name !== 'checkstep' || $component_action !== 'moderation_decision') {
            return $content;
        }

        $text = __('You have a content moderation notice', 'checkstep-integration');
        $link = function_exists('bp_get_notifications_unread_permalink') 
            ? bp_get_notifications_unread_permalink() 
            : home_url();

        if ('string' === $format) {
            return '<a href="' . esc_url($link) . '">' . esc_html($text) . '</a>';
        }

        return array(
            'text' => $text,
            'link' => $link,
        );
    }

    /**
     * Send notification to user
     *
     * Creates and sends notifications about moderation decisions to affected users.
     * Handles different content types including WordPress posts, BuddyBoss activities,
     * forum topics/replies, and user profiles.
     *
     * @since 1.0.0
     * @param array $decision_data Moderation decision data including content ID, content_type, and action
     */
    public function send_notification($decision_data) {
        try {
            $content_id = isset($decision_data['content_id']) ? $decision_data['content_id'] : 0;
            $action = isset($decision_data['action']) ? $decision_data['action'] : '';
            $reason = isset($decision_data['reason']) ? $decision_data['reason'] : '';
            $content_type = isset($decision_data['content_type']) ? $decision_data['content_type'] : 'post';

            $user_id = $this->get_content_author($content_id, $content_type);
            
            if (!$user_id) {
                CheckStep_Logger::error('Could not determine content author for notification', array(
                    'content_id' => $content_id,
                    'content_type' => $content_type
                ));
                return;
            }

            $message = $this->get_notification_message($action, $reason);
            $appeal_link = $this->get_appeal_link($decision_data);

            if (empty($message)) {
                CheckStep_Logger::error('Empty notification message', array(
                    'action' => $action,
                    'reason' => $reason
                ));
                return;
            }

            $this->send_buddyboss_notification($user_id, $message, $appeal_link, $content_id);

            CheckStep_Logger::info('Notification sent successfully', array(
                'user_id' => $user_id,
                'action' => $action,
                'content_id' => $content_id,
                'content_type' => $content_type
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to send notification', array(
                'error' => $e->getMessage(),
                'decision_data' => $decision_data
            ));
        }
    }

    /**
     * Get content author based on content type
     *
     * Retrieves the user ID of the content author for different content types.
     *
     * @since 1.0.21
     * @access private
     * @param int    $content_id   The content ID
     * @param string $content_type The type of content (post, activity, forum_topic, forum_reply, user_profile, message)
     * @return int User ID of the content author, or 0 if not found
     */
    private function get_content_author($content_id, $content_type) {
        $content_id = absint($content_id);
        
        if (!$content_id) {
            return 0;
        }

        switch ($content_type) {
            case 'activity':
                if (function_exists('bp_activity_get_specific')) {
                    $activity = bp_activity_get_specific(array('activity_ids' => array($content_id)));
                    if (!empty($activity['activities'][0])) {
                        return absint($activity['activities'][0]->user_id);
                    }
                }
                break;

            case 'forum_topic':
                if (function_exists('bbp_get_topic_author_id')) {
                    return absint(bbp_get_topic_author_id($content_id));
                }
                break;

            case 'forum_reply':
                if (function_exists('bbp_get_reply_author_id')) {
                    return absint(bbp_get_reply_author_id($content_id));
                }
                break;

            case 'user_profile':
                return $content_id;

            case 'message':
                if (class_exists('BP_Messages_Message')) {
                    $message = new BP_Messages_Message($content_id);
                    if ($message && !empty($message->sender_id)) {
                        return absint($message->sender_id);
                    }
                }
                if (class_exists('BP_Messages_Thread') && function_exists('messages_get_message_thread_id')) {
                    $thread = new BP_Messages_Thread($content_id);
                    if ($thread && !empty($thread->messages[0]->sender_id)) {
                        return absint($thread->messages[0]->sender_id);
                    }
                }
                break;

            case 'post':
            case 'blog_post':
            default:
                $post = get_post($content_id);
                if ($post) {
                    return absint($post->post_author);
                }
                break;
        }

        return 0;
    }

    /**
     * Get notification message
     *
     * Generates localized notification messages based on moderation action type.
     *
     * @since 1.0.0
     * @access private
     * @param string $action Moderation action (delete, hide, warn, ban_user)
     * @param string $reason Reason for the moderation action
     * @return string Formatted notification message
     */
    private function get_notification_message($action, $reason) {
        try {
            $messages = array(
                'delete' => sprintf(
                    __('Your content has been removed due to: %s', 'checkstep-integration'),
                    $reason
                ),
                'hide' => sprintf(
                    __('Your content has been hidden pending review due to: %s', 'checkstep-integration'),
                    $reason
                ),
                'warn' => sprintf(
                    __('A content warning has been added to your post: %s', 'checkstep-integration'),
                    $reason
                ),
                'ban_user' => __('Your account has been suspended due to multiple violations.', 'checkstep-integration'),
            );

            if (!isset($messages[$action])) {
                CheckStep_Logger::warning('Unknown moderation action type', array(
                    'action' => $action
                ));
                return '';
            }

            return $messages[$action];

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to generate notification message', array(
                'error' => $e->getMessage(),
                'action' => $action
            ));
            return '';
        }
    }

    /**
     * Get appeal link
     *
     * Generates a URL for users to appeal moderation decisions.
     *
     * @since 1.0.0
     * @access private
     * @param array $decision_data Decision data containing ID and content information
     * @return string Appeal URL or empty string if appeals are disabled
     */
    private function get_appeal_link($decision_data) {
        try {
            $appeal_url = get_option('checkstep_appeal_url');
            if (!$appeal_url) {
                CheckStep_Logger::debug('Appeals disabled - no appeal URL configured');
                return '';
            }

            return add_query_arg(array(
                'decision_id' => isset($decision_data['decision_id']) ? $decision_data['decision_id'] : '',
                'content_id' => isset($decision_data['content_id']) ? $decision_data['content_id'] : '',
            ), $appeal_url);

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to generate appeal link', array(
                'error' => $e->getMessage(),
                'decision_data' => $decision_data
            ));
            return '';
        }
    }

    /**
     * Get admin user ID for sending system messages
     *
     * Returns a valid sender ID for system-generated messages.
     * Uses the logged-in user if available, otherwise falls back to
     * site admin email or first administrator.
     *
     * @since 1.0.21
     * @access private
     * @return int Admin user ID
     */
    private function get_system_sender_id() {
        if (function_exists('bp_get_loggedin_user_id')) {
            $sender_id = bp_get_loggedin_user_id();
            if ($sender_id > 0) {
                return $sender_id;
            }
        }

        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $admin_user = get_user_by('email', $admin_email);
            if ($admin_user && $admin_user->ID > 0) {
                return absint($admin_user->ID);
            }
        }

        $admins = get_users(array(
            'role' => 'administrator',
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        if (!empty($admins)) {
            return absint($admins[0]->ID);
        }

        return 1;
    }

    /**
     * Send BuddyBoss notification
     *
     * Creates both a notification and a private message in BuddyBoss
     * to inform users about moderation decisions.
     *
     * @since 1.0.0
     * @access private
     * @param int    $user_id      User ID to notify
     * @param string $message      Notification message
     * @param string $appeal_link  Optional appeal link
     * @param int    $content_id   Content ID for reference
     */
    private function send_buddyboss_notification($user_id, $message, $appeal_link, $content_id = 0) {
        try {
            if (!function_exists('bp_notifications_add_notification')) {
                throw new Exception('BuddyBoss notifications component not available');
            }

            $notification_content = $message;
            if ($appeal_link) {
                $notification_content .= "\n\n" . sprintf(
                    __('If you believe this decision was made in error, you can <a href="%s">appeal here</a>.', 'checkstep-integration'),
                    esc_url($appeal_link)
                );
            }

            $notification_id = bp_notifications_add_notification(array(
                'user_id' => $user_id,
                'item_id' => absint($content_id),
                'secondary_item_id' => 0,
                'component_name' => 'checkstep',
                'component_action' => 'moderation_decision',
                'date_notified' => bp_core_current_time(),
                'is_new' => 1,
            ));

            if (!$notification_id) {
                throw new Exception('Failed to create BuddyBoss notification');
            }

            CheckStep_Logger::debug('BuddyBoss notification created', array(
                'notification_id' => $notification_id,
                'user_id' => $user_id
            ));

            if (function_exists('messages_new_message')) {
                $sender_id = $this->get_system_sender_id();
                
                $message_id = messages_new_message(array(
                    'sender_id' => $sender_id,
                    'recipients' => array($user_id),
                    'subject' => __('Content Moderation Notice', 'checkstep-integration'),
                    'content' => $notification_content,
                ));

                if (!$message_id) {
                    CheckStep_Logger::warning('Failed to create BuddyBoss message', array(
                        'user_id' => $user_id,
                        'sender_id' => $sender_id
                    ));
                } else {
                    CheckStep_Logger::debug('BuddyBoss message sent', array(
                        'message_id' => $message_id,
                        'user_id' => $user_id,
                        'sender_id' => $sender_id
                    ));
                }
            }

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to send BuddyBoss notification', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
        }
    }
}
