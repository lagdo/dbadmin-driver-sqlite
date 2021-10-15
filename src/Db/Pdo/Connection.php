<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Pdo;

use Lagdo\DbAdmin\Driver\Db\Pdo\Connection as PdoConnection;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConnectionTrait;

class Connection extends PdoConnection
{
    use ConnectionTrait;

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
