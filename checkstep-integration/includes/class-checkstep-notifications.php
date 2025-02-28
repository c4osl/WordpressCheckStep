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
            add_action('checkstep_decision_handled', array($this, 'send_notification'));
            CheckStep_Logger::debug('Notification hooks initialized');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to initialize notification hooks', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Send notification to user
     *
     * Creates and sends notifications about moderation decisions to affected users.
     *
     * @since 1.0.0
     * @param array $decision_data Moderation decision data including content ID and action
     */
    public function send_notification($decision_data) {
        try {
            $content_id = $decision_data['content_id'];
            $action = $decision_data['action'];
            $reason = $decision_data['reason'];

            $post = get_post($content_id);
            if (!$post) {
                CheckStep_Logger::error('Post not found for notification', array(
                    'content_id' => $content_id
                ));
                return;
            }

            $user_id = $post->post_author;
            $message = $this->get_notification_message($action, $reason);
            $appeal_link = $this->get_appeal_link($decision_data);

            if (empty($message)) {
                CheckStep_Logger::error('Empty notification message', array(
                    'action' => $action,
                    'reason' => $reason
                ));
                return;
            }

            $this->send_buddyboss_notification($user_id, $message, $appeal_link);

            CheckStep_Logger::info('Notification sent successfully', array(
                'user_id' => $user_id,
                'action' => $action,
                'content_id' => $content_id
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to send notification', array(
                'error' => $e->getMessage(),
                'decision_data' => $decision_data
            ));
        }
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
                'decision_id' => $decision_data['decision_id'],
                'content_id' => $decision_data['content_id'],
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
     */
    private function send_buddyboss_notification($user_id, $message, $appeal_link) {
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
                'item_id' => 0,
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
                $message_id = messages_new_message(array(
                    'sender_id' => bp_get_loggedin_user_id(),
                    'recipients' => array($user_id),
                    'subject' => __('Content Moderation Notice', 'checkstep-integration'),
                    'content' => $notification_content,
                ));

                if (!$message_id) {
                    throw new Exception('Failed to create BuddyBoss message');
                }

                CheckStep_Logger::debug('BuddyBoss message sent', array(
                    'message_id' => $message_id,
                    'user_id' => $user_id
                ));
            }

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to send BuddyBoss notification', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
        }
    }
}