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
            if (!function_exists('bp_activity_get')) {
                CheckStep_Logger::warning('BuddyBoss activity component not active');
                return false;
            }

            $activity = bp_activity_get(array('in' => array($activity_id)));
            if (empty($activity['activities'])) {
                CheckStep_Logger::warning('Activity not found', array('activity_id' => $activity_id));
                return false;
            }

            $activity = $activity['activities'][0];
            $content = $this->get_base_content_structure($activity_id, 'activity');

            // Set author information
            $content['author'] = array(
                'id' => $activity->user_id,
                'name' => bp_core_get_user_displayname($activity->user_id),
                'role' => $this->get_user_role($activity->user_id)
            );

            // Add text content
            if (!empty($activity->content)) {
                $this->add_text_field($content, $activity->content);
            }

            // If it's a group activity, add group context
            if ($activity->component === 'groups' && !empty($activity->item_id)) {
                $content['group_id'] = $activity->item_id;
                $group = groups_get_group($activity->item_id);
                if ($group) {
                    $content['group_name'] = $group->name;
                }
            }

            // If it's a reply, set parent ID
            if (!empty($activity->secondary_item_id)) {
                $content['parent_id'] = $activity->secondary_item_id;
            }

            // Handle attached media
            if (function_exists('bp_activity_get_meta')) {
                // Check for attached images
                $media_ids = bp_activity_get_meta($activity_id, 'bp_media_ids', true);
                if (!empty($media_ids)) {
                    $media_ids = explode(',', $media_ids);
                    foreach ($media_ids as $media_id) {
                        if ($media = bp_get_media($media_id)) {
                            $this->add_media_field(
                                $content,
                                $media->attachment_data->full,
                                'image',
                                'media_' . $media_id
                            );
                        }
                    }
                }

                // Check for attached videos
                $video_ids = bp_activity_get_meta($activity_id, 'bp_video_ids', true);
                if (!empty($video_ids)) {
                    $video_ids = explode(',', $video_ids);
                    foreach ($video_ids as $video_id) {
                        if ($video = bp_get_video($video_id)) {
                            $this->add_media_field(
                                $content,
                                $video->attachment_data->full,
                                'video',
                                'video_' . $video_id
                            );
                        }
                    }
                }

                // Check for attached documents
                $document_ids = bp_activity_get_meta($activity_id, 'bp_document_ids', true);
                if (!empty($document_ids)) {
                    $document_ids = explode(',', $document_ids);
                    foreach ($document_ids as $document_id) {
                        if ($document = bp_get_document($document_id)) {
                            $this->add_file_field(
                                $content,
                                $document->attachment_data->url,
                                'document_' . $document_id
                            );
                        }
                    }
                }
            }

            CheckStep_Logger::debug('Activity post data retrieved', array(
                'activity_id' => $activity_id,
                'user_id' => $activity->user_id,
                'has_media' => !empty($media_ids),
                'has_videos' => !empty($video_ids),
                'has_documents' => !empty($document_ids)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get activity post', array(
                'error' => $e->getMessage(),
                'activity_id' => $activity_id
            ));
            return false;
        }
    }

    /**
     * Get user role
     *
     * Helper function to get the user's primary role
     *
     * @param int $user_id User ID
     * @return string User's primary role or empty string if not found
     */
    private function get_user_role($user_id) {
        $user = get_userdata($user_id);
        if ($user && !empty($user->roles)) {
            return reset($user->roles); // Get the first role
        }
        return '';
    }

    /**
     * Format forum post
     *
     * @param int $post_id Forum post ID
     * @return array|false Formatted forum post data or false on failure
     */
    public function get_forum_post($post_id) {
        try {
            if (!function_exists('bbp_get_reply')) {
                CheckStep_Logger::warning('BuddyBoss forums not active');
                return false;
            }

            $forum_post = bbp_get_reply($post_id);
            if (!$forum_post) {
                CheckStep_Logger::warning('Forum post not found', array('post_id' => $post_id));
                return false;
            }

            $content = $this->get_base_content_structure($post_id, 'forum');

            // Set author information
            $content['author'] = array(
                'id' => $forum_post->post_author,
                'name' => bp_core_get_user_displayname($forum_post->post_author),
                'role' => $this->get_user_role($forum_post->post_author)
            );

            // Add text content
            if (!empty($forum_post->post_content)) {
                $this->add_text_field($content, $forum_post->post_content);
            }

            // Set parent thread ID
            if (function_exists('bbp_get_reply_thread_id')) {
                $content['parent_id'] = bbp_get_reply_thread_id($post_id);
            }

            // Set forum ID if available
            if (function_exists('bbp_get_reply_forum_id')) {
                $content['forum_id'] = bbp_get_reply_forum_id($post_id);
            }

            // Handle attached media
            if (function_exists('bp_get_forum_media_ids')) {
                // Check for attached images
                $media_ids = bp_get_forum_media_ids($post_id);
                if (!empty($media_ids)) {
                    $media_ids = explode(',', $media_ids);
                    foreach ($media_ids as $media_id) {
                        if ($media = bp_get_media($media_id)) {
                            $this->add_media_field(
                                $content,
                                $media->attachment_data->full,
                                'image',
                                'media_' . $media_id
                            );
                        }
                    }
                }

                // Check for attached videos
                $video_ids = bp_get_forum_video_ids($post_id);
                if (!empty($video_ids)) {
                    $video_ids = explode(',', $video_ids);
                    foreach ($video_ids as $video_id) {
                        if ($video = bp_get_video($video_id)) {
                            $this->add_media_field(
                                $content,
                                $video->attachment_data->full,
                                'video',
                                'video_' . $video_id
                            );
                        }
                    }
                }

                // Check for attached documents
                $document_ids = bp_get_forum_document_ids($post_id);
                if (!empty($document_ids)) {
                    $document_ids = explode(',', $document_ids);
                    foreach ($document_ids as $document_id) {
                        if ($document = bp_get_document($document_id)) {
                            $this->add_file_field(
                                $content,
                                $document->attachment_data->url,
                                'document_' . $document_id
                            );
                        }
                    }
                }
            }

            CheckStep_Logger::debug('Forum post data retrieved', array(
                'post_id' => $post_id,
                'author_id' => $forum_post->post_author,
                'has_media' => !empty($media_ids),
                'has_videos' => !empty($video_ids),
                'has_documents' => !empty($document_ids)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get forum post', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
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
            if (!function_exists('groups_get_group_post')) {
                CheckStep_Logger::warning('BuddyBoss groups discussions not active');
                return false;
            }

            $discussion = groups_get_group_post($discussion_id);
            if (!$discussion) {
                CheckStep_Logger::warning('Group discussion not found', array('discussion_id' => $discussion_id));
                return false;
            }

            $content = $this->get_base_content_structure($discussion_id, 'discussion');

            // Set author information
            $content['author'] = array(
                'id' => $discussion->post_author,
                'name' => bp_core_get_user_displayname($discussion->post_author),
                'role' => $this->get_user_role($discussion->post_author)
            );

            // Add text content
            if (!empty($discussion->post_content)) {
                $this->add_text_field($content, $discussion->post_content);
            }

            // Set group context
            if (!empty($discussion->group_id)) {
                $content['group_id'] = $discussion->group_id;
                $group = groups_get_group($discussion->group_id);
                if ($group) {
                    $content['group_name'] = $group->name;
                }
            }

            // Handle attached media (similar to forum posts)
            if (function_exists('bp_get_group_discussion_media_ids')) {
                // Images
                $media_ids = bp_get_group_discussion_media_ids($discussion_id);
                if (!empty($media_ids)) {
                    $media_ids = explode(',', $media_ids);
                    foreach ($media_ids as $media_id) {
                        if ($media = bp_get_media($media_id)) {
                            $this->add_media_field(
                                $content,
                                $media->attachment_data->full,
                                'image',
                                'media_' . $media_id
                            );
                        }
                    }
                }

                // Videos
                $video_ids = bp_get_group_discussion_video_ids($discussion_id);
                if (!empty($video_ids)) {
                    $video_ids = explode(',', $video_ids);
                    foreach ($video_ids as $video_id) {
                        if ($video = bp_get_video($video_id)) {
                            $this->add_media_field(
                                $content,
                                $video->attachment_data->full,
                                'video',
                                'video_' . $video_id
                            );
                        }
                    }
                }

                // Documents
                $document_ids = bp_get_group_discussion_document_ids($discussion_id);
                if (!empty($document_ids)) {
                    $document_ids = explode(',', $document_ids);
                    foreach ($document_ids as $document_id) {
                        if ($document = bp_get_document($document_id)) {
                            $this->add_file_field(
                                $content,
                                $document->attachment_data->url,
                                'document_' . $document_id
                            );
                        }
                    }
                }
            }

            CheckStep_Logger::debug('Group discussion data retrieved', array(
                'discussion_id' => $discussion_id,
                'author_id' => $discussion->post_author,
                'group_id' => $discussion->group_id,
                'has_media' => !empty($media_ids),
                'has_videos' => !empty($video_ids),
                'has_documents' => !empty($document_ids)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get group discussion', array(
                'error' => $e->getMessage(),
                'discussion_id' => $discussion_id
            ));
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
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'post') {
                CheckStep_Logger::warning('Blog post not found or invalid type', array('post_id' => $post_id));
                return false;
            }

            $content = $this->get_base_content_structure($post_id, 'blog');

            // Set author information
            $content['author'] = array(
                'id' => $post->post_author,
                'name' => bp_core_get_user_displayname($post->post_author),
                'role' => $this->get_user_role($post->post_author)
            );

            // Add title as a separate field
            if (!empty($post->post_title)) {
                $this->add_text_field($content, $post->post_title, 'title');
            }

            // Add main content
            if (!empty($post->post_content)) {
                $this->add_text_field($content, $post->post_content);
            }

            // Get post taxonomies including content warnings
            $taxonomies = wp_get_post_terms($post_id, 'content-warning', array('fields' => 'names'));
            if (!empty($taxonomies)) {
                $content['taxonomies'] = $taxonomies;
            }

            // Handle attached media
            $attachments = get_attached_media('', $post_id);
            foreach ($attachments as $attachment) {
                $mime_type = get_post_mime_type($attachment->ID);
                if (strpos($mime_type, 'image/') === 0) {
                    $this->add_media_field(
                        $content,
                        wp_get_attachment_url($attachment->ID),
                        'image',
                        'media_' . $attachment->ID
                    );
                } elseif (strpos($mime_type, 'video/') === 0) {
                    $this->add_media_field(
                        $content,
                        wp_get_attachment_url($attachment->ID),
                        'video',
                        'video_' . $attachment->ID
                    );
                } else {
                    $this->add_file_field(
                        $content,
                        wp_get_attachment_url($attachment->ID),
                        'attachment_' . $attachment->ID
                    );
                }
            }

            CheckStep_Logger::debug('Blog post data retrieved', array(
                'post_id' => $post_id,
                'author_id' => $post->post_author,
                'has_taxonomies' => !empty($taxonomies),
                'attachment_count' => count($attachments)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get blog post', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
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
            $attachment = get_post($attachment_id);
            if (!$attachment || strpos(get_post_mime_type($attachment_id), 'image/') !== 0) {
                CheckStep_Logger::warning('Image attachment not found or invalid type', array(
                    'attachment_id' => $attachment_id
                ));
                return false;
            }

            $content = $this->get_base_content_structure($attachment_id, 'image');

            // Set author information
            $content['author'] = array(
                'id' => $attachment->post_author,
                'name' => bp_core_get_user_displayname($attachment->post_author),
                'role' => $this->get_user_role($attachment->post_author)
            );

            // Set parent content if this is an attachment
            if ($attachment->post_parent) {
                $content['parent_id'] = $attachment->post_parent;
            }

            // Add image data
            $this->add_media_field(
                $content,
                wp_get_attachment_url($attachment_id),
                'image',
                'primary_image'
            );

            // Add alt text if available
            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (!empty($alt_text)) {
                $this->add_text_field($content, $alt_text, 'alt_text');
            }

            // Add caption if available
            if (!empty($attachment->post_excerpt)) {
                $this->add_text_field($content, $attachment->post_excerpt, 'caption');
            }

            CheckStep_Logger::debug('Image upload data retrieved', array(
                'attachment_id' => $attachment_id,
                'author_id' => $attachment->post_author,
                'has_alt_text' => !empty($alt_text),
                'has_caption' => !empty($attachment->post_excerpt)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get image upload', array(
                'error' => $e->getMessage(),
                'attachment_id' => $attachment_id
            ));
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
            $attachment = get_post($attachment_id);
            if (!$attachment || strpos(get_post_mime_type($attachment_id), 'video/') !== 0) {
                CheckStep_Logger::warning('Video attachment not found or invalid type', array(
                    'attachment_id' => $attachment_id
                ));
                return false;
            }

            $content = $this->get_base_content_structure($attachment_id, 'video');

            // Set author information
            $content['author'] = array(
                'id' => $attachment->post_author,
                'name' => bp_core_get_user_displayname($attachment->post_author),
                'role' => $this->get_user_role($attachment->post_author)
            );

            // Set parent content if this is an attachment
            if ($attachment->post_parent) {
                $content['parent_id'] = $attachment->post_parent;
            }

            // Add video data
            $this->add_media_field(
                $content,
                wp_get_attachment_url($attachment_id),
                'video',
                'primary_video'
            );

            // Add title if available
            if (!empty($attachment->post_title)) {
                $this->add_text_field($content, $attachment->post_title, 'title');
            }

            // Add description if available
            if (!empty($attachment->post_content)) {
                $this->add_text_field($content, $attachment->post_content, 'description');
            }

            CheckStep_Logger::debug('Video upload data retrieved', array(
                'attachment_id' => $attachment_id,
                'author_id' => $attachment->post_author,
                'has_title' => !empty($attachment->post_title),
                'has_description' => !empty($attachment->post_content)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get video upload', array(
                'error' => $e->getMessage(),
                'attachment_id' => $attachment_id
            ));
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