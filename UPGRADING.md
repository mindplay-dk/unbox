Upgrading
=========

#### 3.1.0

This feature-release introduces requirements (via `requires` and `provides`) and requires PHP 8.0 or later.

#### 3.0.0

This release removes backwards compatibility with the legacy package `container-interop/container-interop`
and legacy interfaces from the `Interop\Container` namespace - the container is now compatible only with
the official, final PSR-11 standard `psr/container` package and interfaces.

#### 2.0.1

This release improves *forward* compatibility with `psr/container`, and backwards compatibility with
the deprecated `container-interop/container-interop` package.

#### 2.0.0

Version 2 introduces some BC breaks from version 1.x, as described below.

**Separation of Concerns**

The biggest change in version 2 is the introduction of `ContainerFactory`, which provides a more
natural separation of the bootstrapping/configuration phase from the life of the `Container` itself.
We no longer have to enforce immutability of components and throw exceptions - the container, once
created, doesn't have any mutation methods, and is thereby naturally immutable.

Version 1 bootstrapping might look like this:

```php
$container = new Container();

$container->add(new FooProvider);
$container->add(new BarProvider);
```

Porting this to Version 2 should be straight-forward in most cases:

```php
$factory = new ContainerFactory();

$factory->add(new FooProvider);
$factory->add(new BarProvider);

$container = $factory->createContainer();
```

A possible edge-case, is if you had closures that depend on a `Container` instance being
in scope, such as:

```php
$container->register(PDO::class, function () use ($container) {
    return new PDO($container->get("db.connection_string"));
});
```

The `Container` is no longer available at the time of registration, but you can port such code
simply by asking for the container instance, rather than relying on an instance being in scope, e.g.:

```php
$container->register(PDO::class, function (Container $container) {
    return new PDO($container->get("db.connection_string"));
});
```

**Provider Interface**

The signature of `ProviderInterface` has changed - a provider is now exposed to `ContainerFactory`
rather than to `Container`.

Assuming your providers weren't reading from the container, which they shouldn't be, porting should
be as simple as updating the method-signatures of your providers to match this change, and renaming
the argument from `$container` to `$factory` to accurately reflect the role of this argument in the
context of your provider, which is now boostrapping the container *factory* rather than the container
itself.

The registration/configuration methods of the API use the same signatures as in version 1.x, so this
should be an easy change to implement.

**Boxed Value Interface**

The signature of `BoxedValueInterface` has changed - the Container instance is now provided as an
argument to the `unbox()` method; this was introduced because some boxed value types (including the
built-in `BoxedReference` type) depend upon the Container, which is now unavailable at the time of
bootstrapping/configuration.

You're not required to use the provided instance for anything - if you don't need it, simply add
the container argument to your boxed value type to satisfy the interface, and ignore the argument.

**Consistent Mutability**

As described [here](https://github.com/mindplay-dk/unbox/issues/4), version 1.x allowed you to overwrite
registrations using `register()` or `set()` under certain specific conditions, but this behavior was
inconsistent and therefore unpredictable.

Version 2 consistently allows no modifications, under any circumstances, once a component is in use.

This is a very minor BC break, and unlikely to affect you - if you *were* relying on the ability to
overwrite a registration after using a component, this points to a dependency issue in your client code.

**Unsafe Name-based Constructor Injection**

As discussed [here](https://github.com/mindplay-dk/unbox/issues/5), the `create()` method in version 1.x
would perform potentially unsafe constructor-injections, matching parameter names against component names
that happened to match - for example:

```php
class FileCache {
    public function __construct($path) { ... }
}

$container->register(CacheInterface::class, FileCache::class);

$container->register("path", "/foo");

$cache = $container->get(CacheInterface::class); // throws an exception as of version 2.0 (!)
```

Injections into user-defined factory-closures, or explicitly via `$map`, is of course still possible,
so the registration in the above example can be ported to an explicit and safe registration, as follows:

```php
$container->register(
    CacheInterface::class,
    FileCache::class,
    [$container->ref("path")] // explicitly references component "path"
);
```

This code is safer and more explicit.

**Stricter configure()**

The `configure()` method no longer attempts to infer the component-name from the parameter name - the
following code previously worked *only* because `configure()` is called *after* `register()`:

```php
$factory = new ContainerFactory();

$factory->register("cache", FileCache:class, ["/tmp/cache"]);

$factory->configure(function (FileCache $cache) {
    // ...
});
```

In version 2, registration order causes no such side-effect - you therefore have to specify the
component-name because it can't be inferred, so the last line of the above code would need to
specify the component-name:

```php
$factory->configure("cache", function (FileCache $cache) {
    // ...
});
```

In other words, `configure()` without a component-name only works for components where the
component name is equal to the type-hint.
