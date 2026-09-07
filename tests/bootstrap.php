<?php

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Container\Container;

if (! function_exists('app')) {
    /**
     * Minimal container bridge so the package can resolve services
     * outside of a full Laravel application.
     */
    function app(?string $abstract = null, array $parameters = [])
    {
        $container = Container::getInstance();

        return is_null($abstract) ? $container : $container->make($abstract, $parameters);
    }
}

Container::setInstance(new Container());