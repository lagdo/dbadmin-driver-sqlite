<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Sqlite;

use Lagdo\DbAdmin\Driver\Db\Connection as AbstractConnection;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConnectionTrait;

use SQLite3;

class Connection extends AbstractConnection
{
    use ConnectionTrait;

    /**
     * @inheritDoc
     */
    public function open(string $database, string $schema = '')
    {
        $options = $this->driver->options();
        $filename = $this->filename($database, $options);
        $this->client = new SQLite3($filename);
        $this->query("PRAGMA foreign_keys = 1");
        return true;
    }

    /**
     * @inheritDoc
     */
    public function serverInfo()
    {
        $version = SQLite3::version();
        return $version["versionString"];
    }

    /**
     * @inheritDoc
     */
    public function query(string $query, bool $unbuffered = false)
    {
        $result = @$this->client->query($query);
        $this->driver->setError();
        if (!$result) {
            $this->driver->setErrno($this->client->lastErrorCode());
            $this->driver->setError($this->client->lastErrorMsg());
            return false;
        } elseif ($result->numColumns()) {
            return new Statement($result);
        }
        $this->driver->setAffectedRows($this->client->changes());
        return true;
    }

    /**
     * @inheritDoc
     */
    public function quote(string $string)
    {
        return ($this->util->isUtf8($string) ?
            "'" . $this->client->escapeString($string) . "'" :
            "x'" . reset(unpack('H*', $string)) . "'");
    }

    /**
     * @inheritDoc
     */
    public function storedResult()
    {
        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function result(string $query, int $field = 0)
    {
        $result = $this->query($query);
        if (!is_object($result)) {
            return false;
        }
        $row = $result->result->fetchArray();
        return is_array($row) && array_key_exists($field, $row) ? $row[$field] : false;
    }
}
