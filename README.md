# MyImouto
[![PHP CI](https://github.com/Wachhund/myimouto/actions/workflows/php.yml/badge.svg)](https://github.com/Wachhund/myimouto/actions/workflows/php.yml)

This repository is actively maintained. Feel free to fork it or open a pull request.

MyImouto is a clone of [Moebooru](https://github.com/moebooru/moebooru) for PHP and MySQL. In order for this clone to be as exact as possible, MyImouto uses a custom framework that is based on Ruby on Rails, thus the code from Moebooru was transcribed to PHP with some small modifications to fit the target language and framework.

For project updates and source code, see:
- https://github.com/Wachhund/myimouto


## Requirements

  * PHP 8.5.
  * MySQL 8.0+ (recommended baseline) or MariaDB 10.6+.
  * Legacy DB compatibility note: most schema/migrations remain compatible with MySQL 5.5.3+, but this is not a tested/supported target anymore.
  * PHP libraries:
    * GD2
    * PDO
    * cURL
    * Imagick (recommended)
    * Memcached (recommended)
  * Composer (Dependency Management for PHP).
  * If running under Apache, the Rewrite mod must be enabled. Also, to serve gzipped CSS and JS files, the Headers mod is needed.


## Installation

For an explained, step-by-step guide, please check:
- [Server Setup (Ubuntu 24.04 + PHP 8.5 + Nginx)](docs/SERVER_SETUP_UBUNTU_24_04_PHP85.md)

Otherwise, here's the quick guide for advanced users:

  * Install system dependencies: `composer install`.
  * Create a database for the booru.
  * Create `config/config.php` and `config/database.yml` by copying their respective _.example_ files.
  * Set your database configuration in `config/database.yml`.
  * Configure the booru by editing `config/config.php`. For a minimum configuration, both `server_host` and `url_base` options must be correctly set.
  * Run the installer: `php install.php`. Enter a name and password for the admin account when asked, then wait for the installation to finish.
  * Finally, point the document root of your web server to the `public` folder. That's where the index.php file is.


## Updating

Every time you update the files, don't forget to run `composer update` to update dependencies, specially for the framework, and also run `php config/boot.php db:migrate` to run database migrations (if any).

## Mail Namespace Migration Notes

MyImouto now treats `MyImouto\\Mail\\*` and `MyImouto\\Mime\\*` as the canonical runtime namespace for mail/message behavior.

`Zend\\Mail\\*` and `Zend\\Mime\\*` are compatibility shims only.

Shim removal criteria:
- no first-party code depends on `Zend\\*` internals beyond compatibility entrypoints.
- mail regression tests (password reset + dmail notification paths) pass on canonical classes.
- no unresolved external/runtime references to `Zend\\*` mail classes are observed during rollout.

Rollback guidance:
- keep `lib/Zend/*` wrappers in place for mixed-version deploy windows.
- if mail regressions occur after migration, roll back to previous release and keep shim wrappers enabled while restoring canonical behavior under `MyImouto\\*`.
