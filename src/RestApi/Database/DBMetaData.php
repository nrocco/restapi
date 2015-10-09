<?php

namespace RestApi\Database;

use Doctrine\DBAL\Connection;

abstract class DBMetaData implements IDBMetaData
{
    protected $db;
    protected $tables;
    protected $tableMap = [];

    protected $lookupTypes = [
        'eq' => '=',
        'neq' => '!=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
        'isnull' => 'x',
        'notnull' => 'x',
        'contains' => 'LIKE',
        'icontains' => 'ILIKE',

        'month' => '=',
        'year' => '=',
    ];

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function getTables()
    {
        if (true === empty($this->tables)) {
            $this->tables = [];
            foreach ($this->getTablesQuery() as $table) {
                $this->tables[] = $table['name'];
            }
        }

        return $this->tables;
    }

    public function getTableMeta($table)
    {
        if (false === array_key_exists($table, $this->tableMap)) {
            $this->tableMap[$table] = [];

            foreach ($this->getTableMetaQuery($table) as $column) {
                $this->tableMap[$table][$column['name']] = $column;
            }
        }

        return $this->tableMap[$table];
    }

    public function getTableColumns($table)
    {
        return array_keys($this->getTableMeta($table));
    }

    public function addWhere($key, $value)
    {
        $parts = explode('__', $key, 2);
        $column = $parts[0];
        $lookupType = isset($parts[1]) ? $parts[1] : 'eq';

        if (array_key_exists($lookupType, $this->lookupTypes) === false) {
            throw new \Exception("Lookup type `{$lookupType}` does not exist.");
        }

        return $this->renderWhere($parts[0], $value, $lookupType);
    }

    protected function renderWhere($column, $value, $lookupType)
    {
        if (null === $value || $lookupType === 'isnull') {
            return "{$column} IS NULL";
        } elseif ($lookupType === 'notnull') {
            return "{$column} IS NOT NULL";
        }

        if ($lookupType === 'contains' || $lookupType === 'icontains') {
            $value = "%{$value}%";
        }

        return "{$column} {$this->lookupTypes[$lookupType]} {$this->db->quote($value)}";
    }
}
