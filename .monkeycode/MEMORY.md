# User Instruction Memory

This file records user instructions, preferences, and project knowledge for reference in future interactions.

## Entries

[Project Knowledge Summary]
- Date: 2026-10-11
- Context: Discovered by Agent while setting up the NaijaMart WordPress store
- Category: Operations & Deployment
- Instructions:
  - Start the site: `wp server --host=0.0.0.0 --port=8080 --allow-root` from the workspace root (requires MariaDB running: `service mariadb start`).
  - Database: MariaDB `wordpress`, user `wpuser`, password `wp_pass_local` (local dev only).
  - WP-CLI is installed globally as `wp`; always run with `--allow-root` in this environment.
  - Test accounts: admin/`Admin@12345`, retail@example.com/`Retail@123`, wholesale@example.com/`Wholesale@123` (approved wholesale role).
  - Store admin runbook lives in `docs/ADMIN-GUIDE.md`.
  - Site URL is set to the public preview domain (https://8080-90627dca4276ecaa.monkeycode-ai.online), NOT localhost. If the preview domain changes, run `wp option update home/siteurl` + `wp search-replace` to the new domain, otherwise links break behind the proxy.
