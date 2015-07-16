mindplay/unbox
==============

This library provides two components: a dependency injection container,
and an abstract base class for your service registry components.

[![Build Status](https://travis-ci.org/mindplay-dk/unbox.svg)](https://travis-ci.org/mindplay-dk/unbox)

[![Code Coverage](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)


## Usage

The `Container` class is used internally by the abstract `Registry`
base class, and can be accessed only via `Registry::register()`, by
passing an object of a class implementing the `ServiceProvider` interface,
or a simple anonymous function.

Example service registry for an application:

```PHP
class MyApp extends Registry
{
    const CACHE =           'cache';
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
```

The `getTypes()` callback defines the components that must be defined in
order for this service registry to be complete, and also defines the types
that these values must conform to.

Type-hinting with `@return` in accessor methods (`getCache()`, etc.) provides
good IDE support (auto-complete, etc.) when working with your service registry.

Using constants for component names is of course optional, but provides
additional safety/inspections and convenient refactoring options in modern IDEs.

You can access the `Container` in this registry by passing an anonymous
function to `Registry::register()` - this callback will receive the `Container`
instance as the first argument. Usually, a better option is to package your
service registrations as a `ServiceProvider` for convenient reuse - example:

```PHP
class MyAppProvider implements ServiceProvider
{
    public function __invoke(Container $container)
    {
        $container->register(MyApp::CACHE, function () {
            return new MemoryCache();
        });

        $container->register(MyApp::USER_REPOSITORY, function (MyApp $app) {
            return new UserRepository($app->getCache());
        });
    }
}
```

Putting these components together is simple:

```PHP
$app = new MyApp();

$app->register(new MyAppProvider);

$app->validate(); // optional, but highly recommended!

$app->getUserRepository()->...
```

The call to `Registry::validate()` ensures completeness - it validates
that all of the components defined in `MyApp::getTypes()` have actually
been registered in the `Container` inside your service registry.

The `Container` class internally performs type-checking, when setting
a component directly, and/or when initializing a registered component,
to ensure that failures happen as early as possible.

Refer to the `Container` class for the full API.


### FAQ

Q: How many freakin' containers are you going to write, Rasmus?

A: I wrote [stockpile](https://github.com/mindplay-dk/stockpile) which
is primarily a service registry - while it provides good IDE support,
parsing annotations was perhaps too much magic. I wanted something
simpler, but still with type-safety, which lead to my second container,
[boxy](https://github.com/mindplay-dk/boxy), which had a much larger API
than I wanted. Which lead to this library, which is simpler than boxy,
while providing (nearly) the same level of safety as stockpile, without
the complexity.

Q: It's very similar to [pimple](https://github.com/silexphp/Pimple).

A: That's not a question, but yes - like boxy, this library is heavily
influenced by pimple, with some key differences: it adds type checking,
and avoids the use of array-syntax - a seemingly convenient, but fucking
weird feature, when you assign a closure on write, and get something else
entirely on read. It also deliberately does not support factory functions,
which ought to be implemented by inserting an actual factory class into
the Container, just like you'd insert any other service/component.
