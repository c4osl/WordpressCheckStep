<?php
/**
 * CheckStep Webhook Handler
 *
 * Handles incoming webhooks from CheckStep and processes moderation decisions
 *
 * @package CheckStep_Integration
 * @since 1.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

class CheckStep_Webhook_Handler {

    /**
     * API client instance
     *
     * @var CheckStep_API
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new CheckStep_API();

        // Register webhook endpoint
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }

    /**
     * Register the webhook endpoint with WordPress REST API
     */
    public function register_webhook_endpoint() {
        register_rest_route('checkstep/v1', '/decisions', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature')
        ));
    }

    /**
     * Verify webhook signature
     *
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error True if signature valid, WP_Error otherwise
     */
    public function verify_webhook_signature($request) {
        $signature = $request->get_header('X-CheckStep-Signature');
        if (empty($signature)) {
            return new WP_Error(
                'invalid_signature',
                'Missing webhook signature',
                array('status' => 401)
            );
        }

        $payload = $request->get_body();
        if (!$this->api->validate_webhook_signature($payload, $signature)) {
            return new WP_Error(
                'invalid_signature',
                'Invalid webhook signature',
                array('status' => 401)
            );
        }

        return true;
    }

    /**
     * Handle incoming webhook
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function handle_webhook($request) {
        try {
            $payload = $request->get_json_params();
            $event_type = $payload['event_type'] ?? '';

            switch ($event_type) {
                case CheckStep_API::EVENT_DECISION_TAKEN:
                    $response = $this->handle_moderation_decision($payload);
                    break;

                case CheckStep_API::EVENT_INCIDENT_CLOSED:
                    $response = $this->handle_incident_closure($payload);
                    break;

                default:
                    throw new Exception("Unsupported event type: {$event_type}");
            }

            return new WP_REST_Response($response, 200);

        } catch (Exception $e) {
            return new WP_REST_Response(
                array('error' => $e->getMessage()),
                500
            );
        }
    }

    /**
     * Handle moderation decision
     *
     * @param array $payload Webhook payload
     * @return array Response data
     */
    private function handle_moderation_decision($payload) {
        $content_id = $payload['content_id'] ?? '';
        $action = $payload['action'] ?? '';
        $reason = $payload['reason'] ?? '';

        if (empty($content_id) || empty($action)) {
            throw new Exception('Missing required fields: content_id or action');
        }

        // Map CheckStep action to BuddyBoss moderation action
        switch ($action) {
            case 'delete':
            case 'hide':
                $this->unpublish_content($content_id);
                break;

            case 'warn':
                $this->add_content_warning($content_id, $reason);
                break;

            case 'ban_user':
                $this->suspend_user($content_id);
                break;

            default:
                throw new Exception("Unsupported action: {$action}");
        }

        return array(
            'status' => 'success',
            'message' => "Moderation action '{$action}' applied successfully"
        );
    }

    /**
     * Handle incident closure
     *
     * @param array $payload Webhook payload
     * @return array Response data
     */
    private function handle_incident_closure($payload) {
        $incident_id = $payload['incident_id'] ?? '';
        $content_id = $payload['content_id'] ?? '';
        $resolution = $payload['resolution'] ?? '';

        if (empty($incident_id) || empty($content_id)) {
            throw new Exception('Missing required fields: incident_id or content_id');
        }

        // Notify user about incident resolution
        $this->notify_user_about_resolution($content_id, $resolution);

        return array(
            'status' => 'success',
            'message' => 'Incident closure processed successfully'
        );
    }

    /**
     * Unpublish content using BuddyBoss moderation system
     *
     * @param string|int $content_id Content ID
     */
    private function unpublish_content($content_id) {
        $content_type = $this->determine_content_type($content_id);
        
        // Use BuddyBoss's moderation system
        switch ($content_type) {
            case 'post':
                bp_moderation_hide(array(
                    'content_id' => $content_id,
                    'content_type' => BP_Moderation_Posts::$moderation_type
                ));
                break;

            case 'media':
                bp_moderation_hide(array(
                    'content_id' => $content_id,
                    'content_type' => BP_Moderation_Media::$moderation_type
                ));
                break;

            case 'video':
                bp_moderation_hide(array(
                    'content_id' => $content_id,
                    'content_type' => BP_Moderation_Video::$moderation_type
                ));
                break;

            default:
                throw new Exception("Unsupported content type: {$content_type}");
        }
    }

    /**
     * Add warning to content
     *
     * @param string|int $content_id Content ID
     * @param string $reason Warning reason
     */
    private function add_content_warning($content_id, $reason) {
        // Add content warning using custom taxonomy
        wp_set_object_terms($content_id, 'warning', 'content_warning', true);
        
        // Store warning reason as meta
        update_post_meta($content_id, '_content_warning_reason', sanitize_text_field($reason));
    }

    /**
     * Suspend user using BuddyBoss moderation
     *
     * @param string|int $user_id User ID
     */
    private function suspend_user($user_id) {
        bp_moderation_hide(array(
            'content_id' => $user_id,
            'content_type' => BP_Moderation_Members::$moderation_type
        ));
    }

    /**
     * Notify user about incident resolution
     *
     * @param string|int $content_id Content ID
     * @param string $resolution Resolution details
     */
    private function notify_user_about_resolution($content_id, $resolution) {
        $user_id = $this->get_content_author($content_id);
        if ($user_id) {
            bp_notifications_add_notification(array(
                'user_id' => $user_id,
                'item_id' => $content_id,
                'component_name' => 'checkstep',
                'component_action' => 'content_moderated',
                'date_notified' => bp_core_current_time(),
                'is_new' => 1,
                'allow_duplicate' => false
            ));
        }
    }

    /**
     * Determine content type from ID
     *
     * @param string|int $content_id Content ID
     * @return string Content type
     */
    private function determine_content_type($content_id) {
        // Check post type
        $post_type = get_post_type($content_id);
        if ($post_type) {
            return $post_type;
        }

        // Check media type
        if (function_exists('bp_get_media') && bp_get_media($content_id)) {
            return 'media';
        }

        // Check video type
        if (function_exists('bp_get_video') && bp_get_video($content_id)) {
            return 'video';
        }

        throw new Exception("Unable to determine content type for ID: {$content_id}");
    }

    /**
     * Get content author ID
     *
     * @param string|int $content_id Content ID
     * @return int|false Author ID or false if not found
     */
    private function get_content_author($content_id) {
        $post = get_post($content_id);
        if ($post) {
            return $post->post_author;
        }

        // Check media/video author
        if (function_exists('bp_get_media')) {
            $media = bp_get_media($content_id);
            if ($media) {
                return $media->user_id;
            }
        }

        if (function_exists('bp_get_video')) {
            $video = bp_get_video($content_id);
            if ($video) {
                return $video->user_id;
            }
        }

        return false;
    }
}
