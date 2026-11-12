<?php
/**
 * BrandHub Theme functions.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'after_setup_theme', function () {
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    register_nav_menus( array(
        'primary' => __( 'Primary Navigation', 'brandhub-theme' ),
        'footer'  => __( 'Footer Navigation', 'brandhub-theme' ),
    ) );
} );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'brandhub-theme-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get( 'Version' )
    );
} );
