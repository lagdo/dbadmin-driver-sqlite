<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;
use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\ForeignKeyEntity;

use Lagdo\DbAdmin\Driver\Db\Table as AbstractTable;

use function str_replace;
use function strtolower;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_quote;

class Table extends AbstractTable
{
    use TableTrait;

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
     * @param string $table
     *
     * @return array
     */
    private function tableFields(string $table)
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
        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function fields(string $table)
    {
        $fields = $this->tableFields($table);
        $query = "SELECT sql FROM sqlite_master WHERE type IN ('table', 'view') AND name = " . $this->driver->quote($table);
        $result = $this->connection->result($query);
        $pattern = '~(("[^"]*+")+|[a-z0-9_]+)\s+text\s+COLLATE\s+(\'[^\']+\'|\S+)~i';
        preg_match_all($pattern, $result, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
            if (isset($fields[$name])) {
                $fields[$name]->collation = trim($match[3], "'");
            }
        }
        return $fields;
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
    public function indexes(string $table, ConnectionInterface $connection = null)
    {
        if (!$connection) {
            $connection = $this->connection;
        }
        $primaryIndex = $this->makePrimaryIndex($table, $connection);
        if ($primaryIndex === null) {
            return [];
        }

        $indexes = ['' => $primaryIndex];
        $query = "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . $this->driver->quote($table);
        $results = $this->driver->keyValues($query, $connection);
        $rows = $this->driver->rows("PRAGMA index_list(" . $this->driver->table($table) . ")", $connection);
        foreach ($rows as $row) {
            $index = $this->makeIndexEntity($row, $results, $table, $connection);
            $name = $row["name"];
            if ($index->type === 'UNIQUE' && $index->columns == $primaryIndex->columns &&
                $index->descs == $primaryIndex->descs && preg_match("~^sqlite_~", $name)) {
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
}
