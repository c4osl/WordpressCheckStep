<?php
/**
 * CheckStep API Integration Class
 *
 * Handles communication with the CheckStep API for content moderation.
 * Provides methods for sending content, retrieving decisions, and handling
 * community reports through the CheckStep REST API endpoints.
 *
 * @package CheckStep_Integration
 * @subpackage API
 * @since 1.0.0
 */

/**
 * Class CheckStep_API
 *
 * Main API integration class that handles all communication with CheckStep's
 * content moderation service.
 *
 * @since 1.0.0
 */
class CheckStep_API {

    /**
     * CheckStep API key for authentication
     *
     * @since 1.0.0
     * @var string
     */
    private $api_key;

    /**
     * Base URL for CheckStep API endpoints
     *
     * @since 1.0.0
     * @var string
     */
    private $api_url;

    /**
     * Constructor
     *
     * Initializes the API client with credentials and base URL.
     *
     * @since 1.0.0
     * @throws Exception If API key is not configured
     */
    public function __construct() {
        $this->api_key = get_option('checkstep_api_key');
        $this->api_url = 'https://api.checkstep.com/v1/';

        if (empty($this->api_key)) {
            CheckStep_Logger::error('API key not configured');
            throw new Exception('CheckStep API key not configured');
        }
    }

    /**
     * Send content to CheckStep
     *
     * Submits content to CheckStep's API for moderation analysis.
     *
     * @since 1.0.0
     * @param string $content_type The type of content being submitted (e.g., 'post', 'comment')
     * @param array  $payload     Content data to be analyzed
     * @return array|false Array of API response data on success, false on failure
     */
    public function send_content($content_type, $payload) {
        try {
            CheckStep_Logger::debug('Sending content to CheckStep', array(
                'content_type' => $content_type,
                'content_id' => isset($payload['id']) ? $payload['id'] : null
            ));

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
                CheckStep_Logger::error('API request failed', array(
                    'error' => $response->get_error_message(),
                    'content_type' => $content_type
                ));
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code !== 200) {
                CheckStep_Logger::error('API request returned non-200 status', array(
                    'status_code' => $status_code,
                    'response' => $body,
                    'content_type' => $content_type
                ));
                return false;
            }

            CheckStep_Logger::info('Content successfully sent to CheckStep', array(
                'content_type' => $content_type,
                'content_id' => isset($payload['id']) ? $payload['id'] : null,
                'status_code' => $status_code
            ));

            return $body;

        } catch (Exception $e) {
            CheckStep_Logger::error('Exception while sending content', array(
                'error' => $e->getMessage(),
                'content_type' => $content_type
            ));
            return false;
        }
    }

    /**
     * Get moderation decision
     *
     * Retrieves a specific moderation decision from CheckStep.
     *
     * @since 1.0.0
     * @param string $content_id ID of the content to retrieve decision for
     * @return array|false Array containing decision data on success, false on failure
     */
    public function get_decision($content_id) {
        try {
            CheckStep_Logger::debug('Retrieving decision', array('content_id' => $content_id));

            $response = wp_remote_get(
                $this->api_url . 'decisions/' . $content_id,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                    ),
                )
            );

            if (is_wp_error($response)) {
                CheckStep_Logger::error('Failed to get decision', array(
                    'error' => $response->get_error_message(),
                    'content_id' => $content_id
                ));
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code !== 200) {
                CheckStep_Logger::warning('Decision request returned non-200 status', array(
                    'status_code' => $status_code,
                    'content_id' => $content_id
                ));
                return false;
            }

            CheckStep_Logger::info('Decision retrieved successfully', array(
                'content_id' => $content_id,
                'decision_id' => isset($body['decision_id']) ? $body['decision_id'] : null
            ));

            return $body;

        } catch (Exception $e) {
            CheckStep_Logger::error('Exception while retrieving decision', array(
                'error' => $e->getMessage(),
                'content_id' => $content_id
            ));
            return false;
        }
    }

    /**
     * Send community report
     *
     * Submits a community report to CheckStep for processing.
     *
     * @since 1.0.0
     * @param array $report_data Report information including content and reason
     * @return array|false Array containing report submission status on success, false on failure
     */
    public function send_report($report_data) {
        try {
            CheckStep_Logger::debug('Sending community report', array(
                'content_id' => isset($report_data['content_id']) ? $report_data['content_id'] : null
            ));

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
                CheckStep_Logger::error('Failed to send report', array(
                    'error' => $response->get_error_message(),
                    'content_id' => isset($report_data['content_id']) ? $report_data['content_id'] : null
                ));
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code !== 200) {
                CheckStep_Logger::warning('Report submission returned non-200 status', array(
                    'status_code' => $status_code,
                    'content_id' => isset($report_data['content_id']) ? $report_data['content_id'] : null
                ));
                return false;
            }

            CheckStep_Logger::info('Report submitted successfully', array(
                'content_id' => isset($report_data['content_id']) ? $report_data['content_id'] : null,
                'report_id' => isset($body['report_id']) ? $body['report_id'] : null
            ));

            return $body;

        } catch (Exception $e) {
            CheckStep_Logger::error('Exception while sending report', array(
                'error' => $e->getMessage(),
                'content_id' => isset($report_data['content_id']) ? $report_data['content_id'] : null
            ));
            return false;
        }
    }
}