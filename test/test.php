<?php

use Interop\Container\ContainerInterface;
use mindplay\unbox\Container;
use mindplay\unbox\ContainerException;
use mindplay\unbox\ContainerFactory;
use mindplay\unbox\NotFoundException;
use mindplay\unbox\Reflection;

require __DIR__ . '/header.php';

// TESTS:

test(
    'can register/set components; can check component state',
    function () {
        $f = new ContainerFactory();

        $f->register('a', function () { return 'A'; });
        $f->register('b', function () { return 'B'; });

        ok($f->createContainer()->has('a'), 'first component defined');
        ok($f->createContainer()->has('b'), 'second component defined');

        ok(!$f->createContainer()->has('c'), 'no third component defined');

        $f->set('c', 'C');

        ok($f->createContainer()->has('c'), 'third component initialized directly');

        $c = $f->createContainer();

        ok(!$c->isActive('a'), 'first component not yet active');
        ok(!$c->isActive('b'), 'first component not yet active');

        eq($c->get('a'), 'A', 'returns the first component');

        ok($c->isActive('a'), 'first component activated');
        ok(!$c->isActive('b'), 'second component still not active');

        eq($c->get('b'), 'B', 'returns the second component');
        ok($c->isActive('b'), 'second component activated');
    }
);

test(
    'Container/Factory: can auto-register with various factory methods',
    function () {
        $f = new ContainerFactory();

        $f->register('x', Foo::class);
        $f->register(Foo::class);
        $f->register(FileCache::class, ['/tmp/foo']);

        $c = $f->createContainer();

        ok($c->get('x') instanceof Foo, 'registers a default factory function when $func is a name');

        ok($c->get(Foo::class) instanceof Foo, 'registers a default factory function when $func is NULL');

        ok($c->get(FileCache::class) instanceof FileCache, 'registers a default factory function when $func is a map');
    }
);

test(
    'Factory: expected exceptions',
    function () {
        $f = new ContainerFactory();

        $c = $f->createContainer();

        expect(
            NotFoundException::class,
            'should throw on attempted to get undefined component',
            function () use ($c) {
                $c->get('nope');
            }
        );

        expect(
            InvalidArgumentException::class,
            'should throw on invalid argument',
            function () use ($f) {
                $f->register('z', (object) []);
            }
        );
    }
);

test(
    'Container: can override components',
    function () {
        // register overrides register:

        $f = new ContainerFactory();

        $f->register('a', function () { return 'A'; });
        $f->register('a', function () { return 'AA'; });

        $c = $f->createContainer();

        eq($c->get('a'), 'AA', 'can override registered component');

        // ---

        $f = new ContainerFactory();

        $f->register('b', function () { return 'B'; });
        $f->set('b', 'BB');

        $c = $f->createContainer();

        eq($c->get('b'), 'BB', 'can overwrite registered component');

        // ---

        $f = new ContainerFactory();

        $f->set('c', 'C');
        $f->register('c', function () { return 'CC'; });

        $c = $f->createContainer();

        eq($c->get('c'), 'CC', 'can override set component');

        // ---

        $f = new ContainerFactory();

        $f->set('d', 'D');
        $f->set('d', 'DD');

        $c = $f->createContainer();

        eq($c->get('d'), 'DD', 'can overwrite set component');
    }
);

test(
    'Factory: can configure registered components',
    function () {
        $f = new ContainerFactory();

        $f->register('a', function () { return 1; }); // $a = 1
        $f->set('b', 2); // $b = 2

        $f->configure('a', function ($a) { return $a + 1; }); // $a = 2
        $f->configure('a', function ($a) { return $a + 1; }); // $a = 3

        $f->configure('b', function ($b) { return $b + 1; }); // $b = 3
        $f->configure('b', function ($b) { return $b + 1; }); // $b = 4

        $f->configure('b', function ($b) { $b += 1; }); // no change

        $c = $f->createContainer();

        eq($c->get('a'), 3, 'can apply multiple configuration functions');
        eq($c->get('b'), 4, 'can infer component name from param name');
    }
);

