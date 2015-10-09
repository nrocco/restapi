<?php

namespace RestApi\Database;

use Doctrine\DBAL\Connection;

class SqliteDBMetadata extends DBMetaData
{
    public function __construct(Connection $db)
    {
        parent::__construct($db);

        // Override the lookup type as sqlite is case insensitive
        $this->lookupTypes['icontains'] = 'LIKE';
    }

    protected function getTablesQuery()
    {
        return $this->db->fetchAll("SELECT name FROM sqlite_master WHERE type IN ('table', 'view') AND name != 'sqlite_sequence'");
    }

    protected function getTableMetaQuery($table)
    {
        return $this->db->fetchAll("PRAGMA table_info({$table})");
    }

    public function getPrimaryKeyField($table)
    {
        foreach ($this->getTableMeta($table) as $column) {
            if ("1" === $column['pk']) {
                return $column['name'];
            }
        }

        return null;
    }

    protected function renderWhere($column, $value, $lookupType)
    {
        if ('year' === $lookupType) {
            return "strftime('%Y', {$column}) = {$this->db->quote($value)}";
        } elseif ('month' === $lookupType) {
            return "strftime('%m', {$column}) = {$this->db->quote($value)}";
        }

        return parent::renderWhere($column, $value, $lookupType);
    }
}
