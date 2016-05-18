<?php

namespace SilexRestApi\Providers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ConfigProvider implements ServiceProviderInterface
{
    protected $filename;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function register(Container $app)
    {
        $config = include $this->filename;

        foreach ($config as $key => $value) {
            $app[$key] = $value;
        }
    }
}
