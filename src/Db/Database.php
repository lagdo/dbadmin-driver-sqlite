<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Db\Database as AbstractDatabase;

use function is_object;
use function intval;
use function implode;
use function array_reverse;

class Database extends AbstractDatabase
{
    use RecreateTrait;

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
        $clauses = [];
        foreach ($tableAttrs->fields as $field) {
            if ($field[1]) {
                $clauses[] = ($field[0] != ''  ? $field[1] : 'ADD ' . implode($field[1]));
            }
        }
        $queries = [];
        foreach ($clauses as $clause) {
            $queries[] = 'ALTER TABLE ' . $this->driver->table($table) . ' ' . $clause;
        }
        if ($table != $tableAttrs->name) {
            $queries[] = 'ALTER TABLE ' . $this->driver->table($table) . ' RENAME TO ' .
                $this->driver->table($tableAttrs->name);
        }
        // TODO: Wrap queries into a transaction
        foreach ($queries as $query) {
            if (!$this->driver->execute($query)) {
                return false;
            }
        }
        $this->setAutoIncrement($tableAttrs->name, $tableAttrs->autoIncrement);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function alterIndexes(string $table, array $alter, array $drop)
    {
        $queries = [];
        foreach (array_reverse($drop) as $index) {
            $queries[] = 'DROP INDEX ' . $this->driver->escapeId($index->name);
        }
        foreach (array_reverse($alter) as $index) {
            // Can't alter primary keys
            if ($index->type !== 'PRIMARY') {
                $queries[] =  $this->driver->sqlForCreateIndex($table, $index->type,
                    $index->name, '(' . implode(', ', $index->columns) . ')');
            }
        }
        // TODO: Wrap queries into a transaction
        foreach ($queries as $query) {
            $this->driver->execute($query);
        }
        return true;
    }
}
