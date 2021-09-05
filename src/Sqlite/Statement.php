<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Sqlite;

use SQLite3Result;

class Statement
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
    }

    public function fetch_assoc()
    {
        return $this->result->fetchArray(SQLITE3_ASSOC);
    }

    public function fetch_row()
    {
        return $this->result->fetchArray(SQLITE3_NUM);
    }

    public function fetch_field()
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
