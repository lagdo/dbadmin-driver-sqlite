<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;

use Lagdo\DbAdmin\Driver\Db\Query as AbstractQuery;

class Query extends AbstractQuery
{
    /**
     * @inheritDoc
     */
    protected function limitToOne(string $table, string $query, string $where, string $separator = "\n")
    {
        return preg_match('~^INTO~', $query) ||
            $this->connection->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ?
            $this->driver->limit($query, $where, 1, 0, $separator) :
            //! use primary key in tables with WITHOUT rowid
            " $query WHERE rowid = (SELECT rowid FROM " . $this->driver->table($table) . $where . $separator . "LIMIT 1)";
    }

    /**
     * @inheritDoc
     */
    public function insertOrUpdate(string $table, array $rows, array $primary)
    {
        $values = [];
        foreach ($rows as $set) {
            $values[] = "(" . implode(", ", $set) . ")";
        }
        $result = $this->driver->execute("REPLACE INTO " . $this->driver->table($table) .
            " (" . implode(", ", array_keys(reset($rows))) . ") VALUES\n" . implode(",\n", $values));
        return $result !== false;
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
