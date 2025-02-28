<?php
/**
 * WordPress Function Stubs
 * 
 * This file contains stub implementations of WordPress functions used in the plugin.
 * These stubs allow for development and testing without a full WordPress environment.
 * 
 * @package CheckStep_Integration
 */

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

if (!function_exists('bp_get_profile_field_data')) {
    function bp_get_profile_field_data($args = array()) {
        // Stub implementation
        return 'Test Profile Data';
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