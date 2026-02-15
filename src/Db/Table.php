<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\AbstractTable;
use Lagdo\DbAdmin\Driver\Dto\ForeignKeyDto;
use Lagdo\DbAdmin\Driver\Dto\IndexDto;
use Lagdo\DbAdmin\Driver\Dto\TableDto;
use Lagdo\DbAdmin\Driver\Dto\TableFieldDto;
use Lagdo\DbAdmin\Driver\Dto\TriggerDto;

use function array_combine;
use function array_filter;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;

class Table extends AbstractTable
{
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
     * @return TableDto
     */
    private function makeStatus(array $row): TableDto
    {
        $status = new TableDto($row['Name']);
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
     * @return IndexDto
     */
    private function makeIndexDto(array $row, array $results, string $table): IndexDto
    {
        $index = new IndexDto();

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
     * @return IndexDto|null
     */
    private function queryPrimaryIndex(string $table): ?IndexDto
    {
        $primaryIndex = null;
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $this->driver->quote($table);
        $result = $this->driver->result($query);
        if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $result, $match)) {
            $primaryIndex = new IndexDto();
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
     * @return IndexDto|null
     */
    private function makePrimaryIndex(string $table): ?IndexDto
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
        $primaryIndex = new IndexDto();
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
     * @param IndexDto $index
     * @param IndexDto $primaryIndex
     *
     * @return bool
     */
    private function indexIsPrimary(IndexDto $index, IndexDto $primaryIndex): bool
    {
        return $index->type === 'UNIQUE' && $index->columns == $primaryIndex->columns &&
            $index->descs == $primaryIndex->descs && preg_match("~^sqlite_~", $index->name);
    }

    /**
     * @inheritDoc
     */
    public function isView(TableDto $tableStatus): bool
    {
        return $tableStatus->engine == 'view';
    }

    /**
     * @inheritDoc
     */
    public function supportForeignKeys(TableDto $tableStatus): bool
    {
        return !$this->driver->result("SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')");
    }

    /**
     * @param string $type
     *
     * @return string
     */
    private function rowType(string $type): string
    {
        return match(true) {
            preg_match('~int~i', $type) > 0 => 'integer',
            preg_match('~char|clob|text~i', $type) > 0 => 'text',
            preg_match('~blob~i', $type) > 0 => 'blob',
            preg_match('~real|floa|doub~i', $type) > 0 => 'real',
            default => 'numeric',
        };
    }

    /**
     * @param array $row
     *
     * @return mixed|null
     */
    private function defaultvalue(array $row)
    {
        $default = $row['dflt_value'] ?? null;
        return match(true) {
            preg_match("~'(.*)'~", $default ?? '', $match) > 0 =>
                str_replace("''", "'", $match[1]),
            $default === null,
            $default === 'NULL' => null,
            default => $default,
        };
    }

    /**
     * @param array $row
     *
     * @return TableFieldDto
     */
    private function makeFieldDto(array $row): TableFieldDto
    {
        $field = new TableFieldDto();

        $type = strtolower($row["type"]);
        $field->name = $row["name"];
        $field->type = $this->rowType($type);
        $field->fullType = $type;
        $field->default = $this->defaultvalue($row);
        $field->nullable = !$row["notnull"];
        $field->privileges = ["select" => 1, "insert" => 1, "update" => 1, "where" => 1, "order" => 1];
        $field->primary = $row["pk"];
        return $field;
    }

    /**
     * @param string $table
     *
     * @return array<TableFieldDto>
     */
    private function tableFields(string $table): array
    {
        $fields = [];
        $infoTableName = 'table_' . ($this->driver->minVersion(3.31) ? 'x' : '') . 'info';
        $tableName = $this->driver->escapeTableName($table);
        $rows = $this->driver->rows("PRAGMA $infoTableName($tableName)");
        $primary = '';
        foreach ($rows as $row) {
            $field = $this->makeFieldDto($row);
            if ($row['pk']) {
                if ($primary != '') {
                    $fields[$primary]->autoIncrement = false;
                } elseif (preg_match('~^integer$~i', $field->fullType)) {
                    $field->autoIncrement = true;
                }
                $primary = $field->name;
            }

            $fields[$field->name] = $field;
        }

        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function fields(string $table): array
    {
        $fields = $this->tableFields($table);

        $tableName = $this->driver->quote($table);
        $sql = $this->driver->result("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = $tableName");
        $idf = '(("[^"]*+")+|[a-z0-9_]+)';
        $pattern = '~' . $idf . '\s+text\s+COLLATE\s+(\'[^\']+\'|\S+)~i';
        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
            if (isset($fields[$name])) {
                $fields[$name]->collation = trim($match[3], "'");
            }
        }

        $pattern = '~' . $idf . '\s.*GENERATED ALWAYS AS \((.+)\) (STORED|VIRTUAL)~i';
        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
            $fields[$name]->default = $match[3];
            $fields[$name]->generated = strtoupper($match[4]);
        }

        return $fields;
    }

    /**
     * @inheritDoc
     */
    public function indexes(string $table): array
    {
        $primaryIndex = $this->makePrimaryIndex($table);
        if ($primaryIndex === null) {
            return [];
        }

        $indexes = ['' => $primaryIndex];
        $query = "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = " . $this->driver->quote($table);
        $results = $this->driver->keyValues($query);
        $rows = $this->driver->rows("PRAGMA index_list(" . $this->driver->escapeTableName($table) . ")");
        foreach ($rows as $row) {
            $index = $this->makeIndexDto($row, $results, $table);
            if ($this->indexIsPrimary($index, $primaryIndex)) {
                $indexes[$index->name] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @inheritDoc
     */
    public function foreignKeys(string $table): array
    {
        $foreignKeys = [];
        $query = 'PRAGMA foreign_key_list(' . $this->driver->escapeTableName($table) . ')';
        foreach ($this->driver->rows($query) as $row) {
            $name = $row['id'];
            if (!isset($foreignKeys[$name])) {
                $foreignKeys[$name] = new ForeignKeyDto();
            }
            //! idf_unescape in SQLite2
            $foreignKeys[$name]->table = $row['table'] ?? '';
            $foreignKeys[$name]->source[] = $row['from'];
            $foreignKeys[$name]->target[] = $row['to'];
        }
        return $foreignKeys;
    }

    /**
     * @inheritDoc
     */
    public function checkConstraints(TableDto $status): array
    {
        $table = $this->driver->quote($status->name);
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = $table";
        preg_match_all('~ CHECK *(\( *(((?>[^()]*[^() ])|(?1))*) *\))~',
            $this->driver->result($query, 0) ?? '', $matches); //! could be inside a comment
        return array_combine($matches[2], $matches[2]);
    }

    /**
     * @inheritDoc
     */
    public function tableStatus(string $table, bool $fast = false): TableDto|null
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
    public function tableStatuses(bool $fast = false): array
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
    public function tableNames(): array
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
    public function triggerOptions(): array
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
    public function trigger(string $name, string $table = ''): TriggerDto|null
    {
        if ($name == "") {
            return new TriggerDto('', '', "BEGIN\n\t;\nEND");
        }

        $idf = '(?:[^`"\s]+|`[^`]*`|"[^"]*")+';
        $options = $this->triggerOptions();
        preg_match("~^CREATE\\s+TRIGGER\\s*$idf\\s*(" . implode("|", $options["Timing"]) .
            ")\\s+([a-z]+)(?:\\s+OF\\s+($idf))?\\s+ON\\s*$idf\\s*(?:FOR\\s+EACH\\s+ROW\\s)?(.*)~is",
            $this->driver->result("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " .
                $this->driver->quote($name)), $match);
        $of = $match[3];
        return new TriggerDto(strtoupper($match[1]), strtoupper($match[2]), $match[4],
            ($of[0] == '`' || $of[0] == '"' ? $this->driver->unescapeId($of) : $of), $name);
    }

    /**
     * @inheritDoc
     */
    public function triggers(string $table): array
    {
        $triggers = [];
        $options = $this->triggerOptions();
        $query = "SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $this->driver->quote($table);
        foreach ($this->driver->rows($query) as $row) {
            preg_match('~^CREATE\s+TRIGGER\s*(?:[^`"\s]+|`[^`]*`|"[^"]*")+\s*(' .
                implode("|", $options["Timing"]) . ')\s*(.*?)\s+ON\b~i', $row["sql"], $match);
            $triggers[$row["name"]] = new TriggerDto($match[1], $match[2], '', '', $row["name"]);
        }
        return $triggers;
    }

    /**
     * @inheritDoc
     */
    public function tableHelp(string $name): string
    {
        return match($name) {
            "sqlite_sequence" => "fileformat2.html#seqtab",
            "sqlite_master" => "fileformat2.html#$name",
            default => '',
        };
    }
}
