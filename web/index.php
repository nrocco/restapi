<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app->register(new SilexRestApi\Providers\ConfigProvider(__DIR__.'/../config.php'));
$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Silex\Provider\DoctrineServiceProvider(), ['db.options' => $app['db.options']]);

// $app->register(new JDesrosiers\Silex\Provider\CorsServiceProvider(), [
//     'cors.allowOrigin' => $app['cors.allowOrigin'],
//     'cors.allowCredentials' => $app['cors.allowCredentials']
// ]);

// $auth = new SilexRestApi\Providers\AuthenticationProvider();
$restapi = new SilexRestApi\Providers\RestApiProvider();

// $app->register($auth);
$app->register($restapi);

// $app->mount('/auth', $auth);
$app->mount('/', $restapi);

// $app->after($app["cors"]);

$app->run();
