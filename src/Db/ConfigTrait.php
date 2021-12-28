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
    private function filename(string $database, array $options)
    {
        // By default, connect to the in memory database.
        if (!$database || !file_exists(($filename = rtrim($options['directory'], '/\\') . "/$database"))) {
            return ':memory:';
        }
        return $filename;
    }
}
