<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app->register(new SilexRestApi\Providers\ConfigProvider(__DIR__.'/../config.php'));
$app->register(new Silex\Provider\DoctrineServiceProvider());
$app->register($auth = new SilexRestApi\Providers\RestApiAuthProvider());
$app->register($restapi = new SilexRestApi\Providers\RestApiProvider());

$app->mount('/auth', $auth);
$app->mount('/', $restapi);

$app->run();
