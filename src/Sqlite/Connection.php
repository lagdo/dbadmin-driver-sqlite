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

    public function query($query, $unbuffered = false)
    {
        $result = @$this->client->query($query);
        $this->db->setError();
        if (!$result) {
            $this->db->setErrno($this->client->lastErrorCode());
            $this->db->setError($this->client->lastErrorMsg());
            return false;
        } elseif ($result->numColumns()) {
            return new Statement($result);
        }
        $this->db->setAffectedRows($this->client->changes());
        return true;
    }

    public function quote($string)
    {
        return ($this->util->isUtf8($string) ?
            "'" . $this->client->escapeString($string) . "'" :
            "x'" . reset(unpack('H*', $string)) . "'");
    }

    public function storedResult()
    {
        return $this->result;
    }

    public function result($query, $field = 0)
    {
        $result = $this->query($query);
        if (!is_object($result)) {
            return false;
        }
        $row = $result->result->fetchArray();
        return is_array($row) && array_key_exists($field, $row) ? $row[$field] : false;
    }
}
