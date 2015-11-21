<?php

namespace SilexRestApi\Providers;

use Symfony\Component\HttpFoundation\Request;
use Silex\Application;
use Silex\ServiceProviderInterface;
use RestApi\RestApi;
use RestApi\HashedStorage;

class RestApiProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app->before(function (Request $request) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = json_decode($request->getContent(), true);
                $request->request->replace(is_array($data) ? $data : array());
            }
        });

        $app->view(function (array $response) use ($app) {
            return $app->json($response['body'], $response['code'], $response['headers']);
        });

        $app['storage'] = new HashedStorage($app['restapi']['storage_path']);

        $app['api'] = new RestApi($app['db'], $app['restapi']['schema_cache']);
        $app['api']->setStorage($app['storage']);
    }

    public function boot(Application $app)
    {
    }
}
