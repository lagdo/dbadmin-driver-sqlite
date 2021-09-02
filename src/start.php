<?php

$di = \jaxon()->di();
// Register the database classes in the dependency container
$di->auto(Lagdo\DbAdmin\Driver\Sqlite\Server::class);
$di->alias('adminer_server_sqlite', Lagdo\DbAdmin\Driver\Sqlite\Server::class);
