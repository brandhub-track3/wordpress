
<?php

if (!defined('ABSPATH')) {
    exit;
}

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

/**
 * Send a content event to Amazon SQS.
 */

function brandhub_send_event($event_data) {

    try {

        $credentialsPath = rtrim(
            getenv('USERPROFILE'),
            '\\/'
        ) . DIRECTORY_SEPARATOR . '.aws' .
        DIRECTORY_SEPARATOR . 'credentials';

        $provider = \Aws\Credentials\CredentialProvider::ini(
            'default',
            $credentialsPath
        );

       
$sqs = new \Aws\Sqs\SqsClient([
    'region' => 'ap-south-1',
    'version' => 'latest',

    // Explicitly use the local AWS credentials file.
    'credentials' => \Aws\Credentials\CredentialProvider::ini(
        'default',
        getenv('USERPROFILE') . '/.aws/credentials'
    )
]);

        $result = $sqs->sendMessage([
            'QueueUrl' => BRANDHUB_SQS_QUEUE_URL,
            'MessageBody' => wp_json_encode($event_data)
        ]);

        error_log(
            'BrandHub SQS Message ID: ' .
            $result->get('MessageId')
        );

        return true;

    } catch (\Throwable $e) {

        error_log(
            'BrandHub SQS failed: ' . $e->getMessage()
        );

        // Do not break WordPress publishing.
        return false;
    }
}


function brandhub_send_sqs_event($event_data) {

    if (!class_exists(SqsClient::class)) {
        error_log('BrandHub: AWS PHP SDK is not loaded.');
        return false;
    }

    $region = function_exists( 'brandhub_env' )
        ? brandhub_env( 'BRANDHUB_AWS_REGION', brandhub_env( 'AWS_REGION', 'ap-south-1' ) )
        : ( getenv( 'BRANDHUB_AWS_REGION' ) ?: ( getenv( 'AWS_REGION' ) ?: 'ap-south-1' ) );

    $queue_url = function_exists( 'brandhub_env' )
        ? brandhub_env( 'BRANDHUB_SQS_QUEUE_URL' )
        : ( getenv( 'BRANDHUB_SQS_QUEUE_URL' ) ?: '' );

    if (empty($queue_url)) {
        error_log('BrandHub: SQS queue URL is missing.');
        return false;
    }

    try {

        // Credentials are loaded from the AWS
        // default credential provider chain.
       $sqs = new \Aws\Sqs\SqsClient([
    'region' => 'ap-south-1',
    'version' => 'latest',

    // Explicitly use the local AWS credentials file.
    'credentials' => \Aws\Credentials\CredentialProvider::ini(
        'default',
        getenv('USERPROFILE') . '/.aws/credentials'
    )
]);
        $result = $sqs->sendMessage([
            'QueueUrl' => $queue_url,
            'MessageBody' => wp_json_encode($event_data)
        ]);

        error_log(
            'BrandHub: SQS message sent. MessageId: ' .
            $result->get('MessageId')
        );

        return $result->get('MessageId');

    } catch (AwsException $e) {

        error_log(
            'BrandHub SQS AWS error: ' .
            $e->getAwsErrorCode()
        );

        error_log(
            'BrandHub SQS error message: ' .
            $e->getAwsErrorMessage()
        );

        return false;

    } catch (\Throwable $e) {

        error_log(
            'BrandHub SQS error: ' . $e->getMessage()
        );

        return false;
    }
}


/**
 * Listen for published content changes.
 *
 * Handles:
 * - First publish
 * - Updates to published posts
 * - Unpublishing published posts
 */
function brandhub_handle_post_status_change(
    $new_status,
    $old_status,
    $post
) {

    if (!$post instanceof WP_Post) {
        return;
    }

    // Only process Posts and Case Studies.
    $allowed_types = ['post', 'case_study'];

    if (!in_array($post->post_type, $allowed_types, true)) {
        return;
    }

    // Ignore revisions and autosaves.
    if (
        wp_is_post_revision($post->ID) ||
        wp_is_post_autosave($post->ID)
    ) {
        return;
    }

    // First publish.
    if (
        $new_status === 'publish' &&
        $old_status !== 'publish'
    ) {
        $event = 'content.published';
        $status = 'published';
    }

    // Update an already published post.
    elseif (
        $new_status === 'publish' &&
        $old_status === 'publish'
    ) {
        $event = 'content.updated';
        $status = 'published';
    }

    // Unpublish an existing published post.
    elseif (
        $old_status === 'publish' &&
        $new_status !== 'publish'
    ) {
        $event = 'content.unpublished';
        $status = 'unpublished';
    } else {
        return;
    }

    $event_data = [
        'event'       => $event,
        'source'      => 'wordpress',
        'contentId'   => (string) $post->ID,
        'contentType' => $post->post_type,
        'status'      => $status,
        'title'       => get_the_title($post->ID),
        'url'         => get_permalink($post->ID),
        'updatedAt'   => get_post_modified_time(
            'c',
            true,
            $post->ID
        )
    ];

    brandhub_send_sqs_event($event_data);
}

add_action(
    'transition_post_status',
    'brandhub_handle_post_status_change',
    10,
    3
);


/**
 * Send delete event before permanent deletion.
 */
function brandhub_handle_post_delete($post_id, $post) {

    if (!$post instanceof WP_Post) {
        return;
    }

    $allowed_types = ['post', 'case_study'];

    if (!in_array($post->post_type, $allowed_types, true)) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    $event_data = [
        'event'       => 'content.deleted',
        'source'      => 'wordpress',
        'contentId'   => (string) $post_id,
        'contentType' => $post->post_type,
        'status'      => 'deleted',
        'title'       => get_the_title($post_id),
        'url'         => get_permalink($post_id),
        'updatedAt'   => gmdate('c')
    ];

    brandhub_send_sqs_event($event_data);
}

add_action(
    'before_delete_post',
    'brandhub_handle_post_delete',
    10,
    2
);
