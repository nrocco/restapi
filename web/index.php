<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app->register(new SilexRestApi\Providers\ConfigProvider(__DIR__.'/../config.php'));
$app->register(new Silex\Provider\MonologServiceProvider());
$app->register(new Silex\Provider\DoctrineServiceProvider());
$app->register($restapi = new SilexRestApi\Providers\RestApiProvider());
$app->mount('/', $restapi);
$app->run();
