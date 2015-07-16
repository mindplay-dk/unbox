<?php

require __DIR__ . '/header.php';

use mindplay\unbox\Container;
use mindplay\unbox\Registry;
use mindplay\unbox\ServiceProvider;

// FIXTURES:

interface CacheProvider {}

class MemoryCache implements CacheProvider {
    public $enabled = true;
}

class UserRepository
{
    /**
     * @var CacheProvider
     */
    private $cache;

    public function __construct(CacheProvider $cache)
    {
        $this->cache = $cache;
    }
}

class App extends Registry
{
    const CACHE = 'cache';
    const USER_REPOSITORY = 'user_repository';

    /**
     * @return CacheProvider
     */
    public function getCache()
    {
        return $this->container->get(self::CACHE);
    }

    /**
     * @return UserRepository
     */
    public function getUserRepository()
    {
        return $this->container->get(self::USER_REPOSITORY);
    }

    /**
     * @return string[] map where component name => class name
     */
    protected function getTypes()
    {
        return array(
            self::CACHE           => CacheProvider::class,
            self::USER_REPOSITORY => UserRepository::class,
        );
    }
}

class AppProvider implements ServiceProvider
{
    public function __invoke(Container $container)
    {
        $container->register(App::CACHE, function (App $c) {
            return new MemoryCache();
        });

        $container->register(App::USER_REPOSITORY, function (App $c) {
            return new UserRepository($c->getCache());
        });
    }
}

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
            'RuntimeException',
            'should throw on attempted to get undefined component',
            function () use ($c) {
                $c->get('nope');
            }
        );

        expect(
            'RuntimeException',
            'should throw on attempt to register initialized component',
            function () use ($c) {
                $c->register('a', function () {});
            }
        );

        expect(
            'RuntimeException',
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
            'RuntimeException',
            'attempted override of registered component after initialization',
            function () use ($c) {
                $c->register('a', function () { return 'AA'; });
            }
        );

        $c->set('b', 'BBB');
        eq($c->get('b'), 'BBB', 'can overwrite registered component after initialization');

        expect(
            'RuntimeException',
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
            'RuntimeException',
            'should throw on attempt to configure undefined component',
            function () use ($c) {
                $c->configure('nope', function () {});
            }
        );
    }
);

test(
    'Container: type checking behavior',
    function () {
        // type-check acceptance via register:

        $c = new Container(
            null,
            array(
                'a' => 'string',
                'b' => 'int',
                'c' => stdClass::class,
            )
        );

        $c->register('a', function () { return 'A'; });
        $c->register('b', function () { return 123; });
        $c->register('c', function () { return (object) array(); });

        eq($c->get('a'), 'A', 'passes string type-check');
        eq($c->get('b'), 123, 'passes int type-check');
        ok($c->get('c') instanceof stdClass, 'passes class type-check');

        // type-check acceptance via set:

        $c = new Container(
            null,
            array(
                'a' => 'string',
                'b' => 'int',
                'c' => stdClass::class,
            )
        );

        $c->set('a', 'A');
        $c->set('b', 123);
        $c->set('c', (object) array());

        eq($c->get('a'), 'A', 'passes string type-check');
        eq($c->get('b'), 123, 'passes int type-check');
        ok($c->get('c') instanceof stdClass, 'passes class type-check');

        // type-check violations via register:

        $c = new Container(
            null,
            array(
                'a' => 'string',
                'b' => 'int',
                'c' => stdClass::class,
            )
        );

        $c->register('a', function () { return 999; });
        $c->register('b', function () { return 'nope'; });
        $c->register('c', function () { return 555; });

        expect(
            RuntimeException::class,
            'should throw on string type-check violation',
            function () use ($c) {
                $c->get('a');
            }
        );

        expect(
            RuntimeException::class,
            'should throw on int type-check violation',
            function () use ($c) {
                $c->get('b');
            }
        );

        expect(
            RuntimeException::class,
            'should throw on class type-check violation',
            function () use ($c) {
                $c->get('c');
            }
        );

        // type-check violations via set:

        $c = new Container(
            null,
            array(
                'a' => 'string',
                'b' => 'int',
                'c' => stdClass::class,
            )
        );

        expect(
            RuntimeException::class,
            'should throw on string type-check violation',
            function () use ($c) {
                $c->set('a', 999);
            }
        );

        expect(
            RuntimeException::class,
            'should throw on int type-check violation',
            function () use ($c) {
                $c->set('b', 'nope');
            }
        );

        expect(
            RuntimeException::class,
            'should throw on class type-check violation',
            function () use ($c) {
                $c->set('c', 555);
            }
        );

        // type-check acceptance via "mixed" and NULL value:

        $c = new Container(
            null,
            array(
                'a' => 'mixed',
                'b' => stdClass::class,
            )
        );

        $c->register('a', function () { return 'A'; });
        $c->set('b', null);

        eq($c->get('a'), 'A', 'passes mixed type-check');
        eq($c->get('b'), null, 'passes class type-check via explicit NULL');
    }
);

test(
    'Container: validate for completeness',
    function () {
        $c = new Container(null, array('a' => 'string', 'b' => 'string'));

        $c->set('a', 'A');

        expect(
            RuntimeException::class,
            'should throw for undefined component',
            function () use ($c) {
                $c->validate();
            }
        );

        $c->register('b', function () { return 'B'; });

        $c->validate();

        ok(true, 'should validate complete Container');
    }
);

test(
    'Registry: can register ServiceProvider',
    function () {
        $c = new App();

        $c->register(new AppProvider());

        $c->register(function (Container $c) {
            $c->configure('cache', function (MemoryCache $cache) {
                $cache->enabled = false;
            });
        });

        $c->validate();

        ok($c->getUserRepository() instanceof UserRepository, 'can configure UserRepository with cache dependency');
        ok($c->getCache() instanceof CacheProvider, 'can configure Container via ServiceProvider interface');
        ok($c->getCache()->enabled === false, 'can configure Container via callable');
    }
);

configure()->enableCodeCoverage(__DIR__ . '/build/clover.xml', dirname(__DIR__) . '/src');

exit(run()); // exits with errorlevel (for CI tools etc.)
