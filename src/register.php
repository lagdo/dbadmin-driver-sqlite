<?php

use Lagdo\DbAdmin\Driver;

Driver\Driver::registerDriver('sqlite',
    function($di, array $options): Driver\DriverInterface {
        $utils = $di->g(Driver\Utils\Utils::class);
        return new Driver\Sqlite\Driver($utils, $options);
    });
