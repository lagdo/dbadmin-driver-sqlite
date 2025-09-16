<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db\Sqlite;

use Lagdo\DbAdmin\Driver\Db\Connection as AbstractConnection;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConfigTrait;
use Lagdo\DbAdmin\Driver\Sqlite\Db\ConnectionTrait;
use Exception;
use SQLite3;

use function preg_match;
use function is_array;
use function unpack;
use function reset;

class Connection extends AbstractConnection
{
    use ConfigTrait;
    use ConnectionTrait;

    /**
     * @inheritDoc
     */
    public function open(string $database, string $schema = ''): bool
    {
        $filename = $this->filename($database, $this->options);
        $flags = $schema === '__create__' ? SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE : SQLITE3_OPEN_READWRITE;
        try {
            $this->client = new SQLite3($filename, $flags);
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
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
        $space = $this->utils->str->spaceRegex();
        if (preg_match("~^$space*+ATTACH\\b~i", $query, $match)) {
            // PHP doesn't support setting SQLITE_LIMIT_ATTACHED
            $this->setError($this->utils->trans->lang('ATTACH queries are not supported.'));
            return false;
        }

        $result = @$this->client->query($query);
        $this->setError();
        if (!$result) {
            $this->setErrno($this->client->lastErrorCode());
            $this->setError($this->client->lastErrorMsg());
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
        $this->statement = $this->query($query);
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
}
