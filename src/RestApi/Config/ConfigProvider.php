<?php

namespace RestApi\Config;

use Silex\Application;
use Silex\ServiceProviderInterface;

class ConfigProvider implements ServiceProviderInterface
{
    protected $filename;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function register(Application $app)
    {
        $config = require $this->filename;

        foreach ($config as $key => $value) {
            $app[$key] = $value;
        }
    }

    public function boot(Application $app)
    {
    }
}
