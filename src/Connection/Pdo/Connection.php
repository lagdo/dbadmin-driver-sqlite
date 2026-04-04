<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Connection\Pdo;

use Lagdo\DbAdmin\Support\Db\Engine\Connection\Pdo\AbstractConnection;
use Lagdo\DbAdmin\Support\Sqlite\Connection\Traits\ConfigTrait;
use Lagdo\DbAdmin\Support\Sqlite\Connection\Traits\ConnectionTrait;

class Connection extends AbstractConnection
{
    use ConfigTrait;
    use ConnectionTrait;

    /**
     * @inheritDoc
     */
    public function open(string $database, string $schema = ''): bool
    {
        $dsn = 'sqlite:' . $this->filename($database, $this->options);
        if (!$this->dsn($dsn, '', '')) {
            return false;
        }

        $this->query('PRAGMA foreign_keys = 1');
        return true;
    }
}
