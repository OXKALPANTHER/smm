# Hosting Royal SMM outside Render

Royal SMM is not locked to Render. The application is a PHP 8.2 web app with two supported database modes:

- **SQLite** for a simple single-server installation. The host must allow PHP to write to `data/`.
- **PostgreSQL/Supabase** for production and multiple app instances. Set `DB_DRIVER=pgsql` and the `DB_*` variables.

The included Docker image installs PHP, cURL, SQLite, and PostgreSQL PDO support. This makes the same image usable on a VPS or a Docker host such as Fly.io, Koyeb, Railway, DigitalOcean, Hetzner, AWS, Google Cloud Run, or another provider that supports Docker.

## Why `config.php` contains many settings

`config.php` is the central compatibility layer. It contains application defaults and shared functions used by every page for:

- database connection and SQLite/PostgreSQL compatibility;
- FastWay provider requests and TSh pricing;
- Brevo HTTPS email delivery;
- sessions, security checks, notifications, and email templates;
- shared support and application links.

Secrets are **not supposed to be hardcoded there**. They are read from environment variables, so the same source code can run on different hosts. The Render-specific file is only `render.yaml`; it is not required elsewhere.

## Option 1: Any Docker host

1. Install Docker and Docker Compose on the host.
2. Clone the repository.
3. Create the environment file:

```bash
cp .env.example .env
nano .env
```

4. For the simplest one-server setup, use:

```text
APP_URL=https://your-domain.example
DB_DRIVER=sqlite
FASTWAY_API_KEY=your-fastway-key
BREVO_API_KEY=your-brevo-api-key
BREVO_FROM_EMAIL=your-verified-sender@example.com
```

5. Start the app:

```bash
docker compose up -d --build
```

The app listens on the host port in `APP_PORT` (default `8080`). Put HTTPS/TLS in front of it using the host's reverse proxy or Caddy/Nginx.

For production with more than one app instance, use PostgreSQL instead of SQLite:

```text
DB_DRIVER=pgsql
DB_HOST=your-postgres-host
DB_PORT=5432
DB_NAME=postgres
DB_USER=your-user
DB_PASS=your-password
```

Run `supabase_schema.sql` once when using Supabase/PostgreSQL. Keep `data/` on a persistent volume if SQLite is used.

## Option 2: Existing PHP/Apache or Nginx server

Required software:

- PHP 8.2 or newer;
- PHP extensions: `curl`, `pdo`, `pdo_sqlite` for SQLite, or `pdo_pgsql` for PostgreSQL;
- Apache with PHP or Nginx with PHP-FPM;
- outbound HTTPS access to FastWay and Brevo;
- a writable `data/` directory when using SQLite.

Point the document root at this repository, copy `.env.example` to `.env`, set the variables, and configure the web server to route `.php` files to PHP. Do not expose `.env`, `.git`, or the `data/` directory for direct downloads.

The application now fails clearly when `DB_DRIVER=pgsql` is selected but PostgreSQL support or the database connection is unavailable; it will not silently create a new empty SQLite database in that situation.

## What is not currently supported

Typical shared PHP hosts that provide only MySQL are not drop-in compatible. This codebase uses SQLite or PostgreSQL through its PDO compatibility layer. Use a Docker/VPS host or PostgreSQL instead of uploading it to a MySQL-only shared host.

## Required host variables

| Variable | Required | Purpose |
|---|---:|---|
| `APP_URL` | Recommended | Absolute URL used in email buttons |
| `DB_DRIVER` | Yes | `sqlite` or `pgsql` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | PostgreSQL only | PostgreSQL/Supabase connection |
| `DB_PATH` | SQLite only, optional | Custom writable SQLite file path |
| `FASTWAY_API_KEY` | Yes | Supplier API credential |
| `BREVO_API_KEY` | For real email | Brevo HTTPS API credential |
| `BREVO_FROM_EMAIL` | For real email | Verified Brevo sender |
| `BREVO_FROM_NAME`, `BREVO_REPLY_TO`, `BREVO_API_TIMEOUT` | Optional | Email presentation and timeout |

Never commit `.env` or place real API keys in tracked PHP files.
