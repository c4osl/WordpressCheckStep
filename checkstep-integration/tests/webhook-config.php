<?php
/**
 * Webhook Configuration
 * 
 * This file contains webhook URL configurations and documents the event types
 * handled by the plugin.
 */

// Test Environment
$test_webhook = array(
    'url' => 'https://fanrefuge.com/wp-json/checkstep/v1/decisions',
    'description' => 'Production webhook endpoint for receiving moderation decisions',
    'method' => 'POST',
    'format' => 'JSON',
    'authentication' => array(
        'type' => 'HMAC',
        'header' => 'X-CheckStep-Signature'
    ),
    'enabled_events' => array(
        'decision_taken' => array(
            'description' => 'Triggers when a moderation decision is made',
            'actions' => array('delete', 'hide', 'warn', 'ban_user'),
            'handler' => 'handle_moderation_decision'
        ),
        'incident_closed' => array(
            'description' => 'Triggers when a moderation incident is closed',
            'actions' => array('notify_user', 'update_appeal_status'),
            'handler' => 'handle_incident_closure'
        )
    ),
    'future_events' => array(
        'content_analysed' => array(
            'description' => 'Could be implemented for early intervention',
            'potential_actions' => array(
                'automated_takedown_pending_review',
                'preemptive_content_warning',
                'risk_score_tracking'
            )
        )
    ),
    'required_fields' => array(
        'decision_id' => 'string',
        'content_id' => 'integer',
        'action' => array('delete', 'hide', 'warn', 'ban_user'),
        'reason' => 'string (optional)'
    )
);

// Document webhook payload format
$example_webhook_payload = array(
    'decision_id' => 'dec_abc123',
    'content_id' => 12345,
    'action' => 'hide',
    'reason' => 'Contains inappropriate content'
);

// Example curl command for testing webhook endpoint
$curl_example = <<<EOT
curl -X POST \\
     -H "Content-Type: application/json" \\
     -H "X-CheckStep-Signature: {signature}" \\
     -d '{
         "decision_id": "dec_abc123",
         "content_id": 12345,
         "action": "hide",
         "reason": "Contains inappropriate content"
     }' \\
     https://fanrefuge.com/wp-json/checkstep/v1/decisions
EOT;

?>