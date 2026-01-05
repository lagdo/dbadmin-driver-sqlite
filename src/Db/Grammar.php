<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\AbstractGrammar;

use function array_map;
use function implode;
use function preg_match;
use function str_replace;
use function uniqid;

class Grammar extends AbstractGrammar
{
    /**
     * @inheritDoc
     */
    public function getUseDatabaseQuery(string $database, string $style = ''): string
    {
        return '';
    }

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
    public function getAutoIncrementModifier(): string
    {
        return " PRIMARY KEY AUTOINCREMENT";
    }

    /**
     * @inheritDoc
     */
    protected function limitToOne(string $table, string $query, string $where): string
    {
        return preg_match('~^INTO~', $query) ||
            $this->driver->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ?
            $this->getLimitClause($query, $where, 1, 0) :
            //! use primary key in tables with WITHOUT rowid
            " $query WHERE rowid = (SELECT rowid FROM " . $this->escapeTableName($table) . $where . ' LIMIT 1)';
    }

    /**
     * @inheritDoc
     */
    public function getTableDefinitionQueries(string $table, bool $autoIncrement, string $style): string
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
            $query .= ";\n\n" . $this->getIndexCreationQuery($table, $index->type, $name, "($columns)");
        }
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function getIndexCreationQuery(string $table, string $type, string $name, string $columns): string
    {
        return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "") .
            $this->escapeId($name != "" ? $name : uniqid($table . "_")) .
            " ON " . $this->driver->escapeTableName($table) . " $columns";
    }

    /**
     * @inheritDoc
     */
    public function getTableTruncationQuery(string $table): string
    {
        return "DELETE FROM " . $this->driver->escapeTableName($table);
    }

    /**
     * @inheritDoc
     */
    public function getTriggerCreationQuery(string $table): string
    {
        $query = "SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " .
            $this->driver->quote($table);
        return implode($this->driver->values($query));
    }
}
