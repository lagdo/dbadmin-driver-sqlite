<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Exception\AuthException;
use Lagdo\DbAdmin\Driver\Driver as AbstractDriver;
use Lagdo\DbAdmin\Driver\TranslatorInterface;
use Lagdo\DbAdmin\Driver\UtilInterface;

use function class_exists;
use function extension_loaded;

class Driver extends AbstractDriver
{
    /**
     * The constructor
     *
     * @param UtilInterface $util
     * @param TranslatorInterface $trans
     * @param array $options
     */
    public function __construct(UtilInterface $util, TranslatorInterface $trans, array $options)
    {
        parent::__construct($util, $trans, $options);

        $this->server = new Db\Server($this, $this->util, $this->trans);
        $this->database = new Db\Database($this, $this->util, $this->trans);
        $this->table = new Db\Table($this, $this->util, $this->trans);
        $this->query = new Db\Query($this, $this->util, $this->trans);
        $this->grammar = new Db\Grammar($this, $this->util, $this->trans);
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
    protected function initConfig()
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
    protected function postConnectConfig()
    {}

    /**
     * @inheritDoc
     * @throws AuthException
     */
    protected function createConnection()
    {
        if (!$this->options('prefer_pdo', false) && class_exists("SQLite3")) {
            $connection = new Db\Sqlite\Connection($this, $this->util, $this->trans, 'SQLite3');
            return $this->connection = $connection;
        }
        if (extension_loaded("pdo_sqlite")) {
            $connection = new Db\Pdo\Connection($this, $this->util, $this->trans, 'PDO_SQLite');
            return $this->connection = $connection;
        }
        throw new AuthException($this->trans->lang('No package installed to open a Sqlite database.'));
    }
}
