<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\StatementInterface;

trait ConnectionTrait
{
    /**
     * @inheritDoc
     */
    public function explain(string $query): StatementInterface|bool
    {
        return $this->query("EXPLAIN QUERY PLAN $query");
    }
}
