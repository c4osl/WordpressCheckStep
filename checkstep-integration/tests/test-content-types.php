<?php
/**
 * Test content type formatting
 */

require_once dirname(__DIR__) . '/includes/class-checkstep-content-types.php';

// Mock CheckStep_Logger if not available
if (!class_exists('CheckStep_Logger')) {
    class CheckStep_Logger {
        public static function debug($message, $context = array()) {
            echo sprintf("[Debug] %s: %s\n", $message, json_encode($context));
        }

        public static function info($message, $context = array()) {
            echo sprintf("[Info] %s: %s\n", $message, json_encode($context));
        }

        public static function warning($message, $context = array()) {
            echo sprintf("[Warning] %s: %s\n", $message, json_encode($context));
        }

        public static function error($message, $context = array()) {
            echo sprintf("[Error] %s: %s\n", $message, json_encode($context));
        }
    }
}

// Mock WordPress functions
if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return (object)array(
            'roles' => array('subscriber')
        );
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        return (object)array(
            'ID' => $post_id,
            'post_author' => 1,
            'post_type' => 'post',
            'post_title' => 'Test Blog Post',
            'post_content' => 'Test blog content'
        );
    }
}

if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args) {
        return array('sensitive-content', 'mature-themes');
    }
}

if (!function_exists('get_attached_media')) {
    function get_attached_media($type, $post_id) {
        return array(
            (object)array(
                'ID' => 123,
                'post_mime_type' => 'image/jpeg'
            ),
            (object)array(
                'ID' => 456,
                'post_mime_type' => 'video/mp4'
            )
        );
    }
}

if (!function_exists('get_post_mime_type')) {
    function get_post_mime_type($attachment_id) {
        return $attachment_id === 123 ? 'image/jpeg' : 'video/mp4';
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id) {
        return "https://example.com/media/{$attachment_id}";
    }
}

// Mock BuddyBoss forum functions
if (!function_exists('bbp_get_reply')) {
    function bbp_get_reply($post_id) {
        return (object)array(
            'ID' => $post_id,
            'post_author' => 1,
            'post_content' => 'Test forum reply content'
        );
    }
}

if (!function_exists('bbp_get_reply_thread_id')) {
    function bbp_get_reply_thread_id($post_id) {
        return 789;
    }
}

if (!function_exists('bbp_get_reply_forum_id')) {
    function bbp_get_reply_forum_id($post_id) {
        return 101;
    }
}

// Mock BuddyBoss group discussion functions
if (!function_exists('groups_get_group_post')) {
    function groups_get_group_post($discussion_id) {
        return (object)array(
            'ID' => $discussion_id,
            'post_author' => 1,
            'post_content' => 'Test group discussion content',
            'group_id' => 202
        );
    }
}

// Mock BuddyBoss media functions
if (!function_exists('bp_get_forum_media_ids')) {
    function bp_get_forum_media_ids($post_id) {
        return '123,124';
    }
}

if (!function_exists('bp_get_forum_video_ids')) {
    function bp_get_forum_video_ids($post_id) {
        return '456';
    }
}

if (!function_exists('bp_get_forum_document_ids')) {
    function bp_get_forum_document_ids($post_id) {
        return '789';
    }
}

if (!function_exists('bp_get_group_discussion_media_ids')) {
    function bp_get_group_discussion_media_ids($discussion_id) {
        return '123,124';
    }
}

if (!function_exists('bp_get_group_discussion_video_ids')) {
    function bp_get_group_discussion_video_ids($discussion_id) {
        return '456';
    }
}

if (!function_exists('bp_get_group_discussion_document_ids')) {
    function bp_get_group_discussion_document_ids($discussion_id) {
        return '789';
    }
}

// Existing BuddyBoss activity mocks
if (!function_exists('bp_activity_get')) {
    function bp_activity_get($args) {
        return array(
            'activities' => array(
                (object)array(
                    'id' => 12345,
                    'user_id' => 1,
                    'content' => 'Test activity content',
                    'component' => 'activity',
                    'type' => 'activity_update',
                    'item_id' => 0,
                    'secondary_item_id' => 0
                )
            )
        );
    }
}

