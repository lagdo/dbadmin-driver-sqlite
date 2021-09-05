<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Sqlite;

use Lagdo\DbAdmin\Driver\Db\StatementInterface;

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

    public function __construct($result)
    {
        $this->result = $result;
        $this->numRows = $result->numColumns();
    }

    public function fetchAssoc()
    {
        return $this->result->fetchArray(SQLITE3_ASSOC);
    }

    public function fetchRow()
    {
        return $this->result->fetchArray(SQLITE3_NUM);
    }

    public function fetchField()
    {
        $column = $this->offset++;
        $type = $this->result->columnType($column);
        return (object) array(
            "name" => $this->result->columnName($column),
            "type" => $type,
            "charsetnr" => ($type == SQLITE3_BLOB ? 63 : 0), // 63 - binary
        );
    }

    public function __destruct()
    {
        return $this->result->finalize();
    }
}
