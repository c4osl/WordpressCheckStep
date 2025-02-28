<?php
/**
 * Content Types Handler Class
 *
 * Handles the extraction and formatting of different content types for CheckStep moderation.
 * Provides methods to get structured data for user profiles, blog posts, forum posts,
 * and media attachments according to CheckStep's complex type specifications.
 *
 * @package CheckStep_Integration
 * @subpackage Content_Types
 * @since 1.0.0
 */

/**
 * Class CheckStep_Content_Types
 *
 * Formats WordPress and BuddyBoss content into structured data for CheckStep analysis.
 * Implements the content type definitions as specified in the integration design document.
 *
 * @since 1.0.0
 */
class CheckStep_Content_Types {
    /**
     * Get user profile data
     *
     * Retrieves and formats user profile information including BuddyBoss extended profile fields.
     *
     * @since 1.0.0
     * @param int $user_id WordPress user ID
     * @return array|false User profile data array or false if user not found
     */
    public function get_user_profile($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        return array(
            'user_id' => $user_id,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'role' => implode(', ', $user->roles),
            'profile_picture' => get_avatar_url($user_id),
            'metadata' => $this->get_buddyboss_profile_data($user_id),
        );
    }

    /**
     * Get blog post data
     *
     * Retrieves and formats blog post content including metadata and media attachments.
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @return array|false Post data array or false if post not found
     */
    public function get_blog_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $content_warnings = wp_get_post_terms($post_id, 'content-warning', array('fields' => 'names'));

        return array(
            'post_id' => $post_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'author' => $this->get_user_profile($post->post_author),
            'publish_date' => $post->post_date,
            'custom_taxonomies' => array(
                'content_warnings' => $content_warnings,
            ),
            'fragments' => $this->get_post_media($post_id),
            'metadata' => get_post_meta($post_id),
        );
    }

    /**
     * Get forum post data
     *
     * Retrieves and formats forum post content including thread context and warnings.
     *
     * @since 1.0.0
     * @param int $post_id Forum post ID
     * @return array|false Forum post data array or false if post not found or forums not active
     */
    public function get_forum_post($post_id) {
        if (!function_exists('bbp_get_reply_id')) {
            return false;
        }

        $forum_post = bbp_get_reply($post_id);
        if (!$forum_post) {
            return false;
        }

        return array(
            'forum_post_id' => $post_id,
            'thread_id' => bbp_get_reply_thread_id($post_id),
            'content' => $forum_post->post_content,
            'author' => $this->get_user_profile($forum_post->post_author),
            'timestamp' => $forum_post->post_date,
            'fragments' => $this->get_post_media($post_id),
            'custom_taxonomies' => array(
                'content_warnings' => wp_get_post_terms($post_id, 'content-warning', array('fields' => 'names')),
            ),
        );
    }

    /**
     * Get media attachment data
     *
     * Retrieves and formats media attachment metadata for both images and videos.
     *
     * @since 1.0.0
     * @param int $attachment_id Attachment ID
     * @return array|false Media data array or false if attachment not found
     */
    public function get_media_data($attachment_id) {
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return false;
        }

        $type = wp_attachment_is('video', $attachment_id) ? 'video' : 'image';

        $data = array(
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'title' => $attachment->post_title,
            'parent_content' => $attachment->post_parent,
        );

        if ($type === 'image') {
            $data['alt_text'] = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            $data['caption'] = $attachment->post_excerpt;
        }

        return $data;
    }

    /**
     * Get BuddyBoss profile data
     *
     * Retrieves extended profile field data from BuddyBoss/BuddyPress.
     *
     * @since 1.0.0
     * @access private
     * @param int $user_id User ID
     * @return array Array of profile field data
     */
    private function get_buddyboss_profile_data($user_id) {
        if (!function_exists('bp_get_profile_field_data')) {
            return array();
        }

        $profile_data = array();
        $profile_groups = bp_xprofile_get_groups();

        foreach ($profile_groups as $group) {
            foreach ($group->fields as $field) {
                $profile_data[$field->name] = bp_get_profile_field_data(array(
                    'field' => $field->id,
                    'user_id' => $user_id,
                ));
            }
        }

        return $profile_data;
    }

    /**
     * Get post media attachments
     *
     * Retrieves all media attachments associated with a post.
     *
     * @since 1.0.0
     * @access private
     * @param int $post_id Post ID
     * @return array Array of media attachment data
     */
    private function get_post_media($post_id) {
        $attachments = get_attached_media('', $post_id);
        $media = array();

        foreach ($attachments as $attachment) {
            $media[] = $this->get_media_data($attachment->ID);
        }

        return $media;
    }
}

?>