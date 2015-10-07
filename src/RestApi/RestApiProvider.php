<?php

namespace RestApi;

use Silex\Application;
use Silex\ServiceProviderInterface;
use RestApi\RestApi;

class RestApiProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['api'] = new RestApi($app['db']);
    }

    public function boot(Application $app)
    {
    }
}
