<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Engine;

use Lagdo\DbAdmin\Driver\Exception\DriverException;
use Lagdo\DbAdmin\Driver\Sql\Specific\Engine\AbstractDatabase;
use Lagdo\DbAdmin\Driver\Sqlite\Connection\Traits\ConfigTrait;
use DirectoryIterator;
use Exception;

use function intval;
use function preg_match;
use function str_replace;
use function unlink;

class Database extends AbstractDatabase
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
    public function tables(): array
    {
        return $this->_engine()->keyValues('SELECT name, type FROM sqlite_master ' .
            "WHERE type IN ('table', 'view') ORDER BY (name = 'sqlite_sequence'), name");
    }

    /**
     * @inheritDoc
     */
    public function databases(bool $flush): array
    {
        $databases = [];
        $directory = $this->directory($this->_engine()->options());
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
    public function databaseSize(string $database): int
    {
        $connection = $this->_engine()->openNewConnection($database); // New connection
        if (!$connection) {
            return 0;
        }
        $pageSize = 0;
        $result = $connection->executeQuery('pragma page_size');
        if ($result->hasRowset() && ($row = $result->fetchRow())) {
            $pageSize = intval($row[0]);
        }
        $pageCount = 0;
        $result = $connection->executeQuery('pragma page_count');
        if ($result->hasRowset() && ($row = $result->fetchRow())) {
            $pageCount = intval($row[0]);
        }
        return $pageSize * $pageCount;
    }

    /**
     * @inheritDoc
     */
    public function databaseCollation(string $database, array $collations): string
    {
        // there is no database list so $database == $this->_engine()->database()
        return $this->_engine()->columnValue("PRAGMA encoding");
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
    public function createDatabase(string $database, string $collation): bool
    {
        $options = $this->_engine()->options();
        if ($this->fileExists($database, $options)) {
            throw new DriverException($this->_utils()->lang('File exists.'));
        }
        $filename = $this->filename($database, $options);
        if (!$this->validateName($filename)) {
            throw new DriverException($this->_utils()->lang('Please use one of the extensions %s.',
                str_replace("|", ", ", $this->extensions)));
        }
        try {
            $connection = $this->_engine()->openNewConnection($database, '__create__'); // New connection
            $connection->executeQuery('PRAGMA encoding = "UTF-8"');
            $connection->executeQuery('CREATE TABLE dbadmin (i)'); // otherwise creates empty file
            $connection->executeQuery('DROP TABLE dbadmin');
        } catch (Exception $ex) {
            throw new DriverException($ex->getMessage());
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function dropDatabase(string $database): bool
    {
        $filename = $this->filename($database, $this->_engine()->options());
        if (!@unlink($filename)) {
            throw new DriverException($this->_utils()->lang('File exists.'));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function countTables(array $databases): array
    {
        $counts = [];
        $query = "SELECT count(*) FROM sqlite_master WHERE type IN ('table', 'view')";
        foreach ($databases as $database) {
            $counts[$database] = 0;
            $connection = $this->_engine()->openNewConnection($database);
            $result = $connection->executeQuery($query);
            if ($result->hasRowset() && ($row = $result->fetchRow())) {
                $counts[$database] = intval($row[0]);
            }
        }
        return $counts;
    }
}
