<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\Entity\TableFieldEntity;
use Lagdo\DbAdmin\Driver\Entity\TableEntity;
use Lagdo\DbAdmin\Driver\Entity\IndexEntity;
use Lagdo\DbAdmin\Driver\Entity\ForeignKeyEntity;
use Lagdo\DbAdmin\Driver\Entity\TriggerEntity;
use Lagdo\DbAdmin\Driver\Entity\RoutineEntity;

use Lagdo\DbAdmin\Driver\Db\ConnectionInterface;

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
            $connection = new Db\Sqlite\Connection($this, $this->util, 'SQLite3');
        }
        elseif (extension_loaded("pdo_sqlite")) {
            $connection = new Db\Pdo\Connection($this, $this->util, 'PDO_SQLite');
        }
        else {
            throw new AuthException($this->util->lang('No package installed to open a Sqlite database.'));
        }

        if ($this->connection === null) {
            $this->connection = $connection;
            // By default, connect to the in memory database.
            $this->connection->open(':memory:', $this->options());
            $this->server = new Db\Server($this, $this->util, $connection);
            $this->table = new Db\Table($this, $this->util, $connection);
            $this->query = new Db\Query($this, $this->util, $connection);
            $this->grammar = new Db\Grammar($this, $this->util, $connection);
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
    protected function setConfig()
    {
        $this->config->jush = 'sqlite';
        $this->config->drivers = ["SQLite3", "PDO_SQLite"];

        $types = [ //! arrays
            $this->util->lang('Numbers') => ["integer" => 0, "real" => 0, "numeric" => 0],
            $this->util->lang('Strings') => ["text" => 0],
            $this->util->lang('Binary') => ["blob" => 0],
        ];
        foreach ($types as $group => $_types) {
            $this->config->structuredTypes[$group] = array_keys($_types);
            $this->config->types = array_merge($this->config->types, $_types);
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
