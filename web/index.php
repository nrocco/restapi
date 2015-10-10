<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app->register(new RestApi\Config\ConfigProvider(__DIR__.'/../config.php'));
$app->register(new JDesrosiers\Silex\Provider\CorsServiceProvider(), [
    'cors.allowOrigin' => $app['cors.allowOrigin'],
    'cors.allowCredentials' => $app['cors.allowCredentials']
]);
$app->register(new Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => $app['db.options']
]);
$app->register(new Silex\Provider\SessionServiceProvider(), [
    'session.storage.options' => $app['session.storage.options']
]);
$app->register(new RestApi\RestApiProvider());

// add cors
$app->after($app["cors"]);

$mustBeLogged = function () use ($app) {
    if (true === $app['request']->cookies->has('PHPSESSID')) {
        // TODO: check if the session is still valid and contains valid data
        $app['api']->setUser($app['session']->get('user')['username']);
        return;
    }

    if (false === $app['request']->server->has('PHP_AUTH_USER')) {
        return $app->json(["message" => "Unauthorized"], 401);
    }

    $username = $app['request']->server->get('PHP_AUTH_USER');
    $password = $app['request']->server->get('PHP_AUTH_PW');

    if ($app['restapi']['users'][$username] !== $password) {
        return $app->json(["message" => "Unauthorized"], 401);
    }

    $app['api']->setUser($username);
};

$app->before(function (\Symfony\Component\HttpFoundation\Request $request) {
    if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : array());
    }
});

$app->view(function (array $response) use ($app) {
    $response['headers']['X-Has-Session'] = (session_status() === PHP_SESSION_ACTIVE) ? 'yes' : 'no';
    return $app->json($response['body'], $response['code'], $response['headers']);
});

$app->post('/login', function(Silex\Application $app) {
    $username = $app['request']->request->get('username', false);
    $password = $app['request']->request->get('password');
    $redirect = $app['request']->request->get('redirect', '/');

    if (!isset($username) || $app['restapi']['users'][$username] !== $password) {
        return $app->json(["message" => "Unauthorized"], 401);
    }

    $app['session']->set('user', array('username' => $username));

    return $app->redirect($redirect);
});

$app->get('/files/{hash}', function($hash) use ($app) {
    return $app->sendFile($app['api']->fetchFile($hash));
})->before($mustBeLogged);

$app->get('/', function() use ($app) {
    return $app['api']->listResources();
})->before($mustBeLogged);

$app->get('/{table}', function($table) use ($app) {
    return $app['api']->readCollection($table, $app['request']->query->all());
})->before($mustBeLogged);

$app->post('/{table}', function($table) use ($app) {
    $params = array_merge(
        $app['request']->request->all(),
        $app['request']->files->all()
    );
    return $app['api']->createResource($table, $params);
})->before($mustBeLogged);

$app->get('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app['api']->readResource($table, $pk, $app['request']->query->all());
})->before($mustBeLogged);

$app->match('/{table}/{pk}', function($table, $pk) use ($app) {
    $params = array_merge(
        $app['request']->request->all(),
        $app['request']->files->all()
    );
    return $app['api']->updateResource($table, $pk, $params);
})->method('POST|PUT|PATCH')->before($mustBeLogged);

$app->delete('/{table}/{pk}', function($table, $pk) use ($app) {
    return $app['api']->deleteResource($table, $pk);
})->before($mustBeLogged);

$app->run();
