<?php

use Aws\S3\S3Client;

if (!defined('ABSPATH')) {
    exit;
}

function brandhub_s3_client(): ?S3Client
{
    if (!class_exists(S3Client::class)) {
        return null;
    }

    return new S3Client([
        'version' => 'latest',
        'region' => getenv('AWS_REGION') ?: 'ap-south-1'
    ]);
}

function brandhub_upload_attachment_to_s3(int $attachment_id): void
{
    $file = get_attached_file($attachment_id);
    $bucket = getenv('BRANDHUB_S3_BUCKET');

    if (!$file || !file_exists($file) || !$bucket) {
        return;
    }

    try {
        $client = brandhub_s3_client();

        if (!$client) {
            return;
        }

        $key = 'wordpress/' . gmdate('Y/m/') . wp_unique_filename(
            '',
            basename($file)
        );

        $client->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'SourceFile' => $file,
            'ContentType' => mime_content_type($file) ?: 'application/octet-stream'
        ]);

        update_post_meta($attachment_id, '_brandhub_s3_key', $key);

    } catch (Throwable $e) {
        error_log('BrandHub S3 upload failed: ' . $e->getMessage());
    }
}

add_action('add_attachment', 'brandhub_upload_attachment_to_s3');

function brandhub_attachment_cdn_url(
    string $url,
    int $attachment_id
): string {
    $key = get_post_meta($attachment_id, '_brandhub_s3_key', true);
    $cdn = rtrim(getenv('BRANDHUB_CDN_URL') ?: '', '/');

    if (!$key || !$cdn) {
        return $url;
    }

    return $cdn . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
}

add_filter('wp_get_attachment_url', 'brandhub_attachment_cdn_url', 10, 2);