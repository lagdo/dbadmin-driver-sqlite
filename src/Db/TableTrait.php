<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;

use function preg_match;
use function str_replace;
use function strtolower;
use function preg_match_all;
use function preg_quote;
use function array_filter;

trait TableTrait
{
    /**
     * @param string $type
     *
     * @return string
     */
    private function rowType(string $type): string
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

    /**
     * @param array $row
     *
     * @return mixed|null
     */
    private function defaultvalue(array $row)
    {
        $default = $row["dflt_value"];
        if (preg_match("~'(.*)'~", $default ?? '', $match)) {
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
    private function makeFieldEntity(array $row): TableFieldEntity
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
    private function tableFields(string $table): array
    {
        $fields = [];
        $rows = $this->driver->rows('PRAGMA table_info(' . $this->driver->escapeTableName($table) . ')');
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
     * @param string $table
     *
     * @return array
     */
    private function queryStatus(string $table = ''): array
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
    private function makeStatus(array $row): TableEntity
    {
        $status = new TableEntity($row['Name']);
        $status->engine = $row['Engine'];
        $status->oid = $row['Oid'];
        // $status->Auto_increment = $row['Auto_increment'];
        $query = 'SELECT COUNT(*) FROM ' . $this->driver->escapeId($row['Name']);
        $status->rows = $this->driver->result($query);

        return $status;
    }

    /**
     * @param array $row
     * @param array $results
     * @param string $table
     *
     * @return IndexEntity
     */
    private function makeIndexEntity(array $row, array $results, string $table): IndexEntity
    {
        $index = new IndexEntity();

        $index->name = $row["name"];
        $index->type = $row["unique"] ? "UNIQUE" : "INDEX";
        $index->lengths = [];
        $index->descs = [];
        $columns = $this->driver->rows("PRAGMA index_info(" . $this->driver->escapeId($index->name) . ")");
        foreach ($columns as $column) {
            $index->columns[] = $column["name"];
            $index->descs[] = null;
        }
        if (preg_match('~^CREATE( UNIQUE)? INDEX ' . preg_quote($this->driver->escapeId($index->name) . ' ON ' .
                $this->driver->escapeId($table), '~') . ' \((.*)\)$~i', $results[$index->name] ?? '', $regs)) {
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
     *
     * @return IndexEntity|null
     */
    private function queryPrimaryIndex(string $table): ?IndexEntity
    {
        $primaryIndex = null;
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $this->driver->quote($table);
        $result = $this->driver->result($query);
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
     *
     * @return IndexEntity|null
     */
    private function makePrimaryIndex(string $table): ?IndexEntity
    {
        $primaryIndex = $this->queryPrimaryIndex($table);
        if ($primaryIndex !== null) {
            return $primaryIndex;
        }
        $primaryFields = array_filter($this->fields($table), function($field) {
            return $field->primary;
        });
        if (!$primaryFields) {
            return null;
        }
        $primaryIndex = new IndexEntity();
        $primaryIndex->type = "PRIMARY";
        $primaryIndex->lengths = [];
        $primaryIndex->descs = [null];
        $primaryIndex->columns = [];
        foreach ($primaryFields as $name => $field) {
            $primaryIndex->columns[] = $name;
        }
        return $primaryIndex;
    }

    /**
     * @param IndexEntity $index
     * @param IndexEntity $primaryIndex
     *
     * @return bool
     */
    private function indexIsPrimary(IndexEntity $index, IndexEntity $primaryIndex): bool
    {
        return $index->type === 'UNIQUE' && $index->columns == $primaryIndex->columns &&
            $index->descs == $primaryIndex->descs && preg_match("~^sqlite_~", $index->name);
    }
}
