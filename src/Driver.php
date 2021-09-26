<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Exception\AuthException;

use Lagdo\DbAdmin\Driver\Driver as AbstractDriver;

class Driver extends AbstractDriver
{
    /**
     * Driver features
     *
     * @var array
     */
    private $features = ['columns', 'database', 'drop_col', 'dump', 'indexes', 'descidx',
        'move_col', 'sql', 'status', 'table', 'trigger', 'variables', 'view', 'view_trigger'];

    /**
     * Data types
     *
     * @var array
     */
    private $types = [ //! arrays
        'Numbers' => ["integer" => 0, "real" => 0, "numeric" => 0],
        'Strings' => ["text" => 0],
        'Binary' => ["blob" => 0],
    ];

    /**
     * Number variants
     *
     * @var array
     */
    private $unsigned = [];

    /**
     * Operators used in select
     *
     * @var array
     */
    private $operators = ["=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%",
        "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"]; // REGEXP can be user defined function;

    /**
     * Functions used in select
     *
     * @var array
     */
    private $functions = ["hex", "length", "lower", "round", "unixepoch", "upper"];

    /**
     * Grouping functions used in select
     *
     * @var array
     */
    private $grouping = ["avg", "count", "count distinct", "group_concat", "max", "min", "sum"];

    /**
     * Functions used to edit data
     *
     * @var array
     */
    private $editFunctions = [[
        // "text" => "date('now')/time('now')/datetime('now')",
    ],[
        "integer|real|numeric" => "+/-",
        // "text" => "date/time/datetime",
        "text" => "||",
    ]];

    /**
     * @inheritDoc
     */
    public function name()
    {
        return "SQLite 3";
    }

    /**
     * @inheritDoc
     */
    public function createConnection()
    {
        $connection = null;
        if (class_exists("SQLite3")) {
            $connection = new Db\Sqlite\Connection($this, $this->util, $this->trans, 'SQLite3');
        }
        elseif (extension_loaded("pdo_sqlite")) {
            $connection = new Db\Pdo\Connection($this, $this->util, $this->trans, 'PDO_SQLite');
        }
        else {
            throw new AuthException($this->trans->lang('No package installed to open a Sqlite database.'));
        }

        if ($this->connection === null) {
            $this->connection = $connection;
            $this->server = new Db\Server($this, $this->util, $this->trans, $connection);
            $this->table = new Db\Table($this, $this->util, $this->trans, $connection);
            $this->query = new Db\Query($this, $this->util, $this->trans, $connection);
            $this->grammar = new Db\Grammar($this, $this->util, $this->trans, $connection);
        }

        return $connection;
    }

    /**
     * @inheritDoc
     */
    public function connect(string $database, string $schema)
    {
        parent::connect($database, $schema);
    }

    /**
     * @inheritDoc
     */
    public function support(string $feature)
    {
        return in_array($feature, $this->features);
    }

    /**
     * @inheritDoc
     */
    protected function initConfig()
    {
        $this->config->jush = 'sqlite';
        $this->config->drivers = ["SQLite3", "PDO_SQLite"];

        foreach ($this->types as $group => $types) {
            $this->config->structuredTypes[$this->trans->lang($group)] = array_keys($types);
            $this->config->types = array_merge($this->config->types, $types);
        }

        // $this->config->unsigned = [];
        $this->config->operators = $this->operators;
        $this->config->functions = $this->functions;
        $this->config->grouping = $this->grouping;
        $this->config->editFunctions = $this->editFunctions;
    }
}
