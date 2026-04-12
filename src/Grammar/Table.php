<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Grammar;

use Lagdo\DbAdmin\Support\Db\Engine\Grammar\AbstractTable;
use Lagdo\DbAdmin\Support\Dto\ColumnDto;
use Lagdo\DbAdmin\Support\Dto\TableAlterDto;
use Lagdo\DbAdmin\Support\Dto\TableCreateDto;

use function array_map;
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
        // $useAllFields = true;

        $columns = array_map(fn(ColumnDto $column) => $column->clause(), $table->columns);
        $columns = [
            ...$columns,
            ...$this->getForeignKeyClauses($table),
        ];
        $quotedTableName = $this->_driver()->quote($table->name);
        $autoIncrementQueries = $table->autoIncrement <= 0 ? [] :
            $this->getAutoIncrementQueries($quotedTableName, $table->autoIncrement);

        $tableName = $this->_grammar()->escapeTableName($table->name);
        return [
            "CREATE TABLE $tableName (\n" . implode(",\n", $columns) . "\n)",
            ...$autoIncrementQueries,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAlterTableQueries(TableAlterDto $table): array
    {
        // $useAllFields = count($table->foreignKeys) > 0 || count($table->changedColumns) > 0;
        // if (!$useAllFields) {
        //     foreach ($table->addedColumns as $column) {
        //         if (!$field[1] || $field[2]) {
        //             $useAllFields = true;
        //         }
        //     }
        // }

        $tableName = $this->_grammar()->escapeTableName($table->name);
        $queries = [];
        foreach ($table->addedColumns as $column) {
            $queries[] = "ALTER TABLE $tableName ADD " . $column->clause();
        }
        foreach ($table->changedColumns as $fieldName => $column) {
            if ($fieldName !== $column->field->name) {
                $fieldName = $this->_grammar()->escapeId($fieldName);
                $queries[] = "ALTER TABLE $tableName RENAME $fieldName TO {$column->name}";
            }
            // SQLite doesn't directly support other changes on a table structure.
            // $queries[] = "ALTER TABLE $tableName " . $column->clause();
        }
        foreach ($table->droppedColumns as $fieldName) {
            $queries[] = "ALTER TABLE $tableName DROP " . $this->_grammar()->escapeId($fieldName);
        }
        if ($table->name !== $table->current->name) {
            $currTableName = $this->_grammar()->escapeTableName($table->current->name);
            $queries[] = "ALTER TABLE $currTableName RENAME TO $tableName";
        }

        $quotedTableName = $this->_driver()->quote($table->name);
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
        $query = $this->_driver()->result("SELECT sql FROM sqlite_master " .
            "WHERE type IN ('table', 'view') AND name = " . $this->_driver()->quote($table));
        foreach ($this->_driver()->indexes($table) as $name => $index) {
            if ($name == '') {
                continue;
            }
            $columns = implode(", ", array_map(function ($key) {
                return $this->_grammar()->escapeId($key);
            }, $index->columns));
            $query .= ";\n\n" . $this->getCreateIndexQuery($table, $index->type, $name, "($columns)");
        }
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function getCreateIndexQuery(string $table, string $type, string $name, string $columns): string
    {
        return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "") .
            $this->_grammar()->escapeId($name != "" ? $name : uniqid($table . "_")) .
            " ON " . $this->_grammar()->escapeTableName($table) . " $columns";
    }

    /**
     * @inheritDoc
     */
    public function getTruncateTableQuery(string $table): string
    {
        return "DELETE FROM " . $this->_grammar()->escapeTableName($table);
    }

    /**
     * @inheritDoc
     */
    public function getCreateTriggerQuery(string $table): string
    {
        $query = "SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " .
            $this->_driver()->quote($table);
        return implode($this->_driver()->values($query));
    }

    /**
     * @inheritDoc
     */
    public function getAlterIndexQueries(string $table, array $alter, array $drop): array
    {
        $queries = [];
        foreach (array_reverse($drop) as $index) {
            $queries[] = 'DROP INDEX ' . $this->_grammar()->escapeId($index->name);
        }
        foreach (array_reverse($alter) as $index) {
            // Can't alter primary keys
            if ($index->type !== 'PRIMARY') {
                $queries[] =  $this->_grammar()->getCreateIndexQuery($table, $index->type,
                    $index->name, '(' . implode(', ', $index->columns) . ')');
            }
        }
        return $queries;
    }
}
