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
     * Get base content structure
     *
     * Returns the common fields that all content types share.
     *
     * @param int    $content_id   Content identifier
     * @param string $content_type Type of content (activity, forum, blog, etc.)
     * @return array Base content structure
     */
    private function get_base_content_structure($content_id, $content_type) {
        return array(
            'id' => $content_id,
            'type' => $content_type,
            'author' => array(
                'id' => 0, // Will be populated with actual user ID
                'name' => '',
                'role' => ''
            ),
            'parent_id' => null, // For replies, comments, etc.
            'group_id' => null,  // For group-related content
            'timestamp' => current_time('mysql'),
            'fields' => array() // Will contain content fields
        );
    }

    /**
     * Format activity stream post
     *
     * @param int $activity_id Activity post ID
     * @return array|false Formatted activity data or false on failure
     */
    public function get_activity_post($activity_id) {
        try {
            $content = $this->get_base_content_structure($activity_id, 'activity');
            // TODO: Implement activity stream post formatting
            return $content;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Format forum post
     *
     * @param int $post_id Forum post ID
     * @return array|false Formatted forum post data or false on failure
     */
    public function get_forum_post($post_id) {
        try {
            $content = $this->get_base_content_structure($post_id, 'forum');
            // TODO: Implement forum post formatting
            return $content;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Format group discussion
     *
     * @param int $discussion_id Group discussion ID
     * @return array|false Formatted discussion data or false on failure
     */
    public function get_group_discussion($discussion_id) {
        try {
            $content = $this->get_base_content_structure($discussion_id, 'discussion');
            // TODO: Implement group discussion formatting
            return $content;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Format user blog post
     *
     * @param int $post_id Blog post ID
     * @return array|false Formatted blog post data or false on failure
     */
    public function get_user_blog_post($post_id) {
        try {
            $content = $this->get_base_content_structure($post_id, 'blog');
            // TODO: Implement user blog post formatting
            return $content;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Format image upload
     *
     * @param int $attachment_id Image attachment ID
     * @return array|false Formatted image data or false on failure
     */
    public function get_image_upload($attachment_id) {
        try {
            $content = $this->get_base_content_structure($attachment_id, 'image');
            // TODO: Implement image upload formatting
            return $content;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Format video upload
     *
     * @param int $attachment_id Video attachment ID
     * @return array|false Formatted video data or false on failure
     */
    public function get_video_upload($attachment_id) {
        try {
            $content = $this->get_base_content_structure($attachment_id, 'video');
            // TODO: Implement video upload formatting
            return $content;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Add text field to content
     *
     * @param array  $content Content structure
     * @param string $text    Text content to add
     * @param string $field_id Optional field identifier
     */
    protected function add_text_field(&$content, $text, $field_id = 'content') {
        $content['fields'][] = array(
            'id' => $field_id,
            'type' => 'text',
            'src' => $text
        );
    }

    /**
     * Add media field to content
     *
     * @param array  $content    Content structure
     * @param string $media_url  URL of the media
     * @param string $media_type Type of media (image, video, audio)
     * @param string $field_id   Optional field identifier
     */
    protected function add_media_field(&$content, $media_url, $media_type, $field_id = null) {
        if (!$field_id) {
            $field_id = $media_type;
        }

        $content['fields'][] = array(
            'id' => $field_id,
            'type' => $media_type,
            'src' => $media_url
        );
    }

    /**
     * Add file field to content
     *
     * @param array  $content  Content structure
     * @param string $file_url URL of the file
     * @param string $field_id Optional field identifier
     */
    protected function add_file_field(&$content, $file_url, $field_id = 'attachment') {
        $content['fields'][] = array(
            'id' => $field_id,
            'type' => 'file',
            'src' => $file_url
        );
    }
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
        try {
            $user = get_userdata($user_id);
            if (!$user) {
                CheckStep_Logger::warning('User not found', array('user_id' => $user_id));
                return false;
            }

            $profile_data = array(
                'user_id' => $user_id,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'role' => implode(', ', $user->roles),
                'profile_picture' => get_avatar_url($user_id),
                'metadata' => $this->get_buddyboss_profile_data($user_id),
            );

            CheckStep_Logger::debug('User profile data retrieved', array(
                'user_id' => $user_id,
                'roles' => $user->roles
            ));

            return $profile_data;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get user profile', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
            return false;
        }
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
        try {
            $post = get_post($post_id);
            if (!$post) {
                CheckStep_Logger::warning('Post not found', array('post_id' => $post_id));
                return false;
            }

            $content_warnings = wp_get_post_terms($post_id, 'content-warning', array('fields' => 'names'));
            $author_data = $this->get_user_profile($post->post_author);
            $media_data = $this->get_post_media($post_id);

            $post_data = array(
                'post_id' => $post_id,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'author' => $author_data,
                'publish_date' => $post->post_date,
                'custom_taxonomies' => array(
                    'content_warnings' => $content_warnings,
                ),
                'fragments' => $media_data,
                'metadata' => get_post_meta($post_id),
            );

            CheckStep_Logger::debug('Blog post data retrieved', array(
                'post_id' => $post_id,
                'author_id' => $post->post_author,
                'media_count' => count($media_data)
            ));

            return $post_data;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get blog post', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
            return false;
        }
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
        try {
            $attachment = get_post($attachment_id);
            if (!$attachment) {
                CheckStep_Logger::warning('Media attachment not found', array(
                    'attachment_id' => $attachment_id
                ));
                return false;
            }

            $type = wp_attachment_is('video', $attachment_id) ? 'video' : 'image';
            $url = wp_get_attachment_url($attachment_id);

            if (!$url) {
                CheckStep_Logger::warning('Media URL not found', array(
                    'attachment_id' => $attachment_id,
                    'type' => $type
                ));
                return false;
            }

            $data = array(
                'id' => $attachment_id,
                'url' => $url,
                'title' => $attachment->post_title,
                'parent_content' => $attachment->post_parent,
            );

            if ($type === 'image') {
                $data['alt_text'] = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                $data['caption'] = $attachment->post_excerpt;
            }

            CheckStep_Logger::debug('Media data retrieved', array(
                'attachment_id' => $attachment_id,
                'type' => $type,
                'parent_id' => $attachment->post_parent
            ));

            return $data;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get media data', array(
                'error' => $e->getMessage(),
                'attachment_id' => $attachment_id
            ));
            return false;
        }
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
        try {
            if (!function_exists('bp_get_profile_field_data')) {
                CheckStep_Logger::debug('BuddyBoss profiles not active');
                return array();
            }

            $profile_data = array();
            $profile_groups = bp_xprofile_get_groups();

            if (empty($profile_groups)) {
                CheckStep_Logger::debug('No profile groups found', array('user_id' => $user_id));
                return array();
            }

            foreach ($profile_groups as $group) {
                foreach ($group->fields as $field) {
                    $field_data = bp_get_profile_field_data(array(
                        'field' => $field->id,
                        'user_id' => $user_id,
                    ));

                    if ($field_data) {
                        $profile_data[$field->name] = $field_data;
                    }
                }
            }

            CheckStep_Logger::debug('Profile field data retrieved', array(
                'user_id' => $user_id,
                'field_count' => count($profile_data)
            ));

            return $profile_data;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get profile field data', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
            return array();
        }
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
        try {
            $attachments = get_attached_media('', $post_id);
            $media = array();

            foreach ($attachments as $attachment) {
                $media_data = $this->get_media_data($attachment->ID);
                if ($media_data) {
                    $media[] = $media_data;
                }
            }

            CheckStep_Logger::debug('Post media retrieved', array(
                'post_id' => $post_id,
                'attachment_count' => count($media)
            ));

            return $media;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get post media', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
            return array();
        }
    }
}
?>