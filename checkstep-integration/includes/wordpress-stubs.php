<?php
/**
 * WordPress Function Stubs
 * 
 * This file contains stub implementations of WordPress functions used in the plugin.
 * These stubs allow for development and testing without a full WordPress environment.
 * 
 * @package CheckStep_Integration
 */

// Test state tracking
$GLOBALS['_wp_test_state'] = array(
    'deleted_posts' => array(),
    'hidden_posts' => array(),
    'content_warnings' => array(),
    'banned_users' => array(),
    'options' => array(),
);

if (!function_exists('update_stub_option')) {
    function update_stub_option($option, $value) {
        $GLOBALS['_wp_test_state']['options'][$option] = $value;
    }
}

if (!function_exists('stub_post_was_deleted')) {
    function stub_post_was_deleted($post_id) {
        return in_array($post_id, $GLOBALS['_wp_test_state']['deleted_posts']);
    }
}

if (!function_exists('stub_post_was_hidden')) {
    function stub_post_was_hidden($post_id) {
        return in_array($post_id, $GLOBALS['_wp_test_state']['hidden_posts']);
    }
}

if (!function_exists('stub_get_content_warning')) {
    function stub_get_content_warning($post_id) {
        return isset($GLOBALS['_wp_test_state']['content_warnings'][$post_id])
            ? $GLOBALS['_wp_test_state']['content_warnings'][$post_id]
            : null;
    }
}

if (!function_exists('stub_user_was_banned')) {
    function stub_user_was_banned($user_id) {
        return in_array($user_id, $GLOBALS['_wp_test_state']['banned_users']);
    }
}

if (!function_exists('stub_get_post_author')) {
    function stub_get_post_author($post_id) {
        $post = get_post($post_id);
        return $post ? $post->post_author : 0;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return isset($GLOBALS['_wp_test_state']['options'][$option])
            ? $GLOBALS['_wp_test_state']['options'][$option]
            : $default;
    }
}

// Update existing stub implementations to track test state
if (!function_exists('wp_delete_post')) {
    function wp_delete_post($post_id, $force_delete = false) {
        $GLOBALS['_wp_test_state']['deleted_posts'][] = $post_id;
        return true;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($post_data) {
        if (isset($post_data['post_status']) && $post_data['post_status'] === 'private') {
            $GLOBALS['_wp_test_state']['hidden_posts'][] = $post_data['ID'];
        }
        return $post_data['ID'];
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false) {
        if ($taxonomy === 'content-warning') {
            $GLOBALS['_wp_test_state']['content_warnings'][$object_id] = $terms;
        }
        return array(1); // Return array of term IDs
    }
}

if (!function_exists('bp_moderation_add')) {
    function bp_moderation_add($args = array()) {
        if (isset($args['user_id']) && isset($args['type']) && $args['type'] === 'user') {
            $GLOBALS['_wp_test_state']['banned_users'][] = $args['user_id'];
        }
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        // Stub implementation
        static $options = array();
        return isset($options[$option]) ? $options[$option] : $default;
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        // Stub implementation
        return new WP_Error('stub_method', 'This is a development stub');
    }
}
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        // Stub implementation
        return new WP_Error('stub_method', 'This is a development stub');
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        // Stub implementation
        return $thing instanceof WP_Error;
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        // Stub implementation
        return '';
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        // Stub implementation
        return 200;
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}
if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        // Stub implementation
        return true;
    }
}
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = array()) {
        // Stub implementation
        return true;
    }
}
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string)) {
            trigger_error('hash_equals(): Expected known_string to be a string, ' . gettype($known_string) . ' given', E_USER_WARNING);
            return false;
        }

        if (!is_string($user_string)) {
            trigger_error('hash_equals(): Expected user_string to be a string, ' . gettype($user_string) . ' given', E_USER_WARNING);
            return false;
        }

        if (strlen($known_string) !== strlen($user_string)) {
            return false;
        }

        $ret = 0;

        for ($i = 0; $i < strlen($known_string); $i++) {
            $ret |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }

        return $ret === 0;
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        // Stub implementation
        return (object) array(
            'display_name' => 'Test User',
            'user_email' => 'test@example.com',
            'roles' => array('subscriber'),
        );
    }
}
if (!function_exists('get_avatar_url')) {
    function get_avatar_url($user_id) {
        // Stub implementation
        return 'https://www.gravatar.com/avatar/test';
    }
}
if (!function_exists('get_post')) {
    function get_post($post_id) {
        // Stub implementation
        return (object) array(
            'ID' => $post_id,
            'post_title' => 'Test Post',
            'post_content' => 'Test Content',
            'post_author' => 1,
            'post_date' => current_time('mysql'),
            'post_status' => 'publish',
            'post_type' => 'post',
        );
    }
}
if (!function_exists('wp_get_post_terms')) {
    function wp_get_post_terms($post_id, $taxonomy, $args = array()) {
        // Stub implementation
        return array('test-warning');
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        // Stub implementation
        return $single ? 'test-meta' : array('test-meta');
    }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        // Stub implementation
        return dirname($file) . '/';
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        // Stub implementation
        return 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}
