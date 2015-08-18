<?php

use mindplay\unbox\Container;
use mindplay\unbox\ContainerException;
use mindplay\unbox\NotFoundException;

require __DIR__ . '/header.php';

// TESTS:

test(
    'Container: can create components',
    function () {
        $c = new Container();

        $c->register('a', function () { return 'A'; });
        $c->register('b', function () { return 'B'; });

        ok($c->has('a'), 'first component defined');
        ok($c->has('b'), 'second component defined');

        ok(!$c->has('c'), 'no third component defined');

        $c->set('c', 'C');

        ok($c->has('c'), 'third component initialized directly');

        ok(!$c->isActive('a'), 'first component not yet active');
        ok(!$c->isActive('b'), 'first component not yet active');

        eq($c->get('a'), 'A', 'returns the first component');

        ok($c->isActive('a'), 'first component activated');
        ok(!$c->isActive('b'), 'second component still not active');

        eq($c->get('b'), 'B', 'returns the second component');
        ok($c->isActive('b'), 'second component activated');

        expect(
            NotFoundException::class,
            'should throw on attempted to get undefined component',
            function () use ($c) {
                $c->get('nope');
            }
        );

        expect(
            ContainerException::class,
            'should throw on attempt to register initialized component',
            function () use ($c) {
                $c->register('a', function () {});
            }
        );

        expect(
            ContainerException::class,
            'should throw on attempt to set initialized component',
            function () use ($c) {
                $c->register('a', function () {});
            }
        );
    }
);

test(
    'Container: can override components',
    function () {
        $c = new Container();

        $c->register('a', function () { return 'A'; });
        $c->register('B', function () { return 'B'; });
        $c->set('c', 'C');
        $c->set('d', 'D');

        $c->register('a', function () { return 'AA'; });
        $c->set('b', 'BB');
        $c->register('c', function () { return 'CC'; });
        $c->set('d', 'DD');

        eq($c->get('a'), 'AA', 'can override registered component');
        eq($c->get('b'), 'BB', 'can overwrite registered component');
        eq($c->get('c'), 'CC', 'can override set component');
        eq($c->get('d'), 'DD', 'can overwrite set component');

        expect(
            ContainerException::class,
            'attempted override of registered component after initialization',
            function () use ($c) {
                $c->register('a', function () { return 'AA'; });
            }
        );

        $c->set('b', 'BBB');
        eq($c->get('b'), 'BBB', 'can overwrite registered component after initialization');

        expect(
            ContainerException::class,
            'attempted override of set component after initialization',
            function () use ($c) {
                $c->set('c', function () { return 'CC'; });
            }
        );

        $c->set('d', 'DDD');
        eq($c->get('d'), 'DDD', 'can overwrite set component after initialization');
    }
);

test(
    'Container: can configure registered component',
    function () {
        $c = new Container();

        $c->register('a', function () { return 1; });
        $c->set('b', 2);

        $c->configure('a', function (&$a) { $a += 1; });
        $c->configure('b', function (&$b) { $b += 1; });

        $c->configure('a', function (&$a) { $a += 1; });
        $c->configure('b', function (&$b) { $b += 1; });

        eq($c->get('a'), 3);
        eq($c->get('b'), 4);

        expect(
            NotFoundException::class,
            'should throw on attempt to configure undefined component',
            function () use ($c) {
                $c->configure('nope', function () {});
            }
        );
    }
);

test(
    'can resolve dependencies by name',
    function () {
        $container = new Container();

        $container->set('cache.path', '/tmp/cache');

        $container->register(CacheProvider::class, function (Container $c) {
            return new FileCache($c->get('cache.path'));
        });

        $container->register(UserRepository::class, function (Container $c) {
            return new UserRepository($c->get(CacheProvider::class));
        });

        $repo = $container->get(UserRepository::class);

        ok($repo instanceof UserRepository);
        ok($repo->cache instanceof CacheProvider);
        eq($repo->cache->path, '/tmp/cache');
    }
);

test(
    'can resolve dependencies using parameter names',
    function () {
        $container = new Container();

        $container->set('cache_path', '/tmp/cache');

        $container->register(CacheProvider::class, function ($cache_path) {
            return new FileCache($cache_path);
        });

        $container->register(UserRepository::class, function (CacheProvider $cache) {
            return new UserRepository($cache);
        });

        $repo = $container->get(UserRepository::class);

        ok($repo instanceof UserRepository);
        ok($repo->cache instanceof CacheProvider);
        eq($repo->cache->path, '/tmp/cache');
    }
);

test(
    'can resolve dependencies using dependency maps',
    function () {
        $container = new Container();

        $container->set('cache.path', '/tmp/cache');

        $container->register(
            'cache',
            function ($path) {
                return new FileCache($path);
            },
            ['cache.path']
        );

        $container->register(
            UserRepository::class,
            function (CacheProvider $cp) {
                return new UserRepository($cp);
            },
            ['cp' => 'cache']
        );

        $repo = $container->get(UserRepository::class);

        ok($repo instanceof UserRepository);
        ok($repo->cache instanceof CacheProvider);
        eq($repo->cache->path, '/tmp/cache');
    }
);

test(
    'can act as a factory',
    function () {
        $container = new Container();

        $container->register(
            CacheProvider::class,
            function () {
                return new FileCache('/tmp/cache');
            }
        );

        $repo = $container->create(UserRepository::class);

        ok($repo instanceof UserRepository);

        $another = $container->create(UserRepository::class);

        ok($repo !== $another);
    }
);

test(
    'can override factory maps',
    function () {
        $container = new Container();

        $container->set('cache_path', '/tmp/cache');

        $container->register('cache', function ($cache_path) {
            return new FileCache($cache_path);
        });

        $cache = $container->create('cache');

        eq($cache->path, '/tmp/cache');

        $cache = $container->create('cache', [$container->value('/my/path')]);

        eq($cache->path, '/my/path');
    }
);

configure()->enableCodeCoverage(__DIR__ . '/build/clover.xml', dirname(__DIR__) . '/src');

exit(run()); // exits with errorlevel (for CI tools etc.)
