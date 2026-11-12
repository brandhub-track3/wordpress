<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function brandhub_load_environment(): void {
    static $loaded = false;

    if ( $loaded ) {
        return;
    }

    $loaded   = true;
    $env_file = ABSPATH . '.env';
    if ( ! is_readable( $env_file ) ) {
        return;
    }

    $lines = file( $env_file, FILE_IGNORE_NEW_LINES );
    if ( false === $lines ) {
        return;
    }

    $supported_keys = array(
        'BRANDHUB_SQS_QUEUE_URL',
        'BRANDHUB_AWS_REGION',
        'AWS_REGION',
        'BRANDHUB_SMTP_HOST',
        'BRANDHUB_SMTP_PORT',
        'BRANDHUB_SMTP_USERNAME',
        'BRANDHUB_SMTP_PASSWORD',
        'BRANDHUB_SMTP_ENCRYPTION',
        'BRANDHUB_SMTP_FROM_EMAIL',
        'BRANDHUB_SMTP_FROM_NAME',
    );
    $key_pattern = implode( '|', array_map( 'preg_quote', $supported_keys ) );

    foreach ( $lines as $line ) {
        if ( ! preg_match( '/^\s*(?:export\s+)?(' . $key_pattern . ')\s*=\s*(.*?)\s*$/', $line, $matches ) ) {
            continue;
        }

        $key   = $matches[1];
        $value = trim( $matches[2] );

        if ( strlen( $value ) >= 2 ) {
            $first = $value[0];
            $last  = substr( $value, -1 );
            if ( ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last ) ) {
                $value = substr( $value, 1, -1 );
            }
        }

        if ( false === getenv( $key ) || '' === getenv( $key ) ) {
            putenv( $key . '=' . $value );
            $_ENV[ $key ]    = $value;
            $_SERVER[ $key ] = $value;
        }
    }

    if ( ( false === getenv( 'BRANDHUB_AWS_REGION' ) || '' === getenv( 'BRANDHUB_AWS_REGION' ) ) && getenv( 'AWS_REGION' ) ) {
        putenv( 'BRANDHUB_AWS_REGION=' . getenv( 'AWS_REGION' ) );
        $_ENV['BRANDHUB_AWS_REGION']    = getenv( 'AWS_REGION' );
        $_SERVER['BRANDHUB_AWS_REGION'] = getenv( 'AWS_REGION' );
    }
}

function brandhub_env( string $key, string $default = '' ): string {
    brandhub_load_environment();

    $value = getenv( $key );
    return false !== $value && '' !== $value ? $value : $default;
}

brandhub_load_environment();
