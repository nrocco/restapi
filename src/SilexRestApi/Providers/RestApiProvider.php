<?php

namespace SilexRestApi\Providers;

use RestApi\HashedStorage;
use RestApi\RestApi;
use SilexRestApi\Controllers\RestApiCrudController;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ServiceProviderInterface;

class RestApiProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    public function register(Application $app)
    {
        // register services
        $app['restapi.storage'] = $app->share(function() use ($app) {
            return new HashedStorage($app['restapi']['storage_path']);
        });

        $app['restapi.service'] = $app->share(function() use ($app) {
            $api = new RestApi($app['db'], $app['restapi']['schema_cache']);
            $api->setStorage($app['restapi.storage']);
            $api->setDebug($app['debug']);

            return $api;
        });

        // register controllers
        $app['restapi.controllers.crud'] = $app->share(function() use ($app) {
            return new RestApiCrudController($app['restapi.service']);
        });
    }

    public function boot(Application $app)
    {
    }

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        // index
        $controllers->get('/', 'restapi.controllers.crud:listResources');

        // collection routes
        $controllers->get('/{table}', 'restapi.controllers.crud:readCollection');
        $controllers->post('/{table}', 'restapi.controllers.crud:createResource');

        // resource routes
        $controllers->get('/{table}/{pk}', 'restapi.controllers.crud:readResource');
        $controllers->post('/{table}/{pk}', 'restapi.controllers.crud:updateResource');
        $controllers->put('/{table}/{pk}', 'restapi.controllers.crud:updateResource');
        $controllers->patch('/{table}/{pk}', 'restapi.controllers.crud:updateResource');
        $controllers->delete('/{table}/{pk}', 'restapi.controllers.crud:deleteResource');

        if (!empty($app['restapi.auth_checker'])) {
            $controllers->before($app['restapi.auth_checker']);  // TODO this should be moved to index.php
        }

        return $controllers;
    }
}

// $app->get('/files/{hash}', function($hash) use ($app) {
//     return $app->sendFile($app['api']->fetchFile($hash));
// });

// $app->get('/thumbs/{hash}', function($hash) use ($app) {
//     $thumb = $app['restapi']['thumbs_path']."/".$app['storage']->hashToFilePath($hash).".png";

//     try {
//         return $app->sendFile($thumb);
//     } catch (\Exception $e) {
//         return new \Symfony\Component\HttpFoundation\Response(null, 404);
//     }
// });
