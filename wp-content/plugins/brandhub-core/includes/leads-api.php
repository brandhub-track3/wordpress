<?php
 
if (!defined('ABSPATH')) {
    exit;
}

function brandhub_create_leads_table(): bool
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = $wpdb->prefix . 'brandhub_leads';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name text NOT NULL,
        email varchar(320) NOT NULL,
        company text NOT NULL,
        message text NOT NULL,
        consent tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta($sql);

    $table_exists = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like($table)
        )
    );

    if ($table_exists !== $table) {
        error_log('BrandHub: Unable to create the leads database table.');
        return false;
    }

    update_option('brandhub_leads_db_version', '1');

    return true;
}

add_action('plugins_loaded', function (): void {
    if (get_option('brandhub_leads_db_version') !== '1') {
        brandhub_create_leads_table();
    }
});

function brandhub_send_lead_notification(array $lead): bool
{
    $recipient = sanitize_email(
        apply_filters(
            'brandhub_lead_notification_email',
            get_option('admin_email'),
            $lead
        )
    );

    if (!is_email($recipient)) {
        error_log('BrandHub: Lead notification recipient is not a valid email address.');
        return false;
    }

    $subject = sprintf(
        '[BrandHub] New demo request from %s',
        $lead['name']
    );
    $body = implode("\n", [
        'A new demo request has been submitted.',
        '',
        'Lead ID: ' . $lead['id'],
        'Name: ' . $lead['name'],
        'Email: ' . $lead['email'],
        'Company: ' . $lead['company'],
        '',
        'Message:',
        $lead['message'],
        '',
        'View all leads: ' . admin_url('admin.php?page=brandhub-leads'),
    ]);

    $sent = wp_mail(
        $recipient,
        $subject,
        $body,
        ['Content-Type: text/plain; charset=UTF-8']
    );

    if (!$sent) {
        error_log('BrandHub: Failed to send a lead notification email.');
    }

    return $sent;
}
 
/**
 * =========================================================
 * 1. REGISTER LEAD REST API
 * POST /wp-json/brandhub/v1/leads
 * =========================================================
 */
 
function brandhub_register_lead_routes(): void
{
    register_rest_route(
        'brandhub/v1',
        '/leads',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'brandhub_create_lead',
            'permission_callback' => '__return_true',
        ]
    );
}
 
add_action(
    'rest_api_init',
    'brandhub_register_lead_routes'
);
 
 
/**
 * =========================================================
 * 2. CREATE LEAD
 * =========================================================
 */
 
function brandhub_create_lead(WP_REST_Request $request)
{
    global $wpdb;
 
 
    /**
     * -----------------------------------------------------
     * CHECK REACT ORIGIN
     * -----------------------------------------------------
     */
 
    $origin = $request->get_header('origin');
 
    $allowed_origins = [
        'http://localhost:5173',
 
        // Production React URL later:
        // 'https://brandhub.example.com',
    ];
 
    if (
        !$origin ||
        !in_array($origin, $allowed_origins, true)
    ) {
 
        return new WP_Error(
            'invalid_origin',
            'Origin is not allowed.',
            [
                'status' => 403
            ]
        );
    }
 
 
    /**
     * -----------------------------------------------------
     * HONEYPOT CHECK
     * -----------------------------------------------------
     */
 
    $website = $request->get_param('website');
 
    if (!empty($website)) {
 
        return new WP_Error(
            'spam_detected',
            'Request rejected.',
            [
                'status' => 400
            ]
        );
    }
 
 
    /**
     * -----------------------------------------------------
     * GET + SANITIZE INPUT
     * -----------------------------------------------------
     */
 
    $name = sanitize_text_field(
        $request->get_param('name')
    );
 
 
    $email = sanitize_email(
        $request->get_param('email')
    );
 
 
    $company = sanitize_text_field(
        $request->get_param('company')
    );
 
 
    $message = sanitize_textarea_field(
        $request->get_param('message')
    );
 
 
    $consent = filter_var(
        $request->get_param('consent'),
        FILTER_VALIDATE_BOOLEAN
    );
 
 
    /**
     * -----------------------------------------------------
     * VALIDATION
     * -----------------------------------------------------
     */
 
    if (
        empty($name) ||
        empty($email) ||
        !is_email($email) ||
        empty($company) ||
        empty($message) ||
        !$consent
    ) {
 
        return new WP_Error(
            'invalid_input',
            'Please provide valid details and consent.',
            [
                'status' => 400
            ]
        );
    }

    /**
     * -----------------------------------------------------
     * BASIC RATE LIMITING
     * Maximum 5 valid requests per IP per hour
     * -----------------------------------------------------
     */

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_key = 'brandhub_lead_' . hash('sha256', $ip);
    $attempts = (int) get_transient($rate_key);

    if ($attempts >= 50) {
        return new WP_Error(
            'rate_limit',
            'Too many requests. Please try later.',
            [
                'status' => 429
            ]
        );
    }

 
    /**
     * -----------------------------------------------------
     * SAVE LEAD TO WORDPRESS DATABASE
     * -----------------------------------------------------
     */
 
    $table = $wpdb->prefix . 'brandhub_leads';
 
 
    $inserted = $wpdb->insert(
        $table,
        [
            'name'       => $name,
            'email'      => $email,
            'company'    => $company,
            'message'    => $message,
            'consent'    => 1,
            'created_at' => current_time('mysql', true),
        ],
        [
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
        ]
    );
 
 
    /**
     * Database error
     */
 
    if ($inserted === false) {
 
        return new WP_Error(
            'database_error',
            'Unable to save your request.',
            [
                'status' => 500
            ]
        );
    }
 
    set_transient(
        $rate_key,
        $attempts + 1,
        HOUR_IN_SECONDS
    );

 
    $lead_id = (int) $wpdb->insert_id;

    $email_sent = brandhub_send_lead_notification([
        'id'      => $lead_id,
        'name'    => $name,
        'email'   => $email,
        'company' => $company,
        'message' => $message,
    ]);


    /**
     * -----------------------------------------------------
     * SEND EVENT TO SQS
     * -----------------------------------------------------
     */
 
    $sent = brandhub_send_event(
        [
            'eventId'       => wp_generate_uuid4(),
            'eventType'     => 'lead.created',
            'source'        => 'wordpress',
 
            'leadId'        => $lead_id,
 
            'name'           => $name,
            'email'          => $email,
            'company'        => $company,
            'message'        => $message,
 
            'schemaVersion' => '1',
        ]
    );
 
 
    /**
     * -----------------------------------------------------
     * SUCCESS RESPONSE
     * -----------------------------------------------------
     */
 
    return new WP_REST_Response(
        [
            'success'            => true,
            'leadId'             => $lead_id,
            'notificationSent'   => $email_sent,
            'notificationQueued' => (bool) $sent,
        ],
        201
    );
}
 