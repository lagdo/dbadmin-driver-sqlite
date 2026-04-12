<?php

namespace Lagdo\DbAdmin\Driver\Sqlite\Statement;

use Lagdo\DbAdmin\Driver\Sql\Specific\Statement\AbstractSyntax;

use function str_replace;

class Syntax extends AbstractSyntax
{
    /**
     * @inheritDoc
     */
    public function escapeId(string $idf): string
    {
        return '"' . str_replace('"', '""', $idf) . '"';
    }
}
