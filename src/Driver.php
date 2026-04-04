<?php

namespace Lagdo\DbAdmin\Support\Sqlite;

use Lagdo\DbAdmin\Support\AbstractDriver;
use Lagdo\DbAdmin\Support\Db\Engine\Driver\AbstractConnection;
use Lagdo\DbAdmin\Support\Exception\AuthException;

use function array_keys;
use function class_exists;
use function extension_loaded;

class Driver extends AbstractDriver
{
    /**
     * @var Grammar|null;
     */
    private Grammar|null $grammar = null;

    /**
     * @var Driver\Server|null
     */
    private Driver\Server|null $server = null;

    /**
     * @var Driver\Database|null
     */
    private Driver\Database|null $database = null;

    /**
     * @var Driver\Table|null
     */
    private Driver\Table|null $table = null;

    /**
     * @var Driver\Query|null
     */
    private Driver\Query|null $query = null;

    /**
     * @return Grammar
     */
    public function grammar(): Grammar
    {
        return $this->grammar ??= new Grammar($this, $this->utils);
    }

    /**
     * @return Driver\Server
     */
    protected function _server(): Driver\Server
    {
        return $this->server ??= new Driver\Server($this, $this->grammar(), $this->utils);
    }

    /**
     * @return Driver\Database
     */
    protected function _database(): Driver\Database
    {
        return $this->database ??= new Driver\Database($this, $this->grammar(), $this->utils);
    }

    /**
     * @return Driver\Table
     */
    protected function _table(): Driver\Table
    {
        return $this->table ??= new Driver\Table($this, $this->grammar(), $this->utils);
    }

    /**
     * @return Driver\Query
     */
    protected function _query(): Driver\Query
    {
        return $this->query ??= new Driver\Query($this, $this->grammar(), $this->utils);
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return "SQLite 3";
    }

    /**
     * @inheritDoc
     */
    protected function beforeConnection(): void
    {
        // Init config
        $this->config->jush = 'sqlite';
        $this->config->drivers = ["SQLite3", "PDO_SQLite"];
        $this->config->types = [["integer" => 0, "real" => 0, "numeric" => 0, "text" => 0, "blob" => 0]];
        // $this->config->unsigned = [];
        $this->config->operators = ["=", "<", ">", "<=", ">=", "!=", "LIKE", "LIKE %%",
            "IN", "IS NULL", "NOT LIKE", "NOT IN", "IS NOT NULL", "SQL"]; // REGEXP can be user defined function;
        $this->config->functions = ["hex", "length", "lower", "round", "unixepoch", "upper"];
        $this->config->grouping = ["avg", "count", "count distinct", "group_concat", "max", "min", "sum"];
        $this->config->insertFunctions = [
            // "text" => ["date('now')", "time('now')", "datetime('now')"],
        ];
        $this->config->editFunctions = [
            "integer|real|numeric" => ["+", "-"],
            // "text" => ["date", "time", "datetime"],
            "text" => ["||"],
        ];
        $this->config->features = ['columns', 'database', 'drop_col', 'dump', 'indexes', 'descidx',
            'move_col', 'sql', 'status', 'table', 'trigger', 'variables', 'view', 'view_trigger'];

        // Regex to parse SQL statements in a text
        $this->config->sqlStatementRegex = '\\s*|[\'"`[]|/\*|-- |$';
    }

    /**
     * @inheritDoc
     */
    protected function configConnection(): void
    {
        if ($this->minVersion(3.31, 0)) {
            $this->config->generated = ["STORED", "VIRTUAL"];
        }
    }

    /**
     * @inheritDoc
     */
    protected function connectionOpened(): void
    {
        $this->_server()->setConnection($this->connection);
    }

    /**
     * @inheritDoc
     * @throws AuthException
     */
    public function createConnection(array $options): AbstractConnection|null
    {
        $preferPdo = $options['prefer_pdo'] ?? false;
        if (!$preferPdo && class_exists("SQLite3")) {
            return new Connection\Sqlite\Connection($this,
                $this->grammar(), $this->utils, $options, 'SQLite3');
        }
        if (extension_loaded("pdo_sqlite")) {
            return new Connection\Pdo\Connection($this,
                $this->grammar(), $this->utils, $options, 'PDO_SQLite');
        }
        throw new AuthException($this->utils->trans
            ->lang('No package installed to open a Sqlite database.'));
    }

    /**
     * @return array
     */
    public function structuredTypes(): array
    {
        return array_keys($this->config->types[0]);
    }
}
