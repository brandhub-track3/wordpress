<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function brandhub_register_case_study_post_type() {
    register_post_type( 'case_study', array(
        'labels' => array(
            'name' => __( 'Case Studies', 'brandhub-core' ),
            'singular_name' => __( 'Case Study', 'brandhub-core' ),
            'add_new_item' => __( 'Add New Case Study', 'brandhub-core' ),
            'edit_item' => __( 'Edit Case Study', 'brandhub-core' ),
        ),
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => array( 'slug' => 'case-studies' ),
        'menu_icon' => 'dashicons-portfolio',
        'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
    ) );
}
add_action( 'init', 'brandhub_register_case_study_post_type' );
