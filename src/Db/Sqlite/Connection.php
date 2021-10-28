<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Sqlite;

use Lagdo\DbAdmin\Driver\Db\Connection as AbstractConnection;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConfigTrait;

use SQLite3;

class Connection extends AbstractConnection
{
    use ConfigTrait;

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
        } elseif ($result->numColumns() > 0) {
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

    public function multiQuery(string $query)
    {
        $this->statement = $this->query($query);
        return !(!$this->statement);
    }

    /**
     * @inheritDoc
     */
    public function storedResult()
    {
        return $this->statement;
    }

    public function nextResult()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function result(string $query, int $field = -1)
    {
        if ($field < 0) {
            $field = $this->defaultField();
        }
        $result = $this->query($query);
        if (!is_object($result)) {
            return null;
        }
        $row = $result->fetchRow();
        return is_array($row) && count($row) > $field ? $row[$field] : null;
    }
}
