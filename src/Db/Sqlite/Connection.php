<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Sqlite;

use Lagdo\DbAdmin\Driver\Db\Connection as AbstractConnection;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConfigTrait;

use Exception;
use SQLite3;

use function preg_match;
use function is_array;
use function is_object;
use function count;
use function unpack;
use function reset;

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
        $flags = $schema === '__create__' ? SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE : SQLITE3_OPEN_READWRITE;
        try {
            $this->client = new SQLite3($filename, $flags);
        } catch (Exception $ex) {
            $this->driver->setError($ex->getMessage());
            return false;
        }
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
        $space = $this->spaceRegex();
        if (preg_match("~^$space*+ATTACH\\b~i", $query, $match)) {
            // PHP doesn't support setting SQLITE_LIMIT_ATTACHED
            $this->driver->setError($this->utils->trans->lang('ATTACH queries are not supported.'));
            return false;
        }

        $result = @$this->client->query($query);
        $this->driver->setError();
        if (!$result) {
            $this->driver->setErrno($this->client->lastErrorCode());
            $this->driver->setError($this->client->lastErrorMsg());
            return false;
        } elseif ($result->numColumns() > 0) {
            return new Statement($result);
        }
        $this->setAffectedRows($this->client->changes());
        return true;
    }

    /**
     * @inheritDoc
     */
    public function quote(string $string)
    {
        if ($this->utils->str->isUtf8($string) || !is_array($unpacked = unpack('H*', $string))) {
            return "'" . $this->client->escapeString($string) . "'";
        }
        return "x'" . reset($unpacked) . "'";
    }

    public function multiQuery(string $query)
    {
        $this->statement = $this->driver->execute($query);
        return $this->statement !== false;
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
        $result = $this->driver->execute($query);
        if (!is_object($result)) {
            return null;
        }
        $row = $result->fetchRow();
        return is_array($row) && count($row) > $field ? $row[$field] : null;
    }
}
