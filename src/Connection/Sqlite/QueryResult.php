<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Connection\Sqlite;

use Lagdo\DbAdmin\Driver\Sql\Connection\QueryResultInterface;
use Lagdo\DbAdmin\Driver\Sql\Dto\ResultColumnDto;
use SQLite3Result;

use function is_bool;

class QueryResult implements QueryResultInterface
{
    /**
     * The query result
     *
     * @var SQLite3Result|null
     */
    private SQLite3Result|null $result = null;

    /**
     * @var bool
     */
    private bool $hasError;

    /**
     * Undocumented variable
     *
     * @var int
     */
    private $columnOffset = 0;

    /**
     * The constructor
     *
     * @param SQLite3Result|bool $result
     */
    public function __construct(SQLite3Result|bool $result)
    {
        $isBool = is_bool($result);
        $this->hasError = $isBool ? !$result : false;
        if (!$isBool) {
            $this->result = $result;
        }
    }

    /**
     * @inheritDoc
     */
    public function hasError(): bool
    {
        return $this->hasError;
    }

    /**
     * @inheritDoc
     */
    public function hasRowset(): bool
    {
        return $this->result !== null;
    }

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
        if ($this->result === null) {
            return 0;
        }

        // Todo: find a simpler way to count the rows.
        $rowCount = 0;
        $this->result->reset();
        while ($this->result->fetchArray()) {
            $rowCount++;
        }
        $this->result->reset();
        return $rowCount;
    }

    /**
     * @inheritDoc
     */
    public function fetchAssoc(): array|null
    {
        return $this->result?->fetchArray(SQLITE3_ASSOC) ?: null;
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(): array|null
    {
        return $this->result?->fetchArray(SQLITE3_NUM) ?: null;
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn(): ResultColumnDto|null
    {
        if ($this->result === null) {
            return null;
        }

        $column = $this->columnOffset++;
        $type = $this->result->columnType($column);
        $name = $this->result->columnName($column);
        return !$type || !$name ? null :
            new ResultColumnDto($type, $type === SQLITE3_BLOB, $name, $name);
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        if ($this->result !== null) {
            $this->result->finalize();
            $this->result = null;
        }
    }
}
