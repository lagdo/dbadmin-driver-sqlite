<?php

namespace Lagdo\DbAdmin\Driver\Sqlite;

use Lagdo\DbAdmin\Driver\AbstractDriver;
use Lagdo\DbAdmin\Driver\Db\AbstractConnection;
use Lagdo\DbAdmin\Driver\Exception\AuthException;

use function array_keys;
use function class_exists;
use function extension_loaded;

class Driver extends AbstractDriver
{
    /**
     * @var Db\Server|null
     */
    private Db\Server|null $server = null;

    /**
     * @var Db\Database|null
     */
    private Db\Database|null $database = null;

    /**
     * @var Db\Table|null
     */
    private Db\Table|null $table = null;

    /**
     * @var Db\Query|null
     */
    private Db\Query|null $query = null;

    /**
     * @var Db\Grammar|null
     */
    private Db\Grammar|null $grammar = null;

    /**
     * @return Db\Server
     */
    protected function _server(): Db\Server
    {
        return $this->server ?: $this->server = new Db\Server($this, $this->utils);
    }

    /**
     * @return Db\Database
     */
    protected function _database(): Db\Database
    {
        return $this->database ?: $this->database = new Db\Database($this, $this->utils);
    }

    /**
     * @return Db\Table
     */
    protected function _table(): Db\Table
    {
        return $this->table ?: $this->table = new Db\Table($this, $this->utils);
    }

    /**
     * @return Db\Grammar
     */
    protected function _grammar(): Db\Grammar
    {
        return $this->grammar ?: $this->grammar = new Db\Grammar($this, $this->utils);
    }

    /**
     * @return Db\Query
     */
    protected function _query(): Db\Query
    {
        return $this->query ?: $this->query = new Db\Query($this, $this->utils);
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
            return new Db\Sqlite\Connection($this, $this->utils, $options, 'SQLite3');
        }
        if (extension_loaded("pdo_sqlite")) {
            return new Db\Pdo\Connection($this, $this->utils, $options, 'PDO_SQLite');
        }
        throw new AuthException($this->utils->trans->lang('No package installed to open a Sqlite database.'));
    }

    /**
     * @return array
     */
    public function structuredTypes(): array
    {
        return array_keys($this->config->types[0]);
    }
}
