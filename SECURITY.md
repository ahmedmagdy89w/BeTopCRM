# Security Policy

## Supported Versions

Security fixes are applied to the latest release on the `main` branch. Please make sure you can reproduce an issue on the current `main` before reporting it.

## Reporting a Vulnerability

**Please do not open a public issue for security vulnerabilities.**

Report privately by email to **security@betop.dev**.

Include as much of the following as you can:

- A description of the vulnerability and its impact
- Steps to reproduce, or a proof of concept
- The affected version or commit, and your environment (PHP, database, browser)
- Any suggested fix

## What to Expect

- Acknowledgement within **72 hours**
- An initial assessment within **7 days**
- Updates as the fix progresses, and credit in the release notes if you wish

Please give us reasonable time to release a fix before any public disclosure.

## Scope

In scope: the Laravel API, the Vue frontend, authentication and authorization, file uploads, webhooks, and import/export.

Out of scope: vulnerabilities in third-party dependencies that have no exploitable path in this project (please report those upstream), social engineering, and denial-of-service through excessive request volume.

## Hardening Notes for Deployers

- Change the seeded administrator credentials (`ADMIN_EMAIL`, `ADMIN_PASSWORD`) before first use.
- Generate your own `APP_KEY`, set `APP_DEBUG=false`, and keep `.env` out of version control.
- Serve the application over HTTPS and set `SANCTUM_STATEFUL_DOMAINS` to your real frontend domain only.
