<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

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
        return $this->driver->execute("REPLACE INTO " . $this->driver->table($table) .
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
            'name' => $name,
            'type' => 'VIEW',
            'materialized' => false,
            'select' => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '',
                $this->connection->result("SELECT sql FROM sqlite_master WHERE name = " .
                $this->driver->quote($name)))
        ]; //! identifiers may be inside []
    }

    /**
     * @inheritDoc
     */
    public function begin()
    {
        return $this->driver->execute("BEGIN");
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