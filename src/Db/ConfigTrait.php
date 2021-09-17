<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

trait ConfigTrait
{
    /**
     * Get the full path to the database file
     *
     * @param string $database
     * @param array $options
     *
     * @return string
     */
    private function filename($database, $options)
    {
        // By default, connect to the in memory database.
        if (!$database) {
            return ':memory:';
        }
        return rtrim($options['directory'], '/\\') . "/$database";
    }
}
