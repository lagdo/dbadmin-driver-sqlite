<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Engine;

use Lagdo\DbAdmin\Driver\Sql\Specific\Engine\AbstractQuery;

use function preg_replace;

class Query extends AbstractQuery
{
    /**
     * @inheritDoc
     */
    public function view(string $name): array
    {
        $viewName = $this->_engine()->quote($name);
        $query = "SELECT sql FROM sqlite_master WHERE name = $viewName";
        // Remove unwanted chars.
        $sqlCode = preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '',
            $this->_engine()->columnValue($query));

        return [
            'name' => $name,
            'type' => 'VIEW',
            'materialized' => false,
            'select' => $sqlCode,
        ]; //! identifiers may be inside []
    }

    /**
     * @inheritDoc
     */
    public function lastAutoIncrementId(): string
    {
        return $this->_engine()->columnValue("SELECT LAST_INSERT_ROWID()");
    }
}
