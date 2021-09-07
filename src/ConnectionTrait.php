<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

trait ConnectionTrait
{
    // public function __construct() {
    //     parent::__construct(":memory:");
    //     $this->query("PRAGMA foreign_keys = 1");
    // }

    public function selectDatabase($database)
    {
        $this->open($database, $this->db->options());
        return true;
    }

    public function multiQuery($query)
    {
        return $this->result = $this->query($query);
    }

    public function nextResult()
    {
        return false;
    }
}
