<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Connection\Traits;

use Lagdo\DbAdmin\Support\Db\Engine\Connection\StatementInterface;

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
