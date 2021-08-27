<?php

namespace Lagdo\Adminer\Driver\Sqlite;

use Lagdo\Adminer\Driver\Db\Driver as AbstractDriver;

class Driver extends AbstractDriver
{
    public function insertUpdate($table, $rows, $primary)
    {
        $values = [];
        foreach ($rows as $set) {
            $values[] = "(" . implode(", ", $set) . ")";
        }
        return $this->db->queries("REPLACE INTO " . $this->server->table($table) ." (" . implode(
            ", ",
            array_keys(reset($rows))
        ) . ") VALUES\n" . implode(",\n", $values));
    }

    public function tableHelp($name)
    {
        if ($name == "sqlite_sequence") {
            return "fileformat2.html#seqtab";
        }
        if ($name == "sqlite_master") {
            return "fileformat2.html#$name";
        }
    }
}
