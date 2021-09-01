<?php

// Register the database classes in the dependency container
\jaxon()->di()->set('adminer_server_sqlite', function($di) {
    return new Lagdo\DbAdmin\Driver\Sqlite\Server(
        $di->get(Lagdo\Adminer\Driver\DbInterface::class),
        $di->get(Lagdo\Adminer\Driver\UtilInterface::class));
});
