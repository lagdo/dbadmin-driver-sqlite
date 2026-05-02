<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Connection\Sqlite;

use Lagdo\DbAdmin\Driver\Sql\Connection\AbstractConnection;
use Lagdo\DbAdmin\Driver\Sql\Connection\PreparedStatement;
use Lagdo\DbAdmin\Driver\Sql\Connection\QueryResultInterface;
use Lagdo\DbAdmin\Driver\Sqlite\Connection\Traits\ConfigTrait;
use Lagdo\DbAdmin\Driver\Sqlite\Connection\Traits\ConnectionTrait;
use Exception;
use SQLite3;
use SQLite3Stmt;

use function is_array;
use function preg_match;
use function reset;
use function unpack;

class Connection extends AbstractConnection
{
    use ConfigTrait;
    use ConnectionTrait;

    /**
     * The client object used to query the database driver
     *
     * @var SQLite3;
     */
    protected SQLite3 $client;

    /**
     * @inheritDoc
     */
    public function open(string $database, string $schema = ''): bool
    {
        try {
            $filename = $this->filename($database, $this->options);
            $flags = $schema !== '__create__' ? SQLITE3_OPEN_READWRITE :
                SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;
            $this->client = new SQLite3($filename, $flags);
        } catch (Exception $ex) {
            $this->setError($ex->getMessage());
            return false;
        }

        $this->executeQuery("PRAGMA foreign_keys = 1");
        return true;
    }

    /**
     * @inheritDoc
     */
    public function serverInfo(): string
    {
        $version = SQLite3::version();
        return $version["versionString"];
    }

    /**
     * @inheritDoc
     */
    public function quote(string $string): string
    {
        $escape = $this->_utils()->str->isUtf8($string) ||
            !is_array($unpacked = unpack('H*', $string));
        return !$escape ? "x'" . reset($unpacked) . "'" :
            "'" . $this->client->escapeString($string) . "'";
    }

    /**
     * @inheritDoc
     */
    public function executeQuery(string $query, bool $unbuffered = false): QueryResultInterface
    {
        $space = $this->_utils()->str->spaceRegex();
        if (preg_match("~^$space*+ATTACH\\b~i", $query, $match)) {
            // PHP doesn't support setting SQLITE_LIMIT_ATTACHED
            $this->setError($this->_utils()->lang('ATTACH queries are not supported.'));
            return new QueryResult(false);
        }

        $this->setError();

        $result = @$this->client->query($query);
        if (!$result) {
            $this->setErrno($this->client->lastErrorCode());
            $this->setError($this->client->lastErrorMsg());
            return new QueryResult(false);
        }

        if ($result->numColumns() > 0) {
            return new QueryResult($result);
        }

        $this->setAffectedRows($this->client->changes());
        return new QueryResult(true);
    }

    /**
     * @inheritDoc
     */
    public function prepareStatement(string $query): PreparedStatement
    {
        [$params] = $this->getPreparedParams($query);
        $statement = $this->client->prepare($query);
        return new PreparedStatement($statement, $query, $params);
    }

    /**
     * @inheritDoc
     */
    public function executeStatement(PreparedStatement $preparedStatement,
        array $values): QueryResultInterface
    {
        /** @var SQLite3Stmt|false */
        $statement = $preparedStatement->statement();
        if (!$statement) {
            $this->setError($this->_utils()->lang($this->statementNotPrepared));
            return new QueryResult(false);
        }

        $values = $preparedStatement->paramValues($values, true);
        foreach ($values as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $result = $statement->execute();

        return new QueryResult($result);
    }

    public function executeMultiQuery(string $query): QueryResultInterface
    {
        return $this->executeQuery($query);
    }

    /**
     * @inheritDoc
     */
    public function readRowset(QueryResultInterface $result): QueryResultInterface
    {
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function nextRowset(QueryResultInterface $result): bool
    {
        return false;
    }
}
