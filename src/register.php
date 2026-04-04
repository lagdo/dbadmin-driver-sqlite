<?php

use Lagdo\DbAdmin\Support\AbstractDriver;
use Lagdo\DbAdmin\Support\DriverInterface;
use Lagdo\DbAdmin\Support\Sqlite\Driver;
use Lagdo\DbAdmin\Support\Utils\Utils;

AbstractDriver::registerDriver('sqlite', fn($di, array $options): DriverInterface =>
    new Driver($di->g(Utils::class), $options));
