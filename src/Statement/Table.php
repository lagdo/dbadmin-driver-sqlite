<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Statement;

use Lagdo\DbAdmin\Driver\Sql\Dto\AbstractTableDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\ColumnInputDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\TableAlterDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\TableCreateDto;
use Lagdo\DbAdmin\Driver\Sql\Specific\Statement\AbstractTable;

use function array_filter;
use function array_map;
use function array_keys;
use function array_reverse;
use function implode;
use function uniqid;

class Table extends AbstractTable
{
    /**
     * @param AbstractTableDto $table
     *
     * @return string[]
     */
    private function getAutoIncrementQueries(AbstractTableDto $table): array
    {
        $tableName = $this->_engine()->quote($table->name);
        if ($table->autoIncrement <= 0) {
            return [];
        }

        // Todo: execute the second only if the first updates no row.
        return [
            "UPDATE sqlite_sequence SET seq = {$table->autoIncrement} WHERE name = $tableName",
            "INSERT INTO sqlite_sequence (name, seq) VALUES ($tableName, {$table->autoIncrement})",
        ];
    }

    /**
     * @inheritDoc
     */
    public function getCreateTableQueries(TableCreateDto $table): array
    {
        // $useAllColumns = true;

        $clauses = array_map($this->getAddColumnClause(...), $table->columns['added']);
        $clauses = implode(",\n", [
            ...$clauses,
            ...$this->getForeignKeyClauses($table),
        ]);

        $tableName = $this->_statement()->escapeTableName($table->name);
        return [
            "CREATE TABLE $tableName (\n$clauses\n)",
            ...$this->getAutoIncrementQueries($table),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAlterTableQueries(TableAlterDto $table): array
    {
        // $useAllColumns = count($table->foreignKeys) > 0 || count($table->changedColumns) > 0;
        // if (!$useAllColumns) {
        //     foreach ($table->inputs['added'] as $input) {
        //         if (!$input[1] || $input[2]) {
        //             $useAllColumns = true;
        //         }
        //     }
        // }

        $tableName = $this->_statement()->escapeTableName($table->name);
        $addColumnCallback = fn(ColumnInputDto $input) =>
            "ALTER TABLE $tableName ADD " . $this->getAddColumnClause($input);
        $addColumnsQueries = array_map($addColumnCallback, $table->columns['added']);

        // SQLite doesn't directly support other changes on a table structure.
        // $queries[] = "ALTER TABLE $tableName " . $this->getAddColumnClause($input);
        $renameColumnCallback = function(ColumnInputDto $input) use($tableName) {
            $currName = $this->_statement()->escapeId($input->column->name);
            $newName = $this->_statement()->escapeId($input->name);
            return "ALTER TABLE $tableName RENAME $currName TO $newName";
        };
        $renameColumnsInputs = array_filter($table->columns['edited'],
            fn(ColumnInputDto $input) => $input->name !== $input->column->name);
        $renameColumnsQueries = array_map($renameColumnCallback, $renameColumnsInputs);

        $dropColumnCallback = function(string $columnName) use($tableName) {
            $columnName = $this->_statement()->escapeId($columnName);
            return "ALTER TABLE $tableName DROP $columnName";
        };
        $dropColumnsQueries = array_map($dropColumnCallback, $table->columns['dropped']);

        $tableQueries = [];
        if ($table->name !== $table->current->name) {
            $currName = $this->_statement()->escapeTableName($table->current->name);
            $tableQueries[] = "ALTER TABLE $currName RENAME TO $tableName";
        }

        return [
            ...$tableQueries,
            ...$addColumnsQueries,
            ...$renameColumnsQueries,
            ...$dropColumnsQueries,
            ...$this->getAutoIncrementQueries($table),
        ];
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

        return [
            ...$dropQueries,
            ...$alterQueries,
        ];
    }
}
