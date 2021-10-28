<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

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
        return " $query$where" . ($limit !== 0 ? $separator .
            "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
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
    public function sqlForCreateTable(string $table, bool $autoIncrement, string $style)
    {
        $query = $this->connection->result("SELECT sql FROM sqlite_master " .
            "WHERE type IN ('table', 'view') AND name = " . $this->driver->quote($table));
        foreach ($this->driver->indexes($table) as $name => $index) {
            if ($name == '') {
                continue;
            }
            $columns = implode(", ", array_map(function ($key) {
                return $this->escapeId($key);
            }, $index->columns));
            $query .= ";\n\n" . $this->sqlForCreateIndex($table, $index->type, $name, "($columns)");
        }
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function sqlForCreateIndex(string $table, string $type, string $name, string $columns)
    {
        return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "") .
            $this->escapeId($name != "" ? $name : uniqid($table . "_")) .
            " ON " . $this->table($table) . " $columns";
    }

    /**
     * @inheritDoc
     */
    public function sqlForTruncateTable(string $table)
    {
        return "DELETE FROM " . $this->table($table);
    }

    /**
     * @inheritDoc
     */
    public function sqlForCreateTrigger(string $table)
    {
        $query = "SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " .
            $this->driver->quote($table);
        return implode($this->driver->values($query));
    }
}
