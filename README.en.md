# SMM Panel

[Tiếng Việt](README.md) · **English**

An open-source, self-hosted SMM (social media marketing) reseller panel that runs on your own VPS with Docker. Customers top up through VietQR/SePay, place orders and chat with the admin; admins manage everything in Filament. The UI is available in Vietnamese and English.

> The first stable release will be `v1.0.0`. Until then only `-rc.N` pre-releases exist and are not meant for production.

## Features

Only what exists in the source code is listed.

**Customers**
- Register / log in with email + password, Google login, password reset by email (SMTP).
- Optional two-factor authentication (TOTP + recovery codes), enabled by the customer on the Security page; admins can switch this off under Settings → Access.
- Wallet balance and VietQR top-ups; deposits are credited automatically when SePay sends a webhook (with automatic reconciliation every 10 minutes) and a configurable top-up bonus.
- Browse services by platform, place orders (link + quantity), track order history and progress; prices are computed server-side with a Member / Agency tier discount.
- Chat with the admin, announcements on the dashboard.
- Vietnamese / English language switch.

**Admin (`/admin`, TOTP MFA required)**
- Users (lock/unlock, change tier, balance adjustment with a mandatory reason), orders (status, progress, refunds), platforms and services, announcements.
- Bank transactions: assign unmatched deposits to a customer, unassign, ignore, manual import, reconcile; append-only wallet ledger; admin audit log.
- Chat inbox and a Telegram bot that alerts admins about new messages and incidents (webhook, reconciliation, backup).
- Settings: General (site name, logo, favicon, contact, SEO, default language), Access (registration, Google, customer 2FA, maintenance), Appearance (color, custom CSS/HTML), Integrations (SMTP, Google, SePay), Bank & top-up, Member tiers, Telegram.
- Updates page: see the running version and update with one button, including a database backup and automatic rollback on failure.

**Operations**
- One-line install (`install.sh`) plus a browser wizard at `/install`.
- Docker Compose: `app` (nginx + php-fpm), `queue`, `scheduler`, `postgres:17`, `caddy` (automatic HTTPS), `updater`.
- Hourly PostgreSQL backup script and a `custom/` directory for customization that survives updates.

**Current limits**: orders are fulfilled manually by the admin (no supplier API integration); top-ups only via VietQR/SePay; light theme only; release images are built for `linux/amd64` only.

## Screenshots

_No screenshots yet._

## VPS requirements

- Ubuntu 22.04+ or Debian 12+, x86_64 (amd64).
- At least 1 GB RAM (2 GB recommended), root access.
- A domain with an A record pointing to the VPS IP; ports 80 and 443 free.

## Install

```bash
curl -fsSL https://github.com/jhin1m/smm-panel/releases/latest/download/install.sh | sudo bash -s -- --domain panel.example.com
```

The script installs Docker if missing, downloads the release and verifies its checksum, generates `.env` (random secrets), starts the containers and prints `https://<your-domain>/install?token=...`.

- `latest` only resolves after the first stable release. To install a specific version (including a release candidate) add `--version`, for example `... | sudo bash -s -- --domain panel.example.com --version v1.0.0-rc.1`. For an rc, also replace `latest/download` with `download/v1.0.0-rc.1` in the script URL.
- Other flags: `--dir <path>` (default `/opt/smm-panel`), `--yes`, `--dry-run` (print the steps without installing).
- Re-running the script is safe: an existing `.env` is kept.

`install.sh` does not enable scheduled backups: add the cron entry it prints at the end (see [docs/deployment.md](docs/deployment.md)).

### First run: the `/install` wizard

Open the URL with the `token` printed by the script. The wizard has 5 steps: token + language, site info, admin account, bank/SMTP (optional), finish. After finishing, the wizard is locked permanently. Then sign in at `https://<your-domain>/admin`, set up MFA right away and store the recovery codes.

## Updating

- **From the admin**: Settings → Updates → "Update to X". The system backs up the database, switches version, health-checks and rolls back automatically on failure.
- **On the server**: `cd /opt/smm-panel && ./update.sh` (or `./update.sh 1.0.1` for an exact version). Use it when the admin button is unavailable.

Details, rollback and backups: [docs/deployment.md](docs/deployment.md) (Vietnamese).

## Customization

Four layers, easiest first: admin settings, the `custom/` directory (views, text, languages, CSS; preserved across updates), an inherited image, a fork. See [docs/customization.md](docs/customization.md) (Vietnamese).

## Documentation

Most project documentation is written in Vietnamese.

- [Deployment and operations](docs/deployment.md)
- [Customization](docs/customization.md)
- [System architecture](docs/system-architecture.md) · [Codebase summary](docs/codebase-summary.md) · [Tech stack](docs/tech-stack.md)
- [Release process](docs/release-process.md)
- [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md)

## Paid installation and support

The software is free. If you want paid installation, customization or operations support, reach out via GitHub Discussions or Issues of this repository.

## License

[MIT](LICENSE)
