<?php

namespace mindplay\unbox;

use Closure;
use InvalidArgumentException;
use ReflectionParameter;

/**
 * This class provides boostrapping/configuration facilities for creation of `Container` instances.
 */
class ContainerFactory
{
    /**
     * @var mixed[] map where component name => value
     */
    protected $values = [];

    /**
     * @var callable[] map where component name => factory function
     */
    protected $factory = [];

    /**
     * @var array map where component name => mixed list/map of parameter names
     */
    protected $factory_map = [];

    /**
     * @var (callable[])[] map where component name => list of configuration functions
     */
    protected $config = [];

    /**
     * @var array map where component name => mixed list/map of parameter names
     */
    protected $config_map = [];

    /**
     * Register a component for dependency injection.
     *
     * There are numerous valid ways to register components.
     *
     *   * `register(Foo::class)` registers a component by it's class-name, and will try to
     *     automatically resolve all of it's constructor arguments.
     *
     *   * `register(Foo::class, ['bar'])` registers a component by it's class-name, and will
     *     use `'bar'` as the first constructor argument, and try to resolve the rest.
     *
     *   * `register(Foo::class, [$container->ref(Bar::class)])` creates a boxed reference to
     *     a registered component `Bar` and provides that as the first argument.
     *
     *   * `register(Foo::class, ['bat' => 'zap'])` registers a component by it's class-name
     *     and will use `'zap'` for the constructor argument named `$bat`, and try to resolve
     *     any other arguments.
     *
     *   * `register(Bar::class, Foo::class)` registers a component `Foo` under another name
     *     `Bar`, which might be an interface or an abstract class.
     *
     *   * `register(Bar::class, Foo::class, ['bar'])` same as above, but uses `'bar'` as the
     *     first argument.
     *
     *   * `register(Bar::class, Foo::class, ['bat' => 'zap'])` same as above, but, well, guess.
     *
     *   * `register(Bar::class, function (Foo $foo) { return new Bar(...); })` registers a
     *     component with a custom creation function.
     *
     *   * `register(Bar::class, function ($name) { ... }, [$container->ref('db.name')]);`
     *     registers a component creation function with a reference to a component "db.name"
     *     as the first argument.
     *
     * In effect, you can think of `$func` as being an optional argument.
     *
     * The provided parameter values may include any `BoxedValueInterface`, such as the boxed
     * component referenced created by {@see Container::ref()} - these will be unboxed as late
     * as possible.
     *
     * @param string                      $name                component name
     * @param callable|mixed|mixed[]|null $func_or_map_or_type creation function or class-name, or, if the first
     *                                                         argument is a class-name, a map of constructor arguments
     * @param mixed|mixed[]               $map                 mixed list/map of parameter values (and/or boxed values)
     *
     * @return void
     *
     * @throws ContainerException
     */
    public function register($name, $func_or_map_or_type = null, $map = [])
    {
        if (is_callable($func_or_map_or_type)) {
            // second argument is a creation function
            $func = $func_or_map_or_type;
        } elseif (is_string($func_or_map_or_type)) {
            // second argument is a class-name
            $func = function (Container $container) use ($func_or_map_or_type, $map) {
                return $container->create($func_or_map_or_type, $map);
            };
            $map = [];
        } elseif (is_array($func_or_map_or_type)) {
            // second argument is a map of constructor arguments
            $func = function (Container $container) use ($name, $func_or_map_or_type) {
                return $container->create($name, $func_or_map_or_type);
            };
        } elseif (is_null($func_or_map_or_type)) {
            // first argument is both the component and class-name
            $func = function (Container $container) use ($name) {
                return $container->create($name);
            };
        } else {
            throw new InvalidArgumentException("unexpected argument type for \$func_or_map_or_type: " . gettype($func_or_map_or_type));
        }

        $this->factory[$name] = $func;

        $this->factory_map[$name] = $map;

        unset($this->values[$name]);
    }

    /**
     * Directly inject a component into the container - use this to register components that
     * have already been created for some reason; for example, the Composer ClassLoader.
     *
     * @param string $name component name
     * @param mixed  $value
     *
     * @return void
     *
     * @throws ContainerException
     */
    public function set($name, $value)
    {
        $this->values[$name] = $value;

        unset($this->factory[$name], $this->factory_map[$name]);
    }

    /**
     * Register a component as an alias of another registered component.
     *
     * @param string $new_name new component name
     * @param string $ref_name referenced existing component name
     */
    public function alias($new_name, $ref_name)
    {
        $this->register($new_name, function (Container $container) use ($ref_name) {
            return $container->get($ref_name);
        });
    }

