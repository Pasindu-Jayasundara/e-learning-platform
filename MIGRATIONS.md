# Migrations & Quick Commands

Run the SQL migration files in `db/` to create required tables.

Notable migrations added by recent work:

- `db/messages.sql` — creates the `messages` table used by the private messaging system.
- `db/certificates.sql` — creates `certificate_templates` and `certificates` tables (if not already applied).

Example (PowerShell):

```powershell
mysql -u root -p YourDatabaseName < "c:\Users\Pasindu\Documents\GitHub\e-learning platform\db\messages.sql"
mysql -u root -p YourDatabaseName < "c:\Users\Pasindu\Documents\GitHub\e-learning platform\db\certificates.sql"
```

Stripe webhook notes:

- Set your Stripe secret keys in `includes/config.php` (`stripe_secret`, `stripe_publishable`, `stripe_webhook_secret`).
- Configure your Stripe webhook endpoint to point to `/stripe_webhook.php` on your server.

Quick lint check (PowerShell):

```powershell
.\scripts\run_lint.ps1
```

## Combined migrations

A single-file combined migration is available at `db/combined_migrations.sql`. It concatenates the repository's individual `db/*.sql` migration files (including `schema.sql`, `certificates.sql`, `messages.sql`, `audit_log.sql`, report and payments migrations) with filename separators and a short header.

Recommended usage:

1. Review `db/combined_migrations.sql` before applying it in production.
2. Run against an empty or test database first. Example (PowerShell):

```powershell
mysql -u root -p YourDatabaseName < "c:\Users\Pasindu\Documents\GitHub\e-learning platform\db\combined_migrations.sql"
```

Note: Some migration files may contain duplicate or overlapping ALTER statements (e.g., multiple report/payment migrations). Review and adjust ordering or remove duplicates as needed for your target schema.

