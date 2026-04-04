<?php

namespace Lagdo\DbAdmin\Support\Sqlite\Grammar;

use Lagdo\DbAdmin\Support\Db\Engine\Grammar\AbstractDatabase;

use function array_map;

class Database extends AbstractDatabase
{
    /**
     * @inheritDoc
     */
    public function getUseDatabaseQuery(string $database, string $style = ''): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getCreateDatabaseQuery(string $database, string $collation): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getDropDatabaseQuery(string $database): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getAutoIncrementModifier(): string
    {
        return " PRIMARY KEY AUTOINCREMENT";
    }

    /**
     * @inheritDoc
     */
    public function getDropViewsQueries(array $views): array
    {
        return array_map(fn(string $view) =>
            'DROP VIEW ' . $this->grammar->escapeTableName($view), $views);
    }

    /**
     * @inheritDoc
     */
    public function getDropTablesQueries(array $tables): array
    {
        return array_map(fn(string $table) =>
            'DROP TABLE ' . $this->grammar->escapeTableName($table), $tables);
    }

    /**
     * @inheritDoc
     */
    public function getTruncateTablesQueries(array $tables): array
    {
        return array_map(fn(string $table) =>
            'DELETE FROM ' . $this->grammar->escapeTableName($table), $tables);
    }
}
