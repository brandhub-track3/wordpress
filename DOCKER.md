# WordPress deployment

The production deployment uses `Dockerfile`, `docker-compose.prod.yml`, and
`.github/workflows/deploy.yml`. A push to `main` builds the image, pushes the
commit-SHA and `latest` tags to GHCR, then SSHes to EC2 to pull and start the
SHA-tagged image. EC2 does not run `git pull`.

The Compose file publishes WordPress on port 80. MySQL data and WordPress
uploads use named volumes; deployment does not run `down -v` or remove them.
Keep the volume names stable. If EC2 already has data in another Docker volume,
change the Compose volume name to that existing volume before deployment.

Create a GitHub Environment named `production` and add these secrets:

- `EC2_HOST`, `EC2_USER`, `EC2_SSH_PRIVATE_KEY`, `EC2_KNOWN_HOSTS`
- `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`
- `WORDPRESS_DB_NAME`, `WORDPRESS_DB_USER`, `WORDPRESS_DB_PASSWORD`
- `BRANDHUB_AWS_REGION`, `BRANDHUB_SQS_QUEUE_URL`

The matching MySQL and WordPress database name, user, and password values must
be identical. Attach an EC2 IAM role with permission to send to the SQS queue;
do not store AWS access keys in GitHub.

EC2 must have Docker Engine and the Docker Compose plugin installed. Allow
inbound port 80 for the website and restrict port 22 to an approved source.
The public GHCR image is pulled without registry credentials.
