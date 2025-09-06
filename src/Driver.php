<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Exception\AuthException;
use Lagdo\DbAdmin\Driver\Utils\Utils;
use Lagdo\DbAdmin\Driver\Driver as AbstractDriver;

use function class_exists;
use function extension_loaded;

class Driver extends AbstractDriver
{
    /**
     * The constructor
     *
     * @param Utils $utils
     * @param array $options
     */
    public function __construct(Utils $utils, array $options)
    {
        parent::__construct($utils, $options);

        $this->server = new Db\Server($this, $this->utils);
        $this->database = new Db\Database($this, $this->utils);
        $this->table = new Db\Table($this, $this->utils);
        $this->query = new Db\Query($this, $this->utils);
        $this->grammar = new Db\Grammar($this, $this->utils);
    }

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
    protected function beforeConnection()
    {
        // Init config
        $this->config->jush = 'sqlite';
        $this->config->drivers = ["SQLite3", "PDO_SQLite"];
        $this->config->setTypes([ //! arrays
            'Numbers' => ["integer" => 0, "real" => 0, "numeric" => 0],
            'Strings' => ["text" => 0],
            'Binary' => ["blob" => 0],
        ]);
        // $this->config->unsigned = [];
        $this->config->operators = ["=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%",
            "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"]; // REGEXP can be user defined function;
        $this->config->functions = ["hex", "length", "lower", "round", "unixepoch", "upper"];
        $this->config->grouping = ["avg", "count", "count distinct", "group_concat", "max", "min", "sum"];
        $this->config->editFunctions = [[
            // "text" => "date('now')/time('now')/datetime('now')",
        ],[
            "integer|real|numeric" => "+/-",
            // "text" => "date/time/datetime",
            "text" => "||",
        ]];
        $this->config->features = ['columns', 'database', 'drop_col', 'dump', 'indexes', 'descidx',
            'move_col', 'sql', 'status', 'table', 'trigger', 'variables', 'view', 'view_trigger'];
    }

    /**
     * @inheritDoc
     */
    protected function afterConnection()
    {}

    /**
     * @inheritDoc
     * @throws AuthException
     */
    public function createConnection(array $options)
    {
        if (!$this->options('prefer_pdo', false) && class_exists("SQLite3")) {
            $connection = new Db\Sqlite\Connection($this, $this->utils, $options, 'SQLite3');
            return $this->connection = $connection;
        }
        if (extension_loaded("pdo_sqlite")) {
            $connection = new Db\Pdo\Connection($this, $this->utils, $options, 'PDO_SQLite');
            return $this->connection = $connection;
        }
        throw new AuthException($this->utils->trans->lang('No package installed to open a Sqlite database.'));
    }
}
