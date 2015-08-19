<?php

use Pimple\Container;

return call_user_func(function () {

    $container = new Container();

    $container['cache_path'] = '/tmp/cache';

    $container[CacheProvider::class] = function (Container $container) {
        return new FileCache($container['cache_path']);
    };

    $container[UserRepository::class] = function (Container $container) {
        return new UserRepository($container[CacheProvider::class]);
    };

    return $container;

});
