<?php

namespace SilexRestApi\Providers;

use RestApi\HashedStorage;
use RestApi\RestApi;
use SilexRestApi\Controllers\RestApiCrudController;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class RestApiProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    public function register(Application $app)
    {
        // register services
        $app['restapi.storage'] = $app->share(function () use ($app) {
            return new HashedStorage($app['restapi']['storage_path']);
        });

        $app['restapi.service'] = $app->share(function () use ($app) {
            $api = new RestApi($app['db'], $app['restapi']['schema_cache']);
            $api->setStorage($app['restapi.storage']);
            $api->setDebug($app['debug']);

            return $api;
        });

        $app['restapi.listener.request_json'] = $app->protect(function () use ($app) {
            if (0 === strpos($app['request']->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($app['request']->getContent(), true);
                $app['request']->request->replace(is_array($data) ? $data : array());
            }
        });

        $app['restapi.listener.response_json'] = $app->protect(function (array $response) use ($app) {
            return $app->json($response['body'], $response['code'], $response['headers']);
        });
    }

    public function boot(Application $app)
    {
    }

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        // Parse request body if Content-Type: application/json
        $controllers->before($app['restapi.listener.request_json']);

        $app->view($app['restapi.listener.response_json']);

        if (!empty($app['restapi.auth_checker'])) {
            $controllers->before($app['restapi.auth_checker']);
        }

        // index
        $controllers->get('/', function () use ($app) {
            return $app['restapi.service']->listResources();
        });

        $controllers->get('/files/{hash}', function ($hash) use ($app) {
            return $app->sendFile($app['restapi.service']->fetchFile($hash));
        });

        $controllers->get('/thumbs/{hash}', function ($hash) use ($app) {
            try {
                return $app->sendFile(
                    $app['restapi']['thumbs_path'].'/'.$app['restapi.storage']->hashToFilePath($hash).'.png'
                );
            } catch (\Exception $e) {
                return new Response(null, 404);
            }
        });

        // collection routes
        $controllers->get('/{table}', function (Request $request, $table) use ($app) {
            return $app['restapi.service']->readCollection($table, $request->query->all());
        });
        $controllers->post('/{table}', function (Request $request, $table) use ($app) {
            $params = array_merge($request->request->all(), $request->files->all());
            return $app['restapi.service']->createResource($table, $params);
        });

        // resource routes
        $controllers->get('/{table}/{pk}', function (Request $request, $table, $pk) use ($app) {
            return $app['restapi.service']->readResource($table, $pk, $request->query->all());
        });
        $controllers->patch('/{table}/{pk}', function (Request $request, $table, $pk) use ($app) {
            $params = array_merge($request->request->all(), $request->files->all());
            return $app['restapi.service']->updateResource($table, $pk, $params);
        });
        $controllers->delete('/{table}/{pk}', function ($table, $pk) use ($app) {
            return $app['restapi.service']->deleteResource($table, $pk);
        });

        return $controllers;
    }
}