test(
    'Factory: can configure with reference-based dependency injection',
    function () {
        $f = new ContainerFactory();

        $f->register(Foo::class);

        $f->register('zap', Bar::class);

        $ok = false;

        $f->configure(
            Foo::class,
            function (Foo $foo, Bar $bar) use (&$ok) {
                $ok = true;
            },
            ['bar' => $f->ref('zap')]
        );

        $got_foo = false;

        $f->configure(function (Foo $few) use (&$got_foo) {
            $got_foo = true;
        });

        $c = $f->createContainer();

        $c->get(Foo::class);

        ok($ok, 'can use parameter list/map in calls to configure()');
        ok($got_foo, 'can infer component name from argument type-hint');
    }
);

test(
    'can configure directly injected components',
    function () {
        $f = new ContainerFactory();

        $f->configure("foo", function ($foo) { return $foo + 1; });

        $f->set("foo", 1);

        $c = $f->createContainer();

        eq($c->get("foo"), 2, 'can apply configuration to directly injected values');
    }
);

test(
    'can configure using static method as callable',
    function () {
        $f = new ContainerFactory();

        $f->set(FileCache::class, new FileCache('/tmp'));

        $f->configure([AbstractClass::class, "staticFunc"]);

        $c = $f->createContainer();

        eq($c->get(FileCache::class)->path, AbstractClass::CACHE_PATH, "can use static configuration function");
    }
);

