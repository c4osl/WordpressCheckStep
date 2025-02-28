<?php
/**
 * PHPUnit bootstrap file
 *
 * @package CheckStep_Integration
 */

// Load WordPress stubs
require_once dirname(__DIR__) . '/includes/wordpress-stubs.php';

// Load plugin files
require_once dirname(__DIR__) . '/includes/class-checkstep-api.php';
require_once dirname(__DIR__) . '/includes/class-checkstep-moderation.php';

/**
 * Create any global test helper functions here
 */
function create_test_api() {
    return new CheckStep_API();
}

function create_test_moderation() {
    $api = create_test_api();
    return new CheckStep_Moderation($api);
}
