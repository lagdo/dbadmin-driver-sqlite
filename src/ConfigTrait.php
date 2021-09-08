<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

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
        return rtrim($options['directory'], '/\\') . "/$database";
    }
}
