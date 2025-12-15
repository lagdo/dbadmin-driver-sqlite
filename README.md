[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/lagdo/dbadmin-driver-sqlite/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/lagdo/dbadmin-driver-sqlite/?branch=main)
[![StyleCI](https://styleci.io/repos/400403244/shield?branch=main)](https://styleci.io/repos/400403244)
[![Build Status](https://api.travis-ci.com/lagdo/dbadmin-driver-sqlite.svg?branch=main)](https://app.travis-ci.com/github/lagdo/dbadmin-driver-sqlite)
[![Coverage Status](https://coveralls.io/repos/github/lagdo/dbadmin-driver-sqlite/badge.svg?branch=main)](https://coveralls.io/github/lagdo/dbadmin-driver-sqlite?branch=main)

[![Latest Stable Version](https://poser.pugx.org/lagdo/dbadmin-driver-sqlite/v/stable)](https://packagist.org/packages/lagdo/dbadmin-driver-sqlite)
[![Total Downloads](https://poser.pugx.org/lagdo/dbadmin-driver-sqlite/downloads)](https://packagist.org/packages/lagdo/dbadmin-driver-sqlite)
[![License](https://poser.pugx.org/lagdo/dbadmin-driver-sqlite/license)](https://packagist.org/packages/lagdo/dbadmin-driver-sqlite)

DbAdmin drivers for SQLite
==========================

This package is based on [Adminer](https://github.com/vrana/adminer).

It provides SQLite drivers for [Jaxon DbAdmin](https://github.com/lagdo/jaxon-dbadmin), and implements the interfaces defined in [https://github.com/lagdo/dbadmin-driver](https://github.com/lagdo/dbadmin-driver).

It requires either the `php-sqlite3` or the `pdo_sqlite` PHP extension to be installed, and uses the former by default.

**Installation**

Install with Composer.

```
composer require lagdo/dbadmin-driver-sqlite
```

**Configuration**

Declare the directories containing the databases in the `packages` section on the `Jaxon` config file. Set the `driver` option to `sqlite`.
Databases are files with extension `db`, `sdb` or `sqlite`.

```php
    'app' => [
        'packages' => [
            Lagdo\DbAdmin\Db\DbAdminPackage::class => [
                'servers' => [
                    'server_id' => [ // A unique identifier for this server
                        'driver' => 'sqlite',
                        'name' => '',      // The name to be displayed in the dashboard UI.
                        'directory' => '', // The directory containing the database files.
                    ],
                ],
            ],
        ],
    ],
```

Check the [Jaxon DbAdmin](https://github.com/lagdo/jaxon-dbadmin) documentation for more information about the package usage.
