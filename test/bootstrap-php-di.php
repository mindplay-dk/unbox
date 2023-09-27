<?php

use DI\ContainerBuilder;

return function ($compiled = false) {

    $builder = new ContainerBuilder();

    if ($compiled) {
        $builder->enableCompilation(__DIR__ . "/.php-di");
    }

    $builder->useAutowiring(false);

    $builder->addDefinitions([
        'cache_path' => '/tmp/cache',

        CacheProvider::class =>
            \DI\create(FileCache::class)->constructor(\DI\get('cache_path')),

        UserRepository::class =>
            \DI\create()->constructor(\DI\get(CacheProvider::class))
    ]);

    return $builder->build();

};
