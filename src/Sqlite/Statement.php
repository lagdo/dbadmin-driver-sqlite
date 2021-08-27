<?php

namespace Lagdo\Adminer\Driver\Sqlite\Sqlite;

class Statement
{
    /**
     * Undocumented variable
     *
     * @var object
     */
    public $_result;

    /**
     * Undocumented variable
     *
     * @var int
     */
    public $_offset = 0;

    /**
     * Undocumented variable
     *
     * @var int
     */
    public $num_rows;

    public function __construct($result)
    {
        $this->_result = $result;
    }

    public function fetch_assoc()
    {
        return $this->_result->fetchArray(SQLITE3_ASSOC);
    }

    public function fetch_row()
    {
        return $this->_result->fetchArray(SQLITE3_NUM);
    }

    public function fetch_field()
    {
        $column = $this->_offset++;
        $type = $this->_result->columnType($column);
        return (object) array(
            "name" => $this->_result->columnName($column),
            "type" => $type,
            "charsetnr" => ($type == SQLITE3_BLOB ? 63 : 0), // 63 - binary
        );
    }

    public function __desctruct()
    {
        return $this->_result->finalize();
    }
}
