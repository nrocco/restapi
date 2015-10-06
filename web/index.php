<?php

// web/index.php
require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$app->get('/', function() use ($app) {
    return json_encode(array());
});

$app->get('/{table}', function($table) use ($app) {
    return 'read collection';
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
