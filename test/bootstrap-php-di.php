<?php

use DI\ContainerBuilder;

return call_user_func(function () {

    $builder = new ContainerBuilder();

    $builder->useAnnotations(false);
    $builder->useAutowiring(false);

    $builder->addDefinitions([
        'cache_path' => '/tmp/cache',

        CacheProvider::class =>
            \DI\create(FileCache::class)->constructor(\DI\get('cache_path')),

        UserRepository::class =>
            \DI\create()->constructor(\DI\get(CacheProvider::class))
    ]);

    return $builder->build();

});
