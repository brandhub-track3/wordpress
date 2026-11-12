<?php

if (!defined('ABSPATH')) {
    exit;
}

function brandhub_configure_smtp($phpmailer): void
{
    $host = brandhub_env('BRANDHUB_SMTP_HOST');
    $username = brandhub_env('BRANDHUB_SMTP_USERNAME');
    $password = brandhub_env('BRANDHUB_SMTP_PASSWORD');

    if ($host === '' && $username === '' && $password === '') {
        return;
    }

    if ($host === '' || $username === '' || $password === '') {
        error_log('BrandHub: SMTP is not configured; host, username, and password are required.');
        return;
    }

    $from_email = sanitize_email(
        brandhub_env('BRANDHUB_SMTP_FROM_EMAIL', $username)
    );

    if (!is_email($from_email)) {
        error_log('BrandHub: SMTP sender email address is invalid.');
        return;
    }

    $port = absint(brandhub_env('BRANDHUB_SMTP_PORT', '587'));

    if ($port < 1 || $port > 65535) {
        error_log('BrandHub: SMTP port must be between 1 and 65535.');
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $host;
    $phpmailer->Port = $port;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = $username;
    $phpmailer->Password = $password;
    $phpmailer->SMTPSecure = brandhub_env('BRANDHUB_SMTP_ENCRYPTION', 'tls');
    $phpmailer->SMTPAutoTLS = true;
    $phpmailer->From = $from_email;
    $phpmailer->FromName = brandhub_env(
        'BRANDHUB_SMTP_FROM_NAME',
        get_bloginfo('name')
    );
}

function brandhub_smtp_from_email(string $from_email): string
{
    $configured_email = sanitize_email(
        brandhub_env('BRANDHUB_SMTP_FROM_EMAIL')
    );

    return is_email($configured_email) ? $configured_email : $from_email;
}

function brandhub_smtp_from_name(string $from_name): string
{
    return sanitize_text_field(
        brandhub_env('BRANDHUB_SMTP_FROM_NAME', $from_name)
    );
}

add_filter('wp_mail_from', 'brandhub_smtp_from_email');
add_filter('wp_mail_from_name', 'brandhub_smtp_from_name');
add_action('phpmailer_init', 'brandhub_configure_smtp');
