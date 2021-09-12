<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\Server as AbstractServer;

use DirectoryIterator;
use Exception;

class Server extends AbstractServer
{
    use ConfigTrait;

    /**
     * The database file extensions
     *
     * @var string
     */
    protected $extensions = "db|sdb|sqlite";

    /**
     * @inheritDoc
     */
    public function databases(bool $flush)
    {
        $databases = [];
        $directory = rtrim($this->driver->options('directory'), '/\\');
        $iterator = new DirectoryIterator($directory);
        // Iterate on dir content
        foreach($iterator as $file)
        {
            // Skip everything except Sqlite files
            if(!$file->isFile() || !$this->checkSqliteName($filename = $file->getFilename()))
            {
                continue;
            }
            $databases[] = $filename;
        }
        return $databases;
    }

    /**
     * @inheritDoc
     */
    public function databaseSize(string $database)
    {
        $options = $this->driver->options();
        $filename = $this->filename($database, $options);
        $connection = $this->driver->createConnection(); // New connection
        $connection->open($filename, $options);
        $pageSize = 0;
        $statement = $connection->query('pragma page_size');
        if (is_object($statement) && ($row = $statement->fetchRow())) {
            $pageSize = intval($row[0]);
        }
        $pageCount = 0;
        $statement = $connection->query('pragma page_count');
        if (is_object($statement) && ($row = $statement->fetchRow())) {
            $pageCount = intval($row[0]);
        }
        return $pageSize * $pageCount;
    }

    /**
     * @inheritDoc
     */
    public function databaseCollation(string $database, array $collations)
    {
        // there is no database list so $database == $this->driver->database()
        return $this->connection->result("PRAGMA encoding");
    }

    /**
     * @inheritDoc
     */
    public function tables()
    {
        return $this->driver->keyValues("SELECT name, type FROM sqlite_master " .
            "WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
    }

    /**
     * @inheritDoc
     */
    public function countTables(array $databases)
    {
        $options = $this->driver->options();
        $connection = $this->driver->createConnection(); // New connection
        $counts = [];
        foreach ($databases as $database) {
            $counts[$database] = 0;
            $filename = $this->filename($database, $options);
            $connection->open($filename, $options);
            $statement = $connection->query("SELECT count(*) FROM sqlite_master WHERE type IN ('table', 'view')");
            if (is_object($statement) && ($row = $statement->fetchRow())) {
                $counts[$database] = intval($row[0]);
            }
        }
        return $counts;
    }

    /**
     * @inheritDoc
     */
    public function collations()
    {
        $create = $this->util->input()->hasTable();
        return ($create) ? $this->driver->values("PRAGMA collation_list", 1) : [];
    }

    /**
     * Validate a name
     *
     * @param string $name
     *
     * @return bool
     */
    private function checkSqliteName(string $name)
    {
        // Avoid creating PHP files on unsecured servers
        return preg_match("~^[^\\0]*\\.({$this->extensions})\$~", $name);
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $database, string $collation)
    {
        $options = $this->driver->options();
        $filename = $this->filename($database, $options);
        if (file_exists($filename)) {
            $this->driver->setError($this->util->lang('File exists.'));
            return false;
        }
        if (!$this->checkSqliteName($filename)) {
            $this->driver->setError($this->util->lang('Please use one of the extensions %s.',
                str_replace("|", ", ", $this->extensions)));
            return false;
        }
        try {
            $connection = $this->driver->createConnection(); // New connection
            $connection->open($filename, $options);
        } catch (Exception $ex) {
            $this->driver->setError($ex->getMessage());
            return false;
        }
        $connection->query('PRAGMA encoding = "UTF-8"');
        $connection->query('CREATE TABLE adminer (i)'); // otherwise creates empty file
        $connection->query('DROP TABLE adminer');
        return true;
    }

    /**
     * @inheritDoc
     */
    public function dropDatabases(array $databases)
    {
        $options = $this->driver->options();
        foreach ($databases as $database) {
            $filename = $this->filename($database, $options);
            if (!@unlink($filename)) {
                $this->driver->setError($this->util->lang('File exists.'));
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function renameDatabase(string $database, string $collation)
    {
        $options = $this->driver->options();
        $filename = $this->filename($database, $options);
        if (!$this->checkSqliteName($filename)) {
            $this->driver->setError($this->util->lang('Please use one of the extensions %s.',
                str_replace("|", ", ", $this->extensions)));
            return false;
        }
        $this->driver->setError($this->util->lang('File exists.'));
        return @rename($this->filename($this->driver->database(), $options), $filename);
    }

    /**
     * @inheritDoc
     */
    public function truncateTables(array $tables)
    {
        return $this->driver->applyQueries("DELETE FROM", $tables);
    }

    /**
     * @inheritDoc
     */
    public function dropViews(array $views)
    {
        return $this->driver->applyQueries("DROP VIEW", $views);
    }

    /**
     * @inheritDoc
     */
    public function dropTables(array $tables)
    {
        return $this->driver->applyQueries("DROP TABLE", $tables);
    }

    /**
     * @inheritDoc
     */
    public function moveTables(array $tables, array $views, string $target)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function variables()
    {
        $variables = [];
        foreach (["auto_vacuum", "cache_size", "count_changes", "default_cache_size", "empty_result_callbacks", "encoding", "foreign_keys", "full_column_names", "fullfsync", "journal_mode", "journal_size_limit", "legacy_file_format", "locking_mode", "page_size", "max_page_count", "read_uncommitted", "recursive_triggers", "reverse_unordered_selects", "secure_delete", "short_column_names", "synchronous", "temp_store", "temp_store_directory", "schema_version", "integrity_check", "quick_check"] as $key) {
            $variables[$key] = $this->connection->result("PRAGMA $key");
        }
        return $variables;
    }

    /**
     * @inheritDoc
     */
    public function statusVariables()
    {
        $variables = [];
        if (!($options = $this->driver->values("PRAGMA compile_options"))) {
            return [];
        }
        foreach ($options as $option) {
            $values = explode("=", $option, 2);
            $variables[$values[0]] = count($values) > 1 ? $values[1] : "true";
        }
        return $variables;
    }
}
