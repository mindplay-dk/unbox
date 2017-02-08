<?php

use mindplay\unbox\Container;
use mindplay\unbox\ContainerFactory;
use mindplay\unbox\ProviderInterface;

function test_func($foo) {
    return $foo;
}

class Foo {
    public function bar($foo) {
        return $foo;
    }

    public static function bat($foo) {
        return $foo;
    }

    public function __invoke($foo) {
        return $foo;
    }
}

class Bar {
    public $value = 1;
}

abstract class AbstractClass
{
    const CACHE_PATH = '/foo';

    public static function staticFunc(FileCache $cache)
    {
        $cache->path = self::CACHE_PATH;
    }
}

interface CacheProvider {}

class FileCache implements CacheProvider
{
    /**
     * @var string
     */
    public $path;

    public function __construct($path)
    {
        $this->path = $path;
    }
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

class TestProvider implements ProviderInterface
{
    public function register(ContainerFactory $container)
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
