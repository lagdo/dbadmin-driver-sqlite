<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Db\Server as AbstractServer;
use Lagdo\DbAdmin\Driver\Entity\TableField;
use Lagdo\DbAdmin\Driver\Entity\Table;
use Lagdo\DbAdmin\Driver\Entity\Index;
use Lagdo\DbAdmin\Driver\Entity\ForeignKey;
use Lagdo\DbAdmin\Driver\Entity\Trigger;

use DirectoryIterator;
use Exception;

class Server extends AbstractServer
{
    /**
     * The database file extensions
     *
     * @var string
     */
    protected $extensions = "db|sdb|sqlite";

    /**
     * @inheritDoc
     */
    public function name()
    {
        return "SQLite 3";
    }

    /**
     * Get the full path to the database file
     *
     * @param string $database
     * @param array $options
     *
     * @return string
     */
    private function filename($database, $options)
    {
        return rtrim($options['directory'], '/\\') . "/$database";
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        $connection = null;
        if (class_exists("SQLite3")) {
            $connection = new Sqlite\Connection($this->db, $this->util, $this, 'SQLite3');
        }
        elseif (extension_loaded("pdo_sqlite")) {
            $connection = new Pdo\Connection($this->db, $this->util, $this, 'PDO_SQLite');
        }
        else {
            throw new AuthException($this->util->lang('No package installed to open a Sqlite database.'));
        }

        if ($this->connection === null) {
            $this->connection = $connection;
            $this->driver = new Driver($this->db, $this->util, $this, $connection);
            // By default, connect to the in memory database.
            $this->connection->open(':memory:', $this->db->options());
        }

        return $connection;
    }

    /**
     * @inheritDoc
     */
    public function escapeId($idf)
    {
        return '"' . str_replace('"', '""', $idf) . '"';
    }

    /**
     * @inheritDoc
     */
    public function databases($flush)
    {
        $databases = [];
        $directory = rtrim($this->db->options('directory'), '/\\');
        $iterator = new DirectoryIterator($directory);
        // Iterate on dir content
        foreach($iterator as $file)
        {
            // Skip everything except Sqlite files
            if(!$file->isFile() || !$this->checkSqliteName($filename = $file->getFilename()))
            {
                continue;
            }
            $databases[] = $filename;
        }
        return $databases;
    }

    public function limit($query, $where, $limit, $offset = 0, $separator = " ")
    {
        return " $query$where" . ($limit !== null ? $separator .
            "LIMIT $limit" . ($offset ? " OFFSET $offset" : "") : "");
    }

    public function limitToOne($table, $query, $where, $separator = "\n")
    {
        return preg_match('~^INTO~', $query) ||
            $this->connection->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ?
            $this->limit($query, $where, 1, 0, $separator) :
            //! use primary key in tables with WITHOUT rowid
            " $query WHERE rowid = (SELECT rowid FROM " . $this->table($table) . $where . $separator . "LIMIT 1)";
    }

    public function databaseCollation($database, $collations)
    {
        // there is no database list so $database == $this->selectedDatabase()
        return $this->connection->result("PRAGMA encoding");
    }

    public function loggedUser()
    {
        return get_current_user(); // should return effective user
    }

