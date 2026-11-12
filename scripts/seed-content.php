<?php

if (!defined('ABSPATH')) {
    exit;
}

$products = [
    'Cloud Platform',
    'Data Analytics',
    'Security',
    'Automation'
];

foreach ($products as $product) {
    if (!term_exists($product, 'product')) {
        wp_insert_term($product, 'product');
    }
}

$tags = [
    'Technology',
    'Business',
    'Innovation',
    'Cloud',
    'Customer Success'
];

foreach ($tags as $tag) {
    if (!term_exists($tag, 'post_tag')) {
        wp_insert_term($tag, 'post_tag');
    }
}

for ($i = 1; $i <= 15; $i++) {
    $slug = 'brandhub-blog-' . $i;

    if (get_page_by_path($slug, OBJECT, 'post')) {
        continue;
    }

    $post_id = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => "BrandHub Blog Article $i",
        'post_name' => $slug,
        'post_content' => "This is sample blog content for BrandHub article $i.",
        'post_excerpt' => "Learn about BrandHub topic $i.",
    ]);

    if (!is_wp_error($post_id) && $post_id) {
        wp_set_object_terms($post_id, ['Cloud Platform'], 'product');
        wp_set_object_terms($post_id, ['Technology', 'Innovation'], 'post_tag');
    }
}

for ($i = 1; $i <= 6; $i++) {
    $slug = 'brandhub-case-study-' . $i;

    if (get_page_by_path($slug, OBJECT, 'case_study')) {
        continue;
    }

    $case_id = wp_insert_post([
        'post_type' => 'case_study',
        'post_status' => 'publish',
        'post_title' => "Customer Case Study $i",
        'post_name' => $slug,
        'post_content' => "This is a sample BrandHub customer success story $i.",
        'post_excerpt' => "How a customer achieved better results with BrandHub.",
    ]);

    if (!is_wp_error($case_id) && $case_id) {
        wp_set_object_terms($case_id, ['Data Analytics'], 'product');
        wp_set_object_terms($case_id, ['Customer Success'], 'post_tag');
    }
}

WP_CLI::success('Created sample posts and case studies.');