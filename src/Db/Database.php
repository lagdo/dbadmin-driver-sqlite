<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\AbstractDatabase;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;

use function array_filter;
use function array_merge;
use function array_reverse;
use function intval;
use function implode;
use function is_object;

class Database extends AbstractDatabase
{
    /**
     * @inheritDoc
     */
    public function tables(): array
    {
        return $this->driver->keyValues('SELECT name, type FROM sqlite_master ' .
            "WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
    }

    /**
     * @inheritDoc
     */
    public function countTables(array $databases): array
    {
        $counts = [];
        $query = "SELECT count(*) FROM sqlite_master WHERE type IN ('table', 'view')";
        foreach ($databases as $database) {
            $counts[$database] = 0;
            $connection = $this->driver->newConnection($database);
            $statement = $connection->query($query);
            if (is_object($statement) && ($row = $statement->fetchRow())) {
                $counts[$database] = intval($row[0]);
            }
        }
        return $counts;
    }

    /**
     * @inheritDoc
     */
    public function dropViews(array $views): bool
    {
        return $this->driver->applyQueries('DROP VIEW', $views);
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables): bool
    {
        return $this->driver->applyQueries('DROP TABLE', $tables);
    }

    /**
     * @inheritDoc
     */
    public function moveTables(array $tables, array $views, string $target): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function truncateTables(array $tables): bool
    {
        return $this->driver->applyQueries('DELETE FROM', $tables);
    }

    /**
     * @inheritDoc
     */
    public function createTable(TableEntity $tableAttrs): bool
    {
        foreach ($tableAttrs->fields as $key => $field) {
            $tableAttrs->fields[$key] = '  ' . implode($field);
        }
        $tableAttrs->fields = array_merge($tableAttrs->fields, array_filter($tableAttrs->foreign));
        if (!$this->driver->execute('CREATE TABLE ' . $this->driver->escapeTableName($tableAttrs->name) .
            " (\n" . implode(",\n", $tableAttrs->fields) . "\n)")) {
            // implicit ROLLBACK to not overwrite $this->driver->error()
            return false;
        }
        $this->setAutoIncrement($tableAttrs->name, $tableAttrs->autoIncrement);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function alterTable(string $table, TableEntity $tableAttrs): bool
    {
        $clauses = $this->getAlterTableClauses($tableAttrs);
        $queries = [];
        foreach ($clauses as $clause) {
            $queries[] = 'ALTER TABLE ' . $this->driver->escapeTableName($table) . ' ' . $clause;
        }
        if ($table != $tableAttrs->name) {
            $queries[] = 'ALTER TABLE ' . $this->driver->escapeTableName($table) . ' RENAME TO ' .
                $this->driver->escapeTableName($tableAttrs->name);
        }
        if (!$this->executeQueries($queries)) {
            return false;
        }
        $this->setAutoIncrement($tableAttrs->name, $tableAttrs->autoIncrement);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function alterIndexes(string $table, array $alter, array $drop): bool
    {
        $queries = [];
        foreach (array_reverse($drop) as $index) {
            $queries[] = 'DROP INDEX ' . $this->driver->escapeId($index->name);
        }
        foreach (array_reverse($alter) as $index) {
            // Can't alter primary keys
            if ($index->type !== 'PRIMARY') {
                $queries[] =  $this->driver->getCreateIndexQuery($table, $index->type,
                    $index->name, '(' . implode(', ', $index->columns) . ')');
            }
        }
        return $this->executeQueries($queries);
    }

    private function executeQueries(array $queries): bool
    {
        if (!$queries) {
            return false;
        }
        $this->driver->execute('BEGIN');
        foreach ($queries as $query) {
            if (!$this->driver->execute($query)) {
                $this->driver->execute('ROLLBACK');
                return false;
            }
        }
        $this->driver->execute('COMMIT');
        return true;
    }

    /**
     * @param string $table
     * @param int $autoIncrement
     *
     * @return void
     */
    private function setAutoIncrement(string $table, int $autoIncrement)
    {
        if ($autoIncrement) {
            $this->driver->execute('BEGIN');
            $this->driver->execute("UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = " .
                $this->driver->quote($table)); // ignores error
            if (!$this->driver->affectedRows()) {
                $this->driver->execute('INSERT INTO sqlite_sequence (name, seq) VALUES (' .
                    $this->driver->quote($table) . ", $autoIncrement)");
            }
            $this->driver->execute('COMMIT');
        }
    }

    /**
     * @param TableEntity $tableAttrs
     *
     * @return array
     */
    private function getAlterTableClauses(TableEntity $tableAttrs): array
    {
        $clauses = [];
        foreach ($tableAttrs->fields as $field) {
            if ($field[1]) {
                $clauses[] = ($field[0] != ''  ? $field[1] : 'ADD ' . implode($field[1]));
            }
        }
        return $clauses;
    }

    /**
     * Recreate a table
     *
     * @param TableEntity $tableAttrs
     * @param string $table
     *
     * @return bool
     */
    /*private function recreateTable(TableEntity $tableAttrs, string $table = '')
    {
        $alter = [];
        $originals = [];
        foreach ($tableAttrs->fields as $field) {
            if ($field[1]) {
                $alter[] = (\is_string($field[1]) ? $field[1] : 'ADD ' . implode($field[1]));
                if ($field[0] != '') {
                    $originals[$field[0]] = $field[1][0];
                }
            }
        }

        if ($table != '') {
            if (empty($tableAttrs->fields)) {
                foreach ($this->driver->fields($table) as $key => $field) {
                    if (!empty($tableAttrs->indexes)) {
                        $field->autoIncrement = 0;
                    }
                    $tableAttrs->fields[] = $this->driver->processField($field, $field);
                    $originals[$key] = $this->driver->escapeId($key);
                }
            }
            $primary_key = false;
            foreach ($tableAttrs->fields as $field) {
                if ($field[6]) {
                    $primary_key = true;
                }
            }
            $drop_indexes = [];
            foreach ($tableAttrs->indexes as $key => $val) {
                if ($val[2] == 'DROP') {
                    $drop_indexes[$val[1]] = true;
                    unset($tableAttrs->indexes[$key]);
                }
            }
            foreach ($this->driver->indexes($table) as $key_name => $index) {
                $columns = [];
                foreach ($index->columns as $key => $column) {
                    if (!$originals[$column]) {
                        continue 2;
                    }
                    $columns[] = $originals[$column] . ($index->descs[$key] ? ' DESC' : '');
                }
                if (!$drop_indexes[$key_name]) {
                    if ($index->type != 'PRIMARY' || !$primary_key) {
                        $tableAttrs->indexes[] = [$index->type, $key_name, $columns];
                    }
                }
            }
            foreach ($tableAttrs->indexes as $key => $val) {
                if ($val[0] == 'PRIMARY') {
                    unset($tableAttrs->indexes[$key]);
                    $foreign[] = '  PRIMARY KEY (' . implode(', ', $val[2]) . ')';
                }
            }
            foreach ($this->driver->foreignKeys($table) as $key_name => $foreignKey) {
                foreach ($foreignKey->source as $key => $column) {
                    if (!$originals[$column]) {
                        continue 2;
                    }
                    $foreignKey->source[$key] = $this->driver->unescapeId($originals[$column]);
                }
                if (!isset($foreign[" $key_name"])) {
                    $foreign[] = ' ' . $this->driver->formatForeignKey($foreignKey);
                }
            }
            $this->driver->execute('BEGIN');
        }
        foreach ($tableAttrs->fields as $key => $field) {
            $tableAttrs->fields[$key] = '  ' . implode($field);
        }
        $tableAttrs->fields = array_merge($tableAttrs->fields, array_filter($tableAttrs->foreign));
        $tempName = ($table == $tableAttrs->name ? "dbadmin_{$tableAttrs->name}" : $tableAttrs->name);
        if (!$this->driver->execute('CREATE TABLE ' . $this->driver->escapeTableName($tempName) .
            " (\n" . implode(",\n", $tableAttrs->fields) . "\n)")) {
            // implicit ROLLBACK to not overwrite $this->driver->error()
            return false;
        }
        if ($table != '') {
            if ($originals && !$this->driver->execute('INSERT INTO ' . $this->driver->escapeTableName($tempName) .
                    ' (' . implode(', ', $originals) . ') SELECT ' . implode(
                        ', ',
                        array_map(function ($key) {
                            return $this->driver->escapeId($key);
                        }, array_keys($originals))
                    ) . ' FROM ' . $this->driver->escapeTableName($table))) {
                return false;
            }
            $triggers = [];
            foreach ($this->driver->triggers($table) as $trigger_name => $timing_event) {
                $trigger = $this->driver->trigger($trigger_name);
                $triggers[] = 'CREATE TRIGGER ' . $this->driver->escapeId($trigger_name) . ' ' .
                    implode(' ', $timing_event) . ' ON ' . $this->driver->escapeTableName($tableAttrs->name) . "\n$trigger[Statement]";
            }
            $autoIncrement = $tableAttrs->autoIncrement ? 0 :
                $this->driver->result('SELECT seq FROM sqlite_sequence WHERE name = ' .
                    $this->driver->quote($table)); // if $autoIncrement is set then it will be updated later
            // Drop before creating indexes and triggers to allow using old names
            if (!$this->driver->execute('DROP TABLE ' . $this->driver->escapeTableName($table)) ||
                ($table == $tableAttrs->name && !$this->driver->execute('ALTER TABLE ' . $this->driver->escapeTableName($tempName) .
                        ' RENAME TO ' . $this->driver->escapeTableName($tableAttrs->name))) || !$this->alterIndexes($tableAttrs->name, $tableAttrs->indexes)
            ) {
                return false;
            }
            if ($autoIncrement) {
                $this->driver->execute('UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = ' .
                    $this->driver->quote($tableAttrs->name)); // ignores error
            }
            foreach ($triggers as $trigger) {
                if (!$this->driver->execute($trigger)) {
                    return false;
                }
            }
            $this->driver->execute('COMMIT');
        }
        return true;
    }*/
}