    public function tables()
    {
        return $this->db->keyValues("SELECT name, type FROM sqlite_master " .
            "WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
    }

    public function countTables($databases)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function tableStatus($name = "", $fast = false)
    {
        $tables = [];
        $query = "SELECT name AS Name, type AS Engine, 'rowid' AS Oid, '' AS Auto_increment " .
            "FROM sqlite_master WHERE type IN ('table', 'view') " . ($name != "" ? "AND name = " .
            $this->quote($name) : "ORDER BY name");
        foreach ($this->db->rows($query) as $row) {
            $status = new Table($row['Name']);
            $status->engine = $row['Engine'];
            $status->oid = $row['Oid'];
            // $status->Auto_increment = $row['Auto_increment'];
            $status->rows = $this->connection->result("SELECT COUNT(*) FROM " . $this->escapeId($row["Name"]));
            $tables[$row["Name"]] = $status;
        }
        // foreach ($this->db->rows("SELECT * FROM sqlite_sequence", null, "") as $row) {
        //     $tables[$row["name"]]["Auto_increment"] = $row["seq"];
        // }
        return ($name != "" ? $tables[$name] : $tables);
    }

    public function isView($tableStatus)
    {
        return $tableStatus->engine == "view";
    }

    public function supportForeignKeys($tableStatus)
    {
        return !$this->connection->result("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
    }

    public function fields($table)
    {
        $fields = [];
        $primary = "";
        foreach ($this->db->rows("PRAGMA table_info(" . $this->table($table) . ")") as $row) {
            $name = $row["name"];
            $type = strtolower($row["type"]);
            $default = $row["dflt_value"];

            $field = new TableField();

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
        $query = "SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " . $this->quote($table);
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

    public function indexes($table, $connection = null)
    {
        if (!is_object($connection)) {
            $connection = $this->connection;
        }
        $indexes = [];
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $this->quote($table);
        $result = $connection->result($query);
        if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $result, $match)) {
            $indexes[""] = new Index();
            $indexes[""]->type = "PRIMARY";
            preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i',
                $match[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $indexes[""]->columns[] = $this->unescapeId($match[2]) . $match[4];
                $indexes[""]->descs[] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
            }
        }
        if (!$indexes) {
            foreach ($this->fields($table) as $name => $field) {
                if ($field->primary) {
                    if (!isset($indexes[""])) {
                        $indexes[""] = new Index();
                    }
                    $indexes[""]->type = "PRIMARY";
                    $indexes[""]->columns = [$name];
                    $indexes[""]->lengths = [];
                    $indexes[""]->descs = [null];
                }
            }
        }
        $query = "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . $this->quote($table);
        $results = $this->db->keyValues($query, $connection);
        foreach ($this->db->rows("PRAGMA index_list(" . $this->table($table) . ")", $connection) as $row) {
            $index = new Index();

            $name = $row["name"];
            $index->type = $row["unique"] ? "UNIQUE" : "INDEX";
            $index->lengths = [];
            $index->descs = [];
            foreach ($this->db->rows("PRAGMA index_info(" . $this->escapeId($name) . ")", $connection) as $row1) {
                $index->columns[] = $row1["name"];
                $index->descs[] = null;
            }
            if (preg_match('~^CREATE( UNIQUE)? INDEX ' . preg_quote($this->escapeId($name) . ' ON ' .
                $this->escapeId($table), '~') . ' \((.*)\)$~i', $results[$name], $regs)) {
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

    public function foreignKeys($table)
    {
        $foreignKeys = [];
        foreach ($this->db->rows("PRAGMA foreign_key_list(" . $this->table($table) . ")") as $row) {
            $name = $row["id"];
            if (!isset($foreignKeys[$name])) {
                $foreignKeys[$name] = new ForeignKey();
            }
            //! idf_unescape in SQLite2
            $foreignKeys[$name]->source[] = $row["from"];
            $foreignKeys[$name]->target[] = $row["to"];
        }
        return $foreignKeys;
    }

    public function view($name)
    {
        return [
            "select" => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '',
                $this->connection->result("SELECT sql FROM sqlite_master WHERE name = " .
                $this->quote($name)))
            ]; //! identifiers may be inside []
    }

    public function collations()
    {
        $create = $this->util->input()->hasTable();
        return ($create) ? $this->db->values("PRAGMA collation_list", 1) : [];
    }

    private function checkSqliteName($name)
    {
        // Avoid creating PHP files on unsecured servers
        return preg_match("~^[^\\0]*\\.({$this->extensions})\$~", $name);
    }

    public function createDatabase($database, $collation)
    {
        $options = $this->db->options();
        $filename = $this->filename($database, $options);
        if (file_exists($filename)) {
            $this->db->setError($this->util->lang('File exists.'));
            return false;
        }
        if (!$this->checkSqliteName($filename)) {
            $this->db->setError($this->util->lang('Please use one of the extensions %s.',
                str_replace("|", ", ", $this->extensions)));
            return false;
        }
        try {
            $connection = $this->connect(); // New connection
            $connection->open($filename, $options);
        } catch (Exception $ex) {
            $this->db->setError($ex->getMessage());
            return false;
        }
        $connection->query('PRAGMA encoding = "UTF-8"');
        $connection->query('CREATE TABLE adminer (i)'); // otherwise creates empty file
        $connection->query('DROP TABLE adminer');
        return true;
    }

    public function dropDatabases($databases)
    {
        $options = $this->db->options();
        foreach ($databases as $database) {
            $filename = $this->filename($database, $options);
            if (!@unlink($filename)) {
                $this->db->setError($this->util->lang('File exists.'));
                return false;
            }
        }
        return true;
    }

    public function renameDatabase($database, $collation)
    {
        $options = $this->db->options();
        $filename = $this->filename($database, $options);
        if (!$this->checkSqliteName($filename)) {
            $this->db->setError($this->util->lang('Please use one of the extensions %s.',
                str_replace("|", ", ", $this->extensions)));
            return false;
        }
        $this->db->setError($this->util->lang('File exists.'));
        return @rename($this->filename($this->selectedDatabase(), $options), $filename);
    }

    public function autoIncrement()
    {
        return " PRIMARY KEY AUTOINCREMENT";
    }

    public function alterTable($table, $name, $fields, $foreign, $comment, $engine, $collation, $autoIncrement, $partitioning)
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
                if (!$this->db->queries("ALTER TABLE " . $this->table($table) . " $val")) {
                    return false;
                }
            }
            if ($table != $name && !$this->db->queries("ALTER TABLE " . $this->table($table) . " RENAME TO " . $this->table($name))) {
                return false;
            }
        } elseif (!$this->recreateTable($table, $name, $alter, $originals, $foreign, $autoIncrement)) {
            return false;
        }
        if ($autoIncrement) {
            $this->db->queries("BEGIN");
            $this->db->queries("UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = " . $this->quote($name)); // ignores error
            if (!$this->db->affectedRows()) {
                $this->db->queries("INSERT INTO sqlite_sequence (name, seq) VALUES (" . $this->quote($name) . ", $autoIncrement)");
            }
            $this->db->queries("COMMIT");
        }
        return true;
    }

    protected function recreateTable($table, $name, $fields, $originals, $foreign, $autoIncrement, $indexes = [])
    {
        if ($table != "") {
            if (!$fields) {
                foreach ($this->fields($table) as $key => $field) {
                    if ($indexes) {
                        $field->autoIncrement = 0;
                    }
                    $fields[] = $this->util->processField($field, $field);
                    $originals[$key] = $this->escapeId($key);
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
                    $foreignKey->source[$key] = $this->unescapeId($originals[$column]);
                }
                if (!isset($foreign[" $key_name"])) {
                    $foreign[] = " " . $this->formatForeignKey($foreignKey);
                }
            }
            $this->db->queries("BEGIN");
        }
        foreach ($fields as $key => $field) {
            $fields[$key] = "  " . implode($field);
        }
        $fields = array_merge($fields, array_filter($foreign));
        $tempName = ($table == $name ? "adminer_$name" : $name);
        if (!$this->db->queries("CREATE TABLE " . $this->table($tempName) . " (\n" . implode(",\n", $fields) . "\n)")) {
            // implicit ROLLBACK to not overwrite $this->db->error()
            return false;
        }
        if ($table != "") {
            if ($originals && !$this->db->queries("INSERT INTO " . $this->table($tempName) .
                " (" . implode(", ", $originals) . ") SELECT " . implode(
                    ", ",
                    array_map(function ($key) {
                   return $this->escapeId($key);
               }, array_keys($originals))
                ) . " FROM " . $this->table($table))) {
                return false;
            }
            $triggers = [];
            foreach ($this->triggers($table) as $trigger_name => $timing_event) {
                $trigger = $this->trigger($trigger_name);
                $triggers[] = "CREATE TRIGGER " . $this->escapeId($trigger_name) . " " .
                    implode(" ", $timing_event) . " ON " . $this->table($name) . "\n$trigger[Statement]";
            }
            $autoIncrement = $autoIncrement ? 0 :
                $this->connection->result("SELECT seq FROM sqlite_sequence WHERE name = " .
                $this->quote($table)); // if $autoIncrement is set then it will be updated later
            // drop before creating indexes and triggers to allow using old names
            if (!$this->db->queries("DROP TABLE " . $this->table($table)) ||
                ($table == $name && !$this->db->queries("ALTER TABLE " . $this->table($tempName) .
                " RENAME TO " . $this->table($name))) || !$this->alterIndexes($name, $indexes)
            ) {
                return false;
            }
            if ($autoIncrement) {
                $this->db->queries("UPDATE sqlite_sequence SET seq = $autoIncrement WHERE name = " . $this->quote($name)); // ignores error
            }
            foreach ($triggers as $trigger) {
                if (!$this->db->queries($trigger)) {
                    return false;
                }
            }
            $this->db->queries("COMMIT");
        }
        return true;
    }

    protected function indexSql($table, $type, $name, $columns)
    {
        return "CREATE $type " . ($type != "INDEX" ? "INDEX " : "") .
            $this->escapeId($name != "" ? $name : uniqid($table . "_")) .
            " ON " . $this->table($table) . " $columns";
    }

    public function alterIndexes($table, $alter)
    {
        foreach ($alter as $primary) {
            if ($primary[0] == "PRIMARY") {
                return $this->recreateTable($table, $table, [], [], [], 0, $alter);
            }
        }
        foreach (array_reverse($alter) as $val) {
            if (!$this->db->queries(
                $val[2] == "DROP" ? "DROP INDEX " . $this->escapeId($val[1]) :
                $this->indexSql($table, $val[0], $val[1], "(" . implode(", ", $val[2]) . ")")
            )) {
                return false;
            }
        }
        return true;
    }

    public function truncateTables($tables)
    {
        return $this->db->applyQueries("DELETE FROM", $tables);
    }

    public function dropViews($views)
    {
        return $this->db->applyQueries("DROP VIEW", $views);
    }

    public function dropTables($tables)
    {
        return $this->db->applyQueries("DROP TABLE", $tables);
    }

    public function moveTables($tables, $views, $target)
    {
        return false;
    }

    public function trigger($name)
    {
        if ($name == "") {
            return ["Statement" => "BEGIN\n\t;\nEND"];
        }
        $idf = '(?:[^`"\s]+|`[^`]*`|"[^"]*")+';
        $options = $this->triggerOptions();
        preg_match("~^CREATE\\s+TRIGGER\\s*$idf\\s*(" . implode("|", $options["Timing"]) .
            ")\\s+([a-z]+)(?:\\s+OF\\s+($idf))?\\s+ON\\s*$idf\\s*(?:FOR\\s+EACH\\s+ROW\\s)?(.*)~is",
            $this->connection->result("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " .
            $this->quote($name)), $match);
        $of = $match[3];
        return [
            "Timing" => strtoupper($match[1]),
            "Event" => strtoupper($match[2]) . ($of ? " OF" : ""),
            "Of" => ($of[0] == '`' || $of[0] == '"' ? $this->unescapeId($of) : $of),
            "Trigger" => $name,
            "Statement" => $match[4],
        ];
    }

    public function triggers($table)
    {
        $triggers = [];
        $options = $this->triggerOptions();
        $query = "SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $this->quote($table);
        foreach ($this->db->rows($query) as $row) {
            preg_match('~^CREATE\s+TRIGGER\s*(?:[^`"\s]+|`[^`]*`|"[^"]*")+\s*(' .
                implode("|", $options["Timing"]) . ')\s*(.*?)\s+ON\b~i', $row["sql"], $match);
            $triggers[$row["name"]] = new Trigger($match[1], $match[2]);
        }
        return $triggers;
    }

    public function triggerOptions()
    {
        return [
            "Timing" => ["BEFORE", "AFTER", "INSTEAD OF"],
            "Event" => ["INSERT", "UPDATE", "UPDATE OF", "DELETE"],
            "Type" => ["FOR EACH ROW"],
        ];
    }

    public function begin()
    {
        return $this->db->queries("BEGIN");
    }

    public function lastAutoIncrementId()
    {
        return $this->connection->result("SELECT LAST_INSERT_ROWID()");
    }

    public function explain($connection, $query)
    {
        return $connection->query("EXPLAIN QUERY PLAN $query");
    }

    public function createTableSql($table, $autoIncrement, $style)
    {
        $query = $this->connection->result("SELECT sql FROM sqlite_master " .
            "WHERE type IN ('table', 'view') AND name = " . $this->quote($table));
        foreach ($this->indexes($table) as $name => $index) {
            if ($name == '') {
                continue;
            }
            $query .= ";\n\n" . $this->indexSql($table, $index->type, $name,
                "(" . implode(", ", array_map(function ($key) {
                    return $this->escapeId($key);
                }, $index->columns)) . ")");
        }
        return $query;
    }

    public function truncateTableSql($table)
    {
        return "DELETE FROM " . $this->table($table);
    }

    public function createTriggerSql($table)
    {
        $query = "SELECT sql || ';;\n' FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $this->quote($table);
        return implode($this->db->values($query));
    }

    public function variables()
    {
        $variables = [];
        foreach (["auto_vacuum", "cache_size", "count_changes", "default_cache_size", "empty_result_callbacks", "encoding", "foreign_keys", "full_column_names", "fullfsync", "journal_mode", "journal_size_limit", "legacy_file_format", "locking_mode", "page_size", "max_page_count", "read_uncommitted", "recursive_triggers", "reverse_unordered_selects", "secure_delete", "short_column_names", "synchronous", "temp_store", "temp_store_directory", "schema_version", "integrity_check", "quick_check"] as $key) {
            $variables[$key] = $this->connection->result("PRAGMA $key");
        }
        return $variables;
    }

    public function statusVariables()
    {
        $variables = [];
        if (!($options = $this->db->values("PRAGMA compile_options"))) {
            return [];
        }
        foreach ($options as $option) {
            $values = explode("=", $option, 2);
            $variables[$values[0]] = count($values) > 1 ? $values[1] : "true";
        }
        return $variables;
    }

    public function support($feature)
    {
        return preg_match('~^(columns|database|drop_col|dump|indexes|descidx|move_col|sql|status|table|trigger|variables|view|view_trigger)$~', $feature);
    }

    /**
     * @inheritDoc
     */
    protected function setConfig()
    {
        $this->config->jush = 'sqlite';
        $this->config->drivers = ["SQLite3", "PDO_SQLite"];

        $types = [ //! arrays
            $this->util->lang('Numbers') => ["integer" => 0, "real" => 0, "numeric" => 0],
            $this->util->lang('Strings') => ["text" => 0],
            $this->util->lang('Binary') => ["blob" => 0],
        ];
        foreach ($types as $group => $_types) {
            $this->config->structuredTypes[$group] = array_keys($_types);
            $this->config->types = array_merge($this->config->types, $_types);
        }

        // $this->config->unsigned = [];
        $this->config->operators = ["=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"]; // REGEXP can be user defined function;
        $this->config->functions = ["hex", "length", "lower", "round", "unixepoch", "upper"];
        $this->config->grouping = ["avg", "count", "count distinct", "group_concat", "max", "min", "sum"];
        $this->config->editFunctions = [[
            // "text" => "date('now')/time('now')/datetime('now')",
        ],[
            "integer|real|numeric" => "+/-",
            // "text" => "date/time/datetime",
            "text" => "||",
        ]];
    }
}
