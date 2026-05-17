<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Statement;

use Lagdo\DbAdmin\Driver\Sql\Dto\ColumnInputDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\TableAlterDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\TableCreateDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\TableDdDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\UpsertDto;
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
     * @inheritDoc
     */
    protected function getColumnModifier(ColumnInputDto $input, TableDdDto $table): string
    {
        $primaryKey = $this->getPrimaryKeyModifier($input, $table);
        $autoIncrement = $input->autoIncrement ? ' AUTOINCREMENT' : '';
        return "$primaryKey$autoIncrement";
    }

    /**
     * @param TableDdDto $table
     *
     * @return array<string|array<array<string>>>
     */
    private function getAutoIncrementQueries(TableDdDto $table): array
    {
        $table->setupAutoIncrement();

        if (!$table->autoIncrementChanged()) {
            return [];
        }

        $queries = [];

        // Drop the current sequence.
        if ($table->autoIncrementDisabled()) {
            $tableName = $this->_engine()->quote($table->statusName());
            $queries[] = "DELETE FROM sqlite_sequence WHERE name=$tableName";
        }
        // Create a new sequence.
        if ($table->autoIncrementEnabled()) {
            $autoIncrement = $table->hasAutoIncrement() ? $table->autoIncrement : 1;
            $seqKeys = ['name' => [$this->_engine()->quote($table->name)]];
            $setValues = ['seq' => ["{$autoIncrement}"]];
            $upsert = new UpsertDto('sqlite_sequence', $seqKeys, $setValues);
            $queries = [
                ...$queries,
                ...$this->_statement()->getTableUpsertQueries($upsert),
            ];
        }
        // Just change the current auto increment value.
        if ($table->autoIncrementValueChanged()) {
            $seqKeys = ['name' => [$this->_engine()->quote($table->name)]];
            $setValues = ['seq' => ["{$table->autoIncrement}"]];
            $upsert = new UpsertDto('sqlite_sequence', $seqKeys, $setValues);
            $queries = [
                ...$queries,
                ...$this->_statement()->getTableUpsertQueries($upsert),
            ];
        }

        return $queries;
    }

    /**
     * @inheritDoc
     */
    public function getCreateTableQueries(TableCreateDto $table): array
    {
        // $useAllColumns = true;

        $inputs = $table->addedColumns();
        $clauses = array_map(fn(ColumnInputDto $input) =>
            $this->getAddColumnClause($input, $table), $inputs);

        if ($table->primaryKeyColumnCount() > 1) {
            $clauses[] = $table->primaryKeyClause($this->_statement()->escapeId(...));
        }

        $clauses = implode(",\n  ", [
            ...$clauses,
            ...$this->getForeignKeyClauses($table),
        ]);

        $tableName = $this->_statement()->escapeTableName($table->name);
        return [
            "CREATE TABLE $tableName (\n  $clauses\n)",
            ...$this->getAutoIncrementQueries($table),
        ];
    }

    /**
     * @param string $tableName
     * @param ColumnInputDto $input
     * @param TableAlterDto $table
     *
     * @return string
     */
    private function getAddColumnQuery(string $tableName,
        ColumnInputDto $input, TableAlterDto $table): string
    {
        return "ALTER TABLE $tableName ADD " . $this->getAddColumnClause($input, $table);
    }

    /**
     * @param string $tableName
     * @param ColumnInputDto $input
     *
     * @return string
     */
    protected function getEditColumnQuery(string $tableName, ColumnInputDto $input): string
    {
        $currName = $this->_statement()->escapeId($input->column->name);
        $newName = $this->_statement()->escapeId($input->name);
        return "ALTER TABLE $tableName RENAME $currName TO $newName";
    }

    /**
     * @param TableAlterDto $table
     *
     * @return string
     */
    public function getRenameTableQuery(TableAlterDto $table): string
    {
        $newName = $this->_statement()->escapeTableName($table->name);
        $currName = $this->_statement()->escapeTableName($table->status->name);
        return "ALTER TABLE $currName RENAME TO $newName";
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
            $this->getAddColumnQuery($tableName, $input, $table);
        $addColumnsQueries = array_map($addColumnCallback, $table->addedColumns());

        $alterColumnCallback = fn(ColumnInputDto $input) =>
            $this->getEditColumnQuery($tableName, $input);
        // SQLite doesn't directly support other changes on a table structure.
        $changedColumns = array_filter($table->editedColumns(),
            fn(ColumnInputDto $input) => $input->nameChanged());
        $alterColumnsQueries = array_map($alterColumnCallback, $changedColumns);

        $dropColumnsQueries = array_map(fn(string $clause) =>
            "ALTER TABLE $tableName $clause", $this->getDropColumnClauses($table));

        $tableQueries = $table->nameChanged() ? [$this->getRenameTableQuery($table)] : [];

        return [
            ...$tableQueries,
            ...$addColumnsQueries,
            ...$alterColumnsQueries,
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
