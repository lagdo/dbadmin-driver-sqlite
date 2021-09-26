DbAdmin drivers for SQLite
==========================

This package is based on [Adminer](https://github.com/vrana/adminer).

It provides SQLite drivers for [Jaxon Adminer](https://github.com/lagdo/jaxon-dbadmin), and implements the interfaces defined in [https://github.com/lagdo/dbadmin-driver](https://github.com/lagdo/dbadmin-driver).

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
            Lagdo\DbAdmin\Package::class => [
                'servers' => [
                    'server_id' => [
                        'driver' => 'sqlite',
                        'name' => '',     // The name to be displayed in the dashboard UI.
                        'directory' => '',// The directory containing the database files.
                    ],
                ],
            ],
        ],
    ],
```

Check the [Jaxon Adminer](https://github.com/lagdo/jaxon-dbadmin) documentation for more information about the package usage.
