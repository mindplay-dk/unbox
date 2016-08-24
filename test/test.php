<?php

use Interop\Container\ContainerInterface;
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

        $c->register('x', Foo::class);
        ok($c->get('x') instanceof Foo, 'registers a default factory function when $func is a name');

        $c->register(Foo::class);
        ok($c->get(Foo::class) instanceof Foo, 'registers a default factory function when $func is NULL');

        $c->register(FileCache::class, ['/tmp/foo']);
        ok($c->get(FileCache::class) instanceof FileCache, 'registers a default factory function when $func is a map');

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

        expect(
            InvalidArgumentException::class,
            'should throw on invalid argument',
            function () use ($c) {
                $c->register('z', (object) []);
            }
        );
    }
);

test(
    'Container: can override components',
    function () {
        $c = new Container();

        $c->register('a', function () { return 'A'; });
        $c->register('a', function () { return 'AA'; });
        eq($c->get('a'), 'AA', 'can override registered component');

        $c->register('b', function () { return 'B'; });
        $c->set('b', 'BB');
        eq($c->get('b'), 'BB', 'can overwrite registered component');

        $c->set('c', 'C');
        $c->register('c', function () { return 'CC'; });
        eq($c->get('c'), 'CC', 'can override set component');

        $c->set('d', 'D');
        $c->set('d', 'DD');
        eq($c->get('d'), 'DD', 'can overwrite set component');

        foreach (['a', 'b', 'c', 'd'] as $id) {
            expect(
                ContainerException::class,
                'should throw on attempted override of active component',
                function () use ($c, $id) {
                    $c->register($id, function () { return 'VALUE'; });
                },
                "/attempted re-registration of active component: {$id}/"
            );

            expect(
                ContainerException::class,
                'should throw on attempted overwrite of active component',
                function () use ($c, $id) {
                    $c->set($id, 'VALUE');
                },
                "/attempted overwrite of initialized component: {$id}/"
            );
        }
    }
);

test(
    'Container: can configure registered components',
    function () {
        $c = new Container();

        $c->register('a', function () { return 1; }); // $a = 1
        $c->set('b', 2); // $b = 2

        $c->configure('a', function ($a) { return $a + 1; }); // $a = 2
        $c->configure('a', function ($a) { return $a + 1; }); // $a = 3

        $c->configure('b', function ($b) { return $b + 1; }); // $b = 3
        $c->configure(function ($b) { return $b + 1; }); // $b = 4 (component name "b" inferred from param name)

        $c->configure('b', function ($b) { $b += 1; }); // no change

        eq($c->get('a'), 3, 'can apply multiple configuration functions');
        eq($c->get('b'), 4, 'can infer component name from param name');

        $c = new Container();

        $c->register(Foo::class);

        $c->register('zap', Bar::class);

        $ok = false;

        $c->configure(
            Foo::class,
            function (Foo $foo, Bar $bar) use (&$ok) {
                $ok = true;
            },
            ['bar' => $c->ref('zap')]
        );

        $got_foo = false;

        $c->configure(function (Foo $few) use (&$got_foo) {
            $got_foo = true;
        });

        $c->get(Foo::class);

        ok($ok, 'can use parameter list/map in calls to configure()');
        ok($got_foo, 'can infer component name from argument type-hint');

        $c = new Container();

        $c->configure("foo", function ($foo) { return $foo + 1; });

        $c->set("foo", 1);

        eq($c->get("foo"), 2, 'can apply configuration to directly injected values');

        $c = new Container();

        $c->set(FileCache::class, new FileCache('/tmp'));

        $c->configure([AbstractClass::class, "staticFunc"]);

        eq($c->get(FileCache::class)->path, AbstractClass::CACHE_PATH, "can use static configuration function");
    }
);

test(
    'configure("name", function (T $o))',
    function () {
        $container = new Container();

        $container->register('name', Bar::class);

        $container->configure('name', function (Bar $bar) {
            $bar->value += 1;
        });

        eq($container->get('name')->value, 2, 'can configure named component');
    }
);

