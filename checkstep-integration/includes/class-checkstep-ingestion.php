<?php
/**
 * Content Ingestion Handler Class
 *
 * Manages the queueing and processing of content for CheckStep moderation.
 * Handles various content types including posts, forum content, user profiles,
 * and media attachments.
 *
 * @package CheckStep_Integration
 * @subpackage Ingestion
 * @since 1.0.0
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
     *
     * @since 1.0.0
     * @access private
     */
    private function setup_hooks() {
        try {
            // Content creation/update hooks
            add_action('save_post', array($this, 'queue_post_for_ingestion'), 10, 3);
            add_action('bbp_new_reply', array($this, 'queue_forum_post_for_ingestion'));
            add_action('bp_core_activated_user', array($this, 'queue_user_profile_for_ingestion'));
            add_action('add_attachment', array($this, 'queue_media_for_ingestion'));

            // Processing hook
            add_action('checkstep_process_queue', array($this, 'process_queue'));

            CheckStep_Logger::info('Content ingestion hooks initialized');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to setup ingestion hooks', array(
                'error' => $e->getMessage()
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
            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }

            if ($post->post_status !== 'publish') {
                return;
            }

            $this->add_to_queue('post', $post_id);
            CheckStep_Logger::debug('Post queued for moderation', array(
                'post_id' => $post_id,
                'is_update' => $update
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue post', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
        }
    }

    /**
     * Queue forum post for ingestion
     *
     * Adds a new forum reply to the moderation queue.
     *
     * @since 1.0.0
     * @param int $reply_id Forum reply ID
     */
    public function queue_forum_post_for_ingestion($reply_id) {
        try {
            $this->add_to_queue('forum_post', $reply_id);
            CheckStep_Logger::debug('Forum post queued for moderation', array(
                'reply_id' => $reply_id
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue forum post', array(
                'error' => $e->getMessage(),
                'reply_id' => $reply_id
            ));
        }
    }

    /**
     * Queue user profile for ingestion
     *
     * Adds a newly activated user profile to the moderation queue.
     *
     * @since 1.0.0
     * @param int $user_id User ID
     */
    public function queue_user_profile_for_ingestion($user_id) {
        try {
            $this->add_to_queue('user_profile', $user_id);
            CheckStep_Logger::debug('User profile queued for moderation', array(
                'user_id' => $user_id
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue user profile', array(
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
            $this->add_to_queue('media', $attachment_id);
            CheckStep_Logger::debug('Media attachment queued for moderation', array(
                'attachment_id' => $attachment_id
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue media', array(
                'error' => $e->getMessage(),
                'attachment_id' => $attachment_id
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
                        $result = $this->api->send_content($item->content_type, $content);

                        if ($result) {
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

                case 'forum_post':
                    return $this->content_types->get_forum_post($content_id);

                case 'user_profile':
                    return $this->content_types->get_user_profile($content_id);

                case 'media':
                    return $this->content_types->get_media_data($content_id);

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