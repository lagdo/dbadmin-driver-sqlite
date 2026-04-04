<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Grammar;

use Lagdo\DbAdmin\Support\Db\Engine\Grammar\AbstractQuery;

use function preg_match;

class Query extends AbstractQuery
{
    /**
     * @inheritDoc
     */
    protected function limitToOne(string $table, string $query, string $where): string
    {
        return preg_match('~^INTO~', $query) ||
            $this->driver->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ?
            $this->getLimitClause($query, $where, 1, 0) :
            //! use primary key in tables with WITHOUT rowid
            " $query WHERE rowid = (SELECT rowid FROM " . $this->grammar->escapeTableName($table) . $where . ' LIMIT 1)';
    }
}
