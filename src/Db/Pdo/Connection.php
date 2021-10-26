<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Pdo;

use Lagdo\DbAdmin\Driver\Db\Pdo\Connection as PdoConnection;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConfigTrait;

class Connection extends PdoConnection
{
    use ConfigTrait;

    public function multiQuery(string $query)
    {
        return $this->result = $this->query($query);
    }

    public function nextResult()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function open(string $database, string $schema = '')
    {
        $options = $this->driver->options();
        $filename = $this->filename($database, $options);
        $this->dsn("sqlite:$filename", "", "");
        $this->query("PRAGMA foreign_keys = 1");
        return true;
    }
}
