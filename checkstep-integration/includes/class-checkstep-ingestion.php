<?php
/**
 * Content Ingestion Handler Class
 */
class CheckStep_Ingestion {
    private $api;
    private $content_types;

    /**
     * Constructor
     */
    public function __construct($api, $content_types) {
        $this->api = $api;
        $this->content_types = $content_types;
        $this->setup_hooks();
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Content creation/update hooks
        add_action('save_post', array($this, 'queue_post_for_ingestion'), 10, 3);
        add_action('bbp_new_reply', array($this, 'queue_forum_post_for_ingestion'));
        add_action('bp_core_activated_user', array($this, 'queue_user_profile_for_ingestion'));
        add_action('add_attachment', array($this, 'queue_media_for_ingestion'));

        // Processing hook
        add_action('checkstep_process_queue', array($this, 'process_queue'));
    }

    /**
     * Queue post for ingestion
     */
    public function queue_post_for_ingestion($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $this->add_to_queue('post', $post_id);
    }

    /**
     * Queue forum post for ingestion
     */
    public function queue_forum_post_for_ingestion($reply_id) {
        $this->add_to_queue('forum_post', $reply_id);
    }

    /**
     * Queue user profile for ingestion
     */
    public function queue_user_profile_for_ingestion($user_id) {
        $this->add_to_queue('user_profile', $user_id);
    }

    /**
     * Queue media for ingestion
     */
    public function queue_media_for_ingestion($attachment_id) {
        $this->add_to_queue('media', $attachment_id);
    }

    /**
     * Add item to ingestion queue
     */
    private function add_to_queue($content_type, $content_id) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'checkstep_queue',
            array(
                'content_type' => $content_type,
                'content_id' => $content_id,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s')
        );
    }

    /**
     * Process ingestion queue
     */
    public function process_queue() {
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

        foreach ($items as $item) {
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
                } else {
                    $wpdb->update(
                        $wpdb->prefix . 'checkstep_queue',
                        array('status' => 'failed'),
                        array('id' => $item->id),
                        array('%s'),
                        array('%d')
                    );
                }
            }
        }
    }

    /**
     * Prepare content for ingestion
     */
    private function prepare_content($content_type, $content_id) {
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
                return false;
        }
    }
}
