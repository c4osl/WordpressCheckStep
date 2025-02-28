<?php
/**
 * Moderation Handler Class
 */
class CheckStep_Moderation {
    private $api;

    /**
     * Constructor
     */
    public function __construct($api) {
        $this->api = $api;
        $this->setup_hooks();
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        add_action('rest_api_init', array($this, 'register_webhooks'));
        add_action('checkstep_handle_decision', array($this, 'handle_moderation_decision'));
    }

    /**
     * Register webhook endpoints
     */
    public function register_webhooks() {
        register_rest_route('checkstep/v1', '/decisions', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_decision_webhook'),
            'permission_callback' => array($this, 'verify_webhook'),
        ));
    }

    /**
     * Verify webhook request
     */
    public function verify_webhook($request) {
        $signature = $request->get_header('X-CheckStep-Signature');
        $webhook_secret = get_option('checkstep_webhook_secret');

        if (!$signature || !$webhook_secret) {
            return false;
        }

        $payload = $request->get_body();
        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Handle incoming decision webhook
     */
    public function handle_decision_webhook($request) {
        $payload = $request->get_json_params();

        if (!isset($payload['decision_id']) || !isset($payload['content_id'])) {
            return new WP_Error('invalid_payload', 'Invalid decision payload', array('status' => 400));
        }

        wp_schedule_single_event(time(), 'checkstep_handle_decision', array($payload));

        return new WP_REST_Response(array('status' => 'queued'), 200);
    }

    /**
     * Handle moderation decision
     */
    public function handle_moderation_decision($decision_data) {
        $content_id = $decision_data['content_id'];
        $action = $decision_data['action'];
        $reason = $decision_data['reason'];

        switch ($action) {
            case 'delete':
                $this->delete_content($content_id);
                break;

            case 'hide':
                $this->hide_content($content_id);
                break;

            case 'warn':
                $this->add_content_warning($content_id, $reason);
                break;

            case 'ban_user':
                $this->ban_user($content_id);
                break;
        }

        do_action('checkstep_decision_handled', $decision_data);
    }

    /**
     * Delete content
     */
    private function delete_content($content_id) {
        if (is_numeric($content_id)) {
            wp_delete_post($content_id, true);
        }
    }

    /**
     * Hide content
     */
    private function hide_content($content_id) {
        wp_update_post(array(
            'ID' => $content_id,
            'post_status' => 'private'
        ));
    }

    /**
     * Add content warning
     */
    private function add_content_warning($content_id, $warning) {
        wp_set_object_terms($content_id, $warning, 'content-warning', true);
    }

    /**
     * Ban user
     */
    private function ban_user($user_id) {
        if (function_exists('bp_is_active')) {
            bp_moderation_add(array(
                'user_id' => $user_id,
                'type' => 'user',
            ));
        }
    }
}
