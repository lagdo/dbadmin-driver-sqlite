<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Tests;

use Lagdo\DbAdmin\Driver\Driver as AbstractDriver;
use Lagdo\DbAdmin\Driver\Input;
use Lagdo\DbAdmin\Driver\Fake\DriverTrait;
use Lagdo\DbAdmin\Driver\Fake\Translator;
use Lagdo\DbAdmin\Driver\Fake\Util;
use Lagdo\DbAdmin\Driver\Fake\Connection;

use Lagdo\DbAdmin\Driver\Sqlite\Driver as SqliteDriver;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Server;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Database;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Table;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Query;
use Lagdo\DbAdmin\Driver\Sqlite\Db\Grammar;

class Driver extends SqliteDriver
{
    use DriverTrait;

    public $realConnection = false;

    /**
     * The constructor
     */
    public function __construct()
    {
        $input = new Input();
        $trans = new Translator();
        $util = new Util($trans, $input);
        parent::__construct($util, $trans, ['directory' => __DIR__ . '/databases']);
    }

    /**
     * @inheritDoc
     */
    public function createConnection()
    {
        if ($this->realConnection) {
            return parent::createConnection();
        }
        $this->testConnection = new Connection($this, $this->util, $this->trans, 'test');
        $this->connection = $this->testConnection;
        $this->server = new Server($this, $this->util, $this->trans);
        $this->database = new Database($this, $this->util, $this->trans);
        $this->table = new Table($this, $this->util, $this->trans);
        $this->query = new Query($this, $this->util, $this->trans);
        $this->grammar = new Grammar($this, $this->util, $this->trans);

        return $this->connection;
    }

    /**
     * @inheritDoc
     */
    public function connect(string $database, string $schema)
    {
        AbstractDriver::connect($database, $schema);
    }
}
