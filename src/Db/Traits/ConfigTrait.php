<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Traits;

use function rtrim;
use function str_replace;
use function file_exists;

trait ConfigTrait
{
    /**
     * Get the full path to the database directory
     *
     * @param array $options
     *
     * @return string
     */
    private function directory(array $options): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $options['directory']), '/\\');
    }

    /**
     * Get the full path to the database file
     *
     * @param string $database
     * @param array $options
     *
     * @return string
     */
    private function filename(string $database, array $options): string
    {
        if (!$database) {
            // By default, connect to the in memory database.
            return ':memory:';
        }
        return $this->directory($options) . DIRECTORY_SEPARATOR . $database;
    }

    /**
     * Check if a database file exists
     *
     * @param string $database
     * @param array $options
     *
     * @return bool
     */
    private function fileExists(string $database, array $options): bool
    {
        return ($database) && file_exists($this->directory($options) . DIRECTORY_SEPARATOR . $database);
    }
}
