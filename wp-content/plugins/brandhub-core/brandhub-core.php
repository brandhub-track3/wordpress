<?php
/**
 * Plugin Name: BrandHub Core
 * Description: BrandHub content types, REST fields, content events, S3 media integration and demo lead management.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: BrandHub
 * Text Domain: brandhub-core
 */

if (!defined('ABSPATH')) {
    exit;
}


add_action('init', function () {
    $allowed_origin = 'http://localhost:5173';

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if ($origin === $allowed_origin) {
        header('Access-Control-Allow-Origin: ' . $allowed_origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
        header('Access-Control-Max-Age: 86400');
    }

    // Handle browser preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if ($origin === $allowed_origin) {
            status_header(200);
            exit;
        }

        status_header(403);
        exit;
    }
}, 0);



define( 'BRANDHUB_CORE_VERSION', '1.0.0' );
define( 'BRANDHUB_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once BRANDHUB_CORE_PATH . 'includes/environment.php';
require_once BRANDHUB_CORE_PATH . 'includes/smtp-mail.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
} elseif ( file_exists( ABSPATH . 'vendor/autoload.php' ) ) {
    require_once ABSPATH . 'vendor/autoload.php';
}


require_once BRANDHUB_CORE_PATH . 'includes/post-types.php';
require_once BRANDHUB_CORE_PATH . 'includes/taxonomies.php';
require_once BRANDHUB_CORE_PATH . 'includes/rest-fields.php';
require_once BRANDHUB_CORE_PATH . 'includes/content-schema.php';
require_once BRANDHUB_CORE_PATH . 'includes/content-events.php';
require_once BRANDHUB_CORE_PATH . 'includes/s3-media.php';
require_once BRANDHUB_CORE_PATH . 'includes/leads-api.php';
require_once BRANDHUB_CORE_PATH . 'includes/leads-admin.php';

register_activation_hook(__FILE__, function () {
    brandhub_register_post_types();
    brandhub_register_taxonomies();
    brandhub_create_leads_table();

    flush_rewrite_rules();
});
register_deactivation_hook( __FILE__, function () {
    brandhub_unschedule_content_event_outbox();
    flush_rewrite_rules();
} );
