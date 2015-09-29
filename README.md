![Unbox](unbox-logo.png)

Unbox is a [fast](#benchmark), simple, [opinionated](#opinionated) dependency injection container,
with a gentle learning curve.

[![Build Status](https://travis-ci.org/mindplay-dk/unbox.svg)](https://travis-ci.org/mindplay-dk/unbox)

[![Code Coverage](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)

## Installation

With Composer: `require mindplay/unbox`

## Introduction

This library implements a dependency injection container with a very small footprint, a small number
of concepts and a reasonably short learning curve, good performance, and quick and easy configuration
relying mainly on the use of closures for IDE support.

The container is capable of resolving constructor arguments, often automatically, with as little
configuration as just the class-name. It will also resolve arguments to any callable, including
objects that implement `__invoke()`. It can also be used as a generic factory class, capable of
creating any object for which the constructor arguments can be resolved - the common use-case
for this is in your own factory classes, e.g. a controller factory or action dispatcher.

The container implementation is compatible
with [container-interop](https://github.com/container-interop/container-interop).

### Quick Overview

Below, you can find a complete guide and full documentation - but to give you an idea of what
this library does, let's open with a quick code sample.

For this basic example, we'll assume you have the following related types:

```php
interface CacheInterface {
    // ...
}

class FileCache implements CacheInterface {
    public function __construct($path) { ... }
}

class UserRepository {
    public function __construct(CacheInterface $cache) { ... }
}
```

Now, let's wire that up with a `Container` in a "bootstrap" file somewhere:

```php
use mindplay\unbox\Container;

$container = new Container();

// register a component named "cache":
$container->register("cache", function ($cache_path) {
    return new FileCache($cache_path);
});

// register "CacheInterface" as a component referencing "cache":
$container->alias(CacheInterface::class, "cache");

// register "UserRepository" as a component:
$container->register(UserRepository::class);
```

Then configure the missing cache path in a "config" file somewhere:

```php
$container->set("cache_path", "/tmp/cache");
```

That's enough to get going - you can now take your `UserRepository` out of the `Container`,
either by asking for it directly:

```php
$users = $container->get(UserRepository::class);
```

Or, by using a type-hinted closure for IDE support:

```php
$container->call(function (UserRepository $users) {
    $users->...
});
```

To round off this quick example, let's say you have a controller:

```php
class UserController
{
    public function __construct(UserRepository $users)
    {
        // ...
    }

    public function show($user_id, ViewEngine $view, FormHelper $form, ...)
    {
        // ...
    }
}
```

Using the container as a factory, you can create an instance of any controller class:

```php
$controller = $container->create(UserController::class);
```

Finally, you can dispatch the `show()` action, with dependency injection - as a naive example,
we're simply going to inject `$_GET` directly as parameters to the method:

```php
$container->call([$controller, "show"], $_GET);
```

Using `$_GET` as parameters to the call, the `$user_id` argument to `UserController:show()` will
be resolved as `$_GET['user_id']`.

That's the quick, high-level overview.

#### API

If you're already comfortable with dependency injection, and just want to know what the API looks
like, below is a quick overview.

```php
get(string $name) : mixed                              # unbox a component
set(string $name, mixed $value)                        # directly insert an existing component
has(string $name) : bool                               # check if a component is defined/exists
isActive(string $name) : bool                          # check if a component has been unboxed

add(ProviderInterface $provider)                       # register a configuration provider

register(string $type)                                 # register a component (for auto-creation)
register(string $type, array $map)                     # ... with custom constructor arguments
register(string $name, string $type)                   # ... with a specific name for auto-creation
register(string $name, string $type, array $map)       # ... and custom constructor arguments
register(string $name, callable $func)                 # ... with a custom creation function
register(string $name, callable $func, array $map)     # ... and custom arguments to that closure

alias(string $name, string $ref_name)                  # make $ref_name available as $name

configure(callable $func)                              # manipulate a component upon creation
configure(callable $func, array $map)                  # ... with custom arguments to the closure
configure(string $name, callable $func)                # ... for a component with a specific name
configure(string $name, callable $func, array $map)    # ... with custom arguments

call(callable $func) : mixed                           # call any callable an inject arguments
call(callable $func, array $map) : mixed               # ... and override or add missing params

create(string $class_name) : mixed                     # invoke a constructor and auto-inject
create(string $class_name, array $map) : mixed         # ... and override or add missing params

ref(string $name) : BoxedValueInterface                # create a boxed reference to a component
```

If you're new to dependency injection, or if any of this baffles you, don't panic - everything is
covered in the guide below.

## Terminology

The following terminology is used in the documentation below:

  * **Callable**: refers to the `callable` pseudo-type
    as [defined in the PHP manual](http://php.net/manual/en/language.types.callable.php).

  * **Component**: any object or value registered in a container, whether registered by class-name,
    interface-name, or some other arbitrary name.

  * **Singleton**: when we say "singleton", we mean there's only one component with a given name
    within the same container instance; of course, you can have multiple container instances, so
    each component is a "singleton" only within the same container.

  * **Dependency**: in our context, we mean any registered component that is required by another
    component, by a constructor (when using the container as a factory) or by any callable.

## Dependency Resolution

Any argument, whether to a closure being manually invoked, or to a constructor being automatically
invoked as part of resolving a longer chain of dependencies, is resolved according to a consistent
set of rules - in order of priority:

  1. If you provide the argument yourself, e.g. when registering a component (or configuration
     function, or when invoking a callable) this always takes precedence. Arguments can include
     boxed values, such as (typically) references to other components, and these will be unboxed
     as late as possible.

  2. Type-hints is the preferred way to resolve singletons, e.g. types of which you have only one
     instance (or one "preferred" instance) in the same container. Singletons are usually registered
     under their class-name, or interface-name, or sometimes both.

  3. Parameter names, e.g. components maching the precise argument name (without `$`) - some may
     think this is risky, as an unresolved type-hint could get resolved by a parameter-name not
     intended for that function; if you don't wish to rely on this feature, you can simply adopt
     a convention of using component names like `"db.name"` instead of `"db_name"`, since names
     with a `"."` can't be used as parameter names.

  4. A default parameter value, if provided, will be used as a last resort - this can be useful
     in cases such as `function ($db_port = 3306) { ... }`, which allows for optional
     configuration of simple values with defaults.

For dependencies resolved using type-hints, the parameter name is ignored - and vice-versa: if a
dependency is resolved by parameter name, the type-hint is ignored, but will of course be checked
by PHP when the function/method/constructor is invoked. Note that using type-hints either way is
good practice (when possible) as this provides self-documenting configurations with IDE support.

## Guide

In the following sections, we'll assume that a `Container` instance is in scope, e.g.:

```php
use mindplay\unbox\Container;

$container = new Container();
```

### Bootstrapping

The most commonly used method to bootstrap a container is `register()` - this is the method
that lets you register a component for dependency injection.

This method generally takes one of the following forms:

```php
register(string $type)                                 # register a component (for auto-creation)
register(string $type, array $map)                     # ... with custom constructor arguments
register(string $name, string $type)                   # ... with a specific name for auto-creation
register(string $name, string $type, array $map)       # ... and custom constructor arguments
register(string $name, callable $func)                 # ... with a custom creation function
register(string $name, callable $func, array $map)     # ... and custom arguments to that closure
```

Where:

  * `$name` is a component name
  * `$type` is a fully-qualified class-name
  * `$map` is a mixed list/map of parameters (see below)
  * `$func` is a custom factory function

When `$type` is used without `$name`, the component name is assumed to also be the name
of the type being registered.

The `$map` argument is mixed list and/or map of parameters. That is, if you include
parameters without keys (such as `['apple', 'pear']`) these are taken as being positional
arguments, while parameters with keys (such as `['lives' => 9]`) are matched against
the parameter name of the callable or constructor being invoked.

When supplying custom arguments via `$map`, it is common to use `$container->ref('name')`
to obtain a "boxed" reference to a component - when the registered component is created
(on first use) any "boxed" arguments will be "unboxed" at that time. In other words, this
enables you to supply other components as arguments "lazily", without activating them
until they're actually needed.

If the callable `$func` is supplied, this is registered as your custom component creation
function - dependency injection is done for this closure, so this is usually the best way
to specify how a component should be created, if you care about IDE support. (You should!)

#### Examples

The following examples are all valid use-cases of the above forms:

  * `register(Foo::class)` registers a component by it's class-name, and will try to
    automatically resolve all of it's constructor arguments.

  * `register(Foo::class, ['bar'])` registers a component by it's class-name, and will
    use `'bar'` as the first constructor argument, and try to resolve the rest.

  * `register(Foo::class, [$container->ref(Bar::class)])` creates a boxed reference to
    a registered component `Bar` and provides that as the first argument.

  * `register(Foo::class, ['bat' => 'zap'])` registers a component by it's class-name
    and will use `'zap'` for the constructor argument named `$bat`, and try to resolve
    any other arguments.

  * `register(Bar::class, Foo::class)` registers a component `Foo` under another name
    `Bar`, which might be an interface or an abstract class.

  * `register(Bar::class, Foo::class, ['bar'])` same as above, but uses `'bar'` as the
    first argument.

  * `register(Bar::class, Foo::class, ['bat' => 'zap'])` same as above, but, well, guess.

  * `register(Bar::class, function (Foo $foo) { return new Bar(...); })` registers a
    component with a custom factory function.

  * `register(Bar::class, function ($name) { ... }, [$container->ref('db.name')]);`
    registers a component creation function with a reference to a component "db.name"
    as the first argument.

In effect, you can think of `$func` as being an optional argument.

The provided parameter values may include any `BoxedValueInterface`, such as the boxed
component reference created by {@see Container::ref()} - these will be unboxed as late
as possible.

#### Aliasing

Sometimes you need to register the same component under two different names - one common
use-case, is to register the same component both for a concrete and abstract type, e.g.
for a class and an interface.

For example, it's ordinary to register a cache component twice:

```php
$container->register(CacheInterface::class, function () {
    return new FileCache();
});

$container->alias("db.cache", CacheInterface::class); // "db.cache" becomes an alias!

var_dump($container->get("db.cache") === $container->get(CacheInterface::class)); // => bool(true)
```

Using an alias, in this example, means that `"db.cache"` by default will resolve as
`CacheInterface`, but gives us the ability to [override](#overrides) the definition of
`"db.cache"` with a different implementation, without affecting other components which
might also be using `CacheInterface` as a default.

#### Direct Insertion

In some cases, you already have an instance of a component, such as the `ClassLoader`
provided by Composer - in this case, you can insert it directly into the container, to
make it available for dependency injection:

```php
$loader = require __DIR__ . '/vendor/autoload.php';

$container->set(ClassLoader::class, $loader);
```

Another common use-case for `set()` is to configure simple values like host-names or
port-numbers.

#### Overrides

To override an existing component, simply call `register()` with an already-registered
component name - this will completely replace an existing component definition.

Note that overriding a component does *not* affect any registered configuration functions -
it is therefore important that, if you do override a component, the new component must be
compatible with the replaced component. Configuration in general is covered below.

#### Configuration

To perform additional configuration of a registered component, use the `configure()` method.

This method takes one of the following forms:

```php
configure(callable $func)
configure(callable $func, array $map)
configure(string $name, callable $func)
configure(string $name, callable $func, array $map)
```

Where:

  * `$name` is the name of a component being configured
  * `$func` is a function that configures the component in some way
  * `$map` is a mixed list/map of parameters (as explained [above](#bootstrapping))

The callable `$func` will be called with dependency injection - the first argument of
this function is the component being configured; you should type-hint it (if possible, for
IDE support) although you're not strictly required to. Any additional arguments will be
resolved as well.

The optional array `$map` is a mixed list/map of parameters, as covered [above](#bootstrapping).

If no `$name` is supplied, the first argument from the given `$func` is used to infer the
component name: if the first argument is type-hinted, the class/interface name is used -
or, if no type-hint is supplied, the parameter name is used.

As an example, let's say you've configured a `PDO` component:

```php
$container->register(PDO::class, function ($db_host, $db_name, $db_user, $db_password) {
    $connection = "mysql:host={$db_host};dbname={$db_name}";

    return new PDO($connection, $db_user, $db_password);
});
```

In a configuration file, simple values like `$db_host` can be inserted directly, e.g. with
`$container->set("db_host", "localhost")` - but suppose you need to do something *after*
the connection is created? Here's where `configure()` comes into play:

```php
$container->configure(function (PDO $db) {
    $db->exec("SET NAMES utf8");
});
```

Note that, in this example, `configure()` will infer the component name `"PDO"` from the
type-hint - in a scenario with multiple named `PDO` instances, you would use the optional
first argument to explicitly specify the component, e.g.:

```php
$container->configure("logger.pdo", function (PDO $db) {
    $db->exec("SET NAMES utf8");
});
```

##### Property or Setter Injection

This library doesn't support neither property nor setter injection, but both can be accomplished
by just doing those things in a call to `configure()` - for example:

```php
$container->configure(function (Connection $db, LoggerInterface $logger) {
    $db->setLogger($logger);
});
```

In this example, upon first use of `Connection`, a dependency `LoggerInterface` will be
unboxed and injected via setter-injection. (We believe this approach is much safer than
offering a function that accepts the method-name as an argument - closures are more powerful,
much safer, and provide full IDE support, inspections, automated refactoring, etc.)

##### Modification

You can use `configure()` to modify values (such as strings, numbers or arrays) in the container.

For example, let's say you have a middleware stack defined as an array:

```php
$container->set("app.middleware", function () {
    return [new RouterMiddleware, new NotFoundMiddleware];
);
```

If you need to append to the stack, you can do this:

```php
$container->configure("app.middleware", function ($middleware) {
    $middleware[] = new CacheMiddleware();

    return $middleware;
});
```

Note the return statement - this is what causes the value to get updated in the container..

##### Decoration

The [decorator](https://en.wikipedia.org/wiki/Decorator_pattern) pattern is another pattern
that can be implemented with `configure()` - for example, lets say you bootstrapped your
container with a product repository implementation and interface:

```php
$container->register(ProductRepository::class, function () { ... });

$container->alias(ProductRepositoryInterface::class, ProductRepository::class);
```

Now lets say you implement a cached product repository decorator - you can bootstrap this
by creating and returning the decorator instance like this:

```php
$container->configure(function (ProductRepositoryInterface $repo) {
    return new CachedProductRepository($repo);
});
```

Note that, when replacing components in this manner, of course you must be certain that the
replacement has type that can pass a type-check in the recipient constructor or method.

##### Packaged Providers

You can package a set of `register()` and `configure()` calls for convenient reuse, by
implementing `ProviderInterface` - for example:

```php
class MyProvider implements ProviderInterface
{
    public function register(Container $container)
    {
        $container->register(...);
        $container->configure(...);
        // ...
    }
}
```

You can then easily bootstrap your projects with providers, e.g.:

```php
$container->add(new MyProvider);
$container->add(new TestDependenciesProvider);
$container->add(new DevelopmentDebugProvider);
// ...
```

Providers of course can also call `Container::add()` to bootstrap other providers - with
this in mind, you can make e.g. development or production setup for your app as easy as
calling e.g. `$container->add(new DevelopmentProvider)` to provide complete bootstrapping
for a quick development setup. Even if somebody wanted to override some of the registrations
in e.g. your default development setup, they can of course still do that, e.g. by calling
`register()` again to override components as needed.

### Consumption

Consuming the contents of a container is very simple, convenient and powerful - and therefore very
tempting! You should [inform yourself](http://stackoverflow.com/questions/11316688/inversion-of-control-vs-dependency-injection-with-selected-quotes-is-my-unders/11319026#11319026)
about the difference, and **avoid** using the container as a [service locator](https://en.wikipedia.org/wiki/Service_locator_pattern).

The most basic form of component access, is a direct lookup:

```php
$cache = $container->get(CacheInterface::class);
$db_name = $container->get("db_name");
```

The more indirect form of component access, is an indirect lookup, by resolving parameters:

```php
$container->call(function (CacheInterface $cache, $db_name) {
    // ...
});
```

The result in these two examples, is the same - but it's important to note that, in the `call()`
example, the two arguments are being resolved in two different ways: the `CacheInterface` param
is resolved by class-name, whereas the `$db_name` param is being resolve by parameter name.

The latter only works because the `$db_name` component is registered under that precise name -
if it had been registered under a name such as `"db.name"`, the container would be unable to
resolve this argument automatically; instead, you would have had to write:

```php
$container->call(function (CacheInterface $cache, $name) {
    // ...
}, ["name" => $container->ref("db.name")]);
```

Note that `call()` will accept [any type of callable](http://php.net/manual/en/language.types.callable.php).

#### Factory Facet

The `create()` method can be used to construct an instance of any class, on demand.

An important thing to understand, is that e.g. `register()` and `configure()` have *no*
bearing on this functionality - the purpose of this method, is to create instance of types
that *aren't* registered as components in the container, but may have *dependencies* that
can be resolved by the container.

Controllers are a great example - you most likely don't want to register every individual
controller class as a component in the container; rather, you probably want a controller
factory, capable of creating any controller.

As an example, here's a simple implementation of a controller factory that resolves the
typical `"foo/bar"` route string as e.g. `FooController::bar()` - like so:

```php
class Action
{
    public function __construct(Controller $controller, $action, array $params) { ... }
}

class ControllerFactory
{
    /** @var FactoryInterface */
    private $factory;

    public function __construct(FactoryInterface $factory) { ... }

    public function create($route, array $params)
    {
        list($controller_name, $action_name) = explode("/", $route);

        $controller_class = ucfirst($controller_name) . "Controller";

        $controller = $this->factory->create($controller_class);

        return new Action($controller, $action_name, $params);
    }
}
```

Note thte `FactoryInterface` type-hint in the constructor - in situations where you care
only about using the container as a factory, you should type-hint against this facet.

#### Inspection

You can inspect the state of components in a container using `has()` and `isActive()`.

To check if a component is defined:

```php
var_dump($container->has("foo")); // => bool(false)

$container->set("foo", "bar");

var_dump($container->has("foo")); // => bool(true)
```

Whether a component is directly inserted with `set()`, or defined using `register()`, the
`has()` method will return `true`.

To check if a component has been activated:

```php
$container->register("foo", function () { return "bar"; });

var_dump($container->isActive("foo")); // => bool(false)

$foo = $container->get("foo"); // component activates on first use

var_dump($container->isActive("foo")); // => bool(true)
```

If a component is directly inserted with `set()`, it is considered active immediately.

## Opinionated

Less is more. We support only what's actually necessary to create beautiful architecture - we do
not provide a wealth of "convenience" features to support patterns we wouldn't use, or patterns
that aren't very common and can easily be implemented with the features we do provide.

Features:

  * **Productivity-oriented** - favoring heavy use of **closures** for full IDE support:
    refactoring-friendly definitions with auto-complete support, inspections and so on.

  * **Performance-oriented** only to the extent that it doesn't encumber the API.

  * **Versatile** - supporting many different options for registration and configuration
    using the same, low number of public methods, including value modifications, decorators, etc.

  * Zero configuration - we don't include any optional features or configurable behavior: the
    container always behaves consistently, with the same predictable performance and interoperability.

  * **PHP 5.5+** for `::class` support, and because you really shouldn't be using anything older.

Non-features:

  * **NO annotations** - because sprinkling bits of your container configuration across
    your domain model is a really terrible idea.

  * **NO auto-wiring** - because `$container->register(Foo::name)` isn't a burden, and explicitly
    designates something as being a service; unintentionally treating a non-singleton as a singleton
    can be a weird experience.

  * **NO caching** and no "builder" or "container factory" class - because configuring a container
    really shouldn't be so much overhead as to justify the need for caching.

  * **NO property/setter injections** because it blurs your dependencies - use constructor injection,
    and for optional dependencies, use optional constructor arguments; you don't, after all, need to
    count the number of arguments anymore, since everything will be injected. (if you do have a good
    reason to inject something via properties or setters, you can do that from inside a closure, in
    a call to `configure()`, with full IDE support.)

  * **NO syntax** - we don't invent or parse any special string syntax, anywhere, period. Any problem
    that can be solved with custom syntax can also be solved with clean, simple PHP code.

  * No chainable API, because call chains (in PHP) don't play nice with source-control.

  * All registered components are singletons - we do not support factory registrations; if you
    need to register a factory, the proper way to do that, is to either implement an actual
    factory class (which is usually better in the long run), or register the factory closure
    itself as a named component.

## Benchmark

This is not intended as a competitive benchmark, but more to give you an idea of the performance
implications of choosing from three very different DI containers with very different goals and
different qualities - from the smallest and simplest to the largest and most ambitious:

  * [pimple](http://pimple.sensiolabs.org/) is as simple as a DI container can get, with absolutely
    no bell and whistles, and barely any learning curve.

  * **unbox** with just two classes (less than 400 lines of code) and a few interfaces - more concepts
    than pimple (and therefore a bit more learning curve) and convenient closure injections, which
    are somewhat more costly in terms of performance.

  * [php-di](http://php-di.org/) is a pristine dependency injection framework with all the bells and
    whistles - rich with features, but also has more concepts and learning curve, and more overhead.

The included [simple benchmark](test/benchmark.php) generates the following benchmark results on
a Windows 8 system running PHP 5.6.6.

Time to configure the container:

    pimple ........ 0.100 msec ...... 59.49% ......... 1.00x
    unbox ......... 0.112 msec ...... 66.85% ......... 1.12x
    php-di ........ 0.167 msec ..... 100.00% ......... 1.68x

Time to resolve the dependencies in the container, on first access:

    pimple ........ 0.043 msec ...... 11.28% ......... 1.00x
    unbox ......... 0.104 msec ...... 27.38% ......... 2.43x
    php-di ........ 0.380 msec ..... 100.00% ......... 8.87x

Time for multiple subsequent lookups:

    pimple: 3 repeated resolutions ........ 0.048 msec ...... 12.31% ......... 1.00x
    unbox: 3 repeated resolutions ......... 0.109 msec ...... 27.86% ......... 2.26x
    php-di: 3 repeated resolutions ........ 0.391 msec ..... 100.00% ......... 8.12x

    pimple: 5 repeated resolutions ........ 0.052 msec ...... 13.03% ......... 1.00x
    unbox: 5 repeated resolutions ......... 0.114 msec ...... 28.48% ......... 2.19x
    php-di: 5 repeated resolutions ........ 0.402 msec ..... 100.00% ......... 7.67x

    pimple: 10 repeated resolutions ....... 0.063 msec ...... 15.05% ......... 1.00x
    unbox: 10 repeated resolutions ........ 0.128 msec ...... 30.43% ......... 2.02x
    php-di: 10 repeated resolutions ....... 0.421 msec ..... 100.00% ......... 6.64x
