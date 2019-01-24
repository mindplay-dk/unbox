<?php

use mindplay\unbox\Container;
use mindplay\unbox\ContainerFactory;
use mindplay\unbox\ProviderInterface;
use Psr\Container\ContainerInterface;

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

// TODO remove support for inject() ?
//
// NOTE: the addition of the `$parent` argument to `createContainer()` is a breaking
//       change in older versions of PHP, so maybe it's time to think about getting
//       rid of 5.x support and clean up any BC support code and tag a major release?
//
//       if so, we should consider getting rid of `inject()` and the test-dependencies
//       below - adding this was a mistake; the Container should not be mutable, not
//       even internally... the example below (and related docs) should be updated
//       to demonstrate an alternative pattern, where, instance of "auto-wiring", we
//       build a simple service locator (e.g. for controllers) which internally
//       caches ever controller instance. (the net effect is a service locator anyway!)
//
//       if we're doing all that an tagging a major release anyhow, we might as well
//       comb the codebase and add static type-hinting for 7.x where possible.
//
//       if we're going all the way, we might as well declare ContainerFactory and
//       Container as `final` and prevent any similar design mistakes in the future.
//
//       I'd also like to change the terminology from `ContainerFactory` to `Context`,
//       which would make sense with the proposed support for sub-contexts.
//
//       did this just turn into a roadmap for version 3.0 ?

class CustomContainerFactory extends ContainerFactory
{
    public function createContainer(ContainerInterface $parent = null)
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
