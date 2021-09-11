<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Pdo;

use Lagdo\DbAdmin\Driver\Db\Pdo\Connection as PdoConnection;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConnectionTrait;

use PDO;

class Connection extends PdoConnection
{
    use ConnectionTrait;

    /**
     * @inheritDoc
     */
    public function open(string $filename, array $options)
    {
        $this->dsn("sqlite:$filename", "", "");
        return true;
    }
}
