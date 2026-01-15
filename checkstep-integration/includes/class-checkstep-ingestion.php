<?php
/**
 * Content Ingestion Handler Class
 *
 * Manages the queueing and processing of content for CheckStep moderation.
 * Handles various content types including posts, forum content, user profiles,
 * activity updates, and media attachments.
 *
 * @package CheckStep_Integration
 * @subpackage Ingestion
 * @since 1.0.0
 * @since 1.0.11 Added comprehensive hook logging and BuddyBoss activity hooks
 */

/**
 * Class CheckStep_Ingestion
 *
 * Implements content ingestion and queuing functionality for moderation processing.
 * Sets up WordPress hooks for content creation/update events and manages the
 * asynchronous processing queue.
 *
 * @since 1.0.0
 */
class CheckStep_Ingestion {
    /**
     * CheckStep API instance
     *
     * @since 1.0.0
     * @var CheckStep_API
     */
    private $api;

    /**
     * Content Types handler instance
     *
     * @since 1.0.0
     * @var CheckStep_Content_Types
     */
    private $content_types;

    /**
     * Constructor
     *
     * Initializes the ingestion handler with required dependencies
     * and sets up WordPress hooks.
     *
     * @since 1.0.0
     * @param CheckStep_API $api API instance for sending content
     * @param CheckStep_Content_Types $content_types Content type handler
     */
    public function __construct($api, $content_types) {
        $this->api = $api;
        $this->content_types = $content_types;
        $this->setup_hooks();
    }

