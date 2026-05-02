<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Connection\Traits;

use Lagdo\DbAdmin\Driver\Sql\Connection\QueryResultInterface;

trait ConnectionTrait
{
    /**
     * @inheritDoc
     */
    public function explain(string $query): QueryResultInterface|bool
    {
        return $this->query("EXPLAIN QUERY PLAN $query");
    }
}
