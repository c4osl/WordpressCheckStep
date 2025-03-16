<?php
/**
 * Queue Handler Class
 *
 * Handles the queuing and processing of content for submission to CheckStep API.
 *
 * @package CheckStep_Integration
 * @subpackage Queue_Handler
 * @since 1.0.0
 */

class CheckStep_Queue_Handler {
    /**
     * Queue option name in WordPress options table
     */
    const QUEUE_OPTION = 'checkstep_moderation_queue';

    /**
     * Maximum number of retries for failed submissions
     */
    const MAX_RETRIES = 3;

    /**
     * Add content to the moderation queue
     *
     * @param string $content_type Type of content (activity, forum, blog, etc.)
     * @param int    $content_id   Content identifier
     * @return bool True if content was queued successfully
     */
    public function queue_content($content_type, $content_id) {
        try {
            // Get current queue
            $queue = get_option(self::QUEUE_OPTION, array());

            // Add new item to queue
            $queue[] = array(
                'content_type' => $content_type,
                'content_id' => $content_id,
                'retries' => 0,
                'queued_at' => current_time('mysql'),
                'status' => 'pending'
            );

            // Update queue
            $result = update_option(self::QUEUE_OPTION, $queue);

            if ($result) {
                CheckStep_Logger::info('Content queued for moderation', array(
                    'content_type' => $content_type,
                    'content_id' => $content_id
                ));

                // Ensure cron job is scheduled
                $this->schedule_processing();
            }

            return $result;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to queue content', array(
                'error' => $e->getMessage(),
                'content_type' => $content_type,
                'content_id' => $content_id
            ));
            return false;
        }
    }

    /**
     * Schedule queue processing via WP-Cron
     */
    private function schedule_processing() {
        if (!wp_next_scheduled('checkstep_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'checkstep_process_queue');
        }
    }

    /**
     * Process items in the moderation queue
     */
    public function process_queue() {
        $queue = get_option(self::QUEUE_OPTION, array());
        if (empty($queue)) {
            return;
        }

        $content_types = new CheckStep_Content_Types();
        $api = new CheckStep_API();

        foreach ($queue as $key => &$item) {
            if ($item['status'] !== 'pending' || $item['retries'] >= self::MAX_RETRIES) {
                continue;
            }

            try {
                // Get formatted content based on type
                $content = $this->get_formatted_content($content_types, $item['content_type'], $item['content_id']);
                if (!$content) {
                    throw new Exception('Failed to format content');
                }

                // Submit to CheckStep API
                $result = $api->send_content($item['content_type'], $content);
                if ($result) {
                    $item['status'] = 'completed';
                    CheckStep_Logger::info('Content submitted for moderation', array(
                        'content_type' => $item['content_type'],
                        'content_id' => $item['content_id']
                    ));
                } else {
                    throw new Exception('API submission failed');
                }

            } catch (Exception $e) {
                $item['retries']++;
                $item['last_error'] = $e->getMessage();
                
                if ($item['retries'] >= self::MAX_RETRIES) {
                    $item['status'] = 'failed';
                    CheckStep_Logger::error('Content submission failed permanently', array(
                        'error' => $e->getMessage(),
                        'content_type' => $item['content_type'],
                        'content_id' => $item['content_id'],
                        'retries' => $item['retries']
                    ));
                } else {
                    CheckStep_Logger::warning('Content submission failed, will retry', array(
                        'error' => $e->getMessage(),
                        'content_type' => $item['content_type'],
                        'content_id' => $item['content_id'],
                        'retry_count' => $item['retries']
                    ));
                }
            }
        }

        // Update queue in options table
        update_option(self::QUEUE_OPTION, $queue);
    }

    /**
     * Get formatted content for submission
     *
     * @param CheckStep_Content_Types $formatter Content type formatter
     * @param string                 $type      Content type
     * @param int                    $id        Content ID
     * @return array|false Formatted content or false on failure
     */
    private function get_formatted_content($formatter, $type, $id) {
        switch ($type) {
            case 'activity':
                return $formatter->get_activity_post($id);
            case 'forum':
                return $formatter->get_forum_post($id);
            case 'discussion':
                return $formatter->get_group_discussion($id);
            case 'blog':
                return $formatter->get_user_blog_post($id);
            case 'image':
                return $formatter->get_image_upload($id);
            case 'video':
                return $formatter->get_video_upload($id);
            default:
                CheckStep_Logger::warning('Unsupported content type', array(
                    'type' => $type,
                    'id' => $id
                ));
                return false;
        }
    }
}
