<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Tests;

use Lagdo\DbAdmin\Driver\Tests\Connection;
use Lagdo\DbAdmin\Driver\Sqlite\Driver as SqliteDriver;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Server;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Database;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Table;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Query;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Grammar;

class Driver extends SqliteDriver
{
    /**
     * @inheritDoc
     */
    public function createConnection()
    {
        $this->connection = new Connection($this, $this->util, $this->trans, 'test');
        $this->server = new Server($this, $this->util, $this->trans, $this->connection);
        $this->database = new Database($this, $this->util, $this->trans, $this->connection);
        $this->table = new Table($this, $this->util, $this->trans, $this->connection);
        $this->query = new Query($this, $this->util, $this->trans, $this->connection);
        $this->grammar = new Grammar($this, $this->util, $this->trans, $this->connection);

        return $this->connection;
    }
}