test(
    'named components take precedence over type-hints',
    function () {
        $container = new Container();

        $container->register(FileCache::class, ["/by-type"]);

        $container->register("cache", FileCache::class, ["/by-name"]);

        /** @var FileCache|null $conf_by_type */
        $conf_by_type = null;

        $container->configure(function (FileCache $cache) use (&$conf_by_type) {
            $conf_by_type = $cache;
        });

        /** @var FileCache|null $conf_by_name */
        $conf_by_name = null;

        $container->configure(function ($cache) use (&$conf_by_name) {
            $conf_by_name = $cache;
        });

        /** @var FileCache|null $by_type */
        $by_type = null;

        $container->call(function (FileCache $cache) use (&$by_type) {
            $by_type = $cache;
        });

        /** @var FileCache|null $by_name */
        $by_name = null;

        $container->call(function ($cache) use (&$by_name) {
            $by_name = $cache;
        });

        eq($container->get(FileCache::class)->path, "/by-type");
        eq($container->get("cache")->path, "/by-name");

        eq($by_type->path, "/by-type");
        eq($by_name->path, "/by-name");

        eq($conf_by_type->path, "/by-type");
        eq($conf_by_name->path, "/by-name");
    }
);

test(
    'can call all the things',
    function () {
        $container = new Container();

        $container->set('foo', 'bar');

        eq($container->call('test_func'), 'bar', 'can call function');

        eq($container->call([Foo::class, 'bat']), 'bar', 'can call static method');

        $foo = new Foo();

        eq($container->call([$foo, 'bar']), 'bar', 'can call instance method');

        eq($container->call($foo), 'bar', 'can call __invoke()');

        eq($container->call(function ($foo) { return $foo; }), 'bar', 'can call Closure');

        eq($container->call(function ($nope = 'nope') { return $nope ? 'yep' : 'whoa'; }), 'yep', 'can supply default argument');
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

        $container = new Container();

        expect(
            ContainerException::class,
            "should throw for unresolvable components",
            function () use ($container) {
                $container->call(function (FileCache $cache) {});
            }
        );
    }
);

test(
    'can alias names',
    function () {
        $container = new Container();

        $container->register(FileCache::class, ['path' => '/tmp/foo']);

        $container->alias(CacheProvider::class, FileCache::class);

        eq($container->get(FileCache::class), $container->get(CacheProvider::class), 'alias return same singleton');
    }
);

/**
 * @param ContainerInterface $container
 */
function test_case(ContainerInterface $container)
{
    $repo = $container->get(UserRepository::class);

    ok($repo instanceof UserRepository);
    ok($repo->cache instanceof CacheProvider);
    eq($repo->cache->path, '/tmp/cache');
}

test(
    'can resolve dependencies using parameter names',
    function () {
        $container = require __DIR__ . '/bootstrap-unbox.php';

        test_case($container);
    }
);

test(
    'php-di test-case is identical to unbox test-case',
    function () {
        $container = require __DIR__ . '/bootstrap-php-di.php';

        test_case($container);
    }
);

class PimpleTestAdapter implements ContainerInterface {
    public function __construct(\Pimple\Container $container)
    {
        $this->container = $container;
    }

    public function get($id)
    {
        return $this->container->offsetGet($id);
    }

    public function has($id)
    {}
}

test(
    'pimple test-case is identical to unbox test-case',
    function () {
        $container = require __DIR__ . '/bootstrap-pimple.php';

        test_case(new PimpleTestAdapter($container));
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
            [$container->ref('cache.path')]
        );

        $container->register(
            UserRepository::class,
            function (CacheProvider $cp) {
                return new UserRepository($cp);
            },
            ['cp' => $container->ref('cache')]
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

        expect(
            InvalidArgumentException::class,
            "should throw for invalid class-name",
            function () use ($container) {
                $container->create("bleh");
            }
        );

        expect(
            InvalidArgumentException::class,
            "should throw for abstract class-name",
            function () use ($container) {
                $container->create(AbstractClass::class);
            }
        );
    }
);

