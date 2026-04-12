<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Driver;

use Lagdo\DbAdmin\Support\Db\Engine\Driver\AbstractQuery;

use function array_keys;
use function implode;
use function preg_replace;

class Query extends AbstractQuery
{
    /**
     * @inheritDoc
     */
    // public function insertOrUpdate(string $table, array $rows, array $primary): bool
    // {
    //     $values = [];
    //     foreach ($rows as $set) {
    //         $values[] = "(" . implode(", ", $set) . ")";
    //     }
    //     $result = $this->_driver()->execute("REPLACE INTO " .
    //         $this->_grammar()->escapeTableName($table) . " (" .
    //         implode(", ", array_keys(reset($rows))) .
    //         ") VALUES\n" . implode(",\n", $values));
    //     return $result !== false;
    // }

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
                $this->_driver()->result("SELECT sql FROM sqlite_master WHERE name = " .
                $this->_driver()->quote($name)))
        ]; //! identifiers may be inside []
    }

    /**
     * @inheritDoc
     */
    public function lastAutoIncrementId(): string
    {
        return $this->_driver()->result("SELECT LAST_INSERT_ROWID()");
    }
}
