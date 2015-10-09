<?php

namespace RestApi\Database;

interface IDBMetaData
{
    public function getTables();
    public function getTableMeta($table);
    public function getTableColumns($table);
    public function getPrimaryKeyField($table);
    public function addWhere($key, $value);
}
