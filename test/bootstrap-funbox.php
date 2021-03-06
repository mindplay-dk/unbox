<?php

use mindplay\funbox\Context;

return call_user_func(function () {

    $context = new Context();

    $context->set('cache_path', '/tmp/cache');

    $context->register(
        CacheProvider::class,
        fn (#[name("cache_path")] string $path) => new FileCache($path)
    );

    $context->register(
        UserRepository::class,
        fn (CacheProvider $cache) => new UserRepository($cache)
    );

    return $context->createContainer();

});
