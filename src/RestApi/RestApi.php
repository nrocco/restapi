<?php

namespace RestApi;

use RestApi\Database\SqliteDBMetaData;
use RestApi\Database\PostgresDBMetaData;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;

class RestApi
{
    protected $database;
    protected $user;
    protected $storage;

    public function __construct($database)
    {
        $this->database = $database;

        switch ($database->getDatabasePlatform()->getName()) {
            case 'sqlite':
                $this->meta = new SqliteDBMetaData($database);
                break;
            case 'postgresql':
                $this->meta = new PostgresDBMetaData($database);
                break;
            default:
                throw new \Exception("Unsupported database platform ".$database->getDatabasePlatform()->getName());
                break;
        }
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setStorage($storage)
    {
        $this->storage = $storage;
    }

    public function getStorage()
    {
        return $storage;
    }

    public function listResources()
    {
        return $this->response($this->meta->getTables());
    }

    public function readCollection($table, $params=[])
    {
        $start = microtime(true); // debug

        if (false === in_array($table, $this->meta->getTables())) {
            return $this->raise("Resource $table does not exist", 400);
        }

        $columns = $this->meta->getTableColumns($table);
        $pkField = $this->meta->getPrimaryKeyField($table);

        $metaTime = microtime(true) - $start; // debug

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

        if (true === in_array('user_id', $columns)) {
            $params['user_id__eq'] = $this->user;
        }

        $qb = $this->database->createQueryBuilder();
        $qb->select($fields);
        $qb->from($table);
        $qb->orderBy($sort, $order);
        $qb->setFirstResult($offset);
        $qb->setMaxResults($limit);

        foreach ($params as $key => $value) {
            if (substr($key, 0, 1) === '_') {
                continue;
            } elseif (false === in_array(reset(explode('__', $key, 2)), $columns)) {
                return $this->raise("Cannot filter on unknown property: {$key}", 400);
            }

            $qb->andWhere($this->meta->addWhere($key, $value));
        }

        $search = array_key_exists('_search', $params) ? $params['_search'] : null;
        if (false === empty($search)) {
            $searchArray = [];
            foreach ($columns as $column) {
                if (true === in_array($column, array($pkField, 'user_id'))) {
                    continue;
                }
                $searchArray[] = $qb->expr()->like($column, ':search');
            }
            $qb->andWhere(call_user_func_array(array($qb->expr(), 'orX'), $searchArray));
            $qb->setParameter(':search', "%$search%");
        }

        $start = microtime(true); // debug
        $response = $qb->execute()->fetchAll();
        $queryTime = microtime(true) - $start; // debug

        return $this->response($response, 200, [
            'X-Query' => $qb->getSQL(),
            'X-Debug' => "query={$queryTime}ms; meta={$metaTime}ms;"
        ]);
    }

    public function createResource($table, $params)
    {
        if (false === in_array($table, $this->meta->getTables())) {
            return $this->raise("Resource $table does not exist", 400);
        }

        $columns = $this->meta->getTableColumns($table);
        $pkField = $this->meta->getPrimaryKeyField($table);

        // TODO: can we get around this check?
        if (true === array_key_exists('user_id', $params)) {
            return $this->raise("Not allowed to POST a user_id", 400);
        }

        // TODO: can we get around this check?
        if (true === array_key_exists($pkField, $params)) {
            return $this->raise("Not allowed to POST a primary key", 400);
        }

        if (false === empty($diff = array_diff(array_keys($params), $columns))) {
            return $this->raise("Unrecognized fields detected: ".implode(', ', $diff), 400);
        }

        if (true === empty($params)) {
            $fields = array_filter($columns, function($value) use ($pkField) { return $value !== $pkField; });

            return $this->raise('Missing fields: '.implode(', ', $fields), 400);
        }

        if (true === in_array('user_id', $columns)) {
            $params['user_id'] = $this->user;
        }

        // /////////////////////////////////////////////////////////////////////

        // TODO: determine the fields that are references to files.
        $fileFields = array_filter($columns, function($v) {
            return in_array($v, array('receipt', 'file'));
        });

        foreach ($fileFields as $fileField) {
            if (false === array_key_exists($fileField, $params)) {
                continue; // api user did not POST a file field.
            }

            if ($params[$fileField] instanceof UploadedFile) {
                $hash = $this->storage->save($params[$fileField]->getPathName());
                $params[$fileField] = $hash;

                continue; // file uploaded, no need to do additional checks.
            }

            if (null === $params[$fileField]) {
                continue; // if `null` was submitted, the api user wants to unset it.
            }

            if (false === $this->storage->exists($params[$fileField])) {
                return $this->raise("{$fileField} {$params[$fileField]} does not exist", 400);
            }
        }

        // /////////////////////////////////////////////////////////////////////

        $qb = $this->database->createQueryBuilder();
        $qb->insert($table);

        foreach ($params as $column => $value) {
            $qb->setValue($column, ":{$column}");
            $qb->setParameter(":{$column}", $value);
        }

        try {
            $result = $qb->execute();
        } catch (NotNullConstraintViolationException $e) {
            return $this->raise("Required parameters missing.", 400);
        }

        return $this->readResource($table, $this->database->lastInsertId());
    }

    public function readResource($table, $pk, $params=[])
    {
        if (false === in_array($table, $this->meta->getTables())) {
            return $this->raise("Resource $table does not exist", 400);
        }

        $columns = $this->meta->getTableColumns($table);
        $pkField = $this->meta->getPrimaryKeyField($table);

        if (null === $pkField) {
            return $this->raise("This operation is not suppored on this resource", 400);
        }

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

        $qb = $this->database->createQueryBuilder();
        $qb->select($fields);
        $qb->from($table);
        $qb->andWhere("{$pkField} = :pk");
        $qb->setParameter(':pk', $pk);

        if (true === in_array('user_id', $columns)) {
            $qb->andWhere("user_id = :user_id");
            $qb->setParameter(':user_id', $this->user);
        }

        if (false === $result = $qb->execute()->fetch()) {
            return $this->raise("Resource not found", 404);
        }

        return $this->response($result);
    }

    public function updateResource($table, $pk, $params)
    {
        if (false === in_array($table, $this->meta->getTables())) {
            return $this->raise("Resource $table does not exist", 400);
        }

        $columns = $this->meta->getTableColumns($table);
        $pkField = $this->meta->getPrimaryKeyField($table);

        if (null === $pkField) {
            return $this->raise("This operation is not suppored on this resource", 400);
        }

        if (false === empty($diff = array_diff(array_keys($params), $columns))) {
            return $this->raise("Unrecognized fields detected: ".implode(', ', $diff), 400);
        }

        if (true === empty($params)) {
            return $this->raise('Empty request not allowed', 400);
        } elseif (true === array_key_exists($pkField, $params)) {
            return $this->raise('Not allowed to change the primary key of this resource', 400);
        } elseif (true === array_key_exists('user_id', $params)) {
            return $this->raise('Not allowed to change the user of this resource', 400);
        }

        // /////////////////////////////////////////////////////////////////////

        // TODO: determine the fields that are references to files.
        $fileFields = array_filter($columns, function($v) {
            return in_array($v, array('receipt', 'file'));
        });

        foreach ($fileFields as $fileField) {
            if (false === array_key_exists($fileField, $params)) {
                continue; // api user did not POST a file field.
            }

            if ($params[$fileField] instanceof UploadedFile) {
                $hash = $this->storage->save($params[$fileField]->getPathName());
                $params[$fileField] = $hash;

                continue; // file uploaded, no need to do additional checks.
            }

            if (null === $params[$fileField]) {
                continue; // if `null` was submitted, the api user wants to unset it.
            }

            if (false === $this->storage->exists($params[$fileField])) {
                return $this->raise("{$fileField} {$params[$fileField]} does not exist", 400);
            }
        }

        // /////////////////////////////////////////////////////////////////////

        $qb = $this->database->createQueryBuilder();
        $qb->update($table);
        $qb->andWhere("{$pkField} = :pk");
        $qb->setParameter(':pk', $pk);

        if (true === in_array('user_id', $columns)) {
            $qb->andWhere("user_id = :user_id");
            $qb->setParameter(':user_id', $this->user);
        }

        foreach ($params as $column => $value) {
            $qb->set($column, ":{$column}");
            $qb->setParameter(":{$column}", $value);
        }

        try {
            // TODO: if this returns 0 an error occurred? how to expose this over the api?
            $result = $qb->execute();
        } catch(\Doctrine\DBAL\Exception\DriverException $e) {
            return $this->raise($e->getMessage(), 400);
        }

        return $this->readResource($table, $pk);
    }

    public function deleteResource($table, $pk)
    {
        if (false === in_array($table, $this->meta->getTables())) {
            return $this->raise("Resource $table does not exist", 400);
        }

        $columns = $this->meta->getTableColumns($table);
        $pkField = $this->meta->getPrimaryKeyField($table);

        if (null === $pkField) {
            return $this->raise("This operation is not suppored on this resource", 400);
        }

        $qb = $this->database->createQueryBuilder();
        $qb->delete($table);
        $qb->andWhere("{$pkField} = :pk");
        $qb->setParameter(':pk', $pk);

        if (true === in_array('user_id', $columns)) {
            $qb->andWhere("user_id = :user_id");
            $qb->setParameter(':user_id', $this->user);
        }

        $result = $qb->execute();

        if (0 === $result) {
            return $this->raise("Resource not found", 404);
        }

        return $this->response(null, 204);
    }

    public function fetchFile($hash)
    {
        if (false === $this->storage->exists($hash)) {
            return $this->response("", 404);
        }

        return $this->storage->hashToFullFilePath($hash);
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
