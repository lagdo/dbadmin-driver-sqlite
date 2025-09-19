<?php

if (function_exists('Jaxon\jaxon')) {
    $di = Jaxon\jaxon()->di();
    // Register the database classes in the dependency container
    $di->set(Lagdo\DbAdmin\Driver\Sqlite\Driver::class, function($di) {
        $utils = $di->g(Lagdo\DbAdmin\Driver\Utils\Utils::class);
        $config = $di->g(Lagdo\DbAdmin\Driver\Utils\ConfigInterface::class);
        return new Lagdo\DbAdmin\Driver\Sqlite\Driver($utils, $config->options());
    });
    $di->alias('dbadmin_driver_sqlite', Lagdo\DbAdmin\Driver\Sqlite\Driver::class);
}
