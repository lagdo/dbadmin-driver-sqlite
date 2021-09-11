<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\ForeignKeyEntity;
use Lagdo\DbAdmin\Driver\Entity\TriggerEntity;
use Lagdo\DbAdmin\Driver\Entity\RoutineEntity;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;

use Lagdo\DbAdmin\Driver\Db\Grammar as AbstractGrammar;

class Grammar extends AbstractGrammar
{
    /**
     * @inheritDoc
     */
    public function escapeId(string $idf)
    {
        return '"' . str_replace('"', '""', $idf) . '"';
    }

    /**
     * @inheritDoc
     */
    public function limit(string $query, string $where, int $limit, int $offset = 0, string $separator = " ")
    {
        return " $query$where" . ($limit !== null ? $separator .
            "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
    }

    /**
     * @inheritDoc
     */
    public function limitToOne(string $table, string $query, string $where, string $separator = "\n")
    {
        return preg_match('~^INTO~', $query) ||
            $this->connection->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ?
            $this->limit($query, $where, 1, 0, $separator) :
            //! use primary key in tables with WITHOUT rowid
            " $query WHERE rowid = (SELECT rowid FROM " . $this->table($table) . $where . $separator . "LIMIT 1)";
    }

    /**
     * @inheritDoc
     */
    public function autoIncrement()
    {
        return " PRIMARY KEY AUTOINCREMENT";
    }

    /**
     * @inheritDoc
     */
    public function createTableSql(string $table, bool $autoIncrement, string $style)
    {
        $query = $this->connection->result("SELECT sql FROM sqlite_master " .
            "WHERE type IN ('table', 'view') AND name = " . $this->quote($table));
        foreach ($this->driver->indexes($table) as $name => $index) {
            if ($name == '') {
                continue;
            }
            $query .= ";\n\n" . $this->createIndexSql($table, $index->type, $name,
                "(" . implode(", ", array_map(function ($key) {
                    return $this->escapeId($key);
                }, $index->columns)) . ")");
        }
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function createIndexSql(string $table, string $type, string $name, array $columns)
    {
        return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "") .
            $this->escapeId($name != "" ? $name : uniqid($table . "_")) .
            " ON " . $this->table($table) . " $columns";
    }

    /**
     * @inheritDoc
     */
    public function truncateTableSql(string $table)
    {
        return "DELETE FROM " . $this->table($table);
    }

    /**
     * @inheritDoc
     */
    public function createTriggerSql(string $table)
    {
        $query = "SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $this->quote($table);
        return implode($this->driver->values($query));
    }
}
