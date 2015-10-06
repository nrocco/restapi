<?php

namespace RestApi\Database;

use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Class DataBaseMetaProvider
 *
 * @author Nico Di Rocco <n.dirocco@tech.leaseweb.com>
 */
class DataBaseMetaProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        print_r($app['db']->getDriver());die();
        $app['dbmeta'] = '';
    }

    public function boot(Application $app)
    {
    }
}