// Admin functions
if (!function_exists('add_options_page')) {
    function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback) {
        // Stub implementation
        return 'settings_page';
    }
}
if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = array()) {
        // Stub implementation
        return true;
    }
}
if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page) {
        // Stub implementation
        return true;
    }
}
if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
        // Stub implementation
        return true;
    }
}
// BuddyBoss specific functions
if (!function_exists('bp_moderation_delete')) {
    function bp_moderation_delete($args = array()) {
        // Stub implementation
        return true;
    }
}
if (!function_exists('bp_moderation_is_user_blocked')) {
    function bp_moderation_is_user_blocked($user_id) {
        // Stub implementation
        return false;
    }
}
if (!function_exists('bp_moderation_is_content_hidden')) {
    function bp_moderation_is_content_hidden($item_id, $type) {
        // Stub implementation
        return false;
    }
}
if (!function_exists('bp_notifications_add_notification')) {
    function bp_notifications_add_notification($args = array()) {
        // Stub implementation
        return 1;
    }
}
if (!function_exists('messages_new_message')) {
    function messages_new_message($args = array()) {
        // Stub implementation
        return 1;
    }
}
if (!function_exists('bp_get_loggedin_user_id')) {
    function bp_get_loggedin_user_id() {
        // Stub implementation
        return 1;
    }
}
if (!function_exists('bp_is_active')) {
    function bp_is_active($component = '') {
        // Stub implementation
        return true;
    }
}
if (!function_exists('bp_core_add_message')) {
    function bp_core_add_message($message, $type = 'success') {
        // Stub implementation
        return true;
    }
}
if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false) {
        // Stub implementation
        return array(1); // Return array of term IDs
    }
}
// Add WP_Error class if it doesn't exist
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();

        public function __construct($code = '', $message = '', $data = '') {
            if (empty($code)) return;

            $this->errors[$code][] = $message;
            if (!empty($data))
                $this->error_data[$code] = $data;
        }

        public function get_error_codes() {
            return array_keys($this->errors);
        }

        public function get_error_message($code = '') {
            if (empty($code))
                $code = $this->get_error_codes();
            if (is_array($code))
                $code = $code[0];
            return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
        }
    }
}
// Add BP_Moderation_Abstract class if it doesn't exist
if (!class_exists('BP_Moderation_Abstract')) {
    abstract class BP_Moderation_Abstract {
        public static $moderation = array();
        protected $item_type;

        protected static function admin_bypass_check() {
            return current_user_can('administrator') && !empty($_GET['bypass_moderation']);
        }

        abstract public static function get_permalink($item_id);
        abstract public static function get_content_owner_id($item_id);
    }
}
if (!function_exists('bbp_get_reply')) {
    function bbp_get_reply($reply_id) {
        // Stub implementation
        return (object) array(
            'ID' => $reply_id,
            'post_content' => 'Test Reply',
            'post_author' => 1,
            'post_date' => current_time('mysql'),
        );
    }
}
if (!function_exists('bbp_get_reply_thread_id')) {
    function bbp_get_reply_thread_id($reply_id) {
        // Stub implementation
        return 1;
    }
}
if (!function_exists('wp_attachment_is')) {
    function wp_attachment_is($type, $attachment_id) {
        // Stub implementation
        return $type === 'image';
    }
}
if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id) {
        // Stub implementation
        return 'https://example.com/test-attachment.jpg';
    }
}
if (!function_exists('bp_xprofile_get_groups')) {
    function bp_xprofile_get_groups() {
        // Stub implementation
        return array(
            (object) array(
                'fields' => array(
                    (object) array(
                        'id' => 1,
                        'name' => 'Bio',
                    ),
                ),
            ),
        );
    }
}
if (!function_exists('bp_get_profile_field_data')) {
    function bp_get_profile_field_data($args = array()) {
        // Stub implementation
        return 'Test Profile Data';
    }
}
/**
 * WordPress REST API Related Stubs
 */
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }
    }
}
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = array();
        private $headers = array();
        private $body = '';

        public function get_params() {
            return $this->params;
        }

        public function get_param($key) {
            return isset($this->params[$key]) ? $this->params[$key] : null;
        }

        public function get_header($key) {
            $key = strtolower($key);
            return isset($this->headers[$key]) ? $this->headers[$key] : '';
        }

        public function get_body() {
            return $this->body;
        }

        // Test helper methods
        public function set_params($params) {
            $this->params = $params;
        }

        public function set_headers($headers) {
            $this->headers = array_change_key_case($headers, CASE_LOWER);
        }

        public function set_body($body) {
            $this->body = $body;
        }
    }
}
if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = array()) {
        // Stub implementation
        return true;
    }
}
if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response) {
        if ($response instanceof WP_REST_Response) {
            return $response;
        }
        return new WP_REST_Response($response);
    }
}