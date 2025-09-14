<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Exception\DbException;
use Lagdo\DbAdmin\Driver\Db\Server as AbstractServer;
use Lagdo\DbAdmin\Driver\Db\StatementInterface;

use DirectoryIterator;
use Exception;

use function is_a;
use function count;
use function rtrim;
use function intval;
use function preg_match;
use function file_exists;
use function str_replace;
use function unlink;
use function explode;
use function rename;

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
    public function databases(bool $flush)
    {
        $databases = [];
        $directory = $this->directory($this->driver->options());
        $iterator = new DirectoryIterator($directory);
        // Iterate on dir content
        foreach($iterator as $file)
        {
            // Skip everything except Sqlite files
            if(!$file->isFile() || !$this->validateName($filename = $file->getFilename()))
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
        $connection = $this->driver->newConnection($database); // New connection
        if (!$connection) {
            return 0;
        }
        $pageSize = 0;
        $statement = $connection->query('pragma page_size');
        if (is_a($statement, StatementInterface::class) && ($row = $statement->fetchRow())) {
            $pageSize = intval($row[0]);
        }
        $pageCount = 0;
        $statement = $connection->query('pragma page_count');
        if (is_a($statement, StatementInterface::class) && ($row = $statement->fetchRow())) {
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
        return $this->driver->result("PRAGMA encoding");
    }

    /**
     * @inheritDoc
     */
    public function collations()
    {
        $create = $this->utils->input->hasTable();
        return ($create) ? $this->driver->values("PRAGMA collation_list", 1) : [];
    }

    /**
     * Validate a name
     *
     * @param string $name
     *
     * @return bool
     */
    private function validateName(string $name)
    {
        // Avoid creating PHP files on unsecured servers
        return preg_match("~^[^\\0]*\\.({$this->extensions})\$~", $name) > 0;
    }

    /**
     * @inheritDoc
     */
    public function createDatabase(string $database, string $collation)
    {
        $options = $this->driver->options();
        if ($this->fileExists($database, $options)) {
            throw new DbException($this->utils->trans->lang('File exists.'));
        }
        $filename = $this->filename($database, $options);
        if (!$this->validateName($filename)) {
            throw new DbException($this->utils->trans->lang('Please use one of the extensions %s.',
                str_replace("|", ", ", $this->extensions)));
        }
        try {
            $connection = $this->driver->newConnection($database, '__create__'); // New connection
            $connection->query('PRAGMA encoding = "UTF-8"');
            $connection->query('CREATE TABLE dbadmin (i)'); // otherwise creates empty file
            $connection->query('DROP TABLE dbadmin');
        } catch (Exception $ex) {
            throw new DbException($ex->getMessage());
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $database)
    {
        $filename = $this->filename($database, $this->driver->options());
        if (!@unlink($filename)) {
            throw new DbException($this->utils->trans->lang('File exists.'));
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
        if (!$this->validateName($filename)) {
            throw new DbException($this->utils->trans->lang('Please use one of the extensions %s.',
                str_replace("|", ", ", $this->extensions)));
        }
        return @rename($this->filename($this->driver->database(), $options), $filename);
    }

    /**
     * @inheritDoc
     */
    public function variables()
    {
        $variables = [];
        foreach ($this->variableNames as $key) {
            $variables[$key] = $this->driver->result("PRAGMA $key");
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
