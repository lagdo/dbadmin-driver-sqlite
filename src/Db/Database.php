<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Db\Database as AbstractDatabase;

class Database extends AbstractDatabase
{
    /**
     * Recreate a table
     *
     * @param TableEntity $tableAttrs
     * @param string $table
     *
     * @return bool
     */
    private function recreateTable(TableEntity $tableAttrs, string $table = '')
    {
        $alter = [];
        $originals = [];
        $indexes = [];
        foreach ($tableAttrs->fields as $field) {
            if ($field[1]) {
                $alter[] = (\is_string($field[1]) ? $field[1] : 'ADD ' . implode($field[1]));
                if ($field[0] != '') {
                    $originals[$field[0]] = $field[1][0];
                }
            }
        }

        if ($table != '') {
            if (empty($fields)) {
                foreach ($this->driver->fields($table) as $key => $field) {
                    if (!empty($indexes)) {
                        $field->autoIncrement = 0;
                    }
                    $fields[] = $this->util->processField($field, $field);
                    $originals[$key] = $this->driver->escapeId($key);
                }
            }
            $primary_key = false;
            foreach ($fields as $field) {
                if ($field[6]) {
                    $primary_key = true;
                }
            }
            $drop_indexes = [];
            foreach ($indexes as $key => $val) {
                if ($val[2] == 'DROP') {
                    $drop_indexes[$val[1]] = true;
                    unset($indexes[$key]);
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
                        $indexes[] = [$index->type, $key_name, $columns];
                    }
                }
            }
            foreach ($indexes as $key => $val) {
                if ($val[0] == 'PRIMARY') {
                    unset($indexes[$key]);
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
        foreach ($fields as $key => $field) {
            $fields[$key] = '  ' . implode($field);
        }
        $fields = array_merge($fields, array_filter($foreign));
        $tempName = ($table == $name ? "adminer_$name" : $name);
        if (!$this->driver->execute('CREATE TABLE ' . $this->driver->table($tempName) .
            " (\n" . implode(",\n", $fields) . "\n)")) {
            // implicit ROLLBACK to not overwrite $this->driver->error()
            return false;
        }
        if ($table != '') {
            if ($originals && !$this->driver->execute('INSERT INTO ' . $this->driver->table($tempName) .
                ' (' . implode(', ', $originals) . ') SELECT ' . implode(
                    ', ',
                    array_map(function ($key) {
                   return $this->driver->escapeId($key);
               }, array_keys($originals))
                ) . ' FROM ' . $this->driver->table($table))) {
                return false;
            }
            $triggers = [];
            foreach ($this->driver->triggers($table) as $trigger_name => $timing_event) {
                $trigger = $this->driver->trigger($trigger_name);
                $triggers[] = 'CREATE TRIGGER ' . $this->driver->escapeId($trigger_name) . ' ' .
                    implode(' ', $timing_event) . ' ON ' . $this->driver->table($name) . "\n$trigger[Statement]";
            }
            $autoIncrement = $autoIncrement ? 0 :
                $this->connection->result('SELECT seq FROM sqlite_sequence WHERE name = ' .
                $this->driver->quote($table)); // if $autoIncrement is set then it will be updated later
            // drop before creating indexes and triggers to allow using old names
            if (!$this->driver->execute('DROP TABLE ' . $this->driver->table($table)) ||
                ($table == $name && !$this->driver->execute('ALTER TABLE ' . $this->driver->table($tempName) .
                ' RENAME TO ' . $this->driver->table($name))) || !$this->alterIndexes($name, $indexes)
            ) {
                return false;
            }
            if ($autoIncrement) {
                $this->driver->execute('UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = ' . $this->driver->quote($name)); // ignores error
            }
            foreach ($triggers as $trigger) {
                if (!$this->driver->execute($trigger)) {
                    return false;
                }
            }
            $this->driver->execute('COMMIT');
        }
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
     * @inheritDoc
     */
    public function createTable(TableEntity $tableAttrs)
    {
        if (!$this->recreateTable($tableAttrs)) {
            return false;
        }
        $this->setAutoIncrement($tableAttrs->name, $tableAttrs->autoIncrement);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function alterTable(string $table, TableEntity $tableAttrs)
    {
        $use_all_fields = !empty($tableAttrs->foreign);
        foreach ($tableAttrs->fields as $field) {
            if ($field[0] != '' || !$field[1] || $field[2]) {
                $use_all_fields = true;
                break;
            }
        }
        if (!$use_all_fields) {
            $alter = [];
            foreach ($tableAttrs->fields as $field) {
                if ($field[1]) {
                    $alter[] = ($use_all_fields ? $field[1] : 'ADD ' . implode($field[1]));
                }
            }
            foreach ($alter as $val) {
                if (!$this->driver->execute('ALTER TABLE ' . $this->driver->table($table) . " $val")) {
                    return false;
                }
            }
            if ($table != $tableAttrs->name && !$this->driver->execute('ALTER TABLE ' .
                $this->driver->table($table) . ' RENAME TO ' . $this->driver->table($tableAttrs->name))) {
                return false;
            }
        } elseif (!$this->recreateTable($tableAttrs, $table)) {
            return false;
        }
        $this->setAutoIncrement($tableAttrs->name, $tableAttrs->autoIncrement);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function alterIndexes(string $table, array $alter, array $drop)
    {
        foreach ($alter as $index) {
            if ($index->type == 'PRIMARY') {
                // return $this->recreateTable($table, $table, [], [], [], 0, $alter);
                // Do not alter primary keys, since it requires to recreate the table.
                return false;
            }
        }
        foreach (array_reverse($drop) as $index) {
            $this->driver->execute('DROP INDEX ' . $this->driver->escapeId($index->name));
        }
        foreach (array_reverse($alter) as $index) {
            $this->driver->execute($this->driver->sqlForCreateIndex($table,
                $index->type, $index->name, '(' . implode(', ', $index->columns) . ')'));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function tables()
    {
        return $this->driver->keyValues('SELECT name, type FROM sqlite_master ' .
            "WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
    }

    /**
     * @inheritDoc
     */
    public function countTables(array $databases)
    {
        $connection = $this->driver->createConnection(); // New connection
        $counts = [];
        $query = "SELECT count(*) FROM sqlite_master WHERE type IN ('table', 'view')";
        foreach ($databases as $database) {
            $counts[$database] = 0;
            $connection->open($database);
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
    public function dropViews(array $views)
    {
        return $this->driver->applyQueries('DROP VIEW', $views);
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables)
    {
        return $this->driver->applyQueries('DROP TABLE', $tables);
    }

    /**
     * @inheritDoc
     */
    public function moveTables(array $tables, array $views, string $target)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function truncateTables(array $tables)
    {
        return $this->driver->applyQueries('DELETE FROM', $tables);
    }
}
