<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Tests;

use Lagdo\DbAdmin\Driver\Utils\History;
use Lagdo\DbAdmin\Driver\Utils\Str;
use Lagdo\DbAdmin\Driver\Utils\Utils;
use Lagdo\DbAdmin\Driver\Driver as AbstractDriver;
use Lagdo\DbAdmin\Driver\Utils\Input;
use Lagdo\DbAdmin\Driver\Fake\DriverTrait;
use Lagdo\DbAdmin\Driver\Fake\Translator;
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

    /**
     * The constructor
     */
    public function __construct()
    {
        $utils = new Utils(new Translator(), new Input(), new Str(), new History());
        parent::__construct($utils, [
            'directory' => __DIR__ . '/databases',
        ]);
    }

    /*
     * @inheritDoc
     */
    // protected function createConnection()
    // {
    //     if ($this->realConnection) {
    //         return parent::createConnection();
    //     }
    //     $this->testConnection = new Connection($this, $this->utils, 'test');
    //     $this->connection = $this->testConnection;
    //     $this->server = new Server($this, $this->utils);
    //     $this->database = new Database($this, $this->utils);
    //     $this->table = new Table($this, $this->utils);
    //     $this->query = new Query($this, $this->utils);
    //     $this->grammar = new Grammar($this, $this->utils);

    //     return $this->connection;
    // }

    /*
     * @inheritDoc
     */
    /*public function connect(string $database, string $schema)
    {
        AbstractDriver::connect($database, $schema);
    }*/
}
