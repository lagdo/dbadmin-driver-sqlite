<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\AbstractGrammar;
use Lagdo\DbAdmin\Driver\Entity\ColumnEntity;
use Lagdo\DbAdmin\Driver\Entity\TableAlterEntity;
use Lagdo\DbAdmin\Driver\Entity\TableCreateEntity;

use function array_map;
use function count;
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
    public function getTableCreationQueries(TableCreateEntity $table): array
    {
        // $useAllFields = true;

        $columns = array_map(fn(ColumnEntity $column) => $column->clause(), $table->columns);
        $columns = [
            ...$columns,
            ...$this->getForeignKeyClauses($table),
        ];
        $quotedTableName = $this->driver->quote($table->name);
        $autoIncrementQueries = $table->autoIncrement <= 0 ? [] :
            $this->getAutoIncrementQueries($quotedTableName, $table->autoIncrement);

        $tableName = $this->driver->escapeTableName($table->name);
        return [
            "CREATE TABLE $tableName (\n" . implode(",\n", $columns) . "\n)",
            ...$autoIncrementQueries,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTableAlterationQueries(TableAlterEntity $table): array
    {
        // $useAllFields = count($table->foreignKeys) > 0 || count($table->changedColumns) > 0;
        // if (!$useAllFields) {
        //     foreach ($table->addedColumns as $column) {
        //         if (!$field[1] || $field[2]) {
        //             $useAllFields = true;
        //         }
        //     }
        // }

        $tableName = $this->driver->escapeTableName($table->name);
        $queries = [];
        foreach ($table->addedColumns as $column) {
            $queries[] = "ALTER TABLE $tableName ADD " . $column->clause();
        }
        foreach ($table->changedColumns as $fieldName => $column) {
            if ($fieldName !== $column->field->name) {
                $fieldName = $this->escapeId($fieldName);
                $queries[] = "ALTER TABLE $tableName RENAME $fieldName TO {$column->name}";
            }
            // SQLite doesn't directly support other changes on a table structure.
            // $queries[] = "ALTER TABLE $tableName " . $column->clause();
        }
        foreach ($table->droppedColumns as $fieldName) {
            $queries[] = "ALTER TABLE $tableName DROP " . $this->escapeId($fieldName);
        }
        if ($table->name !== $table->current->name) {
            $currTableName = $this->driver->escapeTableName($table->current->name);
            $queries[] = "ALTER TABLE $currTableName RENAME TO $tableName";
        }

        $quotedTableName = $this->driver->quote($table->name);
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
