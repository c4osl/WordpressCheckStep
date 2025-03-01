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
     * Webhook event types supported by the integration
     *
     * @since 1.0.0
     */
    const EVENT_DECISION_TAKEN = 'decision_taken';
    const EVENT_INCIDENT_CLOSED = 'incident_closed';
    const EVENT_CONTENT_ANALYSED = 'content_analysed'; // Future implementation

    /**
     * Constructor
     *
     * Initializes the API client with credentials and base URL.
     *
     * @since 1.0.0
     * @throws Exception If API key is not configured
     */
    public function __construct() {
        try {
            $this->api_key = get_option('checkstep_api_key');
            $this->api_url = trim(get_option('checkstep_api_url', 'https://api.checkstep.com/api/v2/'), '/') . '/';

            if (empty($this->api_key)) {
                throw new Exception('CheckStep API key not configured');
            }

            CheckStep_Logger::debug('API client initialized', array(
                'api_url' => $this->api_url
            ));

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to initialize API client', array(
                'error' => $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Get webhook configuration
     *
     * Returns the current webhook configuration including enabled events
     * and callback URL.
     *
     * @since 1.0.0
     * @return array Webhook configuration
     */
    public function get_webhook_config() {
        return array(
            'callback_url' => site_url('/wp-json/checkstep/v1/decisions'),
            'enabled_events' => array(
                self::EVENT_DECISION_TAKEN => array(
                    'description' => 'Triggers when a moderation decision is made',
                    'actions' => array('delete', 'hide', 'warn', 'ban_user'),
                    'handler' => 'handle_moderation_decision'
                ),
                self::EVENT_INCIDENT_CLOSED => array(
                    'description' => 'Triggers when a moderation incident is closed',
                    'actions' => array('notify_user', 'update_appeal_status'),
                    'handler' => 'handle_incident_closure'
                )
            ),
            'future_events' => array(
                self::EVENT_CONTENT_ANALYSED => array(
                    'description' => 'Could be implemented for early intervention',
                    'potential_actions' => array(
                        'automated_takedown_pending_review',
                        'preemptive_content_warning',
                        'risk_score_tracking'
                    )
                )
            )
        );
    }

    /**
     * Validate webhook signature
     *
     * Verifies that a webhook request came from CheckStep using HMAC
     *
     * @since 1.0.0
     * @param string $payload Raw webhook payload
     * @param string $signature Value of X-CheckStep-Signature header
     * @return bool True if signature is valid
     */
    public function validate_webhook_signature($payload, $signature) {
        try {
            $webhook_secret = get_option('checkstep_webhook_secret');
            if (empty($webhook_secret)) {
                throw new Exception('Webhook secret not configured');
            }

            $expected = hash_hmac('sha256', $payload, $webhook_secret);
            return hash_equals($expected, $signature);

        } catch (Exception $e) {
            CheckStep_Logger::error('Webhook signature validation failed', array(
                'error' => $e->getMessage()
            ));
            return false;
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
            if (empty($content_type) || !is_array($payload)) {
                throw new Exception('Invalid content type or payload');
            }

            // Add callback URL to payload
            $payload['callback_url'] = $this->get_webhook_config()['callback_url'];

            CheckStep_Logger::debug('Preparing content submission', array(
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
                throw new Exception('API request failed: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (null === $body && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            // Accept 202 status for async processing
            if ($status_code !== 200 && $status_code !== 202) {
                throw new Exception(
                    sprintf('API returned %d status code: %s',
                        $status_code,
                        isset($body['message']) ? $body['message'] : 'Unknown error'
                    )
                );
            }

            CheckStep_Logger::info('Content submitted successfully', array(
                'content_type' => $content_type,
                'content_id' => isset($payload['id']) ? $payload['id'] : null,
                'status_code' => $status_code
            ));

            return $body;

        } catch (Exception $e) {
            CheckStep_Logger::error('Content submission failed', array(
                'error' => $e->getMessage(),
                'content_type' => $content_type,
                'content_id' => isset($payload['id']) ? $payload['id'] : null
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
            if (empty($content_id)) {
                throw new Exception('Content ID is required');
            }

            CheckStep_Logger::debug('Retrieving moderation decision', array(
                'content_id' => $content_id
            ));

            $response = wp_remote_get(
                $this->api_url . 'decisions/' . urlencode($content_id),
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                    ),
                    'timeout' => 30,
                )
            );

            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (null === $body && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if ($status_code === 404) {
                CheckStep_Logger::info('No decision found', array(
                    'content_id' => $content_id
                ));
                return false;
            }

            if ($status_code !== 200) {
                throw new Exception(
                    sprintf('API returned %d status code: %s',
                        $status_code,
                        isset($body['message']) ? $body['message'] : 'Unknown error'
                    )
                );
            }

            CheckStep_Logger::info('Decision retrieved successfully', array(
                'content_id' => $content_id,
                'decision_id' => isset($body['decision_id']) ? $body['decision_id'] : null
            ));

            return $body;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to retrieve decision', array(
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
            if (!is_array($report_data) || empty($report_data)) {
                throw new Exception('Invalid report data');
            }

            if (empty($report_data['content_id'])) {
                throw new Exception('Content ID is required in report data');
            }

            CheckStep_Logger::debug('Sending community report', array(
                'content_id' => $report_data['content_id']
            ));

            $response = wp_remote_post(
                $this->api_url . 'reports',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json',
                    ),
                    'body' => json_encode($report_data),
                    'timeout' => 30,
                )
            );

            if (is_wp_error($response)) {
                throw new Exception('API request failed: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (null === $body && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if ($status_code !== 200) {
                throw new Exception(
                    sprintf('API returned %d status code: %s',
                        $status_code,
                        isset($body['message']) ? $body['message'] : 'Unknown error'
                    )
                );
            }

            CheckStep_Logger::info('Report submitted successfully', array(
                'content_id' => $report_data['content_id'],
                'report_id' => isset($body['report_id']) ? $body['report_id'] : null
            ));

            return $body;

        } catch (Exception $e) {
            CheckStep_Logger::error('Failed to submit report', array(
                'error' => $e->getMessage(),
                'content_id' => isset($report_data['content_id']) ? $report_data['content_id'] : null
            ));
            return false;
        }
    }

    /**
     * Validate API response
     *
     * Checks that an API response contains the expected data structure.
     *
     * @since 1.0.0
     * @access private
     * @param array $response Response data to validate
     * @param array $required_fields List of required field names
     * @return bool True if valid, false otherwise
     */
    private function validate_response($response, $required_fields) {
        try {
            if (!is_array($response)) {
                throw new Exception('Response must be an array');
            }

            foreach ($required_fields as $field) {
                if (!isset($response[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            return true;

        } catch (Exception $e) {
            CheckStep_Logger::error('Response validation failed', array(
                'error' => $e->getMessage(),
                'response' => $response
            ));
            return false;
        }
    }
}