test(
    'named components take precedence over type-hints',
    function () {
        $f = new ContainerFactory();

        $f->register(FileCache::class, ["/by-type"]);

        $f->register("cache", FileCache::class, ["/by-name"]);

        /** @var FileCache|null $conf_by_type */
        $conf_by_type = null;

        $f->configure(function (FileCache $cache) use (&$conf_by_type) {
            $conf_by_type = $cache;
        });

        /** @var FileCache|null $conf_by_name */
        $conf_by_name = null;

        $f->configure('cache', function ($cache) use (&$conf_by_name) {
            $conf_by_name = $cache;
        });

        /** @var FileCache|null $by_type */
        $by_type = null;

        $container = $f->createContainer();

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
    'configure() requires either type-hint or component name in version 2',
    function () {
        $f = new ContainerFactory();

        $f->register("cache", FileCache::class, ["/foo"]);

        expect(
            InvalidArgumentException::class,
            "should throw for missing component-name and missing type-hint",
            function () use ($f) {
                $f->configure(function ($cache) {});
            },
            "/no component-name or type-hint specified/"
        );
    }
);

test(
    'can call all the things',
    function () {
        $factory = new ContainerFactory();

        $factory->set('foo', 'bar');

        $container = $factory->createContainer();

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
        $factory = new ContainerFactory();

        $factory->set('cache.path', '/tmp/cache');

        $factory->register(CacheProvider::class, function (Container $c) {
            return new FileCache($c->get('cache.path'));
        });

        $factory->register(UserRepository::class, function (Container $c) {
            return new UserRepository($c->get(CacheProvider::class));
        });

        $container = $factory->createContainer();

        $repo = $container->get(UserRepository::class);

        ok($repo instanceof UserRepository);
        ok($repo->cache instanceof CacheProvider);
        eq($repo->cache->path, '/tmp/cache');

        expect(
            ContainerException::class,
            "should throw for unresolvable components",
            function () use ($container) {
                $container->call(function (Foo $foo) {});
            }
        );
    }
);

test(
    'can alias names',
    function () {
        $factory = new ContainerFactory();

        $factory->register(FileCache::class, ['path' => '/tmp/foo']);

        $factory->alias(CacheProvider::class, FileCache::class);

        $container = $factory->createContainer();

        eq($container->get(FileCache::class), $container->get(CacheProvider::class), 'alias returns same component');
    }
);

test(
    'can implement "auto-wiring" by using the inject() method',
    function () {
        $factory = new ContainerFactory();

        $container = $factory->createContainer();

        eq($container->has(Bar::class), false);

        $instance = new Bar();

        $container->inject(Bar::class, $instance);

        eq($container->has(Bar::class), true);

        eq($container->isActive(Bar::class), true);

        eq($container->get(Bar::class), $instance);

        expect(
            InvalidArgumentException::class,
            "should throw on attempted overrride",
            function () use ($container) {
                $container->inject(Bar::class, new Bar());
            }
        );
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
    'can resolve dependencies using named/positional argument maps',
    function () {
        $factory = new ContainerFactory();

        $factory->set('cache.path', '/tmp/cache');

        $factory->register(
            'cache',
            function ($path) {
                return new FileCache($path);
            },
            [$factory->ref('cache.path')]
        );

        $factory->register(
            UserRepository::class,
            function (CacheProvider $cp) {
                return new UserRepository($cp);
            },
            ['cp' => $factory->ref('cache')]
        );

        $container = $factory->createContainer();

        $repo = $container->get(UserRepository::class);

        ok($repo instanceof UserRepository);
        ok($repo->cache instanceof CacheProvider);
        eq($repo->cache->path, '/tmp/cache');
    }
);

test(
    'can act as a factory',
    function () {
        $factory = new ContainerFactory();

        $factory->register(
            CacheProvider::class,
            function () {
                return new FileCache('/tmp/cache');
            }
        );

        $container = $factory->createContainer();

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
    'create() does not make unsafe constructor injections',
    function () {
        $factory = new ContainerFactory();

        $factory->register("path", "/foo");

        $container = $factory->createContainer();

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
        $factory = new ContainerFactory();

        $factory->register("cache", FileCache::class, ["path" => "/bar"]);

        $container = $factory->createContainer();

        eq($container->get("cache")->path, "/bar");
    }
);

test(
    'can override factory maps',
    function () {
        $factory = new ContainerFactory();

        $factory->set('cache.path', '/tmp/cache');

        $factory->register(CacheProvider::class, FileCache::class, [$factory->ref('cache.path')]);

        $container = $factory->createContainer();

        $repo = $container->create(UserRepository::class);

        eq($repo->cache->path, '/tmp/cache');

        $repo = $container->create(UserRepository::class, [new FileCache('/my/path')]);

        eq($repo->cache->path, '/my/path');
    }
);

test(
    'can override components by type-name',
    function () {
        $factory = new ContainerFactory();

        $factory->register(CacheProvider::class, FileCache::class, ["/tmp/cache"]);

        $container = $factory->createContainer();

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
    'internal reflection guard clauses',
    function () {
        expect(
            InvalidArgumentException::class,
            "should throw for invalid callable",
            function () {
                Reflection::createFromCallable(["bleeeh", "meh"]);
            }
        );

        expect(
            InvalidArgumentException::class,
            "should throw for invalid callable",
            function () {
                Reflection::createFromCallable("bleeeh");
            }
        );

        $bar = new Bar();

        expect(
            InvalidArgumentException::class,
            "should throw for uncallable object",
            function () use ($bar) {
                Reflection::createFromCallable($bar);
            }
        );
    }
);

test(
    'can obtain reflections from all types of callable',
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

            eq(
                Reflection::getParameterType($params[0]),
                $expected,
                "{$pattern_str} should match: " . format($expected)
            );
        }
    }
);

test(
    'can import registrations from an existing Container',
    function () {
        $shared = new ContainerFactory();

        $shared->register(CacheProvider::class, FileCache::class, ["path" => $shared->ref("cache.path")]);

        $shared->set("cache.path", "/tmp");

        $factory = new ContainerFactory();

        $factory->register(UserRepository::class);

        $shared_container = $shared->createContainer();

        $factory->import($shared_container);

        eq($shared_container->isActive(CacheProvider::class), false);

        $repo_1 = $factory->createContainer()->get(UserRepository::class);
        $repo_2 = $factory->createContainer()->get(UserRepository::class);

        ok($shared_container->isActive(CacheProvider::class));

        ok($repo_1 !== $repo_2, "each Container creates unique components");

        ok($repo_1->cache === $repo_2->cache, "each Container shares an imported component");
    }
);

if (version_compare(PHP_VERSION, "7", ">=")) {
    require __DIR__ . "/test-php70.php";
} else {
    ok(true, "skipping PHP 7.0 tests");
}

if (version_compare(PHP_VERSION, "7.1.0rc3", ">=")) {
    require __DIR__ . "/test-php71.php";
} else {
    ok(true, "skipping PHP 7.1 tests");
}

configure()->enableCodeCoverage(__DIR__ . '/build/clover.xml', dirname(__DIR__) . '/src');

exit(run()); // exits with errorlevel (for CI tools etc.)
