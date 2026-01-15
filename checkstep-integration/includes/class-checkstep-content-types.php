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
     * Format image upload for CheckStep API
     *
     * Required fields:
     * - image_id: Unique ID or attachment ID
     * - url: Direct URL to the image
     * - alt_text: Accessibility text
     * - caption: Optional caption
     * - parent_content: ID of the content where the image is used
     *
     * @since 1.0.0
     * @since 1.0.10 Updated to use CheckStep API format
     * @param int $attachment_id Image attachment ID
     * @return array|false CheckStep-formatted image data or false on failure
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

            $author_id = $attachment->post_author;
            $url = wp_get_attachment_url($attachment_id);

            if (!$url) {
                CheckStep_Logger::warning('Image URL not found', array(
                    'attachment_id' => $attachment_id
                ));
                return false;
            }

            $fields = array();

            $fields[] = array(
                'id' => 'url',
                'type' => 'image',
                'src' => $url
            );

            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            if (!empty($alt_text)) {
                $fields[] = array(
                    'id' => 'alt_text',
                    'type' => 'text',
                    'src' => $alt_text
                );
            }

            if (!empty($attachment->post_excerpt)) {
                $fields[] = array(
                    'id' => 'caption',
                    'type' => 'text',
                    'src' => $attachment->post_excerpt
                );
            }

            if (!empty($attachment->post_title)) {
                $fields[] = array(
                    'id' => 'title',
                    'type' => 'text',
                    'src' => $attachment->post_title
                );
            }

            if (!empty($attachment->post_content)) {
                $fields[] = array(
                    'id' => 'description',
                    'type' => 'text',
                    'src' => $attachment->post_content
                );
            }

            $content = array(
                'id' => (string) $attachment_id,
                'author' => (string) $author_id,
                'type' => 'image',
                'fields' => $fields
            );

            if ($attachment->post_parent) {
                $parent_type = get_post_type($attachment->post_parent);
                $content['parent'] = array(
                    'type' => $parent_type === 'topic' ? 'thread' : 'post',
                    'id' => (string) $attachment->post_parent
                );
            }

            $metadata = array();

            $metadata[] = array(
                'id' => 'image_id',
                'value' => (string) $attachment_id
            );

            $metadata[] = array(
                'id' => 'platform',
                'value' => 'buddyboss'
            );

            $metadata[] = array(
                'id' => 'upload_date',
                'value' => $attachment->post_date
            );

            $metadata[] = array(
                'id' => 'mime_type',
                'value' => get_post_mime_type($attachment_id)
            );

            if ($attachment->post_parent) {
                $metadata[] = array(
                    'id' => 'parent_content',
                    'value' => (string) $attachment->post_parent
                );

                $parent_type = get_post_type($attachment->post_parent);
                if ($parent_type) {
                    $metadata[] = array(
                        'id' => 'parent_type',
                        'value' => $parent_type
                    );
                }
            }

            $user = get_userdata($author_id);
            if ($user) {
                $metadata[] = array(
                    'id' => 'author_name',
                    'value' => $user->display_name
                );
            }

            $image_meta = wp_get_attachment_metadata($attachment_id);
            if (!empty($image_meta)) {
                if (!empty($image_meta['width']) && !empty($image_meta['height'])) {
                    $metadata[] = array(
                        'id' => 'dimensions',
                        'value' => $image_meta['width'] . 'x' . $image_meta['height']
                    );
                }
                if (!empty($image_meta['filesize'])) {
                    $metadata[] = array(
                        'id' => 'file_size',
                        'value' => size_format($image_meta['filesize'])
                    );
                }
            }

            $content['metadata'] = $metadata;

            CheckStep_Logger::debug('Image data formatted for CheckStep', array(
                'attachment_id' => $attachment_id,
                'author_id' => $author_id,
                'has_alt_text' => !empty($alt_text),
                'has_caption' => !empty($attachment->post_excerpt),
                'parent_id' => $attachment->post_parent
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
     * Format video upload for CheckStep API
     *
     * Required fields:
     * - video_id: Unique identifier
     * - url: URL or embed link
     * - title: Title or description
     * - parent_content: ID of the associated blog or forum post
     *
     * @since 1.0.0
     * @since 1.0.10 Updated to use CheckStep API format
     * @param int $attachment_id Video attachment ID
     * @return array|false CheckStep-formatted video data or false on failure
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

            $author_id = $attachment->post_author;
            $url = wp_get_attachment_url($attachment_id);

            if (!$url) {
                CheckStep_Logger::warning('Video URL not found', array(
                    'attachment_id' => $attachment_id
                ));
                return false;
            }

            $fields = array();

            $fields[] = array(
                'id' => 'url',
                'type' => 'video',
                'src' => $url
            );

            if (!empty($attachment->post_title)) {
                $fields[] = array(
                    'id' => 'title',
                    'type' => 'text',
                    'src' => $attachment->post_title
                );
            }

            if (!empty($attachment->post_content)) {
                $fields[] = array(
                    'id' => 'description',
                    'type' => 'text',
                    'src' => $attachment->post_content
                );
            }

            if (!empty($attachment->post_excerpt)) {
                $fields[] = array(
                    'id' => 'caption',
                    'type' => 'text',
                    'src' => $attachment->post_excerpt
                );
            }

            $content = array(
                'id' => (string) $attachment_id,
                'author' => (string) $author_id,
                'type' => 'video',
                'fields' => $fields
            );

            if ($attachment->post_parent) {
                $parent_type = get_post_type($attachment->post_parent);
                $content['parent'] = array(
                    'type' => $parent_type === 'topic' ? 'thread' : 'post',
                    'id' => (string) $attachment->post_parent
                );
            }

            $metadata = array();

            $metadata[] = array(
                'id' => 'video_id',
                'value' => (string) $attachment_id
            );

            $metadata[] = array(
                'id' => 'platform',
                'value' => 'buddyboss'
            );

            $metadata[] = array(
                'id' => 'upload_date',
                'value' => $attachment->post_date
            );

            $metadata[] = array(
                'id' => 'mime_type',
                'value' => get_post_mime_type($attachment_id)
            );

            if ($attachment->post_parent) {
                $metadata[] = array(
                    'id' => 'parent_content',
                    'value' => (string) $attachment->post_parent
                );

                $parent_type = get_post_type($attachment->post_parent);
                if ($parent_type) {
                    $metadata[] = array(
                        'id' => 'parent_type',
                        'value' => $parent_type
                    );
                }
            }

            $user = get_userdata($author_id);
            if ($user) {
                $metadata[] = array(
                    'id' => 'author_name',
                    'value' => $user->display_name
                );
            }

            $video_meta = wp_get_attachment_metadata($attachment_id);
            if (!empty($video_meta)) {
                if (!empty($video_meta['length_formatted'])) {
                    $metadata[] = array(
                        'id' => 'duration',
                        'value' => $video_meta['length_formatted']
                    );
                }
                if (!empty($video_meta['width']) && !empty($video_meta['height'])) {
                    $metadata[] = array(
                        'id' => 'dimensions',
                        'value' => $video_meta['width'] . 'x' . $video_meta['height']
                    );
                }
                if (!empty($video_meta['filesize'])) {
                    $metadata[] = array(
                        'id' => 'file_size',
                        'value' => size_format($video_meta['filesize'])
                    );
                }
            }

            $content['metadata'] = $metadata;

            CheckStep_Logger::debug('Video data formatted for CheckStep', array(
                'attachment_id' => $attachment_id,
                'author_id' => $author_id,
                'has_title' => !empty($attachment->post_title),
                'has_description' => !empty($attachment->post_content),
                'parent_id' => $attachment->post_parent
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
     * Get user profile data formatted for CheckStep API
     *
     * Retrieves and formats user profile information including BuddyBoss extended profile fields.
     * Uses CheckStep's complex type 'user' with comprehensive field structure.
     *
     * Required fields:
     * - user_id: Unique identifier from WordPress
     * - display_name: User's public display name
     * - email: Email address (if permitted by privacy settings)
     * - role: User's role (subscriber, contributor, moderator, etc.)
     * - profile_picture: URL to the profile image
     * - metadata: Additional BuddyBoss profile fields (bio, social links)
     *
     * @since 1.0.0
     * @since 1.0.10 Updated to use CheckStep API format
     * @param int $user_id WordPress user ID
     * @return array|false CheckStep-formatted user profile data or false if user not found
     */
    public function get_user_profile($user_id) {
        try {
            $user = get_userdata($user_id);
            if (!$user) {
                CheckStep_Logger::warning('User not found', array('user_id' => $user_id));
                return false;
            }

            $fields = array();

            $fields[] = array(
                'id' => 'display_name',
                'type' => 'text',
                'src' => $user->display_name
            );

            if (!empty($user->user_email)) {
                $fields[] = array(
                    'id' => 'email',
                    'type' => 'text',
                    'src' => $user->user_email
                );
            }

            $fields[] = array(
                'id' => 'role',
                'type' => 'text',
                'src' => implode(', ', $user->roles)
            );

            $avatar_url = get_avatar_url($user_id, array('size' => 256));
            if ($avatar_url) {
                $fields[] = array(
                    'id' => 'profile_picture',
                    'type' => 'image',
                    'src' => $avatar_url
                );
            }

            if (!empty($user->user_url)) {
                $fields[] = array(
                    'id' => 'website',
                    'type' => 'text',
                    'src' => $user->user_url
                );
            }

            if (!empty($user->description)) {
                $fields[] = array(
                    'id' => 'bio',
                    'type' => 'text',
                    'src' => $user->description
                );
            }

            $content = array(
                'id' => (string) $user_id,
                'author' => (string) $user_id,
                'type' => 'user',
                'fields' => $fields
            );

            $metadata = array();

            $metadata[] = array(
                'id' => 'user_id',
                'value' => (string) $user_id
            );

            $metadata[] = array(
                'id' => 'username',
                'value' => $user->user_login
            );

            $metadata[] = array(
                'id' => 'registration_date',
                'value' => $user->user_registered
            );

            $metadata[] = array(
                'id' => 'platform',
                'value' => 'buddyboss'
            );

            $profile_url = function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($user_id) : get_author_posts_url($user_id);
            if ($profile_url) {
                $metadata[] = array(
                    'id' => 'profile_url',
                    'value' => $profile_url
                );
            }

            $bb_profile_data = $this->get_buddyboss_profile_data($user_id);
            if (!empty($bb_profile_data)) {
                foreach ($bb_profile_data as $field_name => $field_value) {
                    if (!empty($field_value)) {
                        if (filter_var($field_value, FILTER_VALIDATE_URL)) {
                            $fields[] = array(
                                'id' => sanitize_title($field_name),
                                'type' => 'text',
                                'src' => $field_value
                            );
                        } else {
                            $metadata[] = array(
                                'id' => sanitize_title($field_name),
                                'value' => is_array($field_value) ? implode(', ', $field_value) : $field_value
                            );
                        }
                    }
                }
                $content['fields'] = $fields;
            }

            $content['metadata'] = $metadata;

            CheckStep_Logger::debug('User profile data formatted for CheckStep', array(
                'user_id' => $user_id,
                'roles' => $user->roles,
                'field_count' => count($fields),
                'metadata_count' => count($metadata)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get user profile', array(
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ));
            return false;
        }
    }

    /**
     * Get blog post data formatted for CheckStep API
     *
     * Retrieves and formats blog post content including metadata and media attachments.
     * Uses CheckStep's complex type 'post' with comprehensive field structure.
     *
     * Required fields:
     * - post_id: Unique ID
     * - title: Title of the post
     * - content: Main body of text
     * - author: Reference to the User Profile
     * - publish_date: Timestamp
     * - custom_taxonomies: Includes content-warning taxonomy if set
     * - fragments: References (URLs or attachment IDs) for embedded images/videos
     * - metadata: Custom fields from BuddyPress User Blog if applicable
     *
     * @since 1.0.0
     * @since 1.0.10 Updated to use CheckStep API format
     * @param int $post_id Post ID
     * @return array|false CheckStep-formatted post data or false if post not found
     */
    public function get_blog_post($post_id) {
        try {
            $post = get_post($post_id);
            if (!$post) {
                CheckStep_Logger::warning('Post not found', array('post_id' => $post_id));
                return false;
            }

            $author_id = $post->post_author;
            $fields = array();

            if (!empty($post->post_title)) {
                $fields[] = array(
                    'id' => 'title',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($post->post_title)
                );
            }

            if (!empty($post->post_content)) {
                $fields[] = array(
                    'id' => 'body',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($post->post_content)
                );
            }

            if (!empty($post->post_excerpt)) {
                $fields[] = array(
                    'id' => 'excerpt',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($post->post_excerpt)
                );
            }

            $fields = $this->add_blog_media_fragments($fields, $post_id);

            $content = array(
                'id' => (string) $post_id,
                'author' => (string) $author_id,
                'type' => 'post',
                'fields' => $fields
            );

            $metadata = array();

            $metadata[] = array(
                'id' => 'post_id',
                'value' => (string) $post_id
            );

            $metadata[] = array(
                'id' => 'publish_date',
                'value' => $post->post_date
            );

            $metadata[] = array(
                'id' => 'modified_date',
                'value' => $post->post_modified
            );

            $metadata[] = array(
                'id' => 'post_status',
                'value' => $post->post_status
            );

            $metadata[] = array(
                'id' => 'platform',
                'value' => 'buddyboss'
            );

            $user = get_userdata($author_id);
            if ($user) {
                $metadata[] = array(
                    'id' => 'author_name',
                    'value' => $user->display_name
                );

                $metadata[] = array(
                    'id' => 'author_role',
                    'value' => implode(', ', $user->roles)
                );
            }

            $content_warnings = wp_get_post_terms($post_id, 'content-warning', array('fields' => 'names'));
            if (!empty($content_warnings) && !is_wp_error($content_warnings)) {
                $metadata[] = array(
                    'id' => 'content_warnings',
                    'value' => implode(', ', $content_warnings)
                );
            }

            $categories = wp_get_post_categories($post_id, array('fields' => 'names'));
            if (!empty($categories) && !is_wp_error($categories)) {
                $metadata[] = array(
                    'id' => 'categories',
                    'value' => implode(', ', $categories)
                );
            }

            $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
            if (!empty($tags) && !is_wp_error($tags)) {
                $metadata[] = array(
                    'id' => 'tags',
                    'value' => implode(', ', $tags)
                );
            }

            $post_url = get_permalink($post_id);
            if ($post_url) {
                $metadata[] = array(
                    'id' => 'source_url',
                    'value' => $post_url
                );
            }

            $post_meta = get_post_meta($post_id);
            if (!empty($post_meta)) {
                foreach ($post_meta as $meta_key => $meta_values) {
                    if (strpos($meta_key, '_') !== 0 && !empty($meta_values[0])) {
                        $metadata[] = array(
                            'id' => sanitize_title($meta_key),
                            'value' => is_array($meta_values[0]) ? wp_json_encode($meta_values[0]) : $meta_values[0]
                        );
                    }
                }
            }

            $content['metadata'] = $metadata;

            CheckStep_Logger::debug('Blog post data formatted for CheckStep', array(
                'post_id' => $post_id,
                'author_id' => $author_id,
                'field_count' => count($fields),
                'metadata_count' => count($metadata)
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
     * Add blog media fragments to content fields
     *
     * Extracts embedded images, videos, and other media from blog posts.
     *
     * @since 1.0.10
     * @access private
     * @param array $fields  Existing fields array
     * @param int   $post_id Blog post ID
     * @return array Updated fields array with media fragments
     */
    private function add_blog_media_fragments($fields, $post_id) {
        try {
            $featured_image_id = get_post_thumbnail_id($post_id);
            if ($featured_image_id) {
                $featured_url = wp_get_attachment_url($featured_image_id);
                if ($featured_url) {
                    $fields[] = array(
                        'id' => 'featured_image',
                        'type' => 'image',
                        'src' => $featured_url
                    );
                }
            }

            $attachments = get_attached_media('', $post_id);
            $image_count = 0;
            $video_count = 0;
            $document_count = 0;

            foreach ($attachments as $attachment) {
                $mime_type = get_post_mime_type($attachment->ID);
                $url = wp_get_attachment_url($attachment->ID);

                if (!$url || $attachment->ID == $featured_image_id) {
                    continue;
                }

                if (strpos($mime_type, 'image/') === 0) {
                    $image_count++;
                    $fields[] = array(
                        'id' => 'image_' . $image_count,
                        'type' => 'image',
                        'src' => $url
                    );
                } elseif (strpos($mime_type, 'video/') === 0) {
                    $video_count++;
                    $fields[] = array(
                        'id' => 'video_' . $video_count,
                        'type' => 'video',
                        'src' => $url
                    );
                } else {
                    $document_count++;
                    $fields[] = array(
                        'id' => 'document_' . $document_count,
                        'type' => 'text',
                        'src' => '[Document: ' . basename($url) . '] ' . $url
                    );
                }
            }

            $post = get_post($post_id);
            if ($post && !empty($post->post_content)) {
                preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $embedded_images);
                if (!empty($embedded_images[1])) {
                    foreach ($embedded_images[1] as $img_url) {
                        $image_count++;
                        $fields[] = array(
                            'id' => 'embedded_image_' . $image_count,
                            'type' => 'image',
                            'src' => $img_url
                        );
                    }
                }

                preg_match_all('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $post->post_content, $youtube_videos);
                if (!empty($youtube_videos[1])) {
                    foreach ($youtube_videos[1] as $video_id) {
                        $video_count++;
                        $fields[] = array(
                            'id' => 'youtube_video_' . $video_count,
                            'type' => 'video',
                            'src' => 'https://www.youtube.com/watch?v=' . $video_id
                        );
                    }
                }

                preg_match_all('/vimeo\.com\/(\d+)/i', $post->post_content, $vimeo_videos);
                if (!empty($vimeo_videos[1])) {
                    foreach ($vimeo_videos[1] as $video_id) {
                        $video_count++;
                        $fields[] = array(
                            'id' => 'vimeo_video_' . $video_count,
                            'type' => 'video',
                            'src' => 'https://vimeo.com/' . $video_id
                        );
                    }
                }
            }

        } catch (Exception $e) {
            CheckStep_Logger::warning('Error adding blog media fragments', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
        }

        return $fields;
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

    /**
     * Get forum topic data formatted for CheckStep API
     *
     * Retrieves and formats forum topic/discussion for CheckStep ingestion.
     * Uses CheckStep's complex type 'thread' with proper field structure.
     *
     * @since 1.0.9
     * @param int $topic_id Forum topic ID
     * @return array|false CheckStep-formatted topic data or false on failure
     */
    public function get_forum_topic($topic_id) {
        try {
            if (!function_exists('bbp_get_topic')) {
                CheckStep_Logger::warning('BuddyBoss forums not active');
                return false;
            }

            $topic = bbp_get_topic($topic_id);
            if (!$topic) {
                CheckStep_Logger::warning('Forum topic not found', array('topic_id' => $topic_id));
                return false;
            }

            $author_id = $topic->post_author;
            $forum_id = function_exists('bbp_get_topic_forum_id') ? bbp_get_topic_forum_id($topic_id) : 0;

            $fields = array();

            if (!empty($topic->post_title)) {
                $fields[] = array(
                    'id' => 'title',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($topic->post_title)
                );
            }

            // Use bbp_get_topic_content() for proper content retrieval per BuddyBoss docs
            $topic_content = function_exists('bbp_get_topic_content') 
                ? bbp_get_topic_content($topic_id) 
                : $topic->post_content;
            
            if (!empty($topic_content)) {
                $fields[] = array(
                    'id' => 'body',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($topic_content)
                );
            }

            $fields = $this->add_forum_media_fields($fields, $topic_id);

            $content = array(
                'id' => (string) $topic_id,
                'author' => (string) $author_id,
                'type' => 'thread',
                'fields' => $fields
            );

            if ($forum_id) {
                $content['parent'] = array(
                    'type' => 'channel',
                    'id' => (string) $forum_id
                );
            }

            $content['metadata'] = $this->build_forum_metadata($topic_id, $author_id, 'topic');

            CheckStep_Logger::debug('Forum topic data formatted for CheckStep', array(
                'topic_id' => $topic_id,
                'author_id' => $author_id,
                'forum_id' => $forum_id,
                'field_count' => count($fields)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get forum topic', array(
                'error' => $e->getMessage(),
                'topic_id' => $topic_id
            ));
            return false;
        }
    }

    /**
     * Get forum reply data formatted for CheckStep API
     *
     * Retrieves and formats forum reply for CheckStep ingestion.
     * Uses CheckStep's complex type 'reply' with parent relationship to topic.
     *
     * @since 1.0.9
     * @param int $reply_id Forum reply ID
     * @return array|false CheckStep-formatted reply data or false on failure
     */
    public function get_forum_reply($reply_id) {
        try {
            if (!function_exists('bbp_get_reply')) {
                CheckStep_Logger::warning('BuddyBoss forums not active');
                return false;
            }

            $reply = bbp_get_reply($reply_id);
            if (!$reply) {
                CheckStep_Logger::warning('Forum reply not found', array('reply_id' => $reply_id));
                return false;
            }

            $author_id = $reply->post_author;
            $topic_id = function_exists('bbp_get_reply_topic_id') ? bbp_get_reply_topic_id($reply_id) : 0;
            $forum_id = function_exists('bbp_get_reply_forum_id') ? bbp_get_reply_forum_id($reply_id) : 0;

            $fields = array();

            // Use bbp_get_reply_content() for proper content retrieval per BuddyBoss docs
            $reply_content = function_exists('bbp_get_reply_content') 
                ? bbp_get_reply_content($reply_id) 
                : $reply->post_content;
            
            if (!empty($reply_content)) {
                $fields[] = array(
                    'id' => 'body',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($reply_content)
                );
            }

            $fields = $this->add_forum_media_fields($fields, $reply_id);

            $content = array(
                'id' => (string) $reply_id,
                'author' => (string) $author_id,
                'type' => 'reply',
                'fields' => $fields
            );

            if ($topic_id) {
                $content['parent'] = array(
                    'type' => 'thread',
                    'id' => (string) $topic_id
                );
            }

            $content['metadata'] = $this->build_forum_metadata($reply_id, $author_id, 'reply', $topic_id, $forum_id);

            CheckStep_Logger::debug('Forum reply data formatted for CheckStep', array(
                'reply_id' => $reply_id,
                'author_id' => $author_id,
                'topic_id' => $topic_id,
                'forum_id' => $forum_id,
                'field_count' => count($fields)
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get forum reply', array(
                'error' => $e->getMessage(),
                'reply_id' => $reply_id
            ));
            return false;
        }
    }

    /**
     * Add forum media fields to content
     *
     * Extracts and formats media attachments from forum posts for CheckStep.
     *
     * @since 1.0.9
     * @access private
     * @param array $fields  Existing fields array
     * @param int   $post_id Forum post ID (topic or reply)
     * @return array Updated fields array with media
     */
    private function add_forum_media_fields($fields, $post_id) {
        if (!function_exists('bp_get_forum_media_ids')) {
            return $fields;
        }

        try {
            $media_ids = bp_get_forum_media_ids($post_id);
            if (!empty($media_ids)) {
                $media_ids = is_array($media_ids) ? $media_ids : explode(',', $media_ids);
                foreach ($media_ids as $index => $media_id) {
                    if (function_exists('bp_get_media') && ($media = bp_get_media($media_id))) {
                        $url = isset($media->attachment_data->full) ? $media->attachment_data->full : '';
                        if (!empty($url)) {
                            $fields[] = array(
                                'id' => 'image_' . ($index + 1),
                                'type' => 'image',
                                'src' => $url
                            );
                        }
                    }
                }
            }

            if (function_exists('bp_get_forum_video_ids')) {
                $video_ids = bp_get_forum_video_ids($post_id);
                if (!empty($video_ids)) {
                    $video_ids = is_array($video_ids) ? $video_ids : explode(',', $video_ids);
                    foreach ($video_ids as $index => $video_id) {
                        if (function_exists('bp_get_video') && ($video = bp_get_video($video_id))) {
                            $url = isset($video->attachment_data->full) ? $video->attachment_data->full : '';
                            if (!empty($url)) {
                                $fields[] = array(
                                    'id' => 'video_' . ($index + 1),
                                    'type' => 'video',
                                    'src' => $url
                                );
                            }
                        }
                    }
                }
            }

            if (function_exists('bp_get_forum_document_ids')) {
                $document_ids = bp_get_forum_document_ids($post_id);
                if (!empty($document_ids)) {
                    $document_ids = is_array($document_ids) ? $document_ids : explode(',', $document_ids);
                    foreach ($document_ids as $index => $document_id) {
                        if (function_exists('bp_get_document') && ($document = bp_get_document($document_id))) {
                            $url = isset($document->attachment_data->url) ? $document->attachment_data->url : '';
                            if (!empty($url)) {
                                $fields[] = array(
                                    'id' => 'document_' . ($index + 1),
                                    'type' => 'text',
                                    'src' => '[Document: ' . basename($url) . '] ' . $url
                                );
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            CheckStep_Logger::warning('Error adding forum media fields', array(
                'error' => $e->getMessage(),
                'post_id' => $post_id
            ));
        }

        return $fields;
    }

    /**
     * Build forum metadata for CheckStep
     *
     * Creates metadata array with additional context for moderation.
     * Includes required fields: forum_post_id, thread_id, timestamp, custom_taxonomies
     *
     * @since 1.0.9
     * @since 1.0.10 Added thread_id, timestamp, custom_taxonomies support
     * @access private
     * @param int    $content_id   Content ID
     * @param int    $author_id    Author user ID
     * @param string $content_type Content type (topic/reply)
     * @param int    $topic_id     Parent topic ID (for replies)
     * @param int    $forum_id     Forum ID
     * @return array Metadata array for CheckStep
     */
    private function build_forum_metadata($content_id, $author_id, $content_type, $topic_id = 0, $forum_id = 0) {
        $metadata = array();

        $metadata[] = array(
            'id' => 'forum_post_id',
            'value' => (string) $content_id
        );

        $metadata[] = array(
            'id' => 'content_type',
            'value' => $content_type
        );

        if ($topic_id) {
            $metadata[] = array(
                'id' => 'thread_id',
                'value' => (string) $topic_id
            );
        }

        if ($forum_id) {
            $metadata[] = array(
                'id' => 'forum_id',
                'value' => (string) $forum_id
            );
        }

        $metadata[] = array(
            'id' => 'platform',
            'value' => 'buddyboss'
        );

        $post = get_post($content_id);
        if ($post) {
            $metadata[] = array(
                'id' => 'timestamp',
                'value' => $post->post_date
            );

            $metadata[] = array(
                'id' => 'created_at',
                'value' => get_post_time('c', false, $content_id)
            );

            $metadata[] = array(
                'id' => 'modified_at',
                'value' => get_post_modified_time('c', false, $content_id)
            );
        } else {
            $metadata[] = array(
                'id' => 'created_at',
                'value' => current_time('c')
            );
        }

        $user = get_userdata($author_id);
        if ($user) {
            $metadata[] = array(
                'id' => 'author_name',
                'value' => $user->display_name
            );

            $metadata[] = array(
                'id' => 'author_role',
                'value' => implode(', ', $user->roles)
            );
        }

        if ($forum_id && function_exists('bbp_get_forum_title')) {
            $forum_title = bbp_get_forum_title($forum_id);
            if ($forum_title) {
                $metadata[] = array(
                    'id' => 'forum_name',
                    'value' => $forum_title
                );
            }
        }

        if ($topic_id && function_exists('bbp_get_topic_title')) {
            $topic_title = bbp_get_topic_title($topic_id);
            if ($topic_title) {
                $metadata[] = array(
                    'id' => 'topic_title',
                    'value' => $topic_title
                );
            }
        }

        $content_warnings = wp_get_post_terms($content_id, 'content-warning', array('fields' => 'names'));
        if (!empty($content_warnings) && !is_wp_error($content_warnings)) {
            $metadata[] = array(
                'id' => 'content_warnings',
                'value' => implode(', ', $content_warnings)
            );
        }

        $topic_tags = array();
        if ($content_type === 'topic' && function_exists('bbp_get_topic_tag_names')) {
            $topic_tags = bbp_get_topic_tag_names($content_id);
        } elseif ($content_type === 'reply' && $topic_id && function_exists('bbp_get_topic_tag_names')) {
            $topic_tags = bbp_get_topic_tag_names($topic_id);
        }
        if (!empty($topic_tags)) {
            $metadata[] = array(
                'id' => 'topic_tags',
                'value' => is_array($topic_tags) ? implode(', ', $topic_tags) : $topic_tags
            );
        }

        $site_url = get_permalink($content_id);
        if ($site_url) {
            $metadata[] = array(
                'id' => 'source_url',
                'value' => $site_url
            );
        }

        return $metadata;
    }

    /**
     * Get activity data (wrapper for get_activity_post)
     *
     * @since 1.0.11
     * @param int $activity_id Activity ID
     * @return array|false Formatted activity data or false on failure
     */
    public function get_activity($activity_id) {
        return $this->get_activity_post($activity_id);
    }

    /**
     * Get activity comment data
     *
     * @since 1.0.11
     * @param int $comment_id Activity comment ID
     * @return array|false Formatted comment data or false on failure
     */
    public function get_activity_comment($comment_id) {
        try {
            if (!function_exists('bp_activity_get')) {
                CheckStep_Logger::warning('BuddyBoss activity component not active');
                return false;
            }

            $comment = bp_activity_get(array('in' => array($comment_id)));
            if (empty($comment['activities'])) {
                CheckStep_Logger::warning('Activity comment not found', array('comment_id' => $comment_id));
                return false;
            }

            $comment = $comment['activities'][0];
            
            $fields = array();
            
            if (!empty($comment->content)) {
                $fields[] = array(
                    'id' => 'body',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($comment->content)
                );
            }

            $content = array(
                'id' => (string) $comment_id,
                'author' => (string) $comment->user_id,
                'type' => 'reply',
                'fields' => $fields
            );

            if (!empty($comment->item_id)) {
                $content['parent'] = array(
                    'type' => 'post',
                    'id' => (string) $comment->item_id
                );
            }

            $metadata = array();
            $metadata[] = array('id' => 'comment_id', 'value' => (string) $comment_id);
            $metadata[] = array('id' => 'platform', 'value' => 'buddyboss');
            $metadata[] = array('id' => 'timestamp', 'value' => $comment->date_recorded);
            $metadata[] = array('id' => 'activity_type', 'value' => $comment->type);

            $user = get_userdata($comment->user_id);
            if ($user) {
                $metadata[] = array('id' => 'author_name', 'value' => $user->display_name);
            }

            if (!empty($metadata)) {
                $content['metadata'] = $metadata;
            }

            CheckStep_Logger::debug('Activity comment data formatted for CheckStep', array(
                'comment_id' => $comment_id
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get activity comment', array(
                'error' => $e->getMessage(),
                'comment_id' => $comment_id
            ));
            return false;
        }
    }

    /**
     * Get BuddyBoss media data
     *
     * @since 1.0.11
     * @param int $media_id BuddyBoss media ID
     * @return array|false Formatted media data or false on failure
     */
    public function get_buddyboss_media($media_id) {
        try {
            if (!function_exists('bp_get_media')) {
                CheckStep_Logger::warning('BuddyBoss media component not active');
                return false;
            }

            $media = bp_get_media($media_id);
            if (!$media) {
                CheckStep_Logger::warning('BuddyBoss media not found', array('media_id' => $media_id));
                return false;
            }

            $attachment_id = isset($media->attachment_id) ? $media->attachment_id : 0;
            if (!$attachment_id) {
                return false;
            }

            $mime_type = get_post_mime_type($attachment_id);
            $is_video = strpos($mime_type, 'video') !== false;
            
            if ($is_video) {
                return $this->get_video_upload($attachment_id);
            } else {
                return $this->get_image_upload($attachment_id);
            }

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get BuddyBoss media', array(
                'error' => $e->getMessage(),
                'media_id' => $media_id
            ));
            return false;
        }
    }

    /**
     * Get private message data
     *
     * @since 1.0.11
     * @param int $message_id Message ID
     * @return array|false Formatted message data or false on failure
     */
    public function get_private_message($message_id) {
        try {
            if (!class_exists('BP_Messages_Message')) {
                CheckStep_Logger::warning('BuddyBoss messages component not active');
                return false;
            }

            $message = new BP_Messages_Message($message_id);
            if (!$message->id) {
                CheckStep_Logger::warning('Private message not found', array('message_id' => $message_id));
                return false;
            }

            $fields = array();
            
            if (!empty($message->subject)) {
                $fields[] = array(
                    'id' => 'subject',
                    'type' => 'text',
                    'src' => $message->subject
                );
            }

            if (!empty($message->message)) {
                $fields[] = array(
                    'id' => 'body',
                    'type' => 'text',
                    'src' => wp_strip_all_tags($message->message)
                );
            }

            $content = array(
                'id' => (string) $message_id,
                'author' => (string) $message->sender_id,
                'type' => 'message',
                'fields' => $fields
            );

            if (!empty($message->thread_id)) {
                $content['parent'] = array(
                    'type' => 'thread',
                    'id' => (string) $message->thread_id
                );
            }

            $metadata = array();
            $metadata[] = array('id' => 'message_id', 'value' => (string) $message_id);
            $metadata[] = array('id' => 'thread_id', 'value' => (string) $message->thread_id);
            $metadata[] = array('id' => 'platform', 'value' => 'buddyboss');
            $metadata[] = array('id' => 'timestamp', 'value' => $message->date_sent);

            $user = get_userdata($message->sender_id);
            if ($user) {
                $metadata[] = array('id' => 'sender_name', 'value' => $user->display_name);
            }

            $recipients = BP_Messages_Thread::get_recipients_for_thread($message->thread_id);
            if (!empty($recipients)) {
                $recipient_ids = array_keys($recipients);
                $metadata[] = array('id' => 'recipient_count', 'value' => (string) count($recipient_ids));
            }

            if (!empty($metadata)) {
                $content['metadata'] = $metadata;
            }

            CheckStep_Logger::debug('Private message data formatted for CheckStep', array(
                'message_id' => $message_id
            ));

            return $content;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get private message', array(
                'error' => $e->getMessage(),
                'message_id' => $message_id
            ));
            return false;
        }
    }
}
?>