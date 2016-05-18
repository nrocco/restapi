<?php

namespace SilexRestApi\Providers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RestApi\HashedStorage;
use RestApi\RestApi;
use SilexRestApi\Controllers\RestApiCrudController;
use SilexRestApi\Middleware\CorsMiddleware;
use SilexRestApi\Services\AuthService;
use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RestApiProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    public function register(Container $app)
    {
        $app['restapi.storage'] = function () use ($app) {
            return new HashedStorage($app['restapi']['storage_path']);
        };

        $app['restapi.service'] = function () use ($app) {
            $api = new RestApi($app['db'], $app['restapi']['schema_cache']);
            $api->setStorage($app['restapi.storage']);
            $api->setDebug($app['debug']);

            return $api;
        };

        if (isset($app['restapi']['auth'])) {
            $app['restapi.auth'] = function () use ($app) {
                $auth = new AuthService($app['restapi']['auth']['users']);
                $auth->setTokenOptions($app['restapi']['auth']['token']);
                $auth->setCookieOptions($app['restapi']['auth']['cookie']);

                return $auth;
            };

            $app['restapi.listener.auth_checker'] = $app->protect(function (Request $request) use ($app) {
                if (!$user = $app['restapi.auth']->getAuthenticatedUserFromRequest($request)) {
                    return new Response(null, 401, ['Content-Type' => 'application/json']);
                }

                $app['restapi.service']->setUser($user);
            });
        }

        if (isset($app['restapi']['cors'])) {
            $app['restapi.middleware.cors'] = function () use ($app) {
                return new CorsMiddleware($app['restapi']['cors']);
            };
        }
    }

    public function connect(Application $app)
    {
        $app->view(function (array $result, Request $request) use ($app) {
            return $app->json($result['body'], $result['code'], $result['headers']);
        });

        $app->before(function (Request $request) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : []);
            }
        });

        if (isset($app['restapi']['cors'])) {
            $app->before(function (Request $request, Application $app) {
                return $app['restapi.middleware.cors']->processRequest($request);
            }, Application::EARLY_EVENT);

            $app->after(function (Request $request, Response $response, Application $app) {
                return $app['restapi.middleware.cors']->processResponse($request, $response);
            });
        }

        $controllers = $app['controllers_factory'];

        if (isset($app['restapi']['auth'])) {
            $controllers->post('/auth/login', function (Request $request) use ($app) {
                if (!$request->request->has('username') or !$request->request->has('password')) {
                    return new Response(null, 400, ['Content-Type' => 'application/json']);
                }

                $username = $request->request->get('username');
                $password = $request->request->get('password');

                if (true !== $app['restapi.auth']->verifyCredentials($username, $password)) {
                    return new Response(null, 401, ['Content-Type' => 'application/json']);
                }

                $response = new Response([
                    'username' => $username,
                    'token' => $app['restapi.auth']->createJwtTokenForUser($username),
                ]);

                $response->headers->setCookie($app['restapi.auth']->createCookieForToken($token));

                if (true === $request->request->has('redirect')) {
                    $response->headers->set('Location', $request->request->get('redirect'));
                    $response->setStatusCode(302);
                }

                $response->headers->set('Content-Type', 'application/json');

                return $response;
            });

            $controllers->post('/auth/logout', function () use ($app) {
                $response = new Response(null, 204, ['Content-Type' => 'application/json']);
                $cookie = $app['restapi.auth']->deleteCookie();
                $response->headers->setCookie($cookie);

                return $response;
            });
        }

        $resources = $app['controllers_factory'];

        if (isset($app['restapi']['auth'])) {
            $resources->before($app['restapi.listener.auth_checker']);
        }

        $resources->get('/', function () use ($app) {
            return $app['restapi.service']->listResources();
        });

        $resources->get('/files/{hash}', function ($hash) use ($app) {
            return $app->sendFile($app['restapi.service']->fetchFile($hash));
        });

        $resources->get('/thumbs/{hash}', function ($hash) use ($app) {
            try {
                return $app->sendFile($app['restapi']['thumbs_path'].'/'.$app['restapi.storage']->hashToFilePath($hash).'.png');
            } catch (\Exception $e) {
                return new Response(null, 404);
            }
        });

        $resources->get('/{table}', function (Request $request, $table) use ($app) {
            return $app['restapi.service']->readCollection($table, $request->query->all());
        });

        $resources->post('/{table}', function (Request $request, $table) use ($app) {
            return $app['restapi.service']->createResource($table, array_merge($request->request->all(), $request->files->all()));
        });

        $resources->get('/{table}/{pk}', function (Request $request, $table, $pk) use ($app) {
            return $app['restapi.service']->readResource($table, $pk, $request->query->all());
        });

        $resources->match('/{table}/{pk}', function (Request $request, $table, $pk) use ($app) {
            return $app['restapi.service']->updateResource($table, $pk, array_merge($request->request->all(), $request->files->all()));
        })->method('POST|PATCH');

        $resources->delete('/{table}/{pk}', function ($table, $pk) use ($app) {
            return $app['restapi.service']->deleteResource($table, $pk);
        });

        $controllers->mount('/', $resources);

        return $controllers;
    }
}
