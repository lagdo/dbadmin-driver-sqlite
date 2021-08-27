<?php

namespace Lagdo\Adminer\Driver\Sqlite\Pdo;

use Lagdo\Adminer\Driver\Db\Pdo\Connection as PdoConnection;
use Lagdo\Adminer\Driver\Sqlite\ConnectionTrait;

class Connection extends PdoConnection
{
    use ConnectionTrait;

    /**
     * @inheritDoc
     */
    public function open($filename, array $options)
    {
        $this->dsn("sqlite:$filename", "", "");
    }
}
