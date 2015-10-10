<?php

namespace RestApi;

use Silex\Application;
use Silex\ServiceProviderInterface;
use RestApi\RestApi;
use RestApi\Storage\HashedStorage;

class RestApiProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $storage = new HashedStorage($app['restapi']['storage_path']);

        $app['api'] = new RestApi($app['db']);
        $app['api']->setStorage($storage);
    }

    public function boot(Application $app)
    {
    }
}
