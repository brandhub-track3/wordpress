<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function brandhub_register_product_taxonomy() {
    register_taxonomy( 'product', array( 'post', 'case_study' ), array(
        'labels' => array(
            'name' => __( 'Products', 'brandhub-core' ),
            'singular_name' => __( 'Product', 'brandhub-core' ),
        ),
        'public' => true,
        'hierarchical' => true,
        'show_in_rest' => true,
        'rewrite' => array( 'slug' => 'product' ),
    ) );

    // Use WordPress' built-in Tags taxonomy for both content types.
    register_taxonomy_for_object_type( 'post_tag', 'case_study' );
}
add_action( 'init', 'brandhub_register_product_taxonomy' );