if (!function_exists('bp_core_get_user_displayname')) {
    function bp_core_get_user_displayname($user_id) {
        return "Test User {$user_id}";
    }
}

if (!function_exists('bp_activity_get_meta')) {
    function bp_activity_get_meta($activity_id, $key, $single = true) {
        $meta = array(
            'bp_media_ids' => '123,124',
            'bp_video_ids' => '456',
            'bp_document_ids' => '789'
        );
        return isset($meta[$key]) ? $meta[$key] : '';
    }
}

if (!function_exists('bp_get_media')) {
    function bp_get_media($media_id) {
        return (object)array(
            'id' => $media_id,
            'attachment_data' => (object)array(
                'full' => "https://example.com/media/{$media_id}.jpg"
            )
        );
    }
}

if (!function_exists('bp_get_video')) {
    function bp_get_video($video_id) {
        return (object)array(
            'id' => $video_id,
            'attachment_data' => (object)array(
                'full' => "https://example.com/video/{$video_id}.mp4"
            )
        );
    }
}

if (!function_exists('bp_get_document')) {
    function bp_get_document($document_id) {
        return (object)array(
            'id' => $document_id,
            'attachment_data' => (object)array(
                'url' => "https://example.com/document/{$document_id}.pdf"
            )
        );
    }
}

if (!function_exists('groups_get_group')) {
    function groups_get_group($group_id) {
        return (object)array(
            'id' => $group_id,
            'name' => "Test Group {$group_id}"
        );
    }
}

// Add mock for post metadata
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single = false) {
        if ($key === '_wp_attachment_image_alt') {
            return 'Test image alt text';
        }
        return '';
    }
}

echo "Testing content type formatting...\n\n";

try {
    $formatter = new CheckStep_Content_Types();

    // Test activity post formatting
    echo "Testing activity post formatting...\n";
    $activity_data = $formatter->get_activity_post(12345);
    if ($activity_data) {
        echo "✓ Successfully formatted activity post\n";
        echo "Activity data:\n";
        print_r($activity_data);
    } else {
        echo "✗ Failed to format activity post\n";
    }

    // Test forum post formatting
    echo "\nTesting forum post formatting...\n";
    $forum_data = $formatter->get_forum_post(67890);
    if ($forum_data) {
        echo "✓ Successfully formatted forum post\n";
        echo "Forum data:\n";
        print_r($forum_data);
    } else {
        echo "✗ Failed to format forum post\n";
    }

    // Test group discussion formatting
    echo "\nTesting group discussion formatting...\n";
    $discussion_data = $formatter->get_group_discussion(11111);
    if ($discussion_data) {
        echo "✓ Successfully formatted group discussion\n";
        echo "Discussion data:\n";
        print_r($discussion_data);
    } else {
        echo "✗ Failed to format group discussion\n";
    }

    // Test user blog post formatting
    echo "\nTesting user blog post formatting...\n";
    $blog_data = $formatter->get_user_blog_post(22222);
    if ($blog_data) {
        echo "✓ Successfully formatted blog post\n";
        echo "Blog data:\n";
        print_r($blog_data);
    } else {
        echo "✗ Failed to format blog post\n";
    }

    // Test standalone image upload formatting
    echo "\nTesting image upload formatting...\n";
    $image_data = $formatter->get_image_upload(33333);
    if ($image_data) {
        echo "✓ Successfully formatted image upload\n";
        echo "Image data:\n";
        print_r($image_data);
    } else {
        echo "✗ Failed to format image upload\n";
    }

    // Test standalone video upload formatting
    echo "\nTesting video upload formatting...\n";
    $video_data = $formatter->get_video_upload(44444);
    if ($video_data) {
        echo "✓ Successfully formatted video upload\n";
        echo "Video data:\n";
        print_r($video_data);
    } else {
        echo "✗ Failed to format video upload\n";
    }

    echo "\nContent type formatting tests completed.\n";

} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}

?>