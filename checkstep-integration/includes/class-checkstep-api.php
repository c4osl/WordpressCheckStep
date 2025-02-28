<?php
/**
 * CheckStep API Integration Class
 */
class CheckStep_API {
    private $api_key;
    private $api_url;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('checkstep_api_key');
        $this->api_url = 'https://api.checkstep.com/v1/';
    }

    /**
     * Send content to CheckStep
     */
    public function send_content($content_type, $payload) {
        $response = wp_remote_post(
            $this->api_url . 'content',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($payload),
                'timeout' => 30,
            )
        );

        if (is_wp_error($response)) {
            $this->log_error('API request failed: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $this->log_error('API request failed with status ' . $status_code);
            return false;
        }

        return $body;
    }

    /**
     * Get moderation decision
     */
    public function get_decision($content_id) {
        $response = wp_remote_get(
            $this->api_url . 'decisions/' . $content_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                ),
            )
        );

        if (is_wp_error($response)) {
            $this->log_error('Failed to get decision: ' . $response->get_error_message());
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Send community report
     */
    public function send_report($report_data) {
        $response = wp_remote_post(
            $this->api_url . 'reports',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($report_data),
            )
        );

        if (is_wp_error($response)) {
            $this->log_error('Failed to send report: ' . $response->get_error_message());
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Log error message
     */
    private function log_error($message) {
        error_log('[CheckStep Integration] ' . $message);
    }
}
