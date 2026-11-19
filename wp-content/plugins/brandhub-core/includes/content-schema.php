<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function brandhub_unified_content_schema(): array {
    $context = array( 'view', 'edit' );

    return array(
        'description'          => __( 'Unified BrandHub representation for posts and case studies.', 'brandhub-core' ),
        'type'                 => 'object',
        'context'              => $context,
        'readonly'             => true,
        'required'             => array(
            'schemaVersion',
            'id',
            'contentType',
            'status',
            'slug',
            'title',
            'subtitle',
            'excerpt',
            'content',
            'url',
            'date',
            'modifiedAt',
            'featured',
            'featuredImage',
        ),
        'properties'           => array(
            'schemaVersion' => array(
                'description' => __( 'Version of this unified content schema.', 'brandhub-core' ),
                'type'        => 'string',
                'enum'        => array( '1' ),
                'context'     => $context,
            ),
            'id'            => array(
                'description' => __( 'WordPress post ID.', 'brandhub-core' ),
                'type'        => 'integer',
                'context'     => $context,
            ),
            'contentType'   => array(
                'description' => __( 'WordPress content type.', 'brandhub-core' ),
                'type'        => 'string',
                'enum'        => array( 'post', 'case_study' ),
                'context'     => $context,
            ),
            'status'        => array(
                'description' => __( 'Current WordPress post status.', 'brandhub-core' ),
                'type'        => 'string',
                'context'     => $context,
            ),
            'slug'          => array(
                'description' => __( 'URL-safe post name.', 'brandhub-core' ),
                'type'        => 'string',
                'context'     => $context,
            ),
            'title'         => array(
                'description' => __( 'Rendered post title.', 'brandhub-core' ),
                'type'        => 'string',
                'context'     => $context,
            ),
            'subtitle'      => array(
                'description' => __( 'BrandHub subtitle.', 'brandhub-core' ),
                'type'        => 'string',
                'context'     => $context,
            ),
            'excerpt'       => array(
                'description' => __( 'Rendered post excerpt.', 'brandhub-core' ),
                'type'        => 'string',
                'context'     => $context,
            ),
            'content'       => array(
                'description' => __( 'Rendered post content as HTML.', 'brandhub-core' ),
                'type'        => 'string',
                'context'     => $context,
            ),
            'url'           => array(
                'description' => __( 'Canonical post URL.', 'brandhub-core' ),
                'type'        => 'string',
                'format'      => 'uri',
                'context'     => $context,
            ),
            'date'          => array(
                'description' => __( 'Publication date in ISO 8601 format, or null if not published.', 'brandhub-core' ),
                'type'        => 'string',
                'format'      => 'date-time',
                'nullable'    => true,
                'context'     => $context,
            ),
            'modifiedAt'    => array(
                'description' => __( 'Last-modified date in ISO 8601 format.', 'brandhub-core' ),
                'type'        => 'string',
                'format'      => 'date-time',
                'context'     => $context,
            ),
            'featured'      => array(
                'description' => __( 'Whether this content is marked as featured.', 'brandhub-core' ),
                'type'        => 'boolean',
                'context'     => $context,
            ),
            'featuredImage' => array(
                'description' => __( 'Featured image URL, or null if no image is set.', 'brandhub-core' ),
                'type'        => 'string',
                'format'      => 'uri',
                'nullable'    => true,
                'context'     => $context,
            ),
        ),
    );
}

function brandhub_get_unified_content_field( array $object ): array {
    $post_id = isset( $object['id'] ) ? absint( $object['id'] ) : 0;
    $post    = $post_id ? get_post( $post_id ) : null;

    if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'case_study' ), true ) ) {
        return array();
    }

    $title = $object['title']['rendered'] ?? get_the_title( $post );
    $excerpt = $object['excerpt']['rendered'] ?? get_the_excerpt( $post );
    $content = $object['content']['rendered'] ?? apply_filters( 'the_content', $post->post_content );
    $featured_image = get_the_post_thumbnail_url( $post, 'full' );

    return array(
        'schemaVersion' => '1',
        'id'            => $post_id,
        'contentType'   => $post->post_type,
        'status'        => $post->post_status,
        'slug'          => $post->post_name,
        'title'         => wp_strip_all_tags( $title ),
        'subtitle'      => (string) get_post_meta( $post_id, 'brandhub_subtitle', true ),
        'excerpt'       => (string) $excerpt,
        'content'       => (string) $content,
        'url'           => (string) get_permalink( $post ),
        'date'          => '0000-00-00 00:00:00' === $post->post_date_gmt
            ? null
            : get_post_time( DATE_ATOM, true, $post ),
        'modifiedAt'    => get_post_modified_time( DATE_ATOM, true, $post ),
        'featured'      => rest_sanitize_boolean( get_post_meta( $post_id, 'brandhub_featured', true ) ),
        'featuredImage' => $featured_image ? esc_url_raw( $featured_image ) : null,
    );
}

function brandhub_register_unified_content_schema(): void {
    register_rest_field(
        array( 'post', 'case_study' ),
        'brandhub',
        array(
            'get_callback' => 'brandhub_get_unified_content_field',
            'schema'       => brandhub_unified_content_schema(),
        )
    );
}
add_action( 'rest_api_init', 'brandhub_register_unified_content_schema' );
