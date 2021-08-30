<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Sqlite;

use Lagdo\DbAdmin\Driver\Db\Connection as AbstractConnection;
use Lagdo\DbAdmin\Driver\Sqlite\ConnectionTrait;

use SQLite3;

class Connection extends AbstractConnection
{
    use ConnectionTrait;

    /**
     * @inheritDoc
     */
    public function open($filename, array $options)
    {
        $this->client = new SQLite3($filename);
    }

    /**
     * @inheritDoc
     */
    public function getServerInfo()
    {
        $version = $this->client->version();
        return $version["versionString"];
    }

    public function query($query, $unbuffered = false)
    {
        $result = @$this->client->query($query);
        $this->error = "";
        if (!$result) {
            $this->errno = $this->client->lastErrorCode();
            $this->error = $this->client->lastErrorMsg();
            return false;
        } elseif ($result->numColumns()) {
            return new Statement($result);
        }
        $this->affected_rows = $this->client->changes();
        return true;
    }

    public function quote($string)
    {
        return ($this->util->is_utf8($string)
            ? "'" . $this->client->escapeString($string) . "'"
            : "x'" . reset(unpack('H*', $string)) . "'"
        );
    }

    public function store_result($result = null)
    {
        return $this->_result;
    }

    public function result($query, $field = 0)
    {
        $result = $this->query($query);
        if (!is_object($result)) {
            return false;
        }
        $row = $result->_result->fetchArray();
        return $row[$field];
    }
}