    /**
     * Setup WordPress hooks
     *
     * Registers action hooks for various content creation and update events.
     * Uses bbp_new_topic_post_extras and bbp_new_reply_post_extras as recommended
     * by BuddyBoss documentation - these fire after content is fully saved.
     *
     * @since 1.0.0
     * @since 1.0.11 Added BuddyBoss activity hooks and hook logging
     * @since 1.0.12 Added bbp_new_topic_post_extras and bbp_new_reply_post_extras hooks
     * @access private
     */
    private function setup_hooks() {
        try {
            // WordPress Post hooks
            add_action('save_post', array($this, 'queue_post_for_ingestion'), 10, 3);
            add_action('publish_post', array($this, 'on_publish_post'), 10, 2);
            add_action('transition_post_status', array($this, 'on_post_status_change'), 10, 3);
            
            // BuddyBoss/bbPress Forum hooks - primary hooks (fire with full parameters)
            add_action('bbp_new_topic', array($this, 'queue_forum_topic_for_ingestion'), 10, 4);
            add_action('bbp_new_reply', array($this, 'queue_forum_reply_for_ingestion'), 10, 5);
            add_action('bbp_edit_topic', array($this, 'queue_forum_topic_for_ingestion'), 10, 4);
            add_action('bbp_edit_reply', array($this, 'queue_forum_reply_for_ingestion'), 10, 5);
            
            // BuddyBoss/bbPress Forum hooks - post_extras hooks (fire after content fully saved)
            // These are more reliable according to BuddyBoss documentation
            add_action('bbp_new_topic_post_extras', array($this, 'on_forum_topic_post_extras'), 10, 1);
            add_action('bbp_new_reply_post_extras', array($this, 'on_forum_reply_post_extras'), 10, 2);
            add_action('bbp_edit_topic_post_extras', array($this, 'on_forum_topic_post_extras'), 10, 1);
            add_action('bbp_edit_reply_post_extras', array($this, 'on_forum_reply_post_extras'), 10, 2);
            
            // BuddyBoss Activity hooks
            add_action('bp_activity_posted_update', array($this, 'queue_activity_update'), 10, 3);
            add_action('bp_groups_posted_update', array($this, 'queue_group_activity_update'), 10, 4);
            add_action('bp_activity_add', array($this, 'on_activity_add'), 10, 1);
            add_action('bp_activity_after_save', array($this, 'queue_activity_after_save'), 10, 1);
            
            // BuddyBoss Comment hooks
            add_action('bp_activity_comment_posted', array($this, 'queue_activity_comment'), 10, 3);
            add_action('bp_activity_comment_posted_notification_skipped', array($this, 'queue_activity_comment'), 10, 3);
            
            // User profile hooks
            add_action('bp_core_activated_user', array($this, 'queue_user_profile_for_ingestion'), 10, 1);
            add_action('xprofile_updated_profile', array($this, 'queue_user_profile_update'), 10, 5);
            add_action('profile_update', array($this, 'queue_wp_profile_update'), 10, 2);
            add_action('user_register', array($this, 'queue_new_user_registration'), 10, 1);
            
            // Media/attachment hooks
            add_action('add_attachment', array($this, 'queue_media_for_ingestion'), 10, 1);
            add_action('bp_media_add', array($this, 'queue_buddyboss_media'), 10, 1);
            
            // BuddyBoss Messages (private messages)
            add_action('messages_message_sent', array($this, 'queue_private_message'), 10, 1);

            // Processing hook (for cron)
            add_action('checkstep_process_queue', array($this, 'process_queue'));

            CheckStep_Logger::info('Content ingestion hooks initialized', array(
                'hooks_registered' => array(
                    'save_post',
                    'publish_post',
                    'transition_post_status',
                    'bbp_new_topic',
                    'bbp_new_reply',
                    'bbp_edit_topic',
                    'bbp_edit_reply',
                    'bbp_new_topic_post_extras',
                    'bbp_new_reply_post_extras',
                    'bbp_edit_topic_post_extras',
                    'bbp_edit_reply_post_extras',
                    'bp_activity_posted_update',
                    'bp_groups_posted_update',
                    'bp_activity_add',
                    'bp_activity_after_save',
                    'bp_activity_comment_posted',
                    'bp_core_activated_user',
                    'xprofile_updated_profile',
                    'profile_update',
                    'user_register',
                    'add_attachment',
                    'bp_media_add',
                    'messages_message_sent'
                )
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to setup ingestion hooks', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle forum topic post extras hook
     *
     * Fires after topic is fully saved - more reliable than bbp_new_topic.
     * Hook: bbp_new_topic_post_extras, bbp_edit_topic_post_extras
     *
     * @since 1.0.12
     * @param int $topic_id Topic ID
     */
    public function on_forum_topic_post_extras($topic_id) {
        try {
            $hook_name = current_action();
            $forum_id = function_exists('bbp_get_topic_forum_id') ? bbp_get_topic_forum_id($topic_id) : 0;
            $author_id = function_exists('bbp_get_topic_author_id') ? bbp_get_topic_author_id($topic_id) : 0;
            
            CheckStep_Logger::hook($hook_name, array(
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'author_id' => $author_id
            ));

            // Process immediately instead of just queuing
            $this->process_content_immediately('forum_topic', $topic_id);
            
            CheckStep_Logger::info('Forum topic processed via post_extras hook', array(
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'triggered_by' => $hook_name
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process forum topic post_extras', array(
                'error' => $e->getMessage(),
                'topic_id' => $topic_id
            ));
        }
    }

    /**
     * Handle forum reply post extras hook
     *
     * Fires after reply is fully saved - more reliable than bbp_new_reply.
     * Hook: bbp_new_reply_post_extras, bbp_edit_reply_post_extras
     *
     * @since 1.0.12
     * @param int $reply_id Reply ID
     * @param int $topic_id Topic ID (optional)
     */
    public function on_forum_reply_post_extras($reply_id, $topic_id = 0) {
        try {
            $hook_name = current_action();
            
            if (!$topic_id && function_exists('bbp_get_reply_topic_id')) {
                $topic_id = bbp_get_reply_topic_id($reply_id);
            }
            $forum_id = function_exists('bbp_get_reply_forum_id') ? bbp_get_reply_forum_id($reply_id) : 0;
            $author_id = function_exists('bbp_get_reply_author_id') ? bbp_get_reply_author_id($reply_id) : 0;
            
            CheckStep_Logger::hook($hook_name, array(
                'reply_id' => $reply_id,
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'author_id' => $author_id
            ));

            // Process immediately instead of just queuing
            $this->process_content_immediately('forum_reply', $reply_id);
            
            CheckStep_Logger::info('Forum reply processed via post_extras hook', array(
                'reply_id' => $reply_id,
                'topic_id' => $topic_id,
                'triggered_by' => $hook_name
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process forum reply post_extras', array(
                'error' => $e->getMessage(),
                'reply_id' => $reply_id
            ));
        }
    }

    /**
     * Process content immediately (instead of just queuing)
     *
     * Prepares content and sends to CheckStep API right away.
     *
     * @since 1.0.12
     * @access private
     * @param string $content_type Content type
     * @param int    $content_id   Content ID
     * @return bool Success status
     */
    private function process_content_immediately($content_type, $content_id) {
        try {
            $content = $this->prepare_content($content_type, $content_id);
            
            if (!$content) {
                CheckStep_Logger::warning('No content prepared for immediate processing', array(
                    'content_type' => $content_type,
                    'content_id' => $content_id
                ));
                return false;
            }

            CheckStep_Logger::api('/content', 'POST', array(
                'content_type' => $content_type,
                'content_id' => $content_id,
                'processing' => 'immediate'
            ));

            $result = $this->api->send_content($content_type, $content);

            if ($result) {
                CheckStep_Logger::api_response('/content', 200, array(
                    'content_type' => $content_type,
                    'content_id' => $content_id,
                    'success' => true
                ));
                return true;
            } else {
                CheckStep_Logger::api_response('/content', 500, array(
                    'content_type' => $content_type,
                    'content_id' => $content_id,
                    'success' => false,
                    'error' => 'API returned false'
                ));
                return false;
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process content immediately', array(
                'error' => $e->getMessage(),
                'content_type' => $content_type,
                'content_id' => $content_id
            ));
            return false;
        }
    }

    /**
     * Handle post publish event
     *
     * @since 1.0.11
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     */
    public function on_publish_post($post_id, $post) {
        CheckStep_Logger::hook('publish_post', array(
            'post_id' => $post_id,
            'post_type' => $post->post_type,
            'post_title' => $post->post_title,
            'author_id' => $post->post_author
        ));
    }

    /**
     * Handle post status change
     *
     * @since 1.0.11
     * @param string  $new_status New post status
     * @param string  $old_status Old post status
     * @param WP_Post $post       Post object
     */
    public function on_post_status_change($new_status, $old_status, $post) {
        CheckStep_Logger::hook('transition_post_status', array(
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'old_status' => $old_status,
            'new_status' => $new_status
        ));

        // Queue for moderation when post becomes published
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->add_to_queue('post', $post->ID);
            CheckStep_Logger::info('Post queued on publish via status change', array(
                'post_id' => $post->ID,
                'content_type' => 'post'
            ));
        }
    }

    /**
     * Queue post for ingestion
     *
     * Adds a published post to the moderation queue.
     *
     * @since 1.0.0
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function queue_post_for_ingestion($post_id, $post, $update) {
        try {
            CheckStep_Logger::hook('save_post', array(
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
                'is_update' => $update
            ));

            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                CheckStep_Logger::debug('Skipping revision/autosave', array(
                    'post_id' => $post_id
                ));
                return;
            }

            if ($post->post_status !== 'publish') {
                CheckStep_Logger::debug('Skipping non-published post', array(
                    'post_id' => $post_id,
                    'status' => $post->post_status
                ));
                return;
            }

            // Skip attachment post types (handled by media hook)
            if ($post->post_type === 'attachment') {
                return;
            }

            $this->add_to_queue('post', $post_id);
            CheckStep_Logger::info('Post queued for moderation', array(
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'is_update' => $update,
                'content_type' => 'post'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue post', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
        }
    }

    /**
     * Queue forum topic for ingestion
     *
     * Sends a new forum topic/discussion to CheckStep for moderation.
     * Hook: bbp_new_topic, bbp_edit_topic
     * Uses bbp_get_topic_content() per BuddyBoss Hook Reference.
     *
     * @since 1.0.9
     * @since 1.0.17 Now processes immediately instead of just queuing
     * @param int   $topic_id        Topic ID
     * @param int   $forum_id        Forum ID
     * @param array $anonymous_data  Anonymous user data
     * @param int   $topic_author    Topic author ID
     */
    public function queue_forum_topic_for_ingestion($topic_id, $forum_id = 0, $anonymous_data = array(), $topic_author = 0) {
        try {
            $hook_name = current_action();
            CheckStep_Logger::hook($hook_name, array(
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'author_id' => $topic_author
            ));

            // Process immediately - send to CheckStep API
            $this->process_content_immediately('forum_topic', $topic_id);
            
            CheckStep_Logger::info('Forum topic sent to CheckStep for moderation', array(
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'author_id' => $topic_author,
                'content_type' => 'forum_topic',
                'triggered_by' => $hook_name
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process forum topic', array(
                'error' => $e->getMessage(),
                'topic_id' => $topic_id
            ));
            // Fallback to queue if immediate processing fails
            $this->add_to_queue('forum_topic', $topic_id);
        }
    }

    /**
     * Queue forum reply for ingestion
     *
     * Sends a new forum reply to CheckStep for moderation.
     * Hook: bbp_new_reply, bbp_edit_reply
     * Uses bbp_get_reply_content() per BuddyBoss Hook Reference.
     *
     * @since 1.0.0
     * @since 1.0.17 Now processes immediately instead of just queuing
     * @param int   $reply_id        Reply ID
     * @param int   $topic_id        Topic ID
     * @param int   $forum_id        Forum ID
     * @param array $anonymous_data  Anonymous user data
     * @param int   $reply_author    Reply author ID
     */
    public function queue_forum_reply_for_ingestion($reply_id, $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $reply_author = 0) {
        try {
            $hook_name = current_action();
            CheckStep_Logger::hook($hook_name, array(
                'reply_id' => $reply_id,
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'author_id' => $reply_author
            ));

            // Process immediately - send to CheckStep API
            $this->process_content_immediately('forum_reply', $reply_id);
            
            CheckStep_Logger::info('Forum reply sent to CheckStep for moderation', array(
                'reply_id' => $reply_id,
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'author_id' => $reply_author,
                'content_type' => 'forum_reply',
                'triggered_by' => $hook_name
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process forum reply', array(
                'error' => $e->getMessage(),
                'reply_id' => $reply_id
            ));
            // Fallback to queue if immediate processing fails
            $this->add_to_queue('forum_reply', $reply_id);
        }
    }

    /**
     * Queue BuddyBoss activity update
     *
     * Handles bp_activity_posted_update hook for user status updates.
     *
     * @since 1.0.11
     * @param string $content   Activity content
     * @param int    $user_id   User ID
     * @param int    $activity_id Activity ID
     */
    public function queue_activity_update($content, $user_id, $activity_id) {
        try {
            CheckStep_Logger::hook('bp_activity_posted_update', array(
                'activity_id' => $activity_id,
                'user_id' => $user_id,
                'content_length' => strlen($content)
            ));

            $this->add_to_queue('activity', $activity_id);
            CheckStep_Logger::info('Activity update queued for moderation', array(
                'activity_id' => $activity_id,
                'user_id' => $user_id,
                'content_type' => 'activity'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue activity update', array(
                'error' => $e->getMessage(),
                'activity_id' => $activity_id
            ));
        }
    }

    /**
     * Queue BuddyBoss group activity update
     *
     * Handles bp_groups_posted_update hook for group posts.
     *
     * @since 1.0.11
     * @param string $content     Activity content
     * @param int    $user_id     User ID
     * @param int    $group_id    Group ID
     * @param int    $activity_id Activity ID
     */
    public function queue_group_activity_update($content, $user_id, $group_id, $activity_id) {
        try {
            CheckStep_Logger::hook('bp_groups_posted_update', array(
                'activity_id' => $activity_id,
                'user_id' => $user_id,
                'group_id' => $group_id,
                'content_length' => strlen($content)
            ));

            $this->add_to_queue('group_activity', $activity_id);
            CheckStep_Logger::info('Group activity update queued for moderation', array(
                'activity_id' => $activity_id,
                'user_id' => $user_id,
                'group_id' => $group_id,
                'content_type' => 'group_activity'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue group activity update', array(
                'error' => $e->getMessage(),
                'activity_id' => $activity_id
            ));
        }
    }

    /**
     * Handle bp_activity_add hook
     *
     * Called when activity is being added.
     *
     * @since 1.0.11
     * @param array $args Activity arguments
     */
    public function on_activity_add($args) {
        CheckStep_Logger::hook('bp_activity_add', array(
            'type' => isset($args['type']) ? $args['type'] : 'unknown',
            'component' => isset($args['component']) ? $args['component'] : 'unknown',
            'user_id' => isset($args['user_id']) ? $args['user_id'] : 0,
            'item_id' => isset($args['item_id']) ? $args['item_id'] : 0
        ));
    }

    /**
     * Queue activity after save
     *
     * Handles bp_activity_after_save hook for all activity types.
     *
     * @since 1.0.11
     * @param BP_Activity_Activity $activity Activity object
     */
    public function queue_activity_after_save($activity) {
        try {
            CheckStep_Logger::hook('bp_activity_after_save', array(
                'activity_id' => $activity->id,
                'type' => $activity->type,
                'component' => $activity->component,
                'user_id' => $activity->user_id
            ));

            // Don't double-queue if already queued by specific hooks
            if (in_array($activity->type, array('activity_update', 'activity_comment'))) {
                return;
            }

            $this->add_to_queue('activity', $activity->id);
            CheckStep_Logger::info('Activity queued for moderation via after_save', array(
                'activity_id' => $activity->id,
                'type' => $activity->type,
                'content_type' => 'activity'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue activity after save', array(
                'error' => $e->getMessage(),
                'activity_id' => isset($activity->id) ? $activity->id : 'unknown'
            ));
        }
    }

    /**
     * Queue activity comment
     *
     * @since 1.0.11
     * @param int    $comment_id   Comment ID
     * @param array  $r            Comment args
     * @param object $activity     Parent activity
     */
    public function queue_activity_comment($comment_id, $r, $activity) {
        try {
            CheckStep_Logger::hook('bp_activity_comment_posted', array(
                'comment_id' => $comment_id,
                'parent_activity_id' => isset($activity->id) ? $activity->id : 0,
                'user_id' => isset($r['user_id']) ? $r['user_id'] : 0
            ));

            $this->add_to_queue('activity_comment', $comment_id);
            CheckStep_Logger::info('Activity comment queued for moderation', array(
                'comment_id' => $comment_id,
                'content_type' => 'activity_comment'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue activity comment', array(
                'error' => $e->getMessage(),
                'comment_id' => $comment_id
            ));
        }
    }

    /**
     * Queue user profile for ingestion
     *
     * Sends a newly activated user profile to CheckStep for moderation.
     * Hook: bp_core_activated_user (fires when BuddyBoss account is fully activated)
     *
     * @since 1.0.0
     * @since 1.0.17 Now processes immediately instead of just queuing
     * @param int $user_id User ID
     */
    public function queue_user_profile_for_ingestion($user_id) {
        try {
            CheckStep_Logger::hook('bp_core_activated_user', array(
                'user_id' => $user_id
            ));

            // Process immediately - send to CheckStep API
            $this->process_content_immediately('user_profile', $user_id);
            
            CheckStep_Logger::info('User profile sent to CheckStep for moderation (activation)', array(
                'user_id' => $user_id,
                'content_type' => 'user_profile'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process user profile', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
            // Fallback to queue if immediate processing fails
            $this->add_to_queue('user_profile', $user_id);
        }
    }

    /**
     * Queue user profile update (BuddyBoss xprofile)
     *
     * @since 1.0.11
     * @param int   $user_id          User ID
     * @param array $posted_field_ids Array of field IDs
     * @param bool  $errors           Whether there were errors
     * @param array $old_values       Old field values
     * @param array $new_values       New field values
     */
    public function queue_user_profile_update($user_id, $posted_field_ids = array(), $errors = false, $old_values = array(), $new_values = array()) {
        try {
            CheckStep_Logger::hook('xprofile_updated_profile', array(
                'user_id' => $user_id,
                'fields_updated' => count($posted_field_ids),
                'had_errors' => $errors
            ));

            if ($errors) {
                return;
            }

            $this->add_to_queue('user_profile', $user_id);
            CheckStep_Logger::info('User profile queued for moderation (xprofile update)', array(
                'user_id' => $user_id,
                'content_type' => 'user_profile'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue user profile update', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
        }
    }

    /**
     * Queue WordPress profile update
     *
     * @since 1.0.11
     * @param int   $user_id       User ID
     * @param array $old_user_data Old user data
     */
    public function queue_wp_profile_update($user_id, $old_user_data) {
        try {
            CheckStep_Logger::hook('profile_update', array(
                'user_id' => $user_id
            ));

            $this->add_to_queue('user_profile', $user_id);
            CheckStep_Logger::info('User profile queued for moderation (WP profile update)', array(
                'user_id' => $user_id,
                'content_type' => 'user_profile'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue WP profile update', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
        }
    }

    /**
     * Queue new user registration
     *
     * @since 1.0.11
     * @param int $user_id User ID
     */
    public function queue_new_user_registration($user_id) {
        try {
            CheckStep_Logger::hook('user_register', array(
                'user_id' => $user_id
            ));

            $this->add_to_queue('user_profile', $user_id);
            CheckStep_Logger::info('New user profile queued for moderation', array(
                'user_id' => $user_id,
                'content_type' => 'user_profile'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue new user registration', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
        }
    }

    /**
     * Queue media for ingestion
     *
     * Adds a new media attachment to the moderation queue.
     *
     * @since 1.0.0
     * @param int $attachment_id Attachment ID
     */
    public function queue_media_for_ingestion($attachment_id) {
        try {
            CheckStep_Logger::hook('add_attachment', array(
                'attachment_id' => $attachment_id,
                'mime_type' => get_post_mime_type($attachment_id)
            ));

            $this->add_to_queue('media', $attachment_id);
            CheckStep_Logger::info('Media attachment queued for moderation', array(
                'attachment_id' => $attachment_id,
                'content_type' => 'media'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue media', array(
                'error' => $e->getMessage(),
                'attachment_id' => $attachment_id
            ));
        }
    }

    /**
     * Queue BuddyBoss media
     *
     * @since 1.0.11
     * @param int $media_id BuddyBoss media ID
     */
    public function queue_buddyboss_media($media_id) {
        try {
            CheckStep_Logger::hook('bp_media_add', array(
                'media_id' => $media_id
            ));

            $this->add_to_queue('bp_media', $media_id);
            CheckStep_Logger::info('BuddyBoss media queued for moderation', array(
                'media_id' => $media_id,
                'content_type' => 'bp_media'
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue BuddyBoss media', array(
                'error' => $e->getMessage(),
                'media_id' => $media_id
            ));
        }
    }

    /**
     * Queue private message
     *
     * @since 1.0.11
     * @param object $message Message object
     */
    public function queue_private_message($message) {
        try {
            $message_id = isset($message->id) ? $message->id : 0;
            
            CheckStep_Logger::hook('messages_message_sent', array(
                'message_id' => $message_id,
                'sender_id' => isset($message->sender_id) ? $message->sender_id : 0,
                'thread_id' => isset($message->thread_id) ? $message->thread_id : 0
            ));

            if ($message_id) {
                $this->add_to_queue('private_message', $message_id);
                CheckStep_Logger::info('Private message queued for moderation', array(
                    'message_id' => $message_id,
                    'content_type' => 'private_message'
                ));
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue private message', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Add item to ingestion queue
     *
     * Inserts a new item into the database queue for processing.
     *
     * @since 1.0.0
     * @access private
     * @param string $content_type Type of content ('post', 'forum_post', etc.)
     * @param int    $content_id   Content identifier
     */
    private function add_to_queue($content_type, $content_id) {
        try {
            global $wpdb;

            // Check if item already exists in pending queue
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}checkstep_queue 
                 WHERE content_type = %s AND content_id = %d AND status = 'pending'",
                $content_type,
                $content_id
            ));

            if ($existing) {
                CheckStep_Logger::debug('Item already in queue, skipping', array(
                    'content_type' => $content_type,
                    'content_id' => $content_id,
                    'existing_queue_id' => $existing
                ));
                return;
            }

            $result = $wpdb->insert(
                $wpdb->prefix . 'checkstep_queue',
                array(
                    'content_type' => $content_type,
                    'content_id' => $content_id,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                ),
                array('%s', '%d', '%s', '%s')
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            CheckStep_Logger::info('Content added to queue', array(
                'content_type' => $content_type,
                'content_id' => $content_id,
                'queue_id' => $wpdb->insert_id
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to add content to queue', array(
                'error' => $e->getMessage(),
                'content_type' => $content_type,
                'content_id' => $content_id
            ));
            throw $e;
        }
    }

    /**
     * Process ingestion queue
     *
     * Processes pending items in the moderation queue by sending them
     * to the CheckStep API.
     *
     * @since 1.0.0
     */
    public function process_queue() {
        try {
            global $wpdb;

            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}checkstep_queue 
                    WHERE status = 'pending' 
                    ORDER BY created_at ASC 
                    LIMIT %d",
                    10
                )
            );

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            CheckStep_Logger::info('Processing queue items', array(
                'count' => count($items)
            ));

            foreach ($items as $item) {
                try {
                    $content = $this->prepare_content($item->content_type, $item->content_id);

                    if ($content) {
                        CheckStep_Logger::api('/content', 'POST', array(
                            'content_type' => $item->content_type,
                            'content_id' => $item->content_id,
                            'queue_id' => $item->id
                        ));

                        $result = $this->api->send_content($item->content_type, $content);

                        if ($result) {
                            CheckStep_Logger::api_response('/content', 200, array(
                                'queue_id' => $item->id,
                                'success' => true
                            ));

                            $wpdb->update(
                                $wpdb->prefix . 'checkstep_queue',
                                array(
                                    'status' => 'completed',
                                    'processed_at' => current_time('mysql'),
                                ),
                                array('id' => $item->id),
                                array('%s', '%s'),
                                array('%d')
                            );

                            CheckStep_Logger::info('Queue item processed successfully', array(
                                'queue_id' => $item->id,
                                'content_type' => $item->content_type,
                                'content_id' => $item->content_id
                            ));
                        } else {
                            CheckStep_Logger::api_response('/content', 500, array(
                                'queue_id' => $item->id,
                                'success' => false
                            ));

                            $wpdb->update(
                                $wpdb->prefix . 'checkstep_queue',
                                array('status' => 'failed'),
                                array('id' => $item->id),
                                array('%s'),
                                array('%d')
                            );

                            CheckStep_Logger::error('Failed to process queue item', array(
                                'queue_id' => $item->id,
                                'content_type' => $item->content_type,
                                'content_id' => $item->content_id
                            ));
                        }
                    } else {
                        CheckStep_Logger::warning('No content prepared for queue item', array(
                            'queue_id' => $item->id,
                            'content_type' => $item->content_type,
                            'content_id' => $item->content_id
                        ));
                    }
                } catch (Exception $e) {
                    CheckStep_Logger::error('Error processing queue item', array(
                        'error' => $e->getMessage(),
                        'queue_id' => $item->id,
                        'content_type' => $item->content_type,
                        'content_id' => $item->content_id
                    ));
                }
            }

            update_option('checkstep_last_queue_process', current_time('mysql'));

        } catch (Exception $e) {
            CheckStep_Logger::error('Queue processing failed', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Prepare content for ingestion
     *
     * Formats content data according to its type using the content types handler.
     *
     * @since 1.0.0
     * @access private
     * @param string $content_type Content type identifier
     * @param int    $content_id   Content identifier
     * @return array|false Formatted content data or false on failure
     */
    private function prepare_content($content_type, $content_id) {
        try {
            switch ($content_type) {
                case 'post':
                    return $this->content_types->get_blog_post($content_id);

                case 'forum_topic':
                    return $this->content_types->get_forum_topic($content_id);

                case 'forum_reply':
                case 'forum_post':
                    return $this->content_types->get_forum_reply($content_id);

                case 'user_profile':
                    return $this->content_types->get_user_profile($content_id);

                case 'media':
                    return $this->content_types->get_media_data($content_id);

                case 'activity':
                case 'group_activity':
                    return $this->content_types->get_activity($content_id);

                case 'activity_comment':
                    return $this->content_types->get_activity_comment($content_id);

                case 'bp_media':
                    return $this->content_types->get_buddyboss_media($content_id);

                case 'private_message':
                    return $this->content_types->get_private_message($content_id);

                default:
                    CheckStep_Logger::warning('Unknown content type', array(
                        'content_type' => $content_type,
                        'content_id' => $content_id
                    ));
                    return false;
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to prepare content', array(
                'error' => $e->getMessage(),
                'content_type' => $content_type,
                'content_id' => $content_id
            ));
            return false;
        }
    }
}
