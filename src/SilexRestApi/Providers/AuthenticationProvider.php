<?php

namespace SilexRestApi\Providers;

use Firebase\JWT\JWT;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['auth.jwt_validator'] = $app->protect(function($token) use ($app) {
            try {
                $decodedToken = JWT::decode($token, $app['auth.options']['secret_key'], array('HS256'));

                // TODO: check if the token is still valid and contains valid data
                $app['api']->setUser($decodedToken->user);
                return true;
            } catch(\Exception $e) {
                // TODO: handle this properly
            }

            return false;
        });

        $app['auth'] = $app->protect(function() use ($app) {
            if (true === $app['request']->cookies->has('TOKEN')) {
                $token = $app['request']->cookies->get('TOKEN');

                if (true === $app['auth.jwt_validator']($token)) {
                    return;
                }
            } elseif (true === $app['request']->server->has('PHP_AUTH_USER')) {
                $username = $app['request']->server->get('PHP_AUTH_USER');
                $password = $app['request']->server->get('PHP_AUTH_PW');

                if (true === password_verify($password, $app['restapi']['users'][$username])) {
                    $app['api']->setUser($username);
                    return;
                }
            } elseif (true === $app['request']->headers->has('authorization')) {
                $header = $app['request']->headers->get('authorization');

                if (0 === strpos($header, 'Bearer')) {
                    $token = str_replace('Bearer ', '', $header);

                    if (true === $app['auth.jwt_validator']($token)) {
                        return;
                    }
                }
            }

            if ('/login' === $app['request']->getRequestUri() and 'POST' === $app['request']->getMethod()) {
                return;
            }

            return $app->json(["message" => "Unauthorized"], 401);
        });

        $app->post('/login', function() use ($app) {
            $username = $app['request']->request->get('username');
            $password = $app['request']->request->get('password');

            if (isset($username) and password_verify($password, $app['restapi']['users'][$username])) {
                $token = JWT::encode(["user" => $username], $app['auth.options']['secret_key']);
                $cookie = new Cookie(
                    'TOKEN',
                    $token,
                    mktime()+$app['auth.options']['cookie_lifetime'],
                    $app['auth.options']['cookie_path'],
                    $app['auth.options']['cookie_domain'],
                    $app['auth.options']['cookie_secure'],
                    $app['auth.options']['cookie_httponly']
                );

                $response = new Response();
                $response->headers->setCookie($cookie);
                $response->headers->set('Content-Type', 'application/json');

                if (true === $app['request']->request->has('redirect')) {
                    $response->headers->set('Location', $app['request']->request->get('redirect'));
                    $response->setStatusCode(302);
                } else {
                    $response->setContent(json_encode(['token' => $token]));
                }

                return $response;
            }


            return $app->json(["message" => "Unauthorized"], 401);
        });
    }

    public function boot(Application $app)
    {
    }
}
