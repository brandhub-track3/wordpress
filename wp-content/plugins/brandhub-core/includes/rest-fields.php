<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function brandhub_register_rest_meta_fields() {
    $types = array( 'post', 'case_study' );

    foreach ( $types as $type ) {
        register_post_meta( $type, 'brandhub_subtitle', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
        ) );

        register_post_meta( $type, 'brandhub_featured', array(
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
        ) );
    }
}
add_action( 'init', 'brandhub_register_rest_meta_fields' );
