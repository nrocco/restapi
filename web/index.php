<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app->register(new SilexRestApi\Providers\ConfigProvider(__DIR__.'/../config.php'));
$app->register(new JDesrosiers\Silex\Provider\CorsServiceProvider(), [
    'cors.allowOrigin' => $app['cors.allowOrigin'],
    'cors.allowCredentials' => $app['cors.allowCredentials']
]);
$app->register(new Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => $app['db.options']
]);
$app->register(new SilexRestApi\Providers\AuthenticationProvider());
$app->register(new SilexRestApi\Providers\RestApiProvider());

$app->before($app["auth"]);
$app->after($app["cors"]);

$app->get('/', function() use ($app) {
    return $app['api']->listResources();
});

$app->get('/files/{hash}', function($hash) use ($app) {
    return $app->sendFile($app['api']->fetchFile($hash));
});

$app->get('/{table}', function($table) use ($app) {
    return $app['api']->readCollection($table, $app['request']->query->all());
});

$app->post('/{table}', function($table) use ($app) {
    $params = array_merge(
        $app['request']->request->all(),
        $app['request']->files->all()
    );
    return $app['api']->createResource($table, $params);
});

$app->get('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app['api']->readResource($table, $pk, $app['request']->query->all());
});

$app->match('/{table}/{pk}', function($table, $pk) use ($app) {
    $params = array_merge(
        $app['request']->request->all(),
        $app['request']->files->all()
    );
    return $app['api']->updateResource($table, $pk, $params);
})->method('POST|PUT|PATCH');

$app->delete('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app['api']->deleteResource($table, $pk);
});

$app->run();
