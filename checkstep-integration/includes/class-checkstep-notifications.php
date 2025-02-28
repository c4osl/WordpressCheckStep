<?php
/**
 * Notifications Handler Class
 */
class CheckStep_Notifications {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('checkstep_decision_handled', array($this, 'send_notification'));
    }

    /**
     * Send notification to user
     */
    public function send_notification($decision_data) {
        $content_id = $decision_data['content_id'];
        $action = $decision_data['action'];
        $reason = $decision_data['reason'];

        $post = get_post($content_id);
        if (!$post) {
            return;
        }

        $user_id = $post->post_author;
        $message = $this->get_notification_message($action, $reason);
        $appeal_link = $this->get_appeal_link($decision_data);

        $this->send_buddyboss_notification($user_id, $message, $appeal_link);
    }

    /**
     * Get notification message
     */
    private function get_notification_message($action, $reason) {
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

        return isset($messages[$action]) ? $messages[$action] : '';
    }

    /**
     * Get appeal link
     */
    private function get_appeal_link($decision_data) {
        $appeal_url = get_option('checkstep_appeal_url');
        if (!$appeal_url) {
            return '';
        }

        return add_query_arg(array(
            'decision_id' => $decision_data['decision_id'],
            'content_id' => $decision_data['content_id'],
        ), $appeal_url);
    }

    /**
     * Send BuddyBoss notification
     */
    private function send_buddyboss_notification($user_id, $message, $appeal_link) {
        if (!function_exists('bp_notifications_add_notification')) {
            return;
        }

        $notification_content = $message;
        if ($appeal_link) {
            $notification_content .= "\n\n" . sprintf(
                __('If you believe this decision was made in error, you can <a href="%s">appeal here</a>.', 'checkstep-integration'),
                esc_url($appeal_link)
            );
        }

        bp_notifications_add_notification(array(
            'user_id' => $user_id,
            'item_id' => 0,
            'secondary_item_id' => 0,
            'component_name' => 'checkstep',
            'component_action' => 'moderation_decision',
            'date_notified' => bp_core_current_time(),
            'is_new' => 1,
        ));

        if (function_exists('messages_new_message')) {
            messages_new_message(array(
                'sender_id' => bp_get_loggedin_user_id(),
                'recipients' => array($user_id),
                'subject' => __('Content Moderation Notice', 'checkstep-integration'),
                'content' => $notification_content,
            ));
        }
    }
}
