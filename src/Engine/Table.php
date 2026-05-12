<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Engine;

use Lagdo\DbAdmin\Driver\Sql\Dto\ColumnDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\ForeignKeyDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\IndexDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\TableDto;
use Lagdo\DbAdmin\Driver\Sql\Dto\TriggerDto;
use Lagdo\DbAdmin\Driver\Sql\Specific\Engine\AbstractTable;

use function array_combine;
use function array_filter;
use function array_map;
use function count;
use function implode;
use function is_a;
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
        $tableName = $this->_engine()->quote($table);
        $query = "SELECT name AS Name, type AS Engine, 'rowid' AS Oid,
(SELECT seq FROM sqlite_sequence s WHERE s.name = m.name) AS Auto_increment
FROM sqlite_master m WHERE type IN ('table', 'view') " .
            ($table !== '' ? "AND name = $tableName" : "ORDER BY name");
        return $this->_engine()->rows($query);
    }

    /**
     * @param array $row
     *
     * @return TableDto
     */
    private function makeStatus(array $row): TableDto
    {
        $status = new TableDto($row['Name']);
        $status->engine = $row['Engine'] ?? '';
        $status->oid = $row['Oid'];
        $status->hasAutoIncrement = $row['Auto_increment'] !== null;
        $status->autoIncrementValue = $row['Auto_increment'] ?? 0;
        $query = 'SELECT COUNT(*) FROM ' . $this->_statement()->escapeId($row['Name']);
        $status->rowCount = (int)$this->_engine()->columnValue($query);

        return $status;
    }

    /**
     * @inheritDoc
     */
    public function isView(TableDto $tableStatus): bool
    {
        return $tableStatus->engine === 'view';
    }

    /**
     * @inheritDoc
     */
    public function supportForeignKeys(TableDto $tableStatus): bool
    {
        $query = "SELECT sqlite_compileoption_used('OMIT_FOREIGN_KEY')";
        return !$this->_engine()->columnValue($query);
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
     * @return ColumnDto
     */
    private function makeColumnDto(array $row): ColumnDto
    {
        $column = new ColumnDto();

        $type = strtolower($row["type"]);
        $column->name = $row["name"];
        $column->type = $this->rowType($type);
        $column->fullType = $type;
        $column->default = $this->defaultvalue($row);
        $column->nullable = !$row["notnull"];
        $column->privileges = ["select" => 1, "insert" => 1, "update" => 1, "where" => 1, "order" => 1];
        $column->primary = $row["pk"];

        return $column;
    }

    /**
     * @param string|TableDto $table
     * @param string $tableName
     *
     * @return array<ColumnDto>
     */
    private function tableColumns(string|TableDto $table, string $tableName): array
    {
        $infoTableName = 'table_' . ($this->_engine()->minVersion(3.31) ? 'x' : '') . 'info';
        $tableName = $this->_statement()->escapeTableName($tableName);

        $rows = $this->_engine()->rows("PRAGMA $infoTableName($tableName)");
        $columns = array_map($this->makeColumnDto(...), $rows);
        // Key by column name.
        $columns = array_combine(array_map(fn($column) => $column->name, $columns), $columns);

        if (is_a($table, TableDto::class)) {
            // Set the auto increment only if there is a single
            // column in the primary key, and it is an integer.
            $primaryKeyRows = array_filter($rows, fn(array $row) => (bool)$row['pk']);
            if (count($primaryKeyRows) === 1) {
                $column = $columns[$rows[0]['name']];
                if (preg_match('~^integer$~i', $column->fullType)) {
                    $column->autoIncrement = true;
                    $table->autoIncrementColumn = $column->name;
                }
            }
        }

        return $columns;
    }

    /**
     * @inheritDoc
     */
    public function columns(string|TableDto $table): array
    {
        $tableName = is_a($table, TableDto::class) ? $table->name : $table;
        $columns = $this->tableColumns($table, $tableName);
        $tableName = $this->_engine()->quote($tableName);

        $sql = $this->_engine()->columnValue("SELECT sql FROM sqlite_master
WHERE type = 'table' AND name = $tableName");
        $idf = '(("[^"]*+")+|[a-z0-9_]+)';
        $pattern = '~' . $idf . '\s+text\s+COLLATE\s+(\'[^\']+\'|\S+)~i';
        preg_match_all($pattern, $sql ?? '', $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
            if (isset($columns[$name])) {
                $columns[$name]->collation = trim($match[3], "'");
            }
        }

        $pattern = '~' . $idf . '\s.*GENERATED ALWAYS AS \((.+)\) (STORED|VIRTUAL)~i';
        preg_match_all($pattern, $sql ?? '', $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $name = str_replace('""', '"', preg_replace('~^"|"$~', '', $match[1]));
            $columns[$name]->default = $match[3];
            $columns[$name]->generated = strtoupper($match[4]);
        }

        return $columns;
    }

    /**
     * @param string $table
     *
     * @return IndexDto|null
     */
    private function makePrimaryIndex(string $table): ?IndexDto
    {
        $tableName = $this->_engine()->quote($table);
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = $tableName";
        $result = $this->_engine()->columnValue($query) ?? '';
        if (preg_match('~\bPRIMARY\s+KEY\s*\((([^)"]+|"[^"]*"|`[^`]*`)++)~i', $result, $match)) {
            $primaryIndex = new IndexDto();
            $primaryIndex->type = "PRIMARY";
            preg_match_all('~((("[^"]*+")+|(?:`[^`]*+`)+)|(\S+))(\s+(ASC|DESC))?(,\s*|$)~i',
                $match[1], $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $primaryIndex->columns[] = $this->_statement()->unescapeId($match[2]) . $match[4];
                $primaryIndex->descs[] = (preg_match('~DESC~i', $match[5]) ? '1' : null);
            }
            return $primaryIndex;
        }

        $primaryColumns = array_filter($this->columns($table),
            fn(ColumnDto $column) => $column->primary);
        if (!$primaryColumns) {
            return null;
        }

        $primaryIndex = new IndexDto();
        $primaryIndex->type = "PRIMARY";
        foreach ($primaryColumns as $name => $column) {
            $primaryIndex->columns[] = $name;
            $primaryIndex->descs[] = null;
        }
        return $primaryIndex;
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

        $indexName = $this->_statement()->escapeId($index->name);
        $columns = $this->_engine()->rows("PRAGMA index_info($indexName)");
        foreach ($columns as $column) {
            $index->columns[] = $column["name"];
            $index->descs[] = null;
        }

        $tableName = $this->_statement()->escapeId($table);
        $indexClause = preg_quote("$indexName ON $tableName", '~');
        $regex = "~^CREATE( UNIQUE)? INDEX $indexClause \((.*)\)$~i";
        if (preg_match($regex, $results[$index->name] ?? '', $regs)) {
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
     * @param IndexDto $index
     * @param IndexDto|null $primaryIndex
     *
     * @return bool
     */
    private function indexIsValid(IndexDto $index, IndexDto|null $primaryIndex): bool
    {
        // The arrays are compared using the "!=" operation.
        return $primaryIndex === null || $index->type !== 'UNIQUE' ||
            $index->columns != $primaryIndex->columns ||
            $index->descs != $primaryIndex->descs ||
            preg_match("~^sqlite_~", $index->name);
    }

    /**
     * @inheritDoc
     */
    public function indexes(string $table): array
    {
        $primaryIndex = $this->makePrimaryIndex($table);
        $indexes = $primaryIndex === null ? [] : ['' => $primaryIndex];

        $tableName = $this->_engine()->quote($table);
        $query = "SELECT name, sql FROM sqlite_master
WHERE type = 'index' AND tbl_name = $tableName";
        $results = $this->_engine()->keyValues($query);

        $tableName = $this->_statement()->escapeTableName($table);
        $rows = $this->_engine()->rows("PRAGMA index_list($tableName)");
        foreach ($rows as $row) {
            $index = $this->makeIndexDto($row, $results, $table);
            if ($this->indexIsValid($index, $primaryIndex)) {
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
        $query = 'PRAGMA foreign_key_list(' . $this->_statement()->escapeTableName($table) . ')';
        foreach ($this->_engine()->rows($query) as $row) {
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
        $table = $this->_engine()->quote($status->name);
        $query = "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = $table";
        preg_match_all('~ CHECK *(\( *(((?>[^()]*[^() ])|(?1))*) *\))~',
            $this->_engine()->columnValue($query, 0) ?? '', $matches); //! could be inside a comment
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
            $this->_engine()->columnValue("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = " .
                $this->_engine()->quote($name)), $match);
        $of = $match[3];
        return new TriggerDto(strtoupper($match[1]), strtoupper($match[2]), $match[4],
            ($of[0] == '`' || $of[0] == '"' ? $this->_statement()->unescapeId($of) : $of), $name);
    }

    /**
     * @inheritDoc
     */
    public function triggers(string $table): array
    {
        $triggers = [];
        $options = $this->triggerOptions();
        $query = "SELECT * FROM sqlite_master WHERE type = 'trigger' AND tbl_name = " . $this->_engine()->quote($table);
        foreach ($this->_engine()->rows($query) as $row) {
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
