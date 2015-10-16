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
            array('url' => 'sqlite:///:memory:'),
            new Configuration()
        );
    }

    protected function createSchema()
    {
        $schema = new Schema();

        $todos = $schema->createTable("todos");
        $todos->addColumn("id", "integer", array("unsigned" => true));
        $todos->addColumn("title", "string", array("length" => 32));
        $todos->addColumn("description", "text");
        $todos->setPrimaryKey(array("id"));

        $queries = $schema->toSql($this->database);

        var_dump($queries);die();
    }

    public function testNoResources()
    {
        $api = new RestApi($this->database);

        $resources = $api->listResources();

        $this->assertEmpty($resources['body']);
        $this->assertEquals(200, $resources['code']);
        $this->assertEmpty($resources['headers']);
    }

    public function testResources()
    {
        $sm = $this->database->getSchemaManager();
        $tables = $sm->listTables();

        // $this->createSchema();

        $api = new RestApi($this->database);
    }
}
