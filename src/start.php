<?php

$di = \jaxon()->di();
// Register the database classes in the dependency container
$di->set(Lagdo\DbAdmin\Driver\Sqlite\Server::class, function($di) {
    return new Lagdo\DbAdmin\Driver\Sqlite\Server(
        $di->get(Lagdo\Adminer\Driver\DbInterface::class),
        $di->get(Lagdo\Adminer\Driver\UtilInterface::class));
});
$di->alias('adminer_server_sqlite', Lagdo\DbAdmin\Driver\Sqlite\Server::class);
