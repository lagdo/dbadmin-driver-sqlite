<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\ForeignKeyEntity;
use Lagdo\DbAdmin\Driver\Entity\TriggerEntity;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;

use Lagdo\DbAdmin\Driver\Db\Table as AbstractTable;

use function str_replace;
use function strtolower;
use function strtoupper;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function is_object;
use function preg_quote;
use function implode;

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
     * @param string $type
     *
     * @return string
     */
    private function rowType(string $type)
    {
        if (preg_match('~int~i', $type)) {
            return 'integer';
        }
        if (preg_match('~char|clob|text~i', $type)) {
            return 'text';
        }
        if (preg_match('~blob~i', $type)) {
            return 'blob';
        }
        if (preg_match('~real|floa|doub~i', $type)) {
            return 'real';
        }
        return 'numeric';
    }

    private function defaultvalue(array $row)
    {
        $default = $row["dflt_value"];
        if (preg_match("~'(.*)'~", $default, $match)) {
            return str_replace("''", "'", $match[1]);
        }
        if ($default == "NULL") {
            return null;
        }
        return $default;
    }

    /**
     * @param array $row
     *
     * @return TableFieldEntity
     */
    private function makeFieldEntity(array $row)
    {
        $field = new TableFieldEntity();

        $type = strtolower($row["type"]);
        $field->name = $row["name"];
        $field->type = $this->rowType($type);
        $field->fullType = $type;
        $field->default = $this->defaultvalue($row);
        $field->null = !$row["notnull"];
        $field->privileges = ["select" => 1, "insert" => 1, "update" => 1];
        $field->primary = $row["pk"];
        return $field;
    }

    /**
     * @inheritDoc
     */
    public function fields(string $table)
    {
        $fields = [];
        $rows = $this->driver->rows('PRAGMA table_info(' . $this->driver->table($table) . ')');
        $primary = "";
        foreach ($rows as $row) {
            $name = $row["name"];
            $type = strtolower($row["type"]);
            $field = $this->makeFieldEntity($row);
            if ($row["pk"]) {
                if ($primary != "") {
                    $fields[$primary]->autoIncrement = false;
                } elseif (preg_match('~^integer$~i', $type)) {
                    $field->autoIncrement = true;
                }
                $primary = $name;
            }
            $fields[$name] = $field;
        }
        $query = "SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " .
            $this->driver->quote($table);
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
     * @param string $table
     * @param ConnectionInterface $connection
     *
     * @return IndexEntity
     */
    private function makePrimaryIndex(string $table, ConnectionInterface $connection)
    {
        $primaryIndex = null;
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " .
            $this->driver->quote($table);
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
     * @param array $row
     * @param array $results
     * @param string $table
     * @param ConnectionInterface $connection
     *
     * @return IndexEntity
     */
    private function makeIndexEntity(array $row, array $results, string $table, ConnectionInterface $connection)
    {
        $index = new IndexEntity();

        $name = $row["name"];
        $index->type = $row["unique"] ? "UNIQUE" : "INDEX";
        $index->lengths = [];
        $index->descs = [];
        $columns = $this->driver->rows("PRAGMA index_info(" .
            $this->driver->escapeId($name) . ")", $connection);
        foreach ($columns as $column) {
            $index->columns[] = $column["name"];
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
        return $index;
    }

    /**
     * @inheritDoc
     */
    public function indexes(string $table, ConnectionInterface $connection = null)
    {
        if (!is_object($connection)) {
            $connection = $this->connection;
        }
        $primaryIndex = $this->makePrimaryIndex($table, $connection);
        if ($primaryIndex === null) {
            return [];
        }

        $indexes = [];
        $query = "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " .
            $this->driver->quote($table);
        $results = $this->driver->keyValues($query, $connection);
        $rows = $this->driver->rows("PRAGMA index_list(" .
            $this->driver->table($table) . ")", $connection);
        foreach ($rows as $row) {
            $index = $this->makeIndexEntity($row, $results, $table, $connection);
            $name = $row["name"];
            if ($index->type === 'UNIQUE' && $index->columns == $primaryIndex->columns &&
                $index->descs == $primaryIndex->descs && preg_match("~^sqlite_~", $name)) {
                $indexes[$name] = $index;
            }
        }
        if ($primaryIndex !== null) {
            $indexes[''] = $primaryIndex;
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
