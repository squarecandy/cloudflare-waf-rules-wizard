# Copilot Agent Instructions — cloudflare.localhost

## Project Context

This is a Mac-only PHP tool for managing Cloudflare accounts (WAF rules, proxies, fail2ban integration). Uses `vendor/bin/phpcs` / `vendor/bin/phpcbf` with wp-coding-standards. PHP files are linted and auto-fixed regularly.

## Pre-Approved Terminal Commands

The following commands have standing permission and **do not require confirmation** each session:

### Code Standards
- `vendor/bin/phpcs [files]` — lint PHP files for coding standards violations
- `vendor/bin/phpcbf [files]` — auto-fix coding standards violations in PHP files

### Read-Only Inspection
- `grep [options] [pattern] [files]` — search file contents
- `head [options] [file]` — inspect start of files
- `tail [options] [file]` — inspect end of files
- `php -l [file]` — PHP syntax check (lint only, no execution)
- `ls [options] [path]` — list directory contents
- `cat [file]` — read file contents

### Error Suppression
- Redirecting stderr to `/dev/null` (e.g., `2>/dev/null`) is always acceptable — this suppresses expected "file not found" type errors, not file writes.

## Restrictions That Still Apply
- Do NOT overwrite or delete project files via terminal commands without explicit confirmation.
- Do NOT run commands outside the project root (`/Users/peterwise/Sites/cloudflare.localhost/`).
- Do NOT run background processes (`isBackground: true`) — all commands should show visible output.

## Code Standards
- Standard: wp-coding-standards/wpcs via `vendor/bin/phpcs`
- Config: `phpcs.xml` in project root
- PHP target: compatibility range defined in phpcs.xml
- After any multi-file edit, run phpcs on changed files and auto-fix with phpcbf before finishing.
