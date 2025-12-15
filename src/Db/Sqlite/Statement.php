<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Sqlite;

use Lagdo\DbAdmin\Driver\Db\StatementInterface;
use Lagdo\DbAdmin\Driver\Entity\StatementFieldEntity;
use SQLite3Result;

class Statement implements StatementInterface
{
    /**
     * The query result
     *
     * @var SQLite3Result
     */
    protected $result = null;

    /**
     * Undocumented variable
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * The constructor
     *
     * @param SQLite3Result $result
     */
    public function __construct(SQLite3Result $result)
    {
        $this->result = $result;
    }

    /**
     * @inheritDoc
     */
    public function rowCount(): int
    {
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
        return $this->result->fetchArray(SQLITE3_ASSOC) ?: null;
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(): array|null
    {
        return $this->result->fetchArray(SQLITE3_NUM) ?: null;
    }

    /**
     * @inheritDoc
     */
    public function fetchField(): StatementFieldEntity|null
    {
        $column = $this->offset++;
        $type = $this->result->columnType($column);
        $name = $this->result->columnName($column);
        return new StatementFieldEntity($type, $type === SQLITE3_BLOB, $name, $name);
    }

    /**
     * The destructor
     */
    public function __destruct()
    {
        $this->result->finalize();
    }
}
