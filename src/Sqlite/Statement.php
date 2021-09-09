<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Sqlite;

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
    public $result;

    /**
     * Undocumented variable
     *
     * @var int
     */
    public $offset = 0;

    /**
     * Undocumented variable
     *
     * @var int
     */
    public $numRows;

    /**
     * The constructor
     *
     * @param SQLite3Result $result
     */
    public function __construct($result)
    {
        $this->result = $result;
        $this->numRows = $result->numColumns();
    }

    /**
     * @inheritDoc
     */
    public function fetchAssoc()
    {
        return $this->result->fetchArray(SQLITE3_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function fetchRow()
    {
        return $this->result->fetchArray(SQLITE3_NUM);
    }

    /**
     * @inheritDoc
     */
    public function fetchField()
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
        return $this->result->finalize();
    }
}
