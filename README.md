# MyImouto
[![PHP CI](https://github.com/Wachhund/myimouto/actions/workflows/php.yml/badge.svg)](https://github.com/Wachhund/myimouto/actions/workflows/php.yml)
[![Latest Release](https://img.shields.io/github/v/release/Wachhund/myimouto)](https://github.com/Wachhund/myimouto/releases/latest)

MyImouto is an actively maintained Moebooru/Danbooru-style imageboard for PHP and MySQL. Originally created by [Parziphal](https://github.com/Parziphal), the project is now under active development again with a focus on security, moderation tooling, and modern PHP compatibility.

It runs on a custom Rails-inspired PHP framework and aims to stay close to Moebooru behavior while keeping a modern runtime baseline.

## Features

**Core**
- Image upload, processing, and management (GD2/Imagick)
- Tagging with aliases, implications, and type categories
- Powerful search and filter engine with API endpoints
- Pools, notes, and parent/child post relationships

**Community**
- User accounts with role-based access (Member → Privileged → Contributor → Janitor → Mod → Admin)
- Comments, forum with topic subscriptions, and direct messages (dmail)
- User profile customization and favorites

**Moderation & Admin**
- Post replacement workflow with staged uploads and admin approval
- Mod action audit log for accountability
- Ticket and DMCA takedown system
- Upload whitelist for URL-based uploads
- Exception log viewer with auto-pruning
- IP bans, user records, and flagged post management

**Security & Compliance**
- CSRF protection on all state-changing endpoints
- Scoped API keys with usage tracking and expiration
- Terms of Service acceptance gate with version bumping (HTTP 451 for API)
- User self-deletion with async data cleanup
- Username change requests with moderation workflow
- Rate limiting on auth endpoints

**Infrastructure**
- Background job scheduler with concurrent daemon support
- CI pipeline (GitHub Actions): lint, PHPUnit, PHPStan, PHP-CS-Fixer
- Asset pipeline with CSS/JS compilation and Brotli pre-compression

## Requirements

| Component | Baseline |
| --- | --- |
| PHP | 8.5+ |
| MySQL | 8.0+ |
| MariaDB | 10.6+ |
| Composer | 2.x |

Legacy note: many migrations remain compatible with MySQL 5.5.3+, but this is no longer a tested/supported target.

PHP extensions:
- GD2
- PDO
- cURL
- Imagick (recommended)
- Memcached (recommended)

Web server:
- Apache with `mod_rewrite` (and `mod_headers` for gzipped assets), or
- Nginx with URL rewriting configured.

## Installation

Step-by-step server setup (Wiki):
- [Server Setup Guide (EN)](https://github.com/Wachhund/myimouto/wiki/Server-Setup-Guide-EN)
- [Server Setup Guide (DE)](https://github.com/Wachhund/myimouto/wiki/Server-Setup-Guide-DE)

Quick install:

```bash
git clone https://github.com/Wachhund/myimouto.git
cd myimouto

composer install

# Create your database first, then configure:
cp config/config.php.example config/config.php
cp config/database.yml.example config/database.yml

# Run installer (creates base data/admin prompt):
php install.php
```

Then point your web server document root to `public/`.

## Updating

```bash
git pull origin master
composer update
php config/boot.php db:migrate
```

## Documentation And Support
- Repository: https://github.com/Wachhund/myimouto
- Issues: https://github.com/Wachhund/myimouto/issues
- Discussions: https://github.com/Wachhund/myimouto/discussions

## Contributing
1. Fork the repository.
2. Create a feature branch.
3. Run quality gates before committing: `composer run ci:lint && composer run test && composer run analyse`
4. Push your branch and open a pull request against `master`.

CI runs automatically on every PR (lint, tests, static analysis, code style).

## Troubleshooting

Database connection failed:
- Check `config/database.yml`.
- Confirm MySQL/MariaDB is running and reachable.

Images not uploading:
- Verify GD2 or Imagick is installed.
- Check PHP upload limits (`upload_max_filesize`, `post_max_size`).
- Ensure upload directories are writable.

Permission errors under `public/`:
- Verify ownership and permissions for your web server user.

## License

See [LICENSE](LICENSE) for details.
