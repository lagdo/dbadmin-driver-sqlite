<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\TriggerEntity;

use function strtoupper;
use function preg_match;
use function implode;

trait TableTrait
{
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
     * @inheritDoc
     */
    public function triggerOptions()
    {
        return [
            "Timing" => ["BEFORE", "AFTER", "INSTEAD OF"],
            "Event" => ["INSERT", "UPDATE", "UPDATE OF", "DELETE"],
            "Type" => ["FOR EACH ROW"],
        ];
    }

    /**
     * @inheritDoc
     */
    public function trigger(string $name, string $table = '')
    {
        if ($name == "") {
            return new TriggerEntity('', '', "BEGIN\n\t;\nEND");
        }
        $idf = '(?:[^`"\s]+|`[^`]*`|"[^"]*")+';
        $options = $this->triggerOptions();
        preg_match("~^CREATE\\s+TRIGGER\\s*$idf\\s*(" . implode("|", $options["Timing"]) .
            ")\\s+([a-z]+)(?:\\s+OF\\s+($idf))?\\s+ON\\s*$idf\\s*(?:FOR\\s+EACH\\s+ROW\\s)?(.*)~is",
            $this->connection->result("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " .
                $this->driver->quote($name)), $match);
        $of = $match[3];
        return new TriggerEntity(strtoupper($match[1]), strtoupper($match[2]), $match[4],
            ($of[0] == '`' || $of[0] == '"' ? $this->driver->unescapeId($of) : $of), $name);
    }

    /**
     * @inheritDoc
     */
    public function triggers(string $table)
    {
        $triggers = [];
        $options = $this->triggerOptions();
        $query = "SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $this->driver->quote($table);
        foreach ($this->driver->rows($query) as $row) {
            preg_match('~^CREATE\s+TRIGGER\s*(?:[^`"\s]+|`[^`]*`|"[^"]*")+\s*(' .
                implode("|", $options["Timing"]) . ')\s*(.*?)\s+ON\b~i', $row["sql"], $match);
            $triggers[$row["name"]] = new TriggerEntity($match[1], $match[2], '', '', $row["name"]);
        }
        return $triggers;
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
