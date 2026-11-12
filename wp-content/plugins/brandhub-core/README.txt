BRANDHUB CORE PLUGIN - INSTALLATION

1. Copy the brandhub-core folder into:
   wp-content/plugins/
   Bedrock: web/app/plugins/
2. In the plugin folder, run:
   composer install
   This installs the AWS SDK for PHP.
3. Configure environment variables in your server environment / Bedrock .env:
   BRANDHUB_AWS_REGION=ap-south-1
   BRANDHUB_SQS_QUEUE_URL=https://sqs.ap-south-1.amazonaws.com/ACCOUNT_ID/content-events
   BRANDHUB_S3_BUCKET=your-private-media-bucket
   BRANDHUB_CDN_URL=https://your-cloudfront-domain
4. Use an IAM role on AWS, or configure the AWS SDK default credential chain
   (for local development, use an AWS shared credentials profile). Do not commit
   AWS access keys or store them in the project .env file.
   The plugin also reads these non-secret settings from the WordPress root .env file.
   AWS_REGION is accepted as a fallback for BRANDHUB_AWS_REGION.
   To test WordPress email with Mailtrap Sandbox, add these entries to the
   WordPress root .env file. Keep SMTP credentials private:
   BRANDHUB_SMTP_HOST=sandbox.smtp.mailtrap.io
   BRANDHUB_SMTP_PORT=587
   BRANDHUB_SMTP_ENCRYPTION=tls
   BRANDHUB_SMTP_USERNAME=your-mailtrap-sandbox-username
   BRANDHUB_SMTP_PASSWORD=your-mailtrap-sandbox-password
   BRANDHUB_SMTP_FROM_EMAIL=your-verified-sender@example.com
   BRANDHUB_SMTP_FROM_NAME=BrandHub
   The WordPress root .htaccess blocks public requests for this file, and .env
   is excluded from Git. PHP loads it on each request; no Apache restart is
   needed after editing. Mailtrap Sandbox captures messages in its inbox and
   does not deliver them to the real recipient. For real delivery, configure a
   transactional SMTP provider instead. Demo notification recipients remain
   the WordPress Administration Email Address.
5. Activate "BrandHub Core" in WordPress Admin > Plugins.
6. Open Settings > Permalinks and click Save once if routes need refreshing.

FEATURES
- Case Studies custom post type (standard WordPress Posts remain built in)
- Product taxonomy for Posts and Case Studies
- Built-in Tags enabled for Case Studies
- REST meta fields: brandhub_subtitle and brandhub_featured
- Unified REST response field (`brandhub`) for Posts and Case Studies, with schema version 1
- Publish, update, unpublish and delete events to SQS through a database outbox with retry
- Newly added media uploads to S3 and optional CloudFront URL rewrite
- Demo lead API: POST /wp-json/brandhub/v1/leads
- Admin leads list with search, pagination and CSV export
- Email notification for each valid demo request, sent to the WordPress Administration Email Address

REST TESTS
GET  /wp-json/wp/v2/case_study
GET  /wp-json/wp/v2/posts?_fields=id,brandhub
GET  /wp-json/wp/v2/case_study?_fields=id,brandhub
GET  /wp-json/wp/v2/product
POST /wp-json/brandhub/v1/leads

The `brandhub` field uses the same shape for both content types:
`schemaVersion`, `id`, `contentType`, `status`, `slug`, `title`, `subtitle`,
`excerpt`, `content` (rendered HTML), `url`, `date`, `modifiedAt`, `featured`,
and `featuredImage`. `schemaVersion` is currently `"1"`.

Example JSON:
{
  "name": "Asha Sharma",
  "email": "asha@example.com",
  "company": "Example Ltd",
  "message": "Please contact me.",
  "consent": true,
  "website": ""
}

IMPORTANT POC / PRODUCTION NOTES
- The content event outbox retries failed SQS sends with exponential backoff. For production, configure a system cron to invoke wp-cron.php regularly so retries do not depend on visitor traffic.
- SQS delivery is at least once: consumers must deduplicate by eventId. Events can arrive out of order; consumers should compare modifiedAt and current content state rather than assume arrival order.
- S3 upload handles newly added original attachment files only. It does not migrate existing media, upload generated image sizes, or delete S3 objects on attachment deletion.
- Configure the S3 bucket as private and CloudFront Origin Access Control correctly. Do not make the bucket public.
- Add CAPTCHA/WAF, robust rate limiting, privacy retention/deletion policy, monitoring and automated tests before production.
- Origin checks alone are not authentication. The local CORS allowlist permits `http://localhost:5173`; update it in `brandhub-core.php` for other frontend origins.
- Lead notification email goes to the Administration Email Address in Settings > General. Configure a working WordPress mail/SMTP transport; a `false` `notificationSent` response means the lead was saved but notification delivery failed. The recipient can be customized with the `brandhub_lead_notification_email` filter.
- The lead queue event contains only leadId, not personal data. If Lambda needs lead details, design a secured retrieval path.
- Protect lead admin access and exported CSV files as personal data.

WINDOWS NOTE
Run composer install from the plugin folder in PowerShell or CMD. Ensure Composer and PHP CLI are on PATH.
