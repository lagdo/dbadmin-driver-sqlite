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
    use DatabaseTrait;

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
        $counts = [];
        $query = "SELECT count(*) FROM sqlite_master WHERE type IN ('table', 'view')";
        foreach ($databases as $database) {
            $counts[$database] = 0;
            $connection = $this->driver->connectToDatabase($database);
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
    public function alterTable(string $table, TableEntity $tableAttrs)
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
    public function alterIndexes(string $table, array $alter, array $drop)
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
}
