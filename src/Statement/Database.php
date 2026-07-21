<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Statement;

use Lagdo\DbAdmin\Driver\Sql\Specific\Statement\AbstractDatabase;

use function array_filter;
use function array_keys;
use function array_reverse;
use function implode;
use function uniqid;

class Database extends AbstractDatabase
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
    public function getCreateDatabaseQuery(string $database, string $collation): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDropDatabaseQuery(string $database): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDropViewsQueries(array $views): array
    {
        return array_map(fn(string $view) =>
            'DROP VIEW ' . $this->_statement()->escapeTableName($view), $views);
    }

    /**
     * @inheritDoc
     */
    public function getDropTablesQueries(array $tables): array
    {
        return array_map(fn(string $table) =>
            'DROP TABLE ' . $this->_statement()->escapeTableName($table), $tables);
    }

    /**
     * @inheritDoc
     */
    public function getTruncateTablesQueries(array $tables): array
    {
        return array_map(fn(string $table) =>
            'DELETE FROM ' . $this->_statement()->escapeTableName($table), $tables);
    }

    /**
     * @inheritDoc
     */
    public function getExportTableQueries(string $table, bool $autoIncrement, string $style): string
    {
        $tableName = $this->_engine()->quote($table);
        $query = "SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = $tableName";
        $tableQuery = $this->_engine()->columnValue($query);

        $indexes = array_filter($this->_engine()->indexes($table),
            fn(string $indexName) => $indexName !== '', ARRAY_FILTER_USE_KEY);
        $indexQueries = array_map(function($index, string $name) use($table) {
            $escape = $this->_statement()->escapeId(...);
            $columns = implode(', ', array_map($escape, $index->columns));

            return $this->getCreateIndexQuery($table, $index->type, $name, "($columns)");
        }, $indexes, array_keys($indexes));

        return implode(";\n\n", [$tableQuery, ...$indexQueries]);
    }

    /**
     * @inheritDoc
     */
    public function getCreateIndexQuery(string $table, string $type, string $name, string $columns): string
    {
        $indexType = $type !== 'INDEX' ? "$type INDEX " : $type;
        $indexName = $this->_statement()->escapeId($name !== '' ? $name : uniqid("{$table}_"));
        $tableName = $this->_statement()->escapeTableName($table);

        return "CREATE $indexType $indexName ON $tableName $columns";
    }

    /**
     * @inheritDoc
     */
    public function getAlterIndexQueries(string $table, array $alter, array $drop): array
    {
        $dropCallback = fn($index) => 'DROP INDEX ' . $this->_statement()->escapeId($index->name);
        $dropQueries = array_map($dropCallback, array_reverse($drop));

        // Can't alter primary keys
        $alterQueries = array_filter($alter, fn($index) => $index->type !== 'PRIMARY');
        $alterCallback = fn($index) => $this->_statement()->getCreateIndexQuery($table,
            $index->type, $index->name, '(' . implode(', ', $index->columns) . ')');
        $alterQueries = array_map($alterCallback, array_reverse($alter));

        return [...$dropQueries, ...$alterQueries];
    }

    /**
     * @inheritDoc
     */
    public function getTruncateTableQuery(string $table): string
    {
        return "DELETE FROM " . $this->_statement()->escapeTableName($table);
    }

    /**
     * @inheritDoc
     */
    public function getCreateTriggerQuery(string $table): string
    {
        $tableName = $this->_engine()->quote($table);
        $query = "SELECT sql || ';;\n'
FROM sqlite_master WHERE type = 'trigger' AND tbl_name = $tableName";
        return implode($this->_engine()->columnValues($query));
    }
}
