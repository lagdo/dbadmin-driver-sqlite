<?php

$di = \jaxon()->di();
// Register the database classes in the dependency container
$di->set(Lagdo\DbAdmin\Driver\Sqlite\Driver::class, function($di) {
    $util = $di->get(Lagdo\DbAdmin\Driver\UtilInterface::class);
    $trans = $di->get(Lagdo\DbAdmin\Driver\TranslatorInterface::class);
    $options = $di->get('adminer_config_options');
    return new Lagdo\DbAdmin\Driver\Sqlite\Driver($util, $trans, $options);
});
$di->alias('adminer_driver_sqlite', Lagdo\DbAdmin\Driver\Sqlite\Driver::class);
