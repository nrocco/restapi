<?php

require_once __DIR__.'/../vendor/autoload.php';

$config = require_once __DIR__.'/../config.php';

$app = new Silex\Application();
$app->register(new Silex\Provider\DoctrineServiceProvider(), array('db.options' => $config['db.options']));
$app->register(new RestApi\RestApiProvider());

$app['debug'] = true;

$app->before(function (\Symfony\Component\HttpFoundation\Request $request) use ($app, $config) {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        return $app->json(null, 401);
    }

    if ($config['restapi']['users'][$_SERVER['PHP_AUTH_USER']] !== $_SERVER['PHP_AUTH_PW']) {
        return $app->json(null, 403);
    }
});

$app->before(function (\Symfony\Component\HttpFoundation\Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

$app->view(function (array $response) use ($app) {
    return $app->json($response['body'], $response['code']);
});

$app->get('/', function() use ($app) {
    return $app['api']->listResources();
});

$app->get('/{table}', function($table) use ($app) {
    return $app['api']->readCollection($table, $app['request']->query->all());
});

$app->post('/{table}', function($table) use ($app) {
    return $app['api']->createResource($table, array_merge($app['request']->request->all(), $app['request']->files->all()));
});

$app->get('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app['api']->readResource($table, $pk, $app['request']->query->all());
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
    return $app['api']->deleteResource($table, $pk);
});

$app->run();
