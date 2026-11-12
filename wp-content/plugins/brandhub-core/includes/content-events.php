<?php

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;
use Aws\Exception\CredentialsException;

if (!defined('ABSPATH')) {
    exit;
}

function brandhub_sqs_client(): ?SqsClient
{
    if (!class_exists(SqsClient::class)) {
        return null;
    }

    return new SqsClient(
        array(
            'version' => 'latest',
            'region'  => brandhub_env( 'BRANDHUB_AWS_REGION', brandhub_env( 'AWS_REGION', 'ap-south-1' ) ),
        )
    );
}

function brandhub_send_event(array $event): bool
{
    $queue_url = brandhub_env( 'BRANDHUB_SQS_QUEUE_URL' );

    if (!$queue_url) {
        error_log('BrandHub: SQS queue URL is missing.');
        return false;
    }

    try {
        $client = brandhub_sqs_client();

        if (!$client) {
            return false;
        }

        $client->sendMessage([
            'QueueUrl' => $queue_url,
            'MessageBody' => wp_json_encode($event)
        ]);

        return true;

    } catch (AwsException $e) {
        error_log('BrandHub SQS error: ' . $e->getAwsErrorCode());
        return false;
    } catch (CredentialsException $e) {
        error_log(
            'BrandHub SQS credentials error: ' .
            sanitize_text_field($e->getMessage()) .
            ' Configure an AWS profile, environment credentials, or an instance role.'
        );
        return false;
    } catch (\Throwable $e) {
        error_log(
            'BrandHub SQS delivery failed (' . get_class($e) . '): ' .
            sanitize_text_field($e->getMessage())
        );
        return false;
    }
}

function brandhub_publish_content_event(
    int $post_id,
    string $status
): void {
    $post = get_post($post_id);

    if (!$post || !in_array(
        $post->post_type,
        ['post', 'case_study'],
        true
    )) {
        return;
    }

  
$event = [
        'event' => 'content.changed',
        'eventId' => wp_generate_uuid4(),
        'occurredAt'=>get_post_modified_time('c', true, $post_id),
        'correlationId'=>wp_generate_uuid4(),        
        'source' => 'wordpress',
         'schemaVersion' => '1.0',
        'content' => [
            'id'=> 'wp:'.$post_id,
            'source'=> 'wordpress',
            'type'=> $post->post_type,
            'title'=> get_the_title($post_id),
            'summary'=> get_the_excerpt($post_id),
            'bodyHtml'=> apply_filters('the_content', $post->post_content),
            'plaintext'=> wp_strip_all_tags($post->post_content),
            'tags'=> array_map(
                function ($tag) {
                    return [
                        'id' => (string) $tag->term_id,
                        'name' => $tag->name
                    ];
                },
                wp_get_post_tags($post_id)
            ),
            'product'=> [
                'id'=> (string) $post->post_parent,
                'name'=> get_the_title($post->post_parent)
            ],
            'author'=> [
                'id'=> (string) $post->post_author,
                'name'=> get_the_author_meta('display_name', $post->post_author)
            ],
            'heroImage'=> '',
            'publishedAt'=> get_post_time('c', true, $post_id),
            'updatedAt'=> get_post_modified_time('c', true, $post_id),
            'version'=> (int) get_post_meta($post_id, '_brandhub_content_version', true),
            'hash'=> hash('sha256', $post->post_content),
            'status'=> $status,
            'schemaVersion'=> '1.0'
        ]
    ];
    brandhub_send_event($event);
}

/**
 * First publication, unpublication and trash transitions.
 */
function brandhub_handle_status_transition(
    string $new_status,
    string $old_status,
    WP_Post $post
): void {
    if (!in_array($post->post_type, ['post', 'case_study'], true)) {
        return;
    }

    if ($new_status === 'publish' && $old_status !== 'publish') {
        brandhub_publish_content_event($post->ID, 'published');
    }

    if ($old_status === 'publish' && $new_status !== 'publish') {
        brandhub_publish_content_event($post->ID, 'unpublished');
    }
}

add_action(
    'transition_post_status',
    'brandhub_handle_status_transition',
    10,
    3
);

/**
 * Updates to content that was already published.
 */
function brandhub_handle_published_update(
    int $post_id,
    WP_Post $post_after,
    WP_Post $post_before
): void {
    if (
        $post_after->post_status !== 'publish' ||
        $post_before->post_status !== 'publish' ||
        !in_array($post_after->post_type, ['post', 'case_study'], true) ||
        wp_is_post_revision($post_id) ||
        wp_is_post_autosave($post_id)
    ) {
        return;
    }

    brandhub_publish_content_event($post_id, 'published');
}

add_action('post_updated', 'brandhub_handle_published_update', 10, 3);

/**
 * Permanent deletion.
 */
function brandhub_handle_permanent_delete(int $post_id): void
{
    $post = get_post($post_id);

    if (!$post || !in_array($post->post_type, ['post', 'case_study'], true)) {
        return;
    }

    brandhub_send_event($event = [
        'eventId' => wp_generate_uuid4(),
        'event' => 'content.deleted',
        'source' => 'wordpress',
        'occurredAt'=>get_post_modified_time('c', true, $post_id),
        'correlationId'=>wp_generate_uuid4(),
        'content' => [
             'id'=> 'wp:'.$post_id,
            'source'=> 'wordpress',
            'type'=> $post->post_type,
            'title'=> get_the_title($post_id),
            'summary'=> get_the_excerpt($post_id),
            'bodyHtml'=> apply_filters('the_content', $post->post_content),
            'plaintext'=> wp_strip_all_tags($post->post_content),
            'tags'=> array_map(
                function ($tag) {
                    return [
                        'id' => (string) $tag->term_id,
                        'name' => $tag->name
                    ];
                },
                wp_get_post_tags($post_id)
            ),
            'product'=> null,
            'author'=> [
                'id'=> (string) $post->post_author,
                'name'=> get_the_author_meta('display_name', $post->post_author)
            ],
            'heroImage'=> get_the_post_thumbnail_url($post_id, 'full'),
            'publishedAt'=> get_post_time('c', true, $post_id),
            'updatedAt'=> get_post_modified_time('c', true, $post_id),
            'version'=> (int) get_post_meta($post_id, '_brandhub_content_version', true),
            'url'=> get_permalink($post_id),
            'hash'=> hash('sha256', $post->post_content),
            'status'=> 'deleted',
            'schemaVersion'=> '1.0'
        ],        
        'contentId' => (string) $post_id,
        'contentType' => $post->post_type,
        'status' => 'deleted',
        'title' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'modifiedAt' => get_post_modified_time('c', true, $post_id),
        'schemaVersion' => '1.0'
    ]);
}

add_action('before_delete_post', 'brandhub_handle_permanent_delete');