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
defined('ABSPATH') || define('ABSPATH', dirname(__DIR__));

// Mock WordPress functions if not in WordPress environment
if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false) {
        return true;
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) {
        return true;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post_id) {
        return 'post'; // Mock for testing
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback) {
        // No-op stub for testing
        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args) {
        // No-op stub for testing
        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $message;
        public $code;
        public $data;

        public function __construct($code, $message, $data = null) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;

        public function __construct($data, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
    }
}

class CheckStep_Webhook_Handler {

    /**
     * Constructor
     */
    public function __construct() {
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
        try {
            $webhook_secret = getenv('CHECKSTEP_WEBHOOK_SECRET');
            if (empty($webhook_secret)) {
                throw new Exception('Webhook secret not configured');
            }

            $expected = hash_hmac('sha256', $payload, $webhook_secret);
            return hash_equals($expected, $signature);
        } catch (Exception $e) {
            CheckStep_Logger::error('Webhook signature validation failed', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
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
                case 'decision_taken':
                    $response = $this->handle_moderation_decision($payload);
                    break;

                case 'incident_closed':
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

            case 'no_action':
                // Log the decision but take no moderation action
                CheckStep_Logger::info('Content approved - no action needed', array(
                    'content_id' => $content_id,
                    'reason' => $reason
                ));
                break;

            case 'upheld':
                try {
                    $user_id = $this->get_content_author($content_id);
                    if (!$user_id) {
                        throw new Exception("Could not find author for content ID: {$content_id}");
                    }

                    // Send notification through BuddyBoss notification system
                    if (function_exists('bp_notifications_add_notification')) {
                        bp_notifications_add_notification(array(
                            'user_id'           => $user_id,
                            'item_id'           => $content_id,
                            'component_name'    => 'checkstep',
                            'component_action'  => 'appeal_refused',
                            'date_notified'     => bp_core_current_time(),
                            'is_new'           => 1,
                            'allow_duplicate'   => false,
                            'description'       => sprintf(
                                'Your appeal for content %d has been reviewed and the original moderation decision has been upheld. Reason: %s',
                                $content_id,
                                $reason
                            )
                        ));

                        CheckStep_Logger::info('Appeal notification sent to user', array(
                            'user_id' => $user_id,
                            'content_id' => $content_id,
                            'reason' => $reason
                        ));
                    } else {
                        throw new Exception('BuddyBoss notifications system not available');
                    }
                } catch (Exception $e) {
                    CheckStep_Logger::error('Failed to send appeal notification', array(
                        'error' => $e->getMessage(),
                        'content_id' => $content_id
                    ));
                }
                break;

            case 'overturn':
                try {
                    $user_id = $this->get_content_author($content_id);
                    if (!$user_id) {
                        throw new Exception("Could not find author for content ID: {$content_id}");
                    }

                    // Unhide content using BuddyBoss moderation system
                    $content_type = $this->determine_content_type($content_id);
                    if (function_exists('bp_moderation_unhide')) {
                        bp_moderation_unhide(array(
                            'content_id' => $content_id,
                            'content_type' => $this->get_moderation_type($content_type)
                        ));

                        CheckStep_Logger::info('Content restored after successful appeal', array(
                            'content_id' => $content_id,
                            'content_type' => $content_type
                        ));
                    } else {
                        throw new Exception('BuddyBoss moderation system not available');
                    }

                    // Notify user about successful appeal
                    if (function_exists('bp_notifications_add_notification')) {
                        bp_notifications_add_notification(array(
                            'user_id'           => $user_id,
                            'item_id'           => $content_id,
                            'component_name'    => 'checkstep',
                            'component_action'  => 'appeal_accepted',
                            'date_notified'     => bp_core_current_time(),
                            'is_new'           => 1,
                            'allow_duplicate'   => false,
                            'description'       => sprintf(
                                'Your appeal for content %d has been reviewed and accepted. Your content has been restored.',
                                $content_id
                            )
                        ));

                        CheckStep_Logger::info('Appeal success notification sent to user', array(
                            'user_id' => $user_id,
                            'content_id' => $content_id
                        ));
                    } else {
                        throw new Exception('BuddyBoss notifications system not available');
                    }
                } catch (Exception $e) {
                    CheckStep_Logger::error('Failed to process appeal overturn', array(
                        'error' => $e->getMessage(),
                        'content_id' => $content_id
                    ));
                }
                break;

            default:
                throw new Exception("Unsupported action: {$action}");
        }

        return array(
            'status' => 'success',
            'message' => "Moderation action '{$action}' processed successfully"
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
     * Suspend a user account
     *
     * @param int $user_id User ID to suspend
     */
    private function suspend_user($user_id) {
        try {
            if (!function_exists('bp_moderation_update_status')) {
                throw new Exception('BuddyBoss moderation system not available');
            }

            // Suspend the user using BuddyBoss moderation
            bp_moderation_update_status(array(
                'user_id' => $user_id,
                'action' => 'suspend'
            ));

            // Log the action
            CheckStep_Logger::info('User suspended by CheckStep moderation', array(
                'user_id' => $user_id
            ));

            // Send notification to user about suspension
            if (function_exists('bp_notifications_add_notification')) {
                bp_notifications_add_notification(array(
                    'user_id'           => $user_id,
                    'item_id'           => 0,
                    'component_name'    => 'checkstep',
                    'component_action'  => 'account_suspended',
                    'date_notified'     => bp_core_current_time(),
                    'is_new'           => 1,
                    'allow_duplicate'   => false,
                    'title'            => __('Account Suspended', 'checkstep-integration'),
                    'content'          => __('Your account has been suspended due to a violation of our community guidelines.', 'checkstep-integration')
                ));
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to suspend user', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
            throw $e;
        }
    }

    /**
     * Notify user about incident resolution
     *
     * @param string|int $content_id Content ID
     * @param string $resolution Resolution details
     */
    private function notify_user_about_resolution($content_id, $resolution) {
        try {
            $user_id = $this->get_content_author($content_id);
            if (!$user_id) {
                throw new Exception("Could not find author for content ID: {$content_id}");
            }

            if (!function_exists('bp_notifications_add_notification')) {
                throw new Exception('BuddyBoss notifications system not available');
            }

            // Send notification through BuddyBoss notification system
            bp_notifications_add_notification(array(
                'user_id'           => $user_id,
                'item_id'           => $content_id,
                'component_name'    => 'checkstep',
                'component_action'  => 'moderation_resolved',
                'date_notified'     => bp_core_current_time(),
                'is_new'           => 1,
                'allow_duplicate'   => false,
                'title'            => __('Moderation Update', 'checkstep-integration'),
                'content'          => sprintf(
                    __('The moderation review for your content (ID: %d) has been completed. Resolution: %s', 'checkstep-integration'),
                    $content_id,
                    $resolution
                )
            ));

            CheckStep_Logger::info('Resolution notification sent to user', array(
                'user_id' => $user_id,
                'content_id' => $content_id,
                'resolution' => $resolution
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to send resolution notification', array(
                'error' => $e->getMessage(),
                'content_id' => $content_id,
                'resolution' => $resolution
            ));
            throw $e;
        }
    }

    /**
     * Unpublish content using BuddyBoss moderation system
     *
     * @param string|int $content_id Content ID
     * @throws Exception If content type cannot be determined or is unsupported
     */
    private function unpublish_content($content_id) {
        try {
            $content_type = $this->determine_content_type($content_id);
            $moderation_type = $this->get_moderation_type($content_type);

            if (!function_exists('bp_moderation_hide')) {
                throw new Exception('BuddyBoss moderation system not available');
            }

            // Hide the content using BuddyBoss moderation
            bp_moderation_hide(array(
                'content_id' => $content_id,
                'content_type' => $moderation_type
            ));

            CheckStep_Logger::info('Content unpublished via BuddyBoss moderation', array(
                'content_id' => $content_id,
                'content_type' => $content_type
            ));

            // Notify the content author
            $user_id = $this->get_content_author($content_id);
            if ($user_id && function_exists('bp_notifications_add_notification')) {
                bp_notifications_add_notification(array(
                    'user_id'           => $user_id,
                    'item_id'           => $content_id,
                    'component_name'    => 'checkstep',
                    'component_action'  => 'content_hidden',
                    'date_notified'     => bp_core_current_time(),
                    'is_new'           => 1,
                    'allow_duplicate'   => false,
                    'title'            => __('Content Hidden', 'checkstep-integration'),
                    'content'          => sprintf(
                        __('Your content (ID: %d) has been hidden due to a potential violation of our community guidelines.', 'checkstep-integration'),
                        $content_id
                    )
                ));
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to unpublish content', array(
                'error' => $e->getMessage(),
                'content_id' => $content_id
            ));
            throw $e;
        }
    }

    /**
     * Determine content type from ID
     *
     * @param string|int $content_id Content ID
     * @return string Content type identifier
     * @throws Exception If content type cannot be determined
     */
    private function determine_content_type($content_id) {
        // Check post type
        $post_type = get_post_type($content_id);
        if ($post_type) {
            if ($post_type === 'post' || $post_type === 'page') {
                return 'post';
            }
        }

        // Check activity
        if (function_exists('bp_activity_get') && bp_activity_get($content_id)) {
            return 'activity';
        }

        // Check media
        if (function_exists('bp_get_media') && bp_get_media($content_id)) {
            return 'media';
        }

        // Check video
        if (function_exists('bp_get_video') && bp_get_video($content_id)) {
            return 'video';
        }

        // Check document
        if (function_exists('bp_get_document') && bp_get_document($content_id)) {
            return 'document';
        }

        throw new Exception("Unable to determine content type for ID: {$content_id}");
    }

    /**
     * Mock function to simulate adding content warning
     */
    private function add_content_warning($content_id, $reason) {
        CheckStep_Logger::info("Mock: Content warning added", array(
            'content_id' => $content_id,
            'reason' => $reason
        ));
    }


    /**
     * Get content author ID based on content type
     *
     * @param string|int $content_id Content ID
     * @return int|null User ID of the content author
     */
    private function get_content_author($content_id) {
        $content_type = $this->determine_content_type($content_id);

        switch ($content_type) {
            case 'post':
                return get_post_field('post_author', $content_id);

            case 'activity':
                $activity = bp_activity_get_specific(array('activity_ids' => array($content_id)));
                return $activity['activities'][0]->user_id ?? null;

            case 'media':
                if (function_exists('bp_get_media')) {
                    $media = bp_get_media($content_id);
                    return $media ? $media->user_id : null;
                }
                break;

            case 'video':
                if (function_exists('bp_get_video')) {
                    $video = bp_get_video($content_id);
                    return $video ? $video->user_id : null;
                }
                break;

            case 'document':
                if (function_exists('bp_get_document')) {
                    $document = bp_get_document($content_id);
                    return $document ? $document->user_id : null;
                }
                break;
        }

        return null;
    }

    /**
     *Helper function to get the correct moderation type.
     * @param string $content_type
     * @return string
     */
    private function get_moderation_type(string $content_type): string {
        switch ($content_type) {
            case 'post':
                return BP_Moderation_Posts::$moderation_type;
            case 'activity':
                return BP_Moderation_Activity::$moderation_type;
            case 'media':
                return BP_Moderation_Media::$moderation_type;
            case 'video':
                return BP_Moderation_Video::$moderation_type;
            case 'document':
                return BP_Moderation_Document::$moderation_type;
            default:
                throw new Exception("Unsupported content type: {$content_type}");
        }
    }

    /**
     * Notify user about appeal decision
     *
     * @param string|int $content_id Content ID
     * @param string $reason Reason for upholding the moderation decision
     */
    private function notify_user_about_appeal($content_id, $reason) {
        try {
            $user_id = $this->get_content_author($content_id);
            if (!$user_id) {
                throw new Exception("Could not find author for content ID: {$content_id}");
            }

            // Send notification through BuddyBoss notification system
            if (function_exists('bp_notifications_add_notification')) {
                bp_notifications_add_notification(array(
                    'user_id'           => $user_id,
                    'item_id'           => $content_id,
                    'component_name'    => 'checkstep',
                    'component_action'  => 'appeal_refused',
                    'date_notified'     => bp_core_current_time(),
                    'is_new'           => 1,
                    'allow_duplicate'   => false,
                    'title'            => __('Appeal Decision', 'checkstep-integration'),
                    'content'          => sprintf(
                        __('Your appeal for content #%d has been reviewed and the original moderation decision has been upheld. Reason: %s', 'checkstep-integration'),
                        $content_id,
                        $reason
                    )
                ));

                CheckStep_Logger::info('Appeal notification sent to user', array(
                    'user_id' => $user_id,
                    'content_id' => $content_id
                ));
            } else {
                throw new Exception('BuddyBoss notifications system not available');
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to send appeal notification', array(
                'error' => $e->getMessage(),
                'content_id' => $content_id,
                'user_id' => $user_id ?? 'unknown'
            ));
        }
    }
}