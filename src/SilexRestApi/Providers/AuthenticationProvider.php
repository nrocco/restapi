<?php

namespace SilexRestApi\Providers;

use SilexRestApi\Controllers\RestApiAuthController;
use SilexRestApi\Services\AuthService;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ServiceProviderInterface;

class AuthenticationProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    public function register(Application $app)
    {
        $app['restapi.authentication'] = $app->share(function() use ($app) {
            return new AuthService($app['auth.options'], $app['restapi']['users']);
        });

        $app['restapi.auth_checker'] = $app->protect(function () use ($app) {
            // if ('OPTIONS' === $app['request']->getMethod()) {
            //     return;  // this is a cors preflight request
            // }

            if ($user = $app['restapi.authentication']->getAuthenticatedUserFromRequest($app['request'])) {
                $app['restapi.service']->setUser($user);

                return;
            }

            return $app->json(['message' => 'Unauthorized'], 401);
        });

        // register controllers
        $app['restapi.controllers.auth'] = $app->share(function() use ($app) {
            return new RestApiAuthController($app['restapi.authentication']);
        });
    }

    public function boot(Application $app)
    {
    }

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        // collection routes
        $controllers->post('/login', 'restapi.controllers.auth:login');
        $controllers->post('/logout', 'restapi.controllers.auth:logout');

        return $controllers;
    }
}
