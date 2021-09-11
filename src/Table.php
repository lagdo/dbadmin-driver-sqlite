<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\ForeignKeyEntity;
use Lagdo\DbAdmin\Driver\Entity\TriggerEntity;
use Lagdo\DbAdmin\Driver\Entity\RoutineEntity;

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
     * @inheritDoc
     */
    public function tableStatus(string $table = "", bool $fast = false)
    {
        $tables = [];
        $query = "SELECT name AS Name, type AS Engine, 'rowid' AS Oid, '' AS Auto_increment " .
            "FROM sqlite_master WHERE type IN ('table', 'view') " . ($table != "" ? "AND name = " .
            $this->driver->quote($table) : "ORDER BY name");
        foreach ($this->driver->rows($query) as $row) {
            $status = new TableEntity($row['Name']);
            $status->engine = $row['Engine'];
            $status->oid = $row['Oid'];
            // $status->Auto_increment = $row['Auto_increment'];
            $status->rows = $this->connection->result("SELECT COUNT(*) FROM " . $this->driver->escapeId($row["Name"]));
            $tables[$row["Name"]] = $status;
        }
        // foreach ($this->driver->rows("SELECT * FROM sqlite_sequence", null, "") as $row) {
        //     $tables[$row["name"]]["Auto_increment"] = $row["seq"];
        // }
        return ($table != "" ? $tables[$table] : $tables);
    }

    /**
     * @inheritDoc
     */
    public function isView(TableEntity $tableStatus)
    {
        return $tableStatus->engine == "view";
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
    public function alterTable(string $table, string $name, array $fields, array $foreign,
        string $comment, string $engine, string $collation, int $autoIncrement, string $partitioning)
    {
        $use_all_fields = ($table == "" || $foreign);
        foreach ($fields as $field) {
            if ($field[0] != "" || !$field[1] || $field[2]) {
                $use_all_fields = true;
                break;
            }
        }
        $alter = [];
        $originals = [];
        foreach ($fields as $field) {
            if ($field[1]) {
                $alter[] = ($use_all_fields ? $field[1] : "ADD " . implode($field[1]));
                if ($field[0] != "") {
                    $originals[$field[0]] = $field[1][0];
                }
            }
        }
        if (!$use_all_fields) {
            foreach ($alter as $val) {
                if (!$this->driver->queries("ALTER TABLE " . $this->driver->table($table) . " $val")) {
                    return false;
                }
            }
            if ($table != $name && !$this->driver->queries("ALTER TABLE " . $this->driver->table($table) . " RENAME TO " . $this->driver->table($name))) {
                return false;
            }
        } elseif (!$this->recreateTable($table, $name, $alter, $originals, $foreign, $autoIncrement)) {
            return false;
        }
        if ($autoIncrement) {
            $this->driver->queries("BEGIN");
            $this->driver->queries("UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = " . $this->driver->quote($name)); // ignores error
            if (!$this->driver->affectedRows()) {
                $this->driver->queries("INSERT INTO sqlite_sequence (name, seq) VALUES (" . $this->driver->quote($name) . ", $autoIncrement)");
            }
            $this->driver->queries("COMMIT");
        }
        return true;
    }

    /**
     * Recreate a table
     *
     * @param string $table
     * @param string $name
     * @param array $fields
     * @param array $originals
     * @param array $foreign
     * @param integer $autoIncrement
     * @param array $indexes
     *
     * @return void
     */
    protected function recreateTable(string $table, string $name, array $fields, array $originals,
        array $foreign, int $autoIncrement, array $indexes = [])
    {
        if ($table != "") {
            if (!$fields) {
                foreach ($this->fields($table) as $key => $field) {
                    if ($indexes) {
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
                if ($val[2] == "DROP") {
                    $drop_indexes[$val[1]] = true;
                    unset($indexes[$key]);
                }
            }
            foreach ($this->indexes($table) as $key_name => $index) {
                $columns = [];
                foreach ($index->columns as $key => $column) {
                    if (!$originals[$column]) {
                        continue 2;
                    }
                    $columns[] = $originals[$column] . ($index->descs[$key] ? " DESC" : "");
                }
                if (!$drop_indexes[$key_name]) {
                    if ($index->type != "PRIMARY" || !$primary_key) {
                        $indexes[] = [$index->type, $key_name, $columns];
                    }
                }
            }
            foreach ($indexes as $key => $val) {
                if ($val[0] == "PRIMARY") {
                    unset($indexes[$key]);
                    $foreign[] = "  PRIMARY KEY (" . implode(", ", $val[2]) . ")";
                }
            }
            foreach ($this->foreignKeys($table) as $key_name => $foreignKey) {
                foreach ($foreignKey->source as $key => $column) {
                    if (!$originals[$column]) {
                        continue 2;
                    }
                    $foreignKey->source[$key] = $this->driver->unescapeId($originals[$column]);
                }
                if (!isset($foreign[" $key_name"])) {
                    $foreign[] = " " . $this->driver->formatForeignKey($foreignKey);
                }
            }
            $this->driver->queries("BEGIN");
        }
        foreach ($fields as $key => $field) {
            $fields[$key] = "  " . implode($field);
        }
        $fields = array_merge($fields, array_filter($foreign));
        $tempName = ($table == $name ? "adminer_$name" : $name);
        if (!$this->driver->queries("CREATE TABLE " . $this->driver->table($tempName) . " (\n" . implode(",\n", $fields) . "\n)")) {
            // implicit ROLLBACK to not overwrite $this->driver->error()
            return false;
        }
        if ($table != "") {
            if ($originals && !$this->driver->queries("INSERT INTO " . $this->driver->table($tempName) .
                " (" . implode(", ", $originals) . ") SELECT " . implode(
                    ", ",
                    array_map(function ($key) {
                   return $this->driver->escapeId($key);
               }, array_keys($originals))
                ) . " FROM " . $this->driver->table($table))) {
                return false;
            }
            $triggers = [];
            foreach ($this->triggers($table) as $trigger_name => $timing_event) {
                $trigger = $this->trigger($trigger_name);
                $triggers[] = "CREATE TRIGGER " . $this->driver->escapeId($trigger_name) . " " .
                    implode(" ", $timing_event) . " ON " . $this->driver->table($name) . "\n$trigger[Statement]";
            }
            $autoIncrement = $autoIncrement ? 0 :
                $this->connection->result("SELECT seq FROM sqlite_sequence WHERE name = " .
                $this->driver->quote($table)); // if $autoIncrement is set then it will be updated later
            // drop before creating indexes and triggers to allow using old names
            if (!$this->driver->queries("DROP TABLE " . $this->driver->table($table)) ||
                ($table == $name && !$this->driver->queries("ALTER TABLE " . $this->driver->table($tempName) .
                " RENAME TO " . $this->driver->table($name))) || !$this->alterIndexes($name, $indexes)
            ) {
                return false;
            }
            if ($autoIncrement) {
                $this->driver->queries("UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = " . $this->driver->quote($name)); // ignores error
            }
            foreach ($triggers as $trigger) {
                if (!$this->driver->queries($trigger)) {
                    return false;
                }
            }
            $this->driver->queries("COMMIT");
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function alterIndexes(string $table, array $alter)
    {
        foreach ($alter as $primary) {
            if ($primary[0] == "PRIMARY") {
                return $this->recreateTable($table, $table, [], [], [], 0, $alter);
            }
        }
        foreach (array_reverse($alter) as $val) {
            if (!$this->driver->queries(
                $val[2] == "DROP" ? "DROP INDEX " . $this->driver->escapeId($val[1]) :
                $this->driver->createIndexSql($table, $val[0], $val[1], "(" . implode(", ", $val[2]) . ")")
            )) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function truncateTables(array $tables)
    {
        return $this->driver->applyQueries("DELETE FROM", $tables);
    }

    /**
     * @inheritDoc
     */
    public function dropViews(array $views)
    {
        return $this->driver->applyQueries("DROP VIEW", $views);
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables)
    {
        return $this->driver->applyQueries("DROP TABLE", $tables);
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
    public function trigger(string $trigger)
    {
        if ($trigger == "") {
            return ["Statement" => "BEGIN\n\t;\nEND"];
        }
        $idf = '(?:[^`"\s]+|`[^`]*`|"[^"]*")+';
        $options = $this->triggerOptions();
        preg_match("~^CREATE\\s+TRIGGER\\s*$idf\\s*(" . implode("|", $options["Timing"]) .
            ")\\s+([a-z]+)(?:\\s+OF\\s+($idf))?\\s+ON\\s*$idf\\s*(?:FOR\\s+EACH\\s+ROW\\s)?(.*)~is",
            $this->connection->result("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " .
            $this->driver->quote($trigger)), $match);
        $of = $match[3];
        return [
            "Timing" => strtoupper($match[1]),
            "Event" => strtoupper($match[2]) . ($of ? " OF" : ""),
            "Of" => ($of[0] == '`' || $of[0] == '"' ? $this->driver->unescapeId($of) : $of),
            "Trigger" => $trigger,
            "Statement" => $match[4],
        ];
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
            $triggers[$row["name"]] = new Trigger($match[1], $match[2]);
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
