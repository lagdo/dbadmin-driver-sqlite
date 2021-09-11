<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

trait ConnectionTrait
{
    use ConfigTrait;

    public function selectDatabase($database)
    {
        $options = $this->driver->options();
        $filename = $this->filename($database, $options);
        $opened = $this->open($filename, $options);
        if ($opened) {
            $this->query("PRAGMA foreign_keys = 1");
        }
        return $opened;
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
