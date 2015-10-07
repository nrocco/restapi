<?php

require_once __DIR__.'/../vendor/autoload.php';

$config = require_once __DIR__.'/../config.php';

$app = new Silex\Application();
$app->register(new Silex\Provider\DoctrineServiceProvider(), array('db.options' => $config['db.options']));
$app->register(new RestApi\RestApiProvider());

$app->get('/', function() use ($app) {
    return $app->json($app['api']->listResources());
});

$app->get('/{table}', function($table) use ($app) {
    $response = $app['api']->readCollection($table, $app['request']->query->all());

    return $app->json($response['body'], $response['code']);
});

$app->post('/{table}', function($table) use ($app) {
    return $app->json(array('dkfj' => 'ldkdjf'));
});

$app->get('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app->json(array('dkfj' => 'ldkdjf'));
});

$app->post('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app->json(array('dkfj' => 'ldkdjf'));
});

$app->put('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app->json(array('dkfj' => 'ldkdjf'));
});

$app->patch('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app->json(array('dkfj' => 'ldkdjf'));
});

$app->delete('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app->json(array('dkfj' => 'ldkdjf'));
});

$app->run();
