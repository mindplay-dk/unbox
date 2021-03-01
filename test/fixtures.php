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


class ClassWithOptionalDependency
{
    /**
     * @var OptionalDependency|null
     */
    public $dep;

    public function __construct(?OptionalDependency $dep)
    {
        $this->dep = $dep;
    }
}

class OptionalDependency
{}

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

class UserController
{
    /**
     * @var UserRepository
     */
    public $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
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

class CustomContainerFactory extends ContainerFactory
{
    /**
     * @return CustomContainer
     */
    public function createContainer()
    {
        return new CustomContainer($this);
    }
}

class CustomContainer extends Container
{
    /**
     * @param $name
     *
     * @return mixed
     */
    public function getAutoWired($name)
    {
        if (! $this->has($name)) {
            $this->inject($name, $this->create($name));
        }

        return $this->get($name);
    }
}
