<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Db;

use Lagdo\DbAdmin\Driver\Db\Query as AbstractQuery;

use function array_keys;
use function implode;
use function preg_match;
use function preg_replace;

class Query extends AbstractQuery
{
    /**
     * @inheritDoc
     */
    protected function limitToOne(string $table, string $query, string $where): string
    {
        return preg_match('~^INTO~', $query) ||
            $this->driver->result("SELECT sqlite_compileoption_used('ENABLE_UPDATE_DELETE_LIMIT')") ?
            $this->driver->getLimitClause($query, $where, 1, 0) :
            //! use primary key in tables with WITHOUT rowid
            " $query WHERE rowid = (SELECT rowid FROM " . $this->driver->escapeTableName($table) . $where . ' LIMIT 1)';
    }

    /**
     * @inheritDoc
     */
    public function insertOrUpdate(string $table, array $rows, array $primary): bool
    {
        $values = [];
        foreach ($rows as $set) {
            $values[] = "(" . implode(", ", $set) . ")";
        }
        $result = $this->driver->execute("REPLACE INTO " .
            $this->driver->escapeTableName($table) . " (" .
            implode(", ", array_keys(reset($rows))) .
            ") VALUES\n" . implode(",\n", $values));
        return $result !== false;
    }

    /**
     * @inheritDoc
     */
    public function view(string $name): array
    {
        return [
            'name' => $name,
            'type' => 'VIEW',
            'materialized' => false,
            'select' => preg_replace('~^(?:[^`"[]+|`[^`]*`|"[^"]*")* AS\s+~iU', '',
                $this->driver->result("SELECT sql FROM sqlite_master WHERE name = " .
                $this->driver->quote($name)))
        ]; //! identifiers may be inside []
    }

    /**
     * @inheritDoc
     */
    public function lastAutoIncrementId(): string
    {
        return $this->driver->result("SELECT LAST_INSERT_ROWID()");
    }
}
