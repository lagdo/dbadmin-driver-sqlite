<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

trait ConnectionTrait
{
    use ConfigTrait;

    public function multiQuery(string $query)
    {
        return $this->result = $this->query($query);
    }

    public function nextResult()
    {
        return false;
    }
}
