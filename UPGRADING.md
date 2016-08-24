Upgrading
=========

#### 2.0.0

Version 2.0 introduces some minor BC breaks from version 1.x.

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
