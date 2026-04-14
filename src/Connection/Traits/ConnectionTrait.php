<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Connection\Traits;

use Lagdo\DbAdmin\Driver\Sql\Connection\StatementInterface;

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
