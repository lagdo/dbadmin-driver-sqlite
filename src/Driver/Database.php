<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Driver;

use Lagdo\DbAdmin\Support\Db\Engine\Driver\AbstractDatabase;

use function intval;
use function is_object;

class Database extends AbstractDatabase
{
    /**
     * @inheritDoc
     */
    public function tables(): array
    {
        return $this->driver->keyValues('SELECT name, type FROM sqlite_master ' .
            "WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
    }

    /**
     * @inheritDoc
     */
    public function countTables(array $databases): array
    {
        $counts = [];
        $query = "SELECT count(*) FROM sqlite_master WHERE type IN ('table', 'view')";
        foreach ($databases as $database) {
            $counts[$database] = 0;
            $connection = $this->driver->newConnection($database);
            $statement = $connection->query($query);
            if (is_object($statement) && ($row = $statement->fetchRow())) {
                $counts[$database] = intval($row[0]);
            }
        }
        return $counts;
    }
}
