# Archived Ad-Hoc Scripts

This directory documents ad-hoc scripts that were used during early development and deployment phases.
These scripts are no longer needed for production operations. The canonical deployment workflow is now
handled by `scripts/deploy.sh`, `scripts/fix_perms.sh`, and `scripts/rollback.sh`.

## Previously in repository root (parent `tmp/`)

| Script | Purpose |
|--------|---------|
| `proj1_*.sh` (8 scripts) | PROJ-1 PHP 8.5 migration: login testing, schema checks, VPS smoke tests |
| `proj8_*.sh`, `proj8_*.php` (5 scripts) | PROJ-8 Post Sets: runtime QA, test data seeding, debug |
| `proj16_*.sh` (5 scripts) | PROJ-16 Mail Namespace: smoke tests, seed data, namespace checks |
| `cleanup_remote_script.sh` | One-time server cleanup |
| `ensure_user_logs.sh` | Schema fix for user_logs table |
| `mk_scriptqa.sh` | QA script generator |
| `remote_cmd.sh` | Ad-hoc remote command wrapper |
| `run_cleanup_dry.sh` | Dry-run cleanup |
| `sudo_test.sh` | Sudoers configuration test |
| `tail_prodlog*.sh` (3 scripts) | Production log tailing shortcuts |

## Previously in `myimouto/tmp/`

| Script | Purpose |
|--------|---------|
| `ac3-smoke.js` | PROJ-21 API smoke test (JS) |
| `check_*.sql`, `describe_*.sql`, `fix_*.sql` | Ad-hoc schema inspection/fixes |
| `create_inline_tables.sql` | PROJ-3 Inline module table creation |
| `select_schema_versions.sql`, `sync_schema_migrations.sql` | Migration state inspection |
| `check_*.php`, `debug_*.php`, `generate_*.php` | Debug helpers for various PROJs |
| `proj8_*.ps1` (4 scripts) | Windows PowerShell deploy/QA helpers for PROJ-8 |
| `smoke_settings_api.php` | Settings API smoke test |

## Disposition
These scripts served their one-time purpose and are archived here for reference only.
Do not use them for production operations.
