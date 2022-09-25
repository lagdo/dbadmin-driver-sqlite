<?php

if (function_exists('Jaxon\jaxon')) {
    $di = Jaxon\jaxon()->di();
    // Register the database classes in the dependency container
    $di->set(Lagdo\DbAdmin\Driver\Sqlite\Driver::class, function($di) {
        $util = $di->get(Lagdo\DbAdmin\Driver\UtilInterface::class);
        $trans = $di->get(Lagdo\DbAdmin\Driver\TranslatorInterface::class);
        $options = $di->get('dbadmin_config_options');
        return new Lagdo\DbAdmin\Driver\Sqlite\Driver($util, $trans, $options);
    });
    $di->alias('dbadmin_driver_sqlite', Lagdo\DbAdmin\Driver\Sqlite\Driver::class);
}
