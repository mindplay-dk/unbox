<?php

use DI\ContainerBuilder;

return call_user_func(function () {

    $builder = new ContainerBuilder();

    $builder->useAnnotations(false);
    $builder->useAutowiring(false);

    $builder->addDefinitions([
        'cache_path' => '/tmp/cache',

        CacheProvider::class =>
            \DI\object(FileCache::class)->constructorParameter('path', \DI\get('cache_path')),

        UserRepository::class =>
            \DI\object()->constructorParameter('cache', \DI\get(CacheProvider::class))
    ]);

    return $builder->build();

});
