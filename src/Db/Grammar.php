<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\Grammar as AbstractGrammar;

class Grammar extends AbstractGrammar
{
    /**
     * @inheritDoc
     */
    public function escapeId(string $idf): string
    {
        return '"' . str_replace('"', '""', $idf) . '"';
    }

    /**
     * @inheritDoc
     */
    public function getAutoIncrementModifier()
    {
        return " PRIMARY KEY AUTOINCREMENT";
    }

    /**
     * @inheritDoc
     */
    public function getCreateTableQuery(string $table, bool $autoIncrement, string $style)
    {
        $query = $this->driver->result("SELECT sql FROM sqlite_master " .
            "WHERE type IN ('table', 'view') AND name = " . $this->driver->quote($table));
        foreach ($this->driver->indexes($table) as $name => $index) {
            if ($name == '') {
                continue;
            }
            $columns = implode(", ", array_map(function ($key) {
                return $this->escapeId($key);
            }, $index->columns));
            $query .= ";\n\n" . $this->getCreateIndexQuery($table, $index->type, $name, "($columns)");
        }
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function getCreateIndexQuery(string $table, string $type, string $name, string $columns)
    {
        return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "") .
            $this->escapeId($name != "" ? $name : uniqid($table . "_")) .
            " ON " . $this->driver->escapeTableName($table) . " $columns";
    }

    /**
     * @inheritDoc
     */
    public function getTruncateTableQuery(string $table)
    {
        return "DELETE FROM " . $this->driver->escapeTableName($table);
    }

    /**
     * @inheritDoc
     */
    public function getCreateTriggerQuery(string $table)
    {
        $query = "SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " .
            $this->driver->quote($table);
        return implode($this->driver->values($query));
    }

    /**
     * @inheritDoc
     */
    public function queryRegex()
    {
        return '\\s*|[\'"`[]|/\*|-- |$';
    }
}
