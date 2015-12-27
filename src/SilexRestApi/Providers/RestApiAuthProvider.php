<?php

namespace SilexRestApi\Providers;

use SilexRestApi\Controllers\RestApiAuthController;
use SilexRestApi\Services\AuthService;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RestApiAuthProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    public function register(Application $app)
    {
        $app['restapi.authentication'] = $app->share(function() use ($app) {
            $auth = new AuthService($app['restapi']['users']);
            $auth->setTokenOptions($app['restapi']['token']);
            $auth->setCookieOptions($app['restapi']['cookie']);

            return $auth;
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

        // Parse request body if Content-Type: application/json
        $controllers->before($app['restapi.listener.request_json']);

        $controllers->post('/login', function(Request $request) use ($app) {
            if ($request->request->has('username') and $request->request->has('password')) {
                $username = $request->request->get('username');
                $password = $request->request->get('password');

                if (true === $app['restapi.authentication']->verifyCredentials($username, $password)) {
                    $token = $app['restapi.authentication']->createJwtTokenForUser($username);
                    $cookie = $app['restapi.authentication']->createCookieForToken($token);

                    $response = new JsonResponse([
                        'username' => $username,
                        'token' => $token,
                    ]);
                    $response->headers->setCookie($cookie);

                    if (true === $request->request->has('redirect')) {
                        $response->headers->set('Location', $request->request->get('redirect'));
                        $response->setStatusCode(302);
                    }

                    return $response;
                }
            }

            return new JsonResponse(['message' => 'Unauthorized'], 401);
        });

        $controllers->post('/logout', function() use ($app) {
            $response = new JsonResponse(['message' => 'Logged out'], 200);
            $cookie = $app['restapi.authentication']->deleteCookie();
            $response->headers->setCookie($cookie);

            return $response;
        });

        return $controllers;
    }
}