    /**
     * Register a configuration function, which will be applied as late as possible, e.g.
     * on first use of the component. For example:
     *
     *     $container->configure('stack', function (MiddlewareStack $stack) {
     *         $stack->push(new MoreAwesomeMiddleware());
     *     });
     *
     * The given configuration function should include the configured component as the
     * first parameter to the closure, but may include any number of parameters, which
     * will be resolved and injected.
     *
     * The first argument (component name) is optional - that is, the name can be inferred
     * from the first parameter of the closure; the following will work:
     *
     *     $container->configure(function (PageLayout $layout) {
     *         $layout->title = "Welcome";
     *     });
     *
     * In some cases, such as using component names like "cache.path" (which because of the
     * dot in the name cannot be resolved by parameter name), you can use a boxed reference
     * in the optional `$map` argument, e.g.:
     *
     *     $container->configure(
     *         function (FileCache $cache, $path) {
     *             $cache->setPath($path);
     *         },
     *         ['path' => $container->ref('cache.path')]
     *     );
     *
     * You may optionally provide a list/map of parameter values, similar to the one
     * accepted by {@see Container::register()} - the typical reason to use this, is if
     * you need to inject another component by name, e.g. using {@see Container::ref()}.
     *
     * You can also use `configure()` to decorate objects, or manipulate (or replace) values:
     *
     *     $container->configure('num_kittens', function ($num_kittens) {
     *         return $num_kittens + 6; // add another litter
     *     });
     *
     * In other words, if your closure returns something, the component will be replaced.
     *
     * @param string|callable        $name_or_func component name
     *                                             (or callable, if name is left out)
     * @param callable|mixed|mixed[] $func_or_map  `function (Type $component, ...) : void`
     *                                             (or parameter values, if name is left out)
     * @param mixed|mixed[]          $map          mixed list/map of parameter values and/or boxed values
     *                                             (or unused, if name is left out)
     *
     * @return void
     *
     * @throws ContainerException
     */
    public function configure($name_or_func, $func_or_map = null, $map = [])
    {
        if (is_callable($name_or_func)) {
            $func = $name_or_func;
            $map = $func_or_map ?: [];

            // no component name supplied, infer it from the closure:

            if ($func instanceof Closure) {
                $param = new ReflectionParameter($func, 0); // shortcut reflection for closures (as an optimization)
            } else {
                list($param) = Reflection::createFromCallable($func)->getParameters();
            }

            $name = Reflection::getParameterType($param) // infer component name from type-hint
                ?: $param->name; // infer component name from parameter name

            if (!$this->has($name) && $this->has($param->name)) {
                $name = $param->name; // TODO QA: state-dependent configuration here. wrong?
            }
        } else {
            $name = $name_or_func;
            $func = $func_or_map;

            if (!array_key_exists(0, $map)) {
                $map[0] = $this->ref($name);
            }
        }

        $this->config[$name][] = $func;
        $this->config_map[$name][] = $map;
    }

    /**
     * Check if a component with a given name has been registered
     *
     * TODO QA: should this method be public or protected? it creates state-dependency.
     *
     * @param string $name component name
     *
     * @return bool true, if a component with the given name has been defined
     */
    public function has($name)
    {
        return array_key_exists($name, $this->values) || isset($this->factory[$name]);
    }

    /**
     * Creates a boxed reference to a component in the container.
     *
     * You can use this in conjunction with `register()` to provide a component reference
     * without expanding that reference until first use - for example:
     *
     *     $container->register(UserRepo::class, [$container->ref('cache')]);
     *
     * This will reference the "cache" component and provide it as the first argument to the
     * constructor of `UserRepo` - compared with using `$container->get('cache')`, this has
     * the advantage of not actually activating the "cache" component until `UserRepo` is
     * used for the first time.
     *
     * Another reason (besides performance) to use references, is to defer the reference:
     *
     *     $container->register(FileCache::class, ['root_path' => $container->ref('cache.path')]);
     *
     * In this example, the component "cache.path" will be fetched from the container on
     * first use of `FileCache`, giving you a chance to configure "cache.path" later.
     *
     * @param string $name component name
     *
     * @return BoxedReference component reference
     */
    public function ref($name)
    {
        return new BoxedReference($name);
    }

    /**
     * Add a packaged configuration (a "provider") to this container.
     *
     * @see ProviderInterface
     *
     * @param ProviderInterface $provider
     *
     * @return void
     */
    public function add(ProviderInterface $provider)
    {
        $provider->register($this);
    }

    /**
     * Create and bootstrap a new `Container` instance
     *
     * @return Container
     */
    public function createContainer()
    {
        return new Container(
            $this->values,
            $this->factory,
            $this->factory_map,
            $this->config,
            $this->config_map
        );
    }
}