test(
    'factory does not make unsafe constructor injections',
    function () {
        $container = new Container();

        $container->register("path", "/foo");

        expect(
            ContainerException::class,
            "should NOT inject the 'path' component",
            function () use ($container) {
                $container->create(FileCache::class);
            },
            "/unable to resolve parameter: .path/"
        );
    }
);

test(
    'can register with constructor argument overrides',
    function () {
        $container = new Container();

        $container->register("cache", FileCache::class, ["path" => "/bar"]);

        eq($container->get("cache")->path, "/bar");
    }
);

test(
    'can override factory maps',
    function () {
        $container = new Container();

        $container->set('cache.path', '/tmp/cache');

        $container->register(CacheProvider::class, FileCache::class, [$container->ref('cache.path')]);

        $repo = $container->create(UserRepository::class);

        eq($repo->cache->path, '/tmp/cache');

        $repo = $container->create(UserRepository::class, [new FileCache('/my/path')]);

        eq($repo->cache->path, '/my/path');
    }
);

test(
    'can override components by type-name',
    function () {
        $container = new Container();

        $container->register(CacheProvider::class, FileCache::class, ["/tmp/cache"]);

        $returned = $container->call(
            function (CacheProvider $provider) {
                return $provider;
            },
            [CacheProvider::class => new FileCache("/custom/path")]
        );

        eq($returned->path, "/custom/path", "can override factory map");
    }
);

test(
    'can clone containers',
    function () {
        /**
         * @var UserRepository $original_component
         * @var UserRepository $cloned_component
         */

        $container = new Container();

        $container->register(FileCache::class, ["path" => "/foo"]);

        $called_by = [];

        $container->configure(function (FileCache $cache, Container $container) use (&$called_by) {
            $called_by[] = spl_object_hash($container);
        });

        $original_component = $container->get(FileCache::class);

        $cloned_container = clone $container;

        $cloned_component = $cloned_container->get(FileCache::class);

        ok($cloned_component instanceof FileCache);

        ok($cloned_component !== $original_component);

        eq($called_by, [spl_object_hash($container), spl_object_hash($cloned_container)]);
    }
);

test(
    'internal reflection guard clauses',
    function () {
        $c = new Container();

        expect(
            InvalidArgumentException::class,
            "should throw for invalid callable",
            function () use ($c) {
                invoke($c, "reflect", [["bleeeh", "meh"]]);
            }
        );

        expect(
            InvalidArgumentException::class,
            "should throw for invalid callable",
            function () use ($c) {
                invoke($c, "reflect", ["bleeeh"]);
            }
        );

        $bar = new Bar();

        expect(
            InvalidArgumentException::class,
            "should throw for uncallable object",
            function () use ($c, $bar) {
                invoke($c, "reflect", [$bar]);
            }
        );
    }
);

test(
    'Container::ARG_PATTERN works as intended',
    function () {
        $cases = [
            [function (Foo $foo) {}, 'Foo'],
            [function (Foo $foo = null) {}, 'Foo'],
            [function (Foo\Bar $foo) {}, 'Foo\\Bar'],
            [function ($foo) {}, null],
            [function ($foo = null) {}, null],
            [function ($foo = "string") {}, null],
        ];

        foreach ($cases as $case) {
            list($function, $expected) = $case;

            $reflection = new ReflectionFunction($function);

            $params = $reflection->getParameters();

            $pattern_str = $params[0]->__toString();

            if (preg_match(Container::ARG_PATTERN, $pattern_str, $matches) === 1) {
                eq($matches[1], $expected, "{$pattern_str} should match: " . format($expected));
            } else {
                eq(null, $expected, "{$pattern_str} should match: " . format($expected));
            }
        }
    }
);

configure()->enableCodeCoverage(__DIR__ . '/build/clover.xml', dirname(__DIR__) . '/src');

exit(run()); // exits with errorlevel (for CI tools etc.)
