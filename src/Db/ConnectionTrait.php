<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

trait ConnectionTrait
{
    /**
     * @inheritDoc
     */
    public function explain(string $query)
    {
        return $this->query("EXPLAIN QUERY PLAN $query");
    }
}
