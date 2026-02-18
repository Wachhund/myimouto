# MyImouto
[![PHP CI](https://github.com/Wachhund/myimouto/actions/workflows/php.yml/badge.svg)](https://github.com/Wachhund/myimouto/actions/workflows/php.yml)

MyImouto is an actively maintained Moebooru port for PHP and MySQL.

It runs on a custom Rails-inspired PHP framework and aims to stay close to Moebooru behavior while keeping a modern runtime baseline.

## What Is Included
- Image upload and management
- Tagging with aliases and implications
- Search and filter endpoints
- User accounts, moderation tools, and staff workflows
- Community features (comments, forum, dmail)

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
3. Commit your changes.
4. Push your branch.
5. Open a pull request.

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

## Mail Namespace Migration Notes

MyImouto treats `MyImouto\\Mail\\*` and `MyImouto\\Mime\\*` as canonical runtime namespaces.

`Zend\\Mail\\*` and `Zend\\Mime\\*` are compatibility shims only.

Shim removal criteria:
- no first-party code depends on `Zend\\*` internals beyond compatibility entrypoints
- mail regression tests (password reset + dmail notification paths) pass on canonical classes
- no unresolved external/runtime references to `Zend\\*` mail classes are observed during rollout

Rollback guidance:
- keep `lib/Zend/*` wrappers in place for mixed-version deploy windows
- if regressions occur, roll back to the previous release and keep wrappers enabled while restoring canonical behavior
