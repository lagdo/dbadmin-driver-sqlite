<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;

trait TableTrait
{
    /**
     * @inheritDoc
     */
    public function isView(TableEntity $tableStatus)
    {
        return $tableStatus->engine == 'view';
    }

    /**
     * @param string $table
     *
     * @return array
     */
    private function queryStatus(string $table = '')
    {
        $query = "SELECT name AS Name, type AS Engine, 'rowid' AS Oid, '' AS Auto_increment " .
            "FROM sqlite_master WHERE type IN ('table', 'view') " .
            ($table != "" ? "AND name = " . $this->driver->quote($table) : "ORDER BY name");
        return $this->driver->rows($query);
    }

    /**
     * @param array $row
     *
     * @return TableEntity
     */
    private function makeStatus(array $row)
    {
        $status = new TableEntity($row['Name']);
        $status->engine = $row['Engine'];
        $status->oid = $row['Oid'];
        // $status->Auto_increment = $row['Auto_increment'];
        $query = 'SELECT COUNT(*) FROM ' . $this->driver->escapeId($row['Name']);
        $status->rows = $this->connection->result($query);

        return $status;
    }

    /**
     * @inheritDoc
     */
    public function tableStatus(string $table, bool $fast = false)
    {
        $rows = $this->queryStatus($table);
        if (!($row = reset($rows))) {
            return null;
        }
        return $this->makeStatus($row);
    }

    /**
     * @inheritDoc
     */
    public function tableStatuses(bool $fast = false)
    {
        $tables = [];
        $rows = $this->queryStatus();
        foreach ($rows as $row) {
            $tables[$row['Name']] = $this->makeStatus($row);
        }
        return $tables;
    }

    /**
     * @inheritDoc
     */
    public function tableNames()
    {
        $tables = [];
        $rows = $this->queryStatus();
        foreach ($rows as $row) {
            $tables[] = $row['Name'];
        }
        return $tables;
    }

    /**
     * @param string $table
     * @param ConnectionInterface $connection
     *
     * @return IndexEntity|null
     */
    private function queryPrimaryIndex(string $table, ConnectionInterface $connection)
    {
        $primaryIndex = null;
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $this->driver->quote($table);
        $result = $connection->result($query);
        if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $result, $match)) {
            $primaryIndex = new IndexEntity();
            $primaryIndex->type = "PRIMARY";
            preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i',
                $match[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $primaryIndex->columns[] = $this->driver->unescapeId($match[2]) . $match[4];
                $primaryIndex->descs[] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
            }
        }
        return $primaryIndex;
    }

    /**
     * @param string $table
     * @param ConnectionInterface $connection
     *
     * @return IndexEntity
     */
    private function makePrimaryIndex(string $table, ConnectionInterface $connection)
    {
        $primaryIndex = $this->queryPrimaryIndex($table, $connection);
        if ($primaryIndex !== null) {
            return $primaryIndex;
        }
        foreach ($this->fields($table) as $name => $field) {
            if (!$field->primary) {
                continue;
            }
            if ($primaryIndex === null) {
                $primaryIndex = new IndexEntity();
            }
            $primaryIndex->type = "PRIMARY";
            $primaryIndex->columns = [$name];
            $primaryIndex->lengths = [];
            $primaryIndex->descs = [null];
        }
        return $primaryIndex;
    }

    /**
     * @inheritDoc
     */
    public function tableHelp(string $name)
    {
        if ($name == "sqlite_sequence") {
            return "fileformat2.html#seqtab";
        }
        if ($name == "sqlite_master") {
            return "fileformat2.html#$name";
        }
    }
}
