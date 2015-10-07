<?php

namespace RestApi;

use RestApi\Database\SqliteDBMetaData;

class RestApi
{
    protected $database;
    protected $user;

    public function __construct($database)
    {
        $this->database = $database;
        $this->meta = new SqliteDBMetaData($database);
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function listResources()
    {
        return $this->meta->getTables();
    }

    public function readCollection($table, $params=[])
    {
        if (false === in_array($table, $this->meta->getTables())) {
            return $this->raise("Resource $table does not exist", 400);
        }

        $columns = $this->meta->getTableColumns($table);
        $pkField = $this->meta->getPrimaryKeyField($table);

        $fields = array_key_exists('_fields', $params) ? $params['_fields'] : false;
        if ($fields) {
            foreach (explode(",", $fields) as $field) {
                if (false === in_array($field, $columns)) {
                    return $this->raise("Unknown _field {$field} detected.", 400);
                }
            }
        } else {
            $fields = implode(',', $columns);
        }

        $sort = array_key_exists('_sort', $params) ? $params['_sort'] : $pkField;
        if (null === $sort) {
            $sort = $columns[0];
        } elseif(false === in_array($sort, $columns)) {
            return $this->raise("Cannot sort on unknown property: {$sort}", 400);
        }

        $order = array_key_exists('_order', $params) ? $params['_order'] : 'ASC';
        if (false === in_array($order, array('ASC', 'DESC'))) {
            return $this->raise("Invalid value for _order: $order", 400);
        }

        $limit = array_key_exists('_limit', $params) ? $params['_limit'] : 25;
        if (false === filter_var($limit, FILTER_VALIDATE_INT)) {
            return $this->raise("Invalid value for _limit: $limit");
        }

        $offset = array_key_exists('_offset', $params) ? $params['_offset'] : 0;
        if (false === filter_var($offset, FILTER_VALIDATE_INT)) {
            return $this->raise("Invalid value for _offset: $offset");
        }

        $filters = $params;
        foreach ($filters as $key => &$value) {
            if (substr($key, 0, 1) === '_') {
                unset($filters[$key]);
            } elseif (false === in_array(reset(explode('__', $key, 2)), $columns)) {
                return $this->raise("Cannot filter on unknown property: {$key}", 400);
            }
        }

        $qb = $this->database->createQueryBuilder();

        $qb->select($fields);
        $qb->from($table);
        $qb->orderBy($sort, $order);
        $qb->setFirstResult($offset);
        $qb->setMaxResults($limit);

        foreach ($filters as $key => $value) {
            $qb->andWhere("$key = :$key");
            $qb->setParameter($key, $value);
        }

        echo $qb->getSQL();
        die();
    }

    public function createResource($table, $params)
    {
    }

    public function readResource($table, $pk)
    {
    }

    public function updateResource($table, $pk, $params)
    {
    }

    public function deleteResource($table, $pk)
    {
    }

    protected function raise($message, $code=503)
    {
        return $this->response(array('message' => $message), $code);
    }

    protected function response($body, $code=200, $headers=[])
    {
        return array(
            'body' => $body,
            'code' => $code,
            'headers' => $headers
        );
    }
}
