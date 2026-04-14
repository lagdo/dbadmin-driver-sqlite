<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Engine;

use Lagdo\DbAdmin\Driver\Exception\AuthException;
use Lagdo\DbAdmin\Driver\Sql\Connection\AbstractConnection;
use Lagdo\DbAdmin\Driver\Sql\Specific\Engine\AbstractServer;
use Lagdo\DbAdmin\Driver\Sqlite\Connection;

use function class_exists;
use function count;
use function explode;
use function extension_loaded;
use function get_current_user;

class Server extends AbstractServer
{
    /**
     * @var array
     */
    protected $variableNames = ["auto_vacuum", "cache_size", "count_changes", "default_cache_size",
        "empty_result_callbacks", "encoding", "foreign_keys", "full_column_names", "fullfsync",
        "journal_mode", "journal_size_limit", "legacy_file_format", "locking_mode", "page_size",
        "max_page_count", "read_uncommitted", "recursive_triggers", "reverse_unordered_selects",
        "secure_delete", "short_column_names", "synchronous", "temp_store", "temp_store_directory",
        "schema_version", "integrity_check", "quick_check"];

    /**
     * @inheritDoc
     */
    protected function starting(): void
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
    protected function connected(): void
    {
        if ($this->_engine()->minVersion(3.31, 0)) {
            $this->config->generated = ["STORED", "VIRTUAL"];
        }
    }

    /**
     * @inheritDoc
     * @throws AuthException
     */
    public function createConnection(array $options): AbstractConnection|null
    {
        $preferPdo = $options['prefer_pdo'] ?? false;
        if (!$preferPdo && class_exists("SQLite3")) {
            return new Connection\Sqlite\Connection($this->_engine(),
                $this->_statement(), $this->_utils(), $options, 'SQLite3');
        }
        if (extension_loaded("pdo_sqlite")) {
            return new Connection\Pdo\Connection($this->_engine(),
                $this->_statement(), $this->_utils(), $options, 'PDO_SQLite');
        }

        throw new AuthException($this->_utils()->trans
            ->lang('No package installed to open a Sqlite database.'));
    }

    /**
     * @inheritDoc
     */
    public function user(): string
    {
        return get_current_user(); // should return effective user
    }

    /**
     * @inheritDoc
     */
    public function collations(): array
    {
        return $this->_utils()->input->hasTable() ?
            $this->_engine()->values("PRAGMA collation_list", 1) : [];
    }

    /**
     * @inheritDoc
     */
    public function variables(): array
    {
        $variables = [];
        foreach ($this->variableNames as $key) {
            $variables[$key] = $this->_engine()->result("PRAGMA $key");
        }
        return $variables;
    }

    /**
     * @inheritDoc
     */
    public function statusVariables(): array
    {
        $variables = [];
        if (!($options = $this->_engine()->values("PRAGMA compile_options"))) {
            return [];
        }
        foreach ($options as $option) {
            $values = explode("=", $option, 2);
            $variables[$values[0]] = count($values) > 1 ? $values[1] : "true";
        }
        return $variables;
    }
}
