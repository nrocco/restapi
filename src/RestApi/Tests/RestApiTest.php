<?php

namespace RestApi\Tests;

use RestApi\RestApi;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;

class RestApiTest extends \PHPUnit_Framework_TestCase
{
    protected $database;

    public function setUp()
    {
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

        $api->createResource('todos', array('description' => "hello world"));
        $api->createResource('todos', array('description' => "take out the trash"));
        $api->createResource('todos', array('description' => "watch tv"));

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

    public function testEmptyCollection()
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

    public function testNonExistingCollection()
    {
        $api = new RestApi($this->database);
        $todos = $api->readCollection('todos');

        $this->assertInternalType('array', $todos);
        $this->assertEquals(400, $todos['code']);
        $this->assertInternalType('array', $todos['body']);
        $this->assertEquals('Resource todos does not exist', $todos['body']['message']);
    }

    public function testCollection()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos');
        $this->assertEquals(200, $todos['code']);
        $this->assertEquals(3, $todos['headers']['X-Pagination-Total']);
        $this->assertEquals('hello world', $todos['body'][0]['description']);
    }

    public function testSearchCollection()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_search' => 'trash']);
        $this->assertEquals(200, $todos['code']);
        $this->assertEquals(1, $todos['headers']['X-Pagination-Total']);
    }

    public function testLimitCollection()
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

    public function testLimitCollectionInvalidLimitAndOffset()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_limit' => 'BLAAT']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Invalid value for _limit: BLAAT', $todos['body']['message']);

        $todos = $api->readCollection('todos', ['_offset' => 'BLAAT']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Invalid value for _offset: BLAAT', $todos['body']['message']);
    }

    public function testSortCollection()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_sort' => 'description', '_order' => 'DESC']);
        $this->assertEquals(200, $todos['code']);
        $this->assertEquals(3, $todos['headers']['X-Pagination-Total']);
        $this->assertEquals('watch tv', $todos['body'][0]['description']);
    }

    public function testSortCollectionUnknownProperty()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_sort' => 'foobar']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Cannot sort on unknown property: foobar', $todos['body']['message']);

        $todos = $api->readCollection('todos', ['_sort' => null]);
        $this->assertEquals(200, $todos['code']);
    }

    public function testSortCollectionInvalidOrder()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_sort' => 'description', '_order' => 'BLAAT']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Invalid value for _order: BLAAT', $todos['body']['message']);
    }

    public function testCollectionWithSpecificFields()
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

    public function testCollectionWithUnknownFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['_fields' => 'id,foobar']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Unknown _field foobar detected.', $todos['body']['message']);
    }

    public function testCollectionFilterUnknownFields()
    {
        $api = $this->getApiWithDataLoaded();

        $todos = $api->readCollection('todos', ['foo' => 'bar']);
        $this->assertEquals(400, $todos['code']);
        $this->assertEquals('Cannot filter on unknown property: foo', $todos['body']['message']);
    }

    public function testCreateNonExistingResource()
    {
        $api = new RestApi($this->database);
        $todo = $api->createResource('todos', array('description' => "hello world"));

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

        $todo = $api->createResource('todos', array('description' => "hello world"));

        $this->assertInternalType('array', $todo);
        $this->assertEquals(200, $todo['code']);
        $this->assertInternalType('array', $todo['body']);

        foreach (["id", "created", "updated", "user_id", "category", "description", "done", "urgency"] as $column) {
            $this->assertArrayHasKey($column, $todo['body']);
        }

        $this->assertEquals('tester', $todo['body']['user_id']);
        $this->assertEquals('inbox', $todo['body']['category']);
    }

    public function testCreateResourceWithPrimaryKey()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $todo = $api->createResource('todos', ['id' => 45, 'description' => "hello world"]);

        $this->assertInternalType('array', $todo);
        $this->assertEquals(400, $todo['code']);
        $this->assertInternalType('array', $todo['body']);
        $this->assertEquals('Not allowed to POST a primary key', $todo['body']['message']);
    }

    public function testCreateResourceWithUserId()
    {
        $api = $this->getApi();
        $api->setUser('tester');

        $todo = $api->createResource('todos', ['user_id' => 'rocco', 'description' => "hello world"]);

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
        $this->assertEquals('Missing fields: created, updated, user_id, category, description, done, urgency', $todo['body']['message']);
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

    public function testReadUnknownResource()
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

    public function testDeleteResource()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->deleteResource('todos', 1);
        $this->assertEquals(204, $todo['code']);

        $todo = $api->deleteResource('todos', 1);
        $this->assertEquals(404, $todo['code']);
    }

    public function testUpdateResource()
    {
        $api = $this->getApiWithDataLoaded();

        $todo = $api->updateResource('todos', 1, ['done' => 1]);
        $this->assertEquals(200, $todo['code']);
        $this->assertEquals(1, $todo['body']['done']);
    }
}
