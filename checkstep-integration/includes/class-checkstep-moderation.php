<?php
/**
 * CheckStep Moderation Handler Class
 *
 * This class handles content moderation by integrating with the CheckStep API.
 * It extends the BuddyBoss moderation system to provide automated content
 * moderation capabilities through webhook-based decision handling.
 *
 * @package CheckStep_Integration
 * @subpackage Moderation
 * @since 1.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Class CheckStep_Moderation
 *
 * Handles content moderation via CheckStep API by extending BuddyBoss's moderation system.
 * Provides webhook endpoints for receiving moderation decisions and implementing
 * appropriate actions like hiding content, warning users, or banning accounts.
 *
 * @package CheckStep_Integration
 * @since 1.0.0
 */
class CheckStep_Moderation extends BP_Moderation_Abstract {

    /**
     * Item type identifier for moderated content
     *
     * @since 1.0.0
     * @var string
     */
    public static $moderation_type = 'checkstep_content';

    /**
     * CheckStep API instance for making API calls
     *
     * @since 1.0.0
     * @var CheckStep_API
     */
    private $api;

    /**
     * Constructor
     *
     * Initializes the moderation handler and sets up WordPress hooks.
     * Skips setup for admin backend unless explicitly enabled.
     *
     * @since 1.0.0
     * @param CheckStep_API $api CheckStep API instance for making moderation requests
     */
    public function __construct($api) {
        try {
            $this->api = $api;
            parent::$moderation[self::$moderation_type] = self::class;
            $this->item_type = self::$moderation_type;

            /**
             * Moderation code should not add for WordPress backend and if Bypass argument passed for admin
             */
            if ((is_admin() && !wp_doing_ajax()) || self::admin_bypass_check()) {
                return;
            }

            $this->setup_hooks();
            CheckStep_Logger::info('Moderation handler initialized successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to initialize moderation handler', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Setup WordPress hooks
     *
     * Registers webhook endpoints, decision handlers, and content filters.
     *
     * @since 1.0.0
     * @access private
     */
    private function setup_hooks() {
        try {
            // Register webhook endpoints
            add_action('rest_api_init', array($this, 'register_webhooks'));

            // Handle moderation decisions
            add_action('checkstep_handle_decision', array($this, 'handle_moderation_decision'));

            // Filter content display
            add_filter('bp_activity_get_where_conditions', array($this, 'filter_moderated_content'), 10, 2);
            add_filter('bp_forums_get_where_conditions', array($this, 'filter_moderated_content'), 10, 2);

            CheckStep_Logger::debug('Moderation hooks initialized');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to setup moderation hooks', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Register webhook endpoints
     *
     * Sets up REST API endpoints for receiving moderation decisions from CheckStep.
     *
     * @since 1.0.0
     */
    public function register_webhooks() {
        try {
            register_rest_route('checkstep/v1', '/decisions', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_decision_webhook'),
                'permission_callback' => array($this, 'verify_webhook'),
                'args' => array(
                    'decision_id' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'content_id' => array(
                        'required' => true,
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'action' => array(
                        'required' => true,
                        'type' => 'string',
                        'enum' => array('delete', 'hide', 'warn', 'ban_user'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'reason' => array(
                        'required' => false,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ));
            CheckStep_Logger::info('Webhook endpoints registered successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to register webhook endpoints', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Verify webhook request authenticity
     *
     * Validates incoming webhook requests using HMAC signature verification.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object containing headers and payload
     * @return bool|WP_Error True if verification passes, WP_Error on failure
     */
    public function verify_webhook($request) {
        try {
            $signature = $request->get_header('X-CheckStep-Signature');
            $webhook_secret = get_option('checkstep_webhook_secret');

            if (!$signature) {
                CheckStep_Logger::warning('Missing webhook signature', array(
                    'headers' => $request->get_headers()
                ));
                return new WP_Error(
                    'missing_signature',
                    'Missing CheckStep signature header',
                    array('status' => 401)
                );
            }

            if (!$webhook_secret) {
                CheckStep_Logger::error('Webhook secret not configured');
                return new WP_Error(
                    'missing_secret',
                    'Webhook secret not configured',
                    array('status' => 500)
                );
            }

            $payload = $request->get_body();
            $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);

            if (!hash_equals($expected_signature, $signature)) {
                CheckStep_Logger::warning('Invalid webhook signature received');
                return new WP_Error(
                    'invalid_signature',
                    'Invalid CheckStep signature',
                    array('status' => 401)
                );
            }

            CheckStep_Logger::debug('Webhook signature verified successfully');
            return true;

        } catch (Exception $e) {
            CheckStep_Logger::error('Webhook verification failed', array(
                'error' => $e->getMessage()
            ));
            return new WP_Error(
                'verification_error',
                'Webhook verification failed',
                array('status' => 500)
            );
        }
    }

    /**
     * Handle incoming decision webhook
     *
     * Processes moderation decisions received from CheckStep and schedules
     * asynchronous handling of the decision.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object containing decision data
     * @return WP_REST_Response|WP_Error Response confirming receipt or error details
     */
    public function handle_decision_webhook($request) {
        try {
            $params = $request->get_params();

            // Parameters are already validated and sanitized by register_rest_route args
            $decision_id = $params['decision_id'];
            $content_id = $params['content_id'];
            $action = $params['action'];
            $reason = isset($params['reason']) ? $params['reason'] : '';

            // Schedule async processing
            $scheduled = wp_schedule_single_event(
                time(),
                'checkstep_handle_decision',
                array(
                    array(
                        'decision_id' => $decision_id,
                        'content_id' => $content_id,
                        'action' => $action,
                        'reason' => $reason,
                    )
                )
            );

            if (false === $scheduled) {
                throw new Exception('Failed to schedule decision handling');
            }

            CheckStep_Logger::info('Decision queued for processing', array(
                'decision_id' => $decision_id,
                'content_id' => $content_id,
                'action' => $action
            ));

            return new WP_REST_Response(
                array(
                    'status' => 'queued',
                    'decision_id' => $decision_id,
                ),
                202
            );

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to handle webhook', array(
                'error' => $e->getMessage(),
                'params' => $params ?? null
            ));
            return new WP_Error(
                'webhook_error',
                'Failed to process webhook: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle moderation decision
     *
     * Implements the actual moderation actions based on the decision received
     * from CheckStep. Supports content deletion, hiding, warnings, and user bans.
     *
     * @since 1.0.0
     * @param array $decision_data Decision data containing action and context
     */
    public function handle_moderation_decision($decision_data) {
        try {
            $content_id = absint($decision_data['content_id']);
            $action = sanitize_text_field($decision_data['action']);
            $reason = sanitize_text_field($decision_data['reason']);

            if (!$content_id) {
                throw new Exception('Invalid content ID');
            }

            CheckStep_Logger::debug('Processing moderation decision', array(
                'content_id' => $content_id,
                'action' => $action
            ));

            switch ($action) {
                case 'delete':
                    $this->delete_content($content_id);
                    break;

                case 'hide':
                    $this->hide_content($content_id);
                    break;

                case 'warn':
                    if ($reason) {
                        $this->add_content_warning($content_id, $reason);
                    }
                    break;

                case 'ban_user':
                    $user_id = $this->get_content_owner_id($content_id);
                    if ($user_id) {
                        $this->ban_user($user_id);
                    }
                    break;

                default:
                    throw new Exception('Unknown moderation action: ' . $action);
            }

            /**
             * Fires after a CheckStep moderation decision has been handled
             *
             * @since 1.0.0
             *
             * @param array  $decision_data The complete decision data
             * @param string $action        The specific action taken
             * @param int    $content_id    The ID of the affected content
             */
            do_action('checkstep_decision_handled', $decision_data, $action, $content_id);

            CheckStep_Logger::info('Moderation decision processed successfully', array(
                'content_id' => $content_id,
                'action' => $action
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process moderation decision', array(
                'error' => $e->getMessage(),
                'decision_data' => $decision_data
            ));
        }
    }

    /**
     * Filter moderated content from queries
     *
     * Adds WHERE conditions to exclude content that has been hidden by
     * the moderation system.
     *
     * @since 1.0.0
     * @param string $where_sql Current WHERE clause
     * @param array  $args      Query arguments
     * @return string Modified WHERE clause
     */
    public function filter_moderated_content($where_sql, $args = array()) {
        try {
            global $wpdb;

            if (isset($args['moderation_query']) && false === $args['moderation_query']) {
                return $where_sql;
            }

            // Add conditions to exclude moderated content
            $moderated_content = $wpdb->get_col($wpdb->prepare(
                "SELECT item_id FROM {$wpdb->prefix}bp_moderation WHERE item_type = %s AND hidden = 1",
                self::$moderation_type
            ));

            if ($wpdb->last_error) {
                throw new Exception('Database query failed: ' . $wpdb->last_error);
            }

            if (!empty($moderated_content)) {
                $where_sql .= " AND t.id NOT IN (" . implode(',', array_map('absint', $moderated_content)) . ")";
            }

            CheckStep_Logger::debug('Content filter applied', array(
                'hidden_count' => count($moderated_content)
            ));

            return $where_sql;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to filter moderated content', array(
                'error' => $e->getMessage()
            ));
            return $where_sql;
        }
    }

    /**
     * Get content permalink
     *
     * Required implementation from BP_Moderation_Abstract.
     *
     * @since 1.0.0
     * @param int $content_id Content ID
     * @return string Permalink URL
     */
    public static function get_permalink($content_id) {
        return get_permalink(absint($content_id));
    }

    /**
     * Get content owner id
     *
     * Required implementation from BP_Moderation_Abstract.
     *
     * @since 1.0.0
     * @param int $content_id Content ID
     * @return int Owner user ID
     */
    public static function get_content_owner_id($content_id) {
        $post = get_post(absint($content_id));
        return $post ? absint($post->post_author) : 0;
    }

    /**
     * Delete content
     *
     * Permanently removes content from the site.
     *
     * @since 1.0.0
     * @access private
     * @param int $content_id Content ID
     */
    private function delete_content($content_id) {
        try {
            $content_id = absint($content_id);
            if (!$content_id) {
                throw new Exception('Invalid content ID');
            }

            $result = wp_delete_post($content_id, true);
            if (!$result) {
                throw new Exception('Failed to delete post');
            }

            CheckStep_Logger::info('Content deleted successfully', array(
                'content_id' => $content_id
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to delete content', array(
                'error' => $e->getMessage(),
                'content_id' => $content_id
            ));
            throw $e;
        }
    }

    /**
     * Hide content
     *
     * Makes content private so it's only visible to administrators.
     *
     * @since 1.0.0
     * @access private
     * @param int $content_id Content ID
     */
    private function hide_content($content_id) {
        try {
            $content_id = absint($content_id);
            if (!$content_id) {
                throw new Exception('Invalid content ID');
            }

            $result = wp_update_post(array(
                'ID' => $content_id,
                'post_status' => 'private'
            ));

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            CheckStep_Logger::info('Content hidden successfully', array(
                'content_id' => $content_id
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to hide content', array(
                'error' => $e->getMessage(),
                'content_id' => $content_id
            ));
            throw $e;
        }
    }

    /**
     * Add content warning
     *
     * Attaches a warning message to content using taxonomy terms.
     *
     * @since 1.0.0
     * @access private
     * @param int    $content_id Content ID
     * @param string $warning    Warning message
     */
    private function add_content_warning($content_id, $warning) {
        try {
            $content_id = absint($content_id);
            $warning = sanitize_text_field($warning);

            if (!$content_id || !$warning) {
                throw new Exception('Invalid content ID or warning message');
            }

            $result = wp_set_object_terms($content_id, $warning, 'content-warning', true);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            CheckStep_Logger::info('Content warning added successfully', array(
                'content_id' => $content_id,
                'warning' => $warning
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to add content warning', array(
                'error' => $e->getMessage(),
                'content_id' => $content_id
            ));
            throw $e;
        }
    }

    /**
     * Ban user
     *
     * Bans a user using BuddyBoss's moderation system.
     *
     * @since 1.0.0
     * @access private
     * @param int $user_id User ID to ban
     */
    private function ban_user($user_id) {
        try {
            $user_id = absint($user_id);
            if (!$user_id) {
                throw new Exception('Invalid user ID');
            }

            if (!function_exists('bp_moderation_add')) {
                throw new Exception('BuddyBoss moderation system not available');
            }

            $result = bp_moderation_add(array(
                'user_id' => $user_id,
                'type' => 'user',
            ));

            if (!$result) {
                throw new Exception('Failed to ban user');
            }

            CheckStep_Logger::info('User banned successfully', array(
                'user_id' => $user_id
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to ban user', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
            throw $e;
        }
    }
}