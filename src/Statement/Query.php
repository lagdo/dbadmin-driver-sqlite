<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Statement;

use Lagdo\DbAdmin\Driver\Sql\Specific\Statement\AbstractQuery;

use function preg_match;

class Query extends AbstractQuery
{
    /**
     * @inheritDoc
     */
    public function limitToOne(string $table, string $query, string $where): string
    {
        return preg_match('~^INTO~', $query) ||
            $this->_engine()->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ?
            $this->getLimitClause($query, $where, 1, 0) :
            //! use primary key in tables with WITHOUT rowid
            " $query WHERE rowid = (SELECT rowid FROM " . $this->_statement()->escapeTableName($table) . $where . ' LIMIT 1)';
    }
}
