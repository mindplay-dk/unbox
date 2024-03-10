<?php

use Pimple\Container;

return function () {

    $container = new Container();

    $container['cache_path'] = '/tmp/cache';

    $container[CacheProvider::class] = function ($container) {
        return new FileCache($container['cache_path']);
    };

    $container[UserRepository::class] = function ($container) {
        return new UserRepository($container[CacheProvider::class]);
    };

    return new Pimple\Psr11\Container($container);

};
