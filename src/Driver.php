<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Exception\AuthException;

use Lagdo\DbAdmin\Driver\Driver as AbstractDriver;

class Driver extends AbstractDriver
{
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
            // By default, connect to the in memory database.
            $this->connection->open(':memory:', $this->options());
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
    public function support(string $feature)
    {
        return preg_match('~^(columns|database|drop_col|dump|indexes|descidx|move_col|sql|status|table|trigger|variables|view|view_trigger)$~', $feature);
    }

    /**
     * @inheritDoc
     */
    protected function initConfig()
    {
        $this->config->jush = 'sqlite';
        $this->config->drivers = ["SQLite3", "PDO_SQLite"];

        $groups = [ //! arrays
            $this->trans->lang('Numbers') => ["integer" => 0, "real" => 0, "numeric" => 0],
            $this->trans->lang('Strings') => ["text" => 0],
            $this->trans->lang('Binary') => ["blob" => 0],
        ];
        foreach ($groups as $name => $types) {
            $this->config->structuredTypes[$name] = array_keys($types);
            $this->config->types = array_merge($this->config->types, $types);
        }

        // $this->config->unsigned = [];
        $this->config->operators = ["=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%", "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"]; // REGEXP can be user defined function;
        $this->config->functions = ["hex", "length", "lower", "round", "unixepoch", "upper"];
        $this->config->grouping = ["avg", "count", "count distinct", "group_concat", "max", "min", "sum"];
        $this->config->editFunctions = [[
            // "text" => "date('now')/time('now')/datetime('now')",
        ],[
            "integer|real|numeric" => "+/-",
            // "text" => "date/time/datetime",
            "text" => "||",
        ]];
    }
}
