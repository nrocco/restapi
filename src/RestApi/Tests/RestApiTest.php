<?php

namespace RestApi\Tests;

use RestApi\RestApi;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;

class RestApiTest extends \PHPUnit_Framework_TestCase
{
    protected $database;

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function setUp()
    {
        if (file_exists(sys_get_temp_dir().'/schema.cache')) {
            unlink(sys_get_temp_dir().'/schema.cache');
        }

        $this->database = DriverManager::getConnection(
            ['url' => 'sqlite:///:memory:'],
            new Configuration()
        );
    }

    protected function getApi()
    {
        $sql = file_get_contents(__DIR__.'/schema.sql');
        $queries = explode(';', $sql);

        foreach ($queries as $query) {
            $stmt = $this->database->prepare(trim($query));
            $stmt->execute();
            $stmt->closeCursor();
        }

        return new RestApi($this->database);
    }

    protected function getApiWithDataLoaded()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $api->createResource('todos', ['description' => 'hello world']);
        $api->createResource('todos', ['description' => 'take out the trash']);
        $api->createResource('todos', ['description' => 'watch tv']);

        return $api;
    }

    public function testNoResources()
    {
        $api = new RestApi($this->database);

        $resources = $api->listResources();

        $this->assertEmpty($resources['body']);
        $this->assertEquals(200, $resources['code']);
        $this->assertEmpty($resources['headers']);
    }

    public function testCacheSchemaMetaData()
    {
        $stmt = $this->database->prepare('CREATE TABLE "test" ("name" TEXT)');
        $result = $stmt->execute();

        $cacheFile = sys_get_temp_dir().'/schema.cache';

        $api = new RestApi($this->database, $cacheFile);
        $resources = $api->listResources();

        $schemaMetaData = '{"test":{"name":"test","pk":null,"columns":["name"]}}';
        $this->assertEquals($schemaMetaData, file_get_contents($cacheFile));

        // this loads the schema from the cache
        $api = new RestApi($this->database, $cacheFile);
        $resources = $api->listResources();
    }

    public function testReadCollectionEmpty()
    {
        $api = $this->getApi();

        $todos = $api->readCollection('todos');

        $this->assertInternalType('array', $todos);
        $this->assertEquals(200, $todos['code']);
        $this->assertInternalType('array', $todos['body']);
        $this->assertEmpty($todos['body']);
        $this->assertEquals(25, $todos['headers']['X-Pagination-Limit']);
        $this->assertEquals(0, $todos['headers']['X-Pagination-Offset']);
        $this->assertEquals(0, $todos['headers']['X-Pagination-Total']);
    }

    public function testReadCollectionNonExisting()
    {
        $api = new RestApi($this->database);
        $todos = $api->readCollection('todos');

        $this->assertInternalType('array', $todos);
        $this->assertEquals(400, $todos['code']);
        $this->assertInternalType('array', $todos['body']);
        $this->assertEquals('Resource todos does not exist', $todos['body']['message']);
    }

    public function testReadCollection()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos');
        $this->assertEquals(200, $todos['code']);
        $this->assertEquals(3, $todos['headers']['X-Pagination-Total']);
        $this->assertEquals('hello world', $todos['body'][0]['description']);
    }

    public function testReadCollectionFilter()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['file__notnull' => 'yes', 'created__year' => 2014, 'updated__month' => 6]);
        $this->assertEquals(200, $todos['code']);
        $this->assertEquals(0, $todos['headers']['X-Pagination-Total']);
        $this->assertEmpty($todos['body']);
    }

    public function testReadCollectionInvalidLookupType()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['description__foo' => 'bar']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Lookup type `foo` does not exist.', $todos['body']['message']);
    }

    public function testReadCollectionSearch()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_search' => 'trash']);
        $this->assertEquals(200, $todos['code']);
        $this->assertEquals(1, $todos['headers']['X-Pagination-Total']);
    }

    public function testReadCollectionLimitOffset()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_limit' => 2]);
        $this->assertEquals(200, $todos['code']);
        $this->assertCount(2, $todos['body']);
        $this->assertEquals(3, $todos['headers']['X-Pagination-Total']);
        $this->assertEquals(2, $todos['headers']['X-Pagination-Limit']);
        $this->assertEquals(0, $todos['headers']['X-Pagination-Offset']);

        $todos = $api->readCollection('todos', ['_limit' => 2, '_offset' => 2]);
        $this->assertEquals(200, $todos['code']);
        $this->assertCount(1, $todos['body']);
        $this->assertEquals(3, $todos['headers']['X-Pagination-Total']);
        $this->assertEquals(2, $todos['headers']['X-Pagination-Limit']);
        $this->assertEquals(2, $todos['headers']['X-Pagination-Offset']);
    }

    public function testReadCollectionInvalidLimitAndOffset()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_limit' => 'BLAAT']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Invalid value for _limit: BLAAT', $todos['body']['message']);

        $todos = $api->readCollection('todos', ['_offset' => 'BLAAT']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Invalid value for _offset: BLAAT', $todos['body']['message']);
    }

    public function testReadCollectionSort()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_sort' => 'description', '_order' => 'DESC']);
        $this->assertEquals(200, $todos['code']);
        $this->assertEquals(3, $todos['headers']['X-Pagination-Total']);
        $this->assertEquals('watch tv', $todos['body'][0]['description']);
    }

    public function testReadCollectionSortUnknownProperty()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_sort' => 'foobar']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Cannot sort on unknown property: foobar', $todos['body']['message']);

        $todos = $api->readCollection('todos', ['_sort' => null]);
        $this->assertEquals(200, $todos['code']);
    }

    public function testReadCollectionSortInvalidOrder()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_sort' => 'description', '_order' => 'BLAAT']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Invalid value for _order: BLAAT', $todos['body']['message']);
    }

    public function testReadCollectionWithSpecificFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_fields' => 'id,description']);
        $this->assertEquals(200, $todos['code']);
        $this->assertArrayHasKey('id', $todos['body'][0]);
        $this->assertArrayHasKey('description', $todos['body'][0]);
        $this->assertArrayNotHasKey('user_id', $todos['body'][0]);
        $this->assertArrayNotHasKey('created', $todos['body'][0]);
        $this->assertArrayNotHasKey('updated', $todos['body'][0]);
    }

    public function testReadCollectionWithUnknownFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_fields' => 'id,foobar']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Unknown _field foobar detected.', $todos['body']['message']);
    }

    public function testReadCollectionFilterUnknownFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['foo' => 'bar']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Cannot filter on unknown property: foo', $todos['body']['message']);
    }

    public function testCreateNonExistingResource()
    {
        $api = new RestApi($this->database);
        $todo = $api->createResource('todos', ['description' => 'hello world']);

        $this->assertInternalType('array', $todo);
        $this->assertEquals(400, $todo['code']);
        $this->assertInternalType('array', $todo['body']);
        $this->assertEquals('Resource todos does not exist', $todo['body']['message']);
    }

    public function testCreateResource()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $this->assertEquals('tester', $api->getUser());

        $todo = $api->createResource('todos', ['description' => 'hello world']);

        $this->assertInternalType('array', $todo);
        $this->assertEquals(200, $todo['code']);
        $this->assertInternalType('array', $todo['body']);

        foreach (['id', 'created', 'updated', 'user_id', 'category', 'description', 'done', 'urgency'] as $column) {
            $this->assertArrayHasKey($column, $todo['body']);
        }

        $this->assertEquals('tester', $todo['body']['user_id']);
        $this->assertEquals('inbox', $todo['body']['category']);
    }

    public function testCreateResourceWithPrimaryKey()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $todo = $api->createResource('todos', ['id' => 45, 'description' => 'hello world']);

        $this->assertInternalType('array', $todo);
        $this->assertEquals(400, $todo['code']);
        $this->assertInternalType('array', $todo['body']);
        $this->assertEquals('Not allowed to POST a primary key', $todo['body']['message']);
    }

    public function testCreateResourceWithUserId()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $todo = $api->createResource('todos', ['user_id' => 'rocco', 'description' => 'hello world']);

        $this->assertInternalType('array', $todo);
        $this->assertEquals(400, $todo['code']);
        $this->assertInternalType('array', $todo['body']);
        $this->assertEquals('Not allowed to POST a user_id', $todo['body']['message']);
    }

    public function testCreateResourceWithMissingFields()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $todo = $api->createResource('todos', []);

        $this->assertInternalType('array', $todo);
        $this->assertEquals(400, $todo['code']);
        $this->assertInternalType('array', $todo['body']);
        $this->assertEquals('Missing fields: created, updated, user_id, category, description, file, done, urgency', $todo['body']['message']);
    }

    public function testCreateResourceWithUnrecognizedFields()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $todo = $api->createResource('todos', ['foo' => 'bar']);

        $this->assertInternalType('array', $todo);
        $this->assertEquals(400, $todo['code']);
        $this->assertInternalType('array', $todo['body']);
        $this->assertEquals('Unrecognized fields detected: foo', $todo['body']['message']);
    }

    public function testReadResourceNotExisting()
    {
        $api = new RestApi($this->database);

        $todo = $api->readResource('todos', 1);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Resource todos does not exist', $todo['body']['message']);
    }

    public function testReadResouceNotExists()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->readResource('todos', 293);
        $this->assertEquals(404, $todo['code']);
        $this->assertEquals('Resource not found', $todo['body']['message']);
    }

    public function testReadResourceNotSupported()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->readResource('categories', 293);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('This operation is not suppored on this resource', $todo['body']['message']);
    }

    public function testReadResource()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->readResource('todos', 1);
        $this->assertEquals(200, $todo['code']);
        $this->assertArrayHasKey('id', $todo['body']);
        $this->assertArrayHasKey('description', $todo['body']);
        $this->assertArrayHasKey('user_id', $todo['body']);
        $this->assertArrayHasKey('created', $todo['body']);
        $this->assertArrayHasKey('updated', $todo['body']);
    }

    public function testReadResourceWithSomeFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->readResource('todos', 1, ['_fields' => 'id']);
        $this->assertEquals(200, $todo['code']);
        $this->assertArrayHasKey('id', $todo['body']);
        $this->assertArrayNotHasKey('description', $todo['body']);
        $this->assertArrayNotHasKey('user_id', $todo['body']);
        $this->assertArrayNotHasKey('created', $todo['body']);
        $this->assertArrayNotHasKey('updated', $todo['body']);
    }

    public function testReadResourceWithUnknownFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->readResource('todos', 1, ['_fields' => 'foo,bar']);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Unknown _field foo detected.', $todo['body']['message']);
    }

    public function testDeleteResource()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->deleteResource('todos', 1);
        $this->assertEquals(204, $todo['code']);

        $todo = $api->deleteResource('todos', 1);
        $this->assertEquals(404, $todo['code']);
    }

    public function testDeleteNonExistingResource()
    {
        $api = new RestApi($this->database);

        $todo = $api->deleteResource('todos', 1);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Resource todos does not exist', $todo['body']['message']);
    }

    public function testDeleteResourceUnsupported()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->deleteResource('categories', 1);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('This operation is not suppored on this resource', $todo['body']['message']);
    }

    public function testUpdateResource()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->updateResource('todos', 1, ['done' => 1]);
        $this->assertEquals(200, $todo['code']);
        $this->assertEquals(1, $todo['body']['done']);
    }

    public function testUpdateResourceNonExisting()
    {
        $api = new RestApi($this->database);

        $todo = $api->updateResource('todos', 1, ['done' => 1]);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Resource todos does not exist', $todo['body']['message']);
    }

    public function testUpdateResourceWithPrimaryKey()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->updateResource('todos', 1, ['id' => 11]);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Not allowed to change the primary key of this resource', $todo['body']['message']);
    }

    public function testUpdateResourceWithUserId()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->updateResource('todos', 1, ['user_id' => 'blaat']);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Not allowed to change the user of this resource', $todo['body']['message']);
    }

    public function testUpdateResourceWithUnrecognizedFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->updateResource('todos', 1, ['foo' => 'bar']);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Unrecognized fields detected: foo', $todo['body']['message']);
    }

    public function testUpdateResourceWithEmptyRequest()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->updateResource('todos', 1, []);
        $this->assertEquals(400, $todo['code']);
        $this->assertEquals('Empty request not allowed', $todo['body']['message']);
    }

    public function testUpdateResourceWithoutPrimaryKey()
    {
        $api = $this->getApiWithDataLoaded();

        $response = $api->updateResource('categories', 1, ['name' => 'test']);
        $this->assertEquals(400, $response['code']);
        $this->assertEquals('This operation is not suppored on this resource', $response['body']['message']);
    }

    public function testFetchFileDoesNotExist()
    {
        $storage = new \RestApi\HashedStorage(sys_get_temp_dir().'/__w00t__');

        $api = $this->getApi();
        $api->setStorage($storage);

        $response = $api->fetchFile('b10a8db164e0754105b7a99be72e3fe5');
        $this->assertEquals(404, $response['code']);
    }

    public function testHashedFileStorageIssues()
    {
        $storage = new \RestApi\HashedStorage(sys_get_temp_dir().'/__w00t__');

        $api = $this->getApi();
        $api->setUser('tester');
        $api->setStorage($storage);

        $this->assertEquals($storage, $api->getStorage());

        $response = $api->createResource('todos', ['description' => 'hello world', 'file' => null]);
        $this->assertEquals(200, $response['code']);
        $this->assertEquals(null, $response['body']['file']);

        $response = $api->updateResource('todos', $response['body']['id'], ['file' => 'non-existent']);
        $this->assertEquals(400, $response['code']);
        $this->assertEquals('file non-existent does not exist', $response['body']['message']);

        $response = $api->createResource('todos', ['description' => 'hello world', 'file' => 'non-existent']);
        $this->assertEquals(400, $response['code']);
        $this->assertEquals('file non-existent does not exist', $response['body']['message']);
    }
}
