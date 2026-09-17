<p align="center">
  <img src=".github/logo.svg" alt="" width="80" height="80">
</p>

<h1 align="center">DMARCo Backend</h1>

<p align="center">
  Self-hosted DMARC report processing: the API, the workers, and the pipeline
  behind DMARCo.
</p>

<p align="center">
  <a href="LICENSE"><img alt="License: Apache-2.0" src="https://img.shields.io/badge/license-Apache--2.0-blue.svg"></a>
  <a href="https://github.com/dmarcoapp/backend/actions/workflows/ci.yml"><img alt="CI" src="https://github.com/dmarcoapp/backend/actions/workflows/ci.yml/badge.svg"></a>
  <a href="https://github.com/dmarcoapp/backend/pkgs/container/backend"><img alt="Container image" src="https://img.shields.io/badge/ghcr.io-dmarcoapp%2Fbackend-1f6feb"></a>
</p>

> [!IMPORTANT]
> **Start at [dmarcoapp/dmarcoapp](https://github.com/dmarcoapp/dmarcoapp).**
> That repository installs all of DMARCo with one command: this backend, the
> dashboard, and the mail gateway. It is also the issue tracker for the whole
> project, so
> [report anything that goes wrong there](https://github.com/dmarcoapp/dmarcoapp/issues/new/choose),
> including problems in this component. What follows is one component's source,
> for people working on it.

This is the part of DMARCo that turns report email into data you can query: a
Symfony API, the workers behind it, the database model, the schedulers, and the
report processing pipeline.

Reports reach it as a signed webhook plus a file in S3-compatible storage.
[`dmarcoapp/mail-inbound`](https://github.com/dmarcoapp/mail-inbound) does that
job, and so does any gateway you build against the webhook contract documented
below.

## Overview

```text
DMARC report email
  -> inbound mail gateway
  -> S3-compatible attachment storage
  -> signed webhook
  -> DMARCo Backend
  -> RabbitMQ workers
  -> PostgreSQL
  -> REST API / frontend
```

Services in the Docker stack:

- `php`: Symfony API served by FrankenPHP/Caddy
- `worker`: Symfony Messenger consumer for async report processing and scheduled
  work
- `database`: PostgreSQL
- `rabbitmq`: async queue
- `redis`: cache backend

The stack expects S3-compatible object storage for inbound report attachments.
That can be AWS S3, MinIO, or another compatible service.

## Features

- Signed inbound webhook for DMARC report email metadata
- S3-compatible attachment retrieval
- DMARC aggregate XML validated against `config/dmarc/rua.xsd`
- Async processing of reports, report records, domains, and source IP data
- REST API under `/v1`, with OpenAPI documentation at `/v1/doc`
- Registration, email verification, login, refresh tokens, password reset, and
  logout
- Two-factor authentication by email or authenticator app
- Dashboard, notification settings, and sender blocklist APIs
- Scheduled cleanup and periodic user and domain processing

## Requirements

- Docker and Docker Compose v2
- S3-compatible bucket for inbound report attachments
- SMTP provider for application emails
- Optional: an inbound mail gateway such as
  [dmarcoapp/mail-inbound](https://github.com/dmarcoapp/mail-inbound)

The application targets the PHP and Symfony versions declared in
`composer.json`.

## Get started

Clone the repository and start the development stack:

```bash
git clone https://github.com/dmarcoapp/backend.git
cd backend
docker compose build --pull
docker compose up --wait
```

The API is then on `http://localhost:8080`, and its OpenAPI documentation on
`http://localhost:8080/v1/doc`. The container entrypoint waits for PostgreSQL
and runs Doctrine migrations automatically.

Generate local JWT keys if `config/jwt/private.pem` and `config/jwt/public.pem`
do not exist:

```bash
docker compose exec php bash -lc "bin/console lexik:jwt:generate-keypair --overwrite --no-interaction"
```

Create the first user:

```bash
docker compose exec php bash -lc "bin/console app:user:create"
```

Stop the stack:

```bash
docker compose down --remove-orphans
```

## Configuration

Default values live in `.env`. Put local and production overrides in real
environment variables or uncommitted `.env.local` / `.env.prod.local` files. Do
not commit production secrets.

Important settings:

| Variable | Purpose |
| --- | --- |
| `APP_SECRET` | Symfony application secret |
| `APP_FRONTEND_URL` | Frontend URL used in emails and links |
| `APP_REGISTRATION_ENABLED` | Enables or disables public registration |
| `APP_EMAIL_SENDER_ADDRESS` | Sender address for application emails |
| `APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN` | Domain used to build per-user DMARC aggregate report mailbox addresses |
| `APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET` | HMAC secret for inbound report webhooks |
| `APP_REPORT_RETENTION_DAYS` | Number of days to retain processed reports; `0` disables automatic deletion |
| `CORS_ALLOW_ORIGIN` | Allowed browser origins for the API |
| `DATABASE_URL` | PostgreSQL connection URL |
| `MAILER_DSN` | Symfony Mailer DSN |
| `MESSENGER_ASYNC_HIGH_TRANSPORT_DSN` | High-priority Messenger transport |
| `MESSENGER_ASYNC_LOW_TRANSPORT_DSN` | Low-priority Messenger transport |
| `REDIS_DSN` | Redis cache provider |
| `LOCK_DSN` | Symfony Lock backend |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` / `JWT_PASSPHRASE` | JWT signing key configuration |
| `S3_ENDPOINT` / `S3_REGION` / `S3_BUCKET` | S3-compatible storage target |
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` | S3 credentials |
| `S3_FORCE_PATH_STYLE` | Enables path-style S3 URLs, which MinIO needs |

## Inbound report webhook

The backend accepts inbound report notifications at:

```text
POST /v1/webhook/inbound_report_email
```

The request must include these headers:

- `X-Timestamp`: Unix epoch seconds
- `X-Signature`: `sha256=<hex>`

Signature:

```text
HMAC_SHA256(timestamp + "." + raw_body, APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET)
```

Example payload:

```json
{
  "email_id": "f00dad2064a5c04ad0ef367abe88f46d778d361069036fc59e10bba10b0a8fb1",
  "created_at": "2026-06-03T18:45:00.000Z",
  "from": "reports@example.net",
  "to": ["user-postbox-token@example.com"],
  "message_id": "<report-20260603@example.net>",
  "attachments": [
    {
      "id": "4f7c3ef4-5f9c-41fb-a61a-1d6c75f8f0b7",
      "bucket": "mail",
      "key": "attachments/4f7c3ef4-5f9c-41fb-a61a-1d6c75f8f0b7",
      "filename": "example.net!example.com!1717372800!1717459199.xml",
      "content_type": "application/xml"
    }
  ]
}
```

Message handling:

- The webhook verifies the HMAC signature before dispatching work.
- The first recipient address is matched to a user by its local part.
- User blocklist rules can reject reports by sender address.
- The first attachment is loaded from S3-compatible storage.
- The backend currently processes normalized `.xml` report attachments.
- The XML is validated against the DMARC aggregate report schema before it is
  stored and processed.

If you use `dmarcoapp/mail-inbound`, configure its `WEBHOOK_URL` to point to
this endpoint and use the same webhook secret on both sides.

## Production

For a complete, ready-made installation, including the dashboard and the inbound
mail gateway, use
[`dmarcoapp/dmarcoapp`](https://github.com/dmarcoapp/dmarcoapp). It ships a
Docker Compose stack and an installer that wires all three components together.

Prebuilt images are published to `ghcr.io/dmarcoapp/backend` on every GitHub
release, tagged with the release version and `latest`. To use them instead of a
local build, override the image in a Compose file:

```yaml
services:
    php:
        image: ghcr.io/dmarcoapp/backend:latest
    worker:
        image: ghcr.io/dmarcoapp/backend:latest
```

Build and run the production image yourself:

```bash
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
SERVER_NAME=:80 \
APP_SECRET='<secure random value>' \
APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET='<secure random value>' \
POSTGRES_PASSWORD='<secure random value>' \
RABBITMQ_DEFAULT_USER='<rabbitmq user>' \
RABBITMQ_DEFAULT_PASS='<secure random value>' \
REDIS_DSN='redis://redis' \
MAILER_DSN='<smtp dsn>' \
APP_EMAIL_SENDER_ADDRESS='no-reply@example.com' \
APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN='aggregate-reports.example.com' \
APP_FRONTEND_URL='https://dash.example.com' \
CORS_ALLOW_ORIGIN='^https://dash\.example\.com$' \
S3_ENDPOINT='https://s3.example.com' \
S3_REGION='us-east-1' \
S3_ACCESS_KEY='<s3 access key>' \
S3_SECRET_KEY='<s3 secret key>' \
S3_BUCKET='mail' \
docker compose -f compose.yaml -f compose.prod.yaml up -d --wait
```

The supplied compose file binds the HTTP service to `127.0.0.1:8080` by default.
For a public deployment, either place the app behind a reverse proxy or override
the published ports. If exposing FrankenPHP/Caddy directly with automatic TLS,
publish ports `80`, `443`, and `443/udp`, then set `SERVER_NAME` to the public
API hostname.

Before exposing the service:

- Replace all default secrets and credentials.
- Generate deployment-specific JWT keys.
- Configure PostgreSQL, RabbitMQ, Redis, and S3 storage for durable production
  use.
- Configure `MAILER_DSN` and `APP_EMAIL_SENDER_ADDRESS`.
- Restrict `CORS_ALLOW_ORIGIN` to trusted frontend origins.
- Confirm the inbound gateway validates DMARC emails and signs webhook requests.
- Confirm workers are running with `docker compose ps`.

The `docker_prod.sh` wrapper runs Docker Compose with `compose.yaml`,
`compose.prod.yaml`, and `.env`:

```bash
./docker_prod.sh up -d --wait
```

## Development

Run project commands inside the PHP container:

```bash
docker compose exec php bash -lc "vendor/bin/phpunit"
docker compose exec php bash -lc "vendor/bin/php-cs-fixer fix --dry-run"
docker compose exec php bash -lc "vendor/bin/psalm"
```

Common console commands:

```bash
docker compose exec php bash -lc "bin/console app:user:create"
docker compose exec php bash -lc "bin/console app:user:reset-password"
docker compose exec php bash -lc "bin/console app:email:process-bulk"
docker compose exec php bash -lc "bin/console app:dmarc:report:process-bulk"
docker compose exec php bash -lc "bin/console app:dmarc:domain:process-bulk"
docker compose exec php bash -lc "bin/console app:utils:ip-lookup 8.8.8.8"
```

Generate migrations only with Symfony Maker:

```bash
docker compose exec php bash -lc "bin/console make:migration"
```

## Useful files

- `compose.yaml`: base Docker Compose stack
- `compose.override.yaml`: development overrides
- `compose.prod.yaml`: production image build overrides
- `.env`: committed defaults
- `.env.local`: uncommitted local overrides
- `config/dmarc/rua.xsd`: DMARC aggregate report XML schema
- `config/packages/flysystem.yaml`: S3-compatible attachment storage
  configuration
- `config/packages/messenger.yaml`: async queue configuration
- `frankenphp/Caddyfile`: FrankenPHP/Caddy server configuration

## Related projects

- [`dmarcoapp/dmarcoapp`](https://github.com/dmarcoapp/dmarcoapp): ready-made
  Docker Compose stack and installer for the full application
- [`dmarcoapp/dashboard`](https://github.com/dmarcoapp/dashboard): web UI for
  reviewing DMARC aggregate reports
- [`dmarcoapp/mail-inbound`](https://github.com/dmarcoapp/mail-inbound):
  self-hostable inbound mail gateway for DMARC aggregate reports

## License

Licensed under the Apache License, Version 2.0. See [`LICENSE`](LICENSE) and
[`NOTICE`](NOTICE).
