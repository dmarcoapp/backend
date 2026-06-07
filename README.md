# DMARCo Backend

Open-source, self-hostable backend for DMARC aggregate report analysis.

DMARCo helps domain owners receive DMARC aggregate reports, validate report XML,
process sending sources and authentication results, and expose the data through a
REST API for dashboards and account workflows.

This repository contains the Symfony API, background workers, database model,
schedulers, and report processing pipeline. For inbound SMTP collection, pair it
with [dmarcoapp/mail-inbound](https://github.com/dmarcoapp/mail-inbound), or use
any gateway that uploads report attachments to S3-compatible storage and calls
the signed webhook documented below.

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
- `worker`: Symfony Messenger consumer for async report processing and scheduled work
- `database`: PostgreSQL
- `rabbitmq`: async queue
- `redis`: cache backend

The stack expects S3-compatible object storage for inbound report attachments.
That can be AWS S3, MinIO, or another compatible service.

## Features

- Signed inbound webhook for DMARC report email metadata
- S3-compatible attachment retrieval
- DMARC aggregate XML validation against `config/dmarc/rua.xsd`
- Async processing of reports, report records, domains, and source IP data
- REST API under `/v1`
- OpenAPI documentation at `/v1/doc`
- User registration, email verification, login, refresh tokens, password reset, and logout
- Two-factor authentication with email and authenticator app support
- User dashboard, notification settings, and sender blocklist APIs
- Scheduled cleanup and periodic user/domain processing
- Docker-based development and production deployment

## Requirements

- Docker and Docker Compose v2
- S3-compatible bucket for inbound report attachments
- SMTP provider for application emails
- Optional: an inbound mail gateway such as
  [dmarcoapp/mail-inbound](https://github.com/dmarcoapp/mail-inbound)

The application targets the PHP and Symfony versions declared in
`composer.json`.

## Get Started

Clone the repository and start the development stack:

```bash
git clone <repo-url>
cd dmarco-backend
docker compose build --pull
docker compose up --wait
```

The API is available on:

```text
http://localhost:8080
```

OpenAPI documentation is available on:

```text
http://localhost:8080/v1/doc
```

The container entrypoint waits for PostgreSQL and runs Doctrine migrations
automatically.

Generate local JWT keys if `config/jwt/private.pem` and
`config/jwt/public.pem` do not exist:

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
environment variables or uncommitted `.env.local` / `.env.prod.local` files.
Do not commit production secrets.

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
| `S3_FORCE_PATH_STYLE` | Enables path-style S3 URLs, useful for MinIO |

## Inbound Report Webhook

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
- The XML is validated against the DMARC aggregate report schema before it is stored and processed.

If you use `dmarcoapp/mail-inbound`, configure its `WEBHOOK_URL` to point to
this endpoint and use the same webhook secret on both sides.

## Production

Build and run the production image:

```bash
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
SERVER_NAME=:80 \
APP_SECRET='<secure random value>' \
docker compose -f compose.yaml -f compose.prod.yaml up -d --wait
```

The supplied compose file binds the HTTP service to `127.0.0.1:8080` by
default. For a public deployment, either place the app behind a reverse proxy or
override the published ports. If exposing FrankenPHP/Caddy directly with
automatic TLS, publish ports `80`, `443`, and `443/udp`, then set
`SERVER_NAME` to the public API hostname.

Before exposing the service:

- Replace all default secrets and credentials.
- Generate deployment-specific JWT keys.
- Configure PostgreSQL, RabbitMQ, Redis, and S3 storage for durable production use.
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

## Useful Files

- `compose.yaml`: base Docker Compose stack
- `compose.override.yaml`: development overrides
- `compose.prod.yaml`: production image build overrides
- `.env`: committed defaults
- `.env.local`: uncommitted local overrides
- `config/dmarc/rua.xsd`: DMARC aggregate report XML schema
- `config/packages/flysystem.yaml`: S3-compatible attachment storage configuration
- `config/packages/messenger.yaml`: async queue configuration
- `frankenphp/Caddyfile`: FrankenPHP/Caddy server configuration
- `docs/`: additional Docker and deployment notes

## License

MIT.
