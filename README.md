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
> dashboard, and the mail gateway if you want it. It is also the issue tracker
> for the whole project, so
> [report anything that goes wrong there](https://github.com/dmarcoapp/dmarcoapp/issues/new/choose),
> including problems in this component. What follows is one component's source,
> for people working on it.

This is the part of DMARCo that turns DMARC reports into data you can query: a
Symfony API, the workers behind it, the database model, the schedulers, and the
report processing pipeline.

Reports reach it three ways. The worker reads them from mailboxes over IMAP,
uploads arrive through the API with a key, and a mail gateway such as
[`dmarcoapp/mail-inbound`](https://github.com/dmarcoapp/mail-inbound), which is
being retired, posts them as a signed webhook. See
[Report intake](#report-intake).

## Overview

```text
mailbox, read over IMAP  ----.
upload with an API key   ----+--> backend -> RabbitMQ workers -> PostgreSQL
mail gateway's webhook   ----'                                -> REST API
```

Services in the Docker stack:

- `php`: Symfony API served by FrankenPHP/Caddy
- `worker`: Symfony Messenger consumer for async report processing and scheduled
  work
- `database`: PostgreSQL
- `rabbitmq`: async queue
- `redis`: cache backend

## Features

- Reports read from any number of mailboxes over IMAP, with TLS and a checked
  certificate
- Reports uploaded through the API with revocable keys, as XML, gzip, or zip
- Signed inbound webhook for a mail gateway, carrying the report XML
- Report archives unpacked within size and compression ratio limits, and XML
  with a document type refused
- DMARC aggregate XML validated against `config/dmarc/rua.xsd` (RFC 7489),
  or `config/dmarc/rfc9990.xsd` when the root is in the RFC 9990 namespace
- Async processing of reports, report records, domains, and source IP data
- REST API under `/v1`, with OpenAPI documentation at `/v1/doc`
- One operator account, created from the command line, with login, refresh
  tokens, and logout
- Optional two-factor authentication with an authenticator app
- Dashboard, notification settings, platform settings, and sender blocklist
  APIs
- Settings that are not infrastructure kept in the database, with the secrets
  among them encrypted
- Scheduled mailbox reads, DNS checks of the domains reports name, deletion of
  reports past the retention window, the weekly overview email, and cleanup

## Requirements

- Docker and Docker Compose v2
- Optional: an SMTP provider, for notification emails
- Optional: a mailbox reachable over IMAP with TLS, where DMARC reports arrive

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

Create the account:

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
| `APP_ENCRYPTION_KEY` | 64 hexadecimal characters, from `openssl rand -hex 32`; encrypts the secrets kept in the database |
| `APP_FRONTEND_URL` | Frontend URL used in emails and links |
| `CORS_ALLOW_ORIGIN` | Allowed browser origins for the API |
| `DATABASE_URL` | PostgreSQL connection URL |
| `MESSENGER_ASYNC_HIGH_TRANSPORT_DSN` | High-priority Messenger transport |
| `MESSENGER_ASYNC_LOW_TRANSPORT_DSN` | Low-priority Messenger transport |
| `REDIS_DSN` | Redis cache provider |
| `LOCK_DSN` | Symfony Lock backend |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` / `JWT_PASSPHRASE` | JWT signing key configuration |

### Settings kept in the database

Report retention, IP lookups, outgoing mail, the sender address, and the report
domain are set on the dashboard's Platform page, through
`/v1/platform/settings`, and kept in the database. The SMTP password is stored
encrypted with `APP_ENCRYPTION_KEY`. Without that key it cannot be read, and has
to be entered again.

Until `APP_ENCRYPTION_KEY` is set, these settings come from the environment
variables that held them before: `MAILER_DSN`, `APP_EMAIL_SENDER_ADDRESS`,
`APP_REPORT_RETENTION_DAYS`, `APP_IP_LOOKUP_ENABLED`,
`APP_DMARC_AGGREGATE_REPORT_EMAIL_RECEIVER_DOMAIN`, and
`APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET`. A webhook secret taken over this way
switches API intake on, so the gateway keeps working. Once there is a key, the
image runs `bin/console app:platform:import-env` on every start. It takes over
the variables an operator set, never the defaults committed in `.env`, and never
overwrites a setting already made. From then on the variables are ignored, and
`--format=json` lists the ones that can go.

## Report intake

Reports come in three ways, side by side if need be. The domain a report is
about comes from the report itself, so any of them can carry any domain.

### Mailboxes over IMAP

Mailboxes are added on the dashboard's Platform page, through
`/v1/platform/mailboxes`, or with `bin/console app:mailbox:add`, which reads the
password from `DMARCO_MAILBOX_PASSWORD` or a hidden prompt. Passwords are stored
encrypted with `APP_ENCRYPTION_KEY`.

The worker reads each mailbox on its own schedule, every 15 minutes by default:

- Only TLS and STARTTLS, with the certificate checked. STARTTLS that the server
  does not take up ends the attempt before the password is sent.
- Messages are found by UID from where the last read stopped, and fetched
  without marking them read. The first read goes back 30 days by default.
- A message that is not a report is never touched. A report email is marked
  read, moved, marked deleted, or left alone once its report is stored. DMARCo
  never expunges.
- At most 100 messages per read, none over 10 MB, one report attachment each.
- After five failed reads in a row a mailbox is tried hourly.

### Uploads with an API key

With API intake switched on, a report file goes to:

```text
POST /v1/intake/reports
Authorization: Bearer dmarco_<key>
```

The body is the report: XML, or the gzip or zip it came in, at most 10 MB. The
answer is `202` when it is stored, `200` when it was stored before, and `422`
with the reason when it is not a report. `X-Report-From`, `X-Report-Recipient`,
and `X-Report-Filename` are recorded when given. Keys are made and revoked on
the Platform page, or with `bin/console app:intake:api-key:create`. Only a hash
of each key is kept.

### Mail gateway webhook

With API intake switched on and a gateway secret set, the backend accepts
inbound report notifications at the address below. Otherwise it answers `503`,
which the gateway takes as a reason to keep the report and try again later.
The endpoint is:

```text
POST /v1/webhook/inbound_report_email
```

The request must include these headers:

- `X-Timestamp`: Unix epoch seconds
- `X-Signature`: `sha256=<hex>`

Signature:

```text
HMAC_SHA256(timestamp + "." + raw_body, gateway secret)
```

Example payload:

```json
{
  "email_id": "f00dad2064a5c04ad0ef367abe88f46d778d361069036fc59e10bba10b0a8fb1",
  "created_at": "2026-06-03T18:45:00.000Z",
  "from": "reports@example.net",
  "to": ["report-address-token@example.com"],
  "message_id": "<report-20260603@example.net>",
  "attachments": [
    {
      "filename": "example.net!example.com!1717372800!1717459199.xml",
      "content_type": "application/xml",
      "content": "H4sIAAAAAAAAA7Ox..."
    }
  ]
}
```

Message handling:

- The webhook verifies the HMAC signature before dispatching work.
- The first recipient address must be the gateway's report address.
- Blocklist rules can reject reports by sender address.
- The first attachment carries the report XML in `content`, gzip-compressed and
  base64-encoded, at most 8 MB once unpacked. One that cannot be unpacked gets
  `422`, so the sender keeps it.
- The backend currently processes normalized `.xml` report attachments.
- The XML is validated against the DMARC aggregate report schema before it is
  stored and processed.

If you use `dmarcoapp/mail-inbound`, configure its `WEBHOOK_URL` to point to
this endpoint and use the same secret on both sides. Until `APP_ENCRYPTION_KEY`
is set, the secret is `APP_INBOUND_REPORT_EMAIL_WEBHOOK_SECRET`.

## Production

For a complete, ready-made installation, including the dashboard, use
[`dmarcoapp/dmarcoapp`](https://github.com/dmarcoapp/dmarcoapp). It ships a
Docker Compose stack and an installer that wires the components together and
sets up how reports come in.

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
POSTGRES_PASSWORD='<secure random value>' \
RABBITMQ_DEFAULT_USER='<rabbitmq user>' \
RABBITMQ_DEFAULT_PASS='<secure random value>' \
REDIS_DSN='redis://redis' \
APP_ENCRYPTION_KEY='<openssl rand -hex 32>' \
APP_FRONTEND_URL='https://dash.example.com' \
CORS_ALLOW_ORIGIN='^https://dash\.example\.com$' \
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
- Configure PostgreSQL, RabbitMQ, and Redis for durable production use.
- Set up outgoing mail on the dashboard's Platform page if you want
  notification emails.
- Restrict `CORS_ALLOW_ORIGIN` to trusted frontend origins.
- Add a mailbox or an API key on the Platform page, so reports have a way in.
- If you run a mail gateway, confirm it signs its webhook requests.
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
docker compose exec php bash -lc "bin/console app:user:disable-two-factor"
docker compose exec php bash -lc "bin/console app:email:process-bulk"
docker compose exec php bash -lc "bin/console app:dmarc:report:process-bulk"
docker compose exec php bash -lc "bin/console app:dmarc:domain:process-bulk"
docker compose exec php bash -lc "bin/console app:dmarc:domain:process-new"
docker compose exec php bash -lc "bin/console app:dmarc:report:delete-expired"
docker compose exec php bash -lc "bin/console app:weekly-overview:send"
docker compose exec php bash -lc "bin/console app:utils:ip-lookup 8.8.8.8"
docker compose exec php bash -lc "bin/console app:mailbox:list"
docker compose exec php bash -lc "bin/console app:mailbox:test <name>"
docker compose exec php bash -lc "bin/console app:mailbox:poll <name>"
docker compose exec php bash -lc "bin/console app:intake:api-key:create <name>"
docker compose exec php bash -lc "bin/console app:platform:import-env"
```

`bin/console list app` shows them all.

Most mailbox tests need a real IMAP server, and are skipped without one. The
STARTTLS tests run against a fake server of their own. This starts GreenMail
with a certificate from a throwaway CA, which the tests trust through
`openssl.cafile`:

```bash
tests/Integration/Imap/start-greenmail.sh var/imap-test dmarco-backend_default
docker compose run --rm --no-deps --entrypoint "" \
  -v "$PWD/var/imap-test:/imap-test:ro" -e APP_ENV=test \
  -e IMAP_TEST_HOST=dmarco-greenmail -e IMAP_TEST_API=http://dmarco-greenmail:8080 \
  php php -d openssl.cafile=/imap-test/ca.pem vendor/bin/phpunit --group imap
docker rm -f dmarco-greenmail
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
- `config/dmarc/rua.xsd`: RFC 7489 DMARC aggregate report XML schema
- `config/dmarc/rfc9990.xsd`: RFC 9990 DMARC aggregate report XML schema
- `config/packages/messenger.yaml`: async queue configuration
- `frankenphp/Caddyfile`: FrankenPHP/Caddy server configuration

## Related projects

- [`dmarcoapp/dmarcoapp`](https://github.com/dmarcoapp/dmarcoapp): ready-made
  Docker Compose stack and installer for the full application
- [`dmarcoapp/dashboard`](https://github.com/dmarcoapp/dashboard): web UI for
  reviewing DMARC aggregate reports
- [`dmarcoapp/mail-inbound`](https://github.com/dmarcoapp/mail-inbound): the
  mail gateway that receives report email on port 25, being retired in favor of
  mailboxes read over IMAP

## License

Licensed under the Apache License, Version 2.0. See [`LICENSE`](LICENSE) and
[`NOTICE`](NOTICE).
