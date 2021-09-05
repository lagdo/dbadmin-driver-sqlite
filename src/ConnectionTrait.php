<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

trait ConnectionTrait
{
    // public function __construct() {
    //     parent::__construct(":memory:");
    //     $this->query("PRAGMA foreign_keys = 1");
    // }

    public function selectDatabase($filename)
    {
        // Only one database at once on this version.
        return true;

        // if (is_readable($filename) && $this->query("ATTACH " .
        //     $this->quote(preg_match("~(^[/\\\\]|:)~", $filename) ? $filename :
        //     dirname($_SERVER["SCRIPT_FILENAME"]) . "/$filename") . " AS a")) { // is_readable - SQLite 3
        //     $this->query("PRAGMA foreign_keys = 1");
        //     $this->query("PRAGMA busy_timeout = 500");
        //     return true;
        // }
        // return false;
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
