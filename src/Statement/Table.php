<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Statement;

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
     * @param string $table
     * @param int $autoIncrement
     *
     * @return string[]
     */
    private function getAutoIncrementQueries(string $table, int $autoIncrement): array
    {
        return [
            "UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = $table",
            "INSERT INTO sqlite_sequence (name, seq) VALUES ($table, $autoIncrement)",
        ];
    }

    /**
     * @inheritDoc
     */
    public function getCreateTableQueries(TableCreateDto $table): array
    {
        // $useAllColumns = true;

        $clauses = array_map(fn(ColumnInputDto $input) => $input->clauses(), $table->inputs['added']);
        $clauses = implode(",\n", [
            ...$clauses,
            ...$this->getForeignKeyClauses($table),
        ]);
        $quotedTableName = $this->_engine()->quote($table->name);
        $autoIncrementQueries = $table->autoIncrement <= 0 ? [] :
            $this->getAutoIncrementQueries($quotedTableName, $table->autoIncrement);

        $tableName = $this->_statement()->escapeTableName($table->name);
        return [
            "CREATE TABLE $tableName (\n$clauses\n)",
            ...$autoIncrementQueries,
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
        $queries = [];
        foreach ($table->inputs['added'] as $input) {
            $queries[] = "ALTER TABLE $tableName ADD " . $input->clauses();
        }
        foreach ($table->inputs['edited'] as $input) {
            if ($input->name !== $input->column->name) {
                $columnName = $this->_statement()->escapeId($input->column->name);
                $queries[] = "ALTER TABLE $tableName RENAME $columnName TO {$input->name}";
            }
            // SQLite doesn't directly support other changes on a table structure.
            // $queries[] = "ALTER TABLE $tableName " . $input->clauses();
        }
        foreach ($table->droppedColumns as $columnName) {
            $columnName = $this->_statement()->escapeId($columnName);
            $queries[] = "ALTER TABLE $tableName DROP $columnName";
        }
        if ($table->name !== $table->current->name) {
            $currTableName = $this->_statement()->escapeTableName($table->current->name);
            $queries[] = "ALTER TABLE $currTableName RENAME TO $tableName";
        }

        $quotedTableName = $this->_engine()->quote($table->name);
        $autoIncrementQueries = $table->autoIncrement <= 0 ? [] :
            $this->getAutoIncrementQueries($quotedTableName, $table->autoIncrement);

        return [
            ...$queries,
            ...$autoIncrementQueries,
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
        $query = "SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " .
            $this->_engine()->quote($table);
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

        return [...$dropQueries, ...$alterQueries];
    }
}
