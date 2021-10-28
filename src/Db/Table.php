<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\ForeignKeyEntity;
use Lagdo\DbAdmin\Driver\Entity\TriggerEntity;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;

use Lagdo\DbAdmin\Driver\Db\Table as AbstractTable;

class Table extends AbstractTable
{
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
    public function isView(TableEntity $tableStatus)
    {
        return $tableStatus->engine == 'view';
    }

    /**
     * @inheritDoc
     */
    public function supportForeignKeys(TableEntity $tableStatus)
    {
        return !$this->connection->result("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
    }

    /**
     * @inheritDoc
     */
    public function fields(string $table)
    {
        $fields = [];
        $primary = "";
        foreach ($this->driver->rows("PRAGMA table_info(" . $this->driver->table($table) . ")") as $row) {
            $name = $row["name"];
            $type = strtolower($row["type"]);
            $default = $row["dflt_value"];

            $field = new TableFieldEntity();

            $field->name = $name;
            $field->type = (preg_match('~int~i', $type) ? "integer" : (preg_match('~char|clob|text~i', $type) ?
                "text" : (preg_match('~blob~i', $type) ? "blob" : (preg_match('~real|floa|doub~i', $type) ?
                "real" : "numeric"))));
            $field->fullType = $type;
            $field->default = (preg_match("~'(.*)'~", $default, $match) ? str_replace("''", "'", $match[1]) :
                ($default == "NULL" ? null : $default));
            $field->null = !$row["notnull"];
            $field->privileges = ["select" => 1, "insert" => 1, "update" => 1];
            $field->primary = $row["pk"];

            if ($row["pk"]) {
                if ($primary != "") {
                    $fields[$primary]->autoIncrement = false;
                } elseif (preg_match('~^integer$~i', $type)) {
                    $field->autoIncrement = true;
                }
                $primary = $name;
            }

            $fields[$field->name] = $field;
        }
        $query = "SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " . $this->driver->quote($table);
        $result = $this->connection->result($query);
        preg_match_all('~(("[^"]*+")+|[a-z0-9_]+)\s+text\s+COLLATE\s+(\'[^\']+\'|\S+)~i',
            $result, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
            if (isset($fields[$name])) {
                $fields[$name]->collation = trim($match[3], "'");
            }
        }
        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function indexes(string $table, ConnectionInterface $connection = null)
    {
        if (!is_object($connection)) {
            $connection = $this->connection;
        }
        $indexes = [];
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $this->driver->quote($table);
        $result = $connection->result($query);
        if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $result, $match)) {
            $indexes[""] = new IndexEntity();
            $indexes[""]->type = "PRIMARY";
            preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i',
                $match[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $indexes[""]->columns[] = $this->driver->unescapeId($match[2]) . $match[4];
                $indexes[""]->descs[] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
            }
        }
        if (!$indexes) {
            foreach ($this->fields($table) as $name => $field) {
                if ($field->primary) {
                    if (!isset($indexes[""])) {
                        $indexes[""] = new IndexEntity();
                    }
                    $indexes[""]->type = "PRIMARY";
                    $indexes[""]->columns = [$name];
                    $indexes[""]->lengths = [];
                    $indexes[""]->descs = [null];
                }
            }
        }
        $query = "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . $this->driver->quote($table);
        $results = $this->driver->keyValues($query, $connection);
        foreach ($this->driver->rows("PRAGMA index_list(" . $this->driver->table($table) . ")", $connection) as $row) {
            $index = new IndexEntity();

            $name = $row["name"];
            $index->type = $row["unique"] ? "UNIQUE" : "INDEX";
            $index->lengths = [];
            $index->descs = [];
            foreach ($this->driver->rows("PRAGMA index_info(" . $this->driver->escapeId($name) . ")", $connection) as $row1) {
                $index->columns[] = $row1["name"];
                $index->descs[] = null;
            }
            if (preg_match('~^CREATE( UNIQUE)? INDEX ' . preg_quote($this->driver->escapeId($name) . ' ON ' .
                $this->driver->escapeId($table), '~') . ' \((.*)\)$~i', $results[$name], $regs)) {
                preg_match_all('/("[^"]*+")+( DESC)?/', $regs[2], $matches);
                foreach ($matches[2] as $key => $val) {
                    if ($val) {
                        $index->descs[$key] = '1';
                    }
                }
            }
            if (!$indexes[""] || $index->type != "UNIQUE" || $index->columns != $indexes[""]->columns ||
                $index->descs != $indexes[""]->descs || !preg_match("~^sqlite_~", $name)) {
                $indexes[$name] = $index;
            }
        }
        return $indexes;
    }

    /**
     * @inheritDoc
     */
    public function foreignKeys(string $table)
    {
        $foreignKeys = [];
        foreach ($this->driver->rows("PRAGMA foreign_key_list(" . $this->driver->table($table) . ")") as $row) {
            $name = $row["id"];
            if (!isset($foreignKeys[$name])) {
                $foreignKeys[$name] = new ForeignKeyEntity();
            }
            //! idf_unescape in SQLite2
            $foreignKeys[$name]->source[] = $row["from"];
            $foreignKeys[$name]->target[] = $row["to"];
        }
        return $foreignKeys;
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
    public function triggerOptions()
    {
        return [
            "Timing" => ["BEFORE", "AFTER", "INSTEAD OF"],
            "Event" => ["INSERT", "UPDATE", "UPDATE OF", "DELETE"],
            "Type" => ["FOR EACH ROW"],
        ];
    }
}
