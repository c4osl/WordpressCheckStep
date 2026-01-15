<?php
/**
 * Admin Interface Handler Class
 *
 * Manages the WordPress admin interface for the CheckStep integration plugin.
 * Handles settings registration, admin menu creation, and rendering of
 * configuration forms for API credentials and webhook settings.
 *
 * @package CheckStep_Integration
 * @subpackage Admin
 * @since 1.0.0
 */

/**
 * Class CheckStep_Admin
 *
 * Implements the WordPress admin interface for managing CheckStep integration settings.
 * Creates a settings page under the WordPress Settings menu and registers all necessary
 * options for storing API credentials and configuration.
 *
 * @since 1.0.0
 */
class CheckStep_Admin {
    /**
     * Constructor
     *
     * Initializes the admin interface by registering actions for the admin menu
     * and plugin settings.
     *
     * @since 1.0.0
     */
    public function __construct() {
        try {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('wp_ajax_get_checkstep_queue_status', array($this, 'ajax_get_queue_status'));
            add_action('wp_ajax_process_checkstep_queue_item', array($this, 'ajax_process_queue_item'));
            add_action('wp_ajax_test_checkstep_connection', array($this, 'ajax_test_connection'));
            add_action('wp_ajax_checkstep_clear_logs', array($this, 'ajax_clear_logs'));
            CheckStep_Logger::info('Admin interface initialized');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to initialize admin interface', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Add admin menu items
     *
     * Creates the settings page menu item under the WordPress Settings menu.
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        try {
            // Add main settings page under Settings menu
            add_options_page(
                __('CheckStep Integration', 'checkstep-integration'),
                __('CheckStep', 'checkstep-integration'),
                'manage_options',
                'checkstep-settings',
                array($this, 'render_settings_page')
            );

            // Add moderation queue page under Tools menu
            add_management_page(
                __('CheckStep Moderation Queue', 'checkstep-integration'),
                __('Moderation Queue', 'checkstep-integration'),
                'manage_options',
                'checkstep-queue',
                array($this, 'render_queue_page')
            );

            // Add process flow diagram page under Tools menu
            add_management_page(
                __('CheckStep Process Flow', 'checkstep-integration'),
                __('Process Flow', 'checkstep-integration'),
                'manage_options',
                'checkstep-process-flow',
                array($this, 'render_process_flow_page')
            );

            CheckStep_Logger::debug('Admin menu registered successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to add admin menu', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        try {
            // Only load on our admin pages
            if (!in_array($hook, array('tools_page_checkstep-queue', 'settings_page_checkstep-settings', 'tools_page_checkstep-process-flow'))) {
                return;
            }

            wp_enqueue_style(
                'checkstep-admin',
                CHECKSTEP_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                CHECKSTEP_VERSION
            );

            wp_enqueue_script(
                'checkstep-admin',
                CHECKSTEP_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                CHECKSTEP_VERSION,
                true
            );

            wp_localize_script('checkstep-admin', 'CheckStepAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('checkstep-admin'),
                'i18n' => array(
                    'testingConnection' => __('Testing connection...', 'checkstep-integration'),
                    'connectionSuccess' => __('Connected successfully', 'checkstep-integration'),
                    'connectionFailed' => __('Connection failed', 'checkstep-integration'),
                    'settingsSaved' => __('Settings saved successfully', 'checkstep-integration'),
                    'settingsError' => __('Failed to save settings', 'checkstep-integration'),
                    'showText' => __('Show', 'checkstep-integration'),
                    'hideText' => __('Hide', 'checkstep-integration')
                )
            ));

            CheckStep_Logger::debug('Admin assets enqueued successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to enqueue admin assets', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Render moderation queue page
     */
    public function render_queue_page() {
        try {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            require_once CHECKSTEP_PLUGIN_DIR . 'admin/partials/dashboard-page.php';
            CheckStep_Logger::info('Queue page rendered successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to render queue page', array(
                'error' => $e->getMessage()
            ));
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('Error loading queue page. Please check error logs.', 'checkstep-integration'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render process flow diagram page
     *
     * @since 1.0.17
     */
    public function render_process_flow_page() {
        try {
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            require_once CHECKSTEP_PLUGIN_DIR . 'admin/partials/process-flow-page.php';
            CheckStep_Logger::info('Process flow page rendered successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to render process flow page', array(
                'error' => $e->getMessage()
            ));
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('Error loading process flow page. Please check error logs.', 'checkstep-integration'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler for queue status updates
     */
    public function ajax_get_queue_status() {
        try {
            check_ajax_referer('checkstep-admin', 'nonce');

            global $wpdb;
            $pending_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}checkstep_queue WHERE status = 'pending'"
            );

            $last_processed = get_option('checkstep_last_queue_process');

            wp_send_json_success(array(
                'pending_count' => intval($pending_count),
                'last_processed' => $last_processed ? date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($last_processed)
                ) : __('Never', 'checkstep-integration')
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to get queue status', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error(array(
                'message' => __('Failed to get queue status', 'checkstep-integration')
            ));
        }
    }

    /**
     * AJAX handler for processing queue items
     */
    public function ajax_process_queue_item() {
        try {
            check_ajax_referer('checkstep-admin', 'nonce');

            $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
            $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

            if (!$item_id || !$action) {
                throw new Exception('Missing required parameters');
            }

            // Process the queue item based on action
            switch ($action) {
                case 'approve':
                    $this->approve_queue_item($item_id);
                    break;
                case 'reject':
                    $this->reject_queue_item($item_id);
                    break;
                case 'requeue':
                    $this->requeue_item($item_id);
                    break;
                default:
                    throw new Exception('Invalid action type');
            }

            wp_send_json_success(array(
                'message' => __('Queue item processed successfully', 'checkstep-integration')
            ));
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to process queue item', array(
                'error' => $e->getMessage(),
                'item_id' => $item_id ?? 'unknown',
                'action' => $action ?? 'unknown'
            ));
            wp_send_json_error(array(
                'message' => __('Failed to process queue item', 'checkstep-integration')
            ));
        }
    }

    private function approve_queue_item($item_id) {
        //Implementation to approve queue item.  Needs database interaction.
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'checkstep_queue',
            array('status' => 'approved'),
            array('id' => $item_id),
            array('%s'),
            array('%d')
        );
        update_option('checkstep_last_queue_process', current_time('mysql'));
    }

    private function reject_queue_item($item_id) {
        //Implementation to reject queue item. Needs database interaction.
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'checkstep_queue',
            array('status' => 'rejected'),
            array('id' => $item_id),
            array('%s'),
            array('%d')
        );
        update_option('checkstep_last_queue_process', current_time('mysql'));

    }

    private function requeue_item($item_id) {
        //Implementation to requeue item. Needs database interaction.
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'checkstep_queue',
            array('status' => 'pending'),
            array('id' => $item_id),
            array('%s'),
            array('%d')
        );
        update_option('checkstep_last_queue_process', current_time('mysql'));
    }

    /**
     * AJAX handler for clearing logs
     *
     * @since 1.0.11
     */
    public function ajax_clear_logs() {
        try {
            check_ajax_referer('checkstep-admin', 'nonce');

            if (!current_user_can('manage_options')) {
                throw new Exception(__('You do not have permission to clear logs.', 'checkstep-integration'));
            }

            $result = CheckStep_Logger::clear_logs();

            if ($result) {
                CheckStep_Logger::info('All logs cleared by admin');
                wp_send_json_success(array(
                    'message' => __('Logs cleared successfully.', 'checkstep-integration')
                ));
            } else {
                throw new Exception(__('Failed to clear logs.', 'checkstep-integration'));
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to clear logs', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_connection() {
        try {
            check_ajax_referer('checkstep-admin', 'nonce');

            $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
            $webhook_secret = isset($_POST['webhook_secret']) ? sanitize_text_field($_POST['webhook_secret']) : '';

            if (empty($api_key)) {
                throw new Exception(__('API key is required', 'checkstep-integration'));
            }

            // Test the API connection
            $api = new CheckStep_API();
            $test_result = $api->test_connection($api_key);

            if ($test_result) {
                wp_send_json_success(array(
                    'message' => __('Connection successful! API key is valid.', 'checkstep-integration')
                ));
            } else {
                throw new Exception(__('Connection failed. Please check your API key.', 'checkstep-integration'));
            }
        } catch (Exception $e) {
            CheckStep_Logger::error('API connection test failed', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }


    /**
     * Register plugin settings
     *
     * Sets up all plugin settings and their sections in the WordPress Settings API.
     * Registers the API key, webhook secret, and appeal URL settings.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        try {
            // Register settings with proper sanitization callbacks
            register_setting('checkstep_settings', 'checkstep_api_key', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ));
            
            register_setting('checkstep_settings', 'checkstep_webhook_secret', array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ));
            
            register_setting('checkstep_settings', 'checkstep_appeal_url', array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => ''
            ));
            
            register_setting('checkstep_settings', 'checkstep_enabled_log_levels', array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_log_levels'),
                'default' => array('error', 'warning', 'info')
            ));

            add_settings_section(
                'checkstep_main_section',
                __('API Configuration', 'checkstep-integration'),
                array($this, 'render_section_info'),
                'checkstep-settings'
            );

            add_settings_field(
                'checkstep_api_key',
                __('API Key', 'checkstep-integration'),
                array($this, 'render_api_key_field'),
                'checkstep-settings',
                'checkstep_main_section'
            );

            add_settings_field(
                'checkstep_webhook_secret',
                __('Webhook Secret', 'checkstep-integration'),
                array($this, 'render_webhook_secret_field'),
                'checkstep-settings',
                'checkstep_main_section'
            );

            add_settings_field(
                'checkstep_appeal_url',
                __('Appeal URL', 'checkstep-integration'),
                array($this, 'render_appeal_url_field'),
                'checkstep-settings',
                'checkstep_main_section'
            );
            
            // Logging settings section
            add_settings_section(
                'checkstep_logging_section',
                __('Logging Settings', 'checkstep-integration'),
                array($this, 'render_logging_section_info'),
                'checkstep-settings'
            );
            
            add_settings_field(
                'checkstep_enabled_log_levels',
                __('Enabled Log Levels', 'checkstep-integration'),
                array($this, 'render_log_levels_field'),
                'checkstep-settings',
                'checkstep_logging_section'
            );

            CheckStep_Logger::info('Settings registered successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to register settings', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Render settings page
     *
     * Outputs the HTML for the plugin's settings page in the WordPress admin.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        try {
            if (!current_user_can('manage_options')) {
                CheckStep_Logger::warning('Unauthorized access attempt to settings page', array(
                    'user_id' => get_current_user_id()
                ));
                wp_die(__('You do not have sufficient permissions to access this page.'));
            }

            $template_path = plugin_dir_path(__FILE__) . 'partials/settings-page.php';
            if (!file_exists($template_path)) {
                throw new Exception('Settings page template not found');
            }

            require_once $template_path;
            CheckStep_Logger::info('Settings page rendered successfully');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to render settings page', array(
                'error' => $e->getMessage()
            ));
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('Error loading settings page. Please check error logs.', 'checkstep-integration'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render section info
     *
     * Outputs the description for the main settings section.
     *
     * @since 1.0.0
     */
    public function render_section_info() {
        try {
            echo '<p>' . __('Configure your CheckStep API credentials and settings below.', 'checkstep-integration') . '</p>';
            CheckStep_Logger::debug('Section info rendered');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to render section info', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Render API key field
     *
     * Outputs the form field for the CheckStep API key setting.
     *
     * @since 1.0.0
     */
    public function render_api_key_field() {
        try {
            $api_key = get_option('checkstep_api_key');
            ?>
            <input type="password"
                   id="checkstep_api_key"
                   name="checkstep_api_key"
                   value="<?php echo esc_attr($api_key); ?>"
                   class="regular-text"
            />
            <?php
            CheckStep_Logger::debug('API key field rendered');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to render API key field', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Render webhook secret field
     *
     * Outputs the form field for the CheckStep webhook secret setting.
     *
     * @since 1.0.0
     */
    public function render_webhook_secret_field() {
        try {
            $webhook_secret = get_option('checkstep_webhook_secret');
            ?>
            <input type="password"
                   id="checkstep_webhook_secret"
                   name="checkstep_webhook_secret"
                   value="<?php echo esc_attr($webhook_secret); ?>"
                   class="regular-text"
            />
            <?php
            CheckStep_Logger::debug('Webhook secret field rendered');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to render webhook secret field', array(
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Render appeal URL field
     *
     * Outputs the form field for the appeal URL setting.
     *
     * @since 1.0.0
     */
    public function render_appeal_url_field() {
        try {
            $appeal_url = get_option('checkstep_appeal_url');
            ?>
            <input type="url"
                   id="checkstep_appeal_url"
                   name="checkstep_appeal_url"
                   value="<?php echo esc_url($appeal_url); ?>"
                   class="regular-text"
            />
            <?php
            CheckStep_Logger::debug('Appeal URL field rendered');
        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to render appeal URL field', array(
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Render logging section info
     *
     * Outputs the description for the logging settings section.
     *
     * @since 1.0.15
     */
    public function render_logging_section_info() {
        echo '<p>' . __('Configure which log levels to capture in the logs viewer.', 'checkstep-integration') . '</p>';
    }
    
    /**
     * Render log levels field
     *
     * Outputs the checkboxes for selecting enabled log levels.
     *
     * @since 1.0.15
     */
    public function render_log_levels_field() {
        $enabled_levels = get_option('checkstep_enabled_log_levels', array('error', 'warning', 'info'));
        if (!is_array($enabled_levels)) {
            $enabled_levels = array('error', 'warning', 'info');
        }
        
        $log_levels = array(
            'error'   => __('Error - Critical errors that need immediate attention', 'checkstep-integration'),
            'warning' => __('Warning - Potential issues that should be reviewed', 'checkstep-integration'),
            'info'    => __('Info - General information about plugin operations', 'checkstep-integration'),
            'debug'   => __('Debug - Detailed technical information for debugging', 'checkstep-integration')
        );
        
        foreach ($log_levels as $level => $description) {
            $checked = in_array($level, $enabled_levels) ? 'checked' : '';
            ?>
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox"
                       name="checkstep_enabled_log_levels[]"
                       value="<?php echo esc_attr($level); ?>"
                       <?php echo $checked; ?>
                />
                <strong><?php echo esc_html(ucfirst($level)); ?></strong>
                - <?php echo esc_html($description); ?>
            </label>
            <?php
        }
        ?>
        <p class="description">
            <?php _e('Select which log levels to record. Uncheck levels you don\'t want to see in the logs viewer.', 'checkstep-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * Sanitize log levels setting
     *
     * Validates and sanitizes the log levels array.
     *
     * @since 1.0.15
     * @param array $input The submitted log levels
     * @return array Sanitized log levels
     */
    public function sanitize_log_levels($input) {
        $valid_levels = array('error', 'warning', 'info', 'debug');
        
        if (!is_array($input)) {
            return array('error', 'warning', 'info');
        }
        
        $sanitized = array();
        foreach ($input as $level) {
            $level = sanitize_text_field($level);
            if (in_array($level, $valid_levels)) {
                $sanitized[] = $level;
            }
        }
        
        // Always include at least error level
        if (empty($sanitized)) {
            $sanitized = array('error');
        }
        
        return $sanitized;
    }
}