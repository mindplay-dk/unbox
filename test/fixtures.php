<?php

use mindplay\unbox\Container;
use mindplay\unbox\ProviderInterface;

interface CacheProvider {}

class FileCache implements CacheProvider
{
    public function __construct($path)
    {
        $this->path = $path;
    }

    public $path;
}

class UserRepository
{
    /**
     * @var CacheProvider
     */
    public $cache;

    public function __construct(CacheProvider $cache)
    {
        $this->cache = $cache;
    }
}

class TestProviderInterface implements ProviderInterface
{
    public function register(Container $container)
    {
        $container->set('cache_path', '/tmp/cache');

        $container->register(CacheProvider::class, function ($cache_path) {
            return new FileCache($cache_path);
        });

        $container->register(UserRepository::class, function (CacheProvider $cache) {
            return new UserRepository($cache);
        });
    }
}
