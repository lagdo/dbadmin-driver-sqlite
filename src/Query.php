<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\ForeignKeyEntity;
use Lagdo\DbAdmin\Driver\Entity\TriggerEntity;
use Lagdo\DbAdmin\Driver\Entity\RoutineEntity;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;

use Lagdo\DbAdmin\Driver\Db\Query as AbstractQuery;

class Query extends AbstractQuery
{
    /**
     * @inheritDoc
     */
    public function insertOrUpdate(string $table, array $rows, array $primary)
    {
        $values = [];
        foreach ($rows as $set) {
            $values[] = "(" . implode(", ", $set) . ")";
        }
        return $this->driver->queries("REPLACE INTO " . $this->driver->table($table) .
            " (" . implode(", ", array_keys(reset($rows))) . ") VALUES\n" . implode(",\n", $values));
    }

    /**
     * @inheritDoc
     */
    public function user()
    {
        return get_current_user(); // should return effective user
    }

    /**
     * @inheritDoc
     */
    public function view(string $name)
    {
        return [
            "select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '',
                $this->connection->result("SELECT sql FROM sqlite_master WHERE name = " .
                $this->driver->quote($name)))
            ]; //! identifiers may be inside []
    }

    /**
     * @inheritDoc
     */
    public function begin()
    {
        return $this->driver->queries("BEGIN");
    }

    /**
     * @inheritDoc
     */
    public function lastAutoIncrementId()
    {
        return $this->connection->result("SELECT LAST_INSERT_ROWID()");
    }

    /**
     * @inheritDoc
     */
    public function explain(ConnectionInterface $connection, string $query)
    {
        return $connection->query("EXPLAIN QUERY PLAN $query");
    }
}
