<?php

namespace RestApi\Database;

use Doctrine\DBAL\Connection;

class PostgresDBMetaData extends DBMetaData
{
    protected function getTablesQuery()
    {
        return $this->db->fetchAll("SELECT table_name as name FROM information_schema.tables WHERE table_schema = 'public' AND table_name NOT LIKE 'oc_%'");
    }

    protected function getTableMetaQuery($table)
    {
        $query = $this->db->executeQuery("SELECT
    a.attname::varchar AS name,
    t.typname::varchar AS type,
    a.attnum AS position,
    i.indisprimary AS pk
FROM pg_attribute a
LEFT JOIN pg_type t ON a.atttypid = t.oid
LEFT JOIN pg_index i ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
WHERE
    a.attisdropped = False
AND
    a.attnum > 0
AND
    a.attrelid = :table::regclass", [':table'=>$table]);

        return $query->fetchAll();
    }

    public function getPrimaryKeyField($table)
    {
        foreach ($this->getTableMeta($table) as $column) {
            if (true === $column['pk']) {
                return $column['name'];
            }
        }

        return null;
    }

    protected function renderWhere($column, $value, $lookupType)
    {
        if ('year' === $lookupType) {
            return "date_part('year', {$column}) = {$this->db->quote($value)}";
        } elseif ('month' === $lookupType) {
            return "date_part('month', {$column}) = {$this->db->quote($value)}";
        } else if (null === $value || $lookupType === 'isnull') {
            return "{$column} IS NULL";
        } elseif ($lookupType === 'notnull') {
            return "{$column} IS NOT NULL";
        }

        if ($lookupType === 'contains' || $lookupType === 'icontains') {
            $value = "%{$value}%";
        }

        return "{$column}::text {$this->lookupTypes[$lookupType]} {$this->db->quote($value)}";
    }
}
