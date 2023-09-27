<?php

namespace mindplay\unbox;

use Closure;
use ReflectionParameter;
use Psr\Container\ContainerInterface;

/**
 * This class provides boostrapping/configuration facilities for creation of `Container` instances.
 */
class ContainerFactory extends Configuration
{
    /**
     * @var string[][] map where Requirement ID => list of requirement descriptions
     */
    protected $required = [];

    /**
     * @var string[] map where Requirement ID => description of provided abstract requirements
     */
    protected $provided = [];

    public function __construct()
    {}

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
     * @param mixed[]                     $map                 mixed list/map of parameter values (and/or boxed values)
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function register($name, $func_or_map_or_type = null, $map = []): void
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
     */
    public function set(string $name, $value): void
    {
        $this->values[$name] = $value;

        unset($this->factory[$name], $this->factory_map[$name]);
    }

    /**
     * Register a component as an alias of another registered component.
     *
     * @param string $new_name new component name
     * @param string $ref_name referenced existing component name
     * 
     * @return void
     */
    public function alias(string $new_name, string $ref_name): void
    {
        $this->register($new_name, function (Container $container) use ($ref_name) {
            return $container->get($ref_name);
        });
    }

    /**
     * Register a configuration function, which will be applied as late as possible, e.g.
     * on first use of the component. For example:
     *
     *     $factory->configure('stack', function (MiddlewareStack $stack) {
     *         $stack->push(new MoreAwesomeMiddleware());
     *     });
     *
     * The given configuration function should include the configured component as the
     * first parameter to the closure, but may include any number of parameters, which
     * will be resolved and injected.
     *
     * The first argument (component name) is optional - that is, the name can be inferred
     * from a type-hint on the first parameter of the closure, so the following will work:
     *
     *     $factory->register(PageLayout::class);
     *
     *     $factory->configure(function (PageLayout $layout) {
     *         $layout->title = "Welcome";
     *     });
     *
     * In some cases, you may wish to fetch additional dependencies, by using additional
     * arguments, and specifying how these should be resolved, e.g. using
     * {@see Container::ref()} - for example:
     *
     *     $factory->register("cache", FileCache::class);
     *
     *     $factory->configure(
     *         "cache",
     *         function (FileCache $cache, $path) {
     *             $cache->setPath($path);
     *         },
     *         ['path' => $container->ref('cache.path')]
     *     );
     *
     * You can also use `configure()` to decorate objects, or manipulate (or replace) values:
     *
     *     $factory->configure('num_kittens', function ($num_kittens) {
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
     * @throws InvalidArgumentException
     */
    public function configure($name_or_func, $func_or_map = null, $map = []): void
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

            $name = Reflection::getParameterType($param); // infer component name from type-hint

            if ($name === null) {
                throw new InvalidArgumentException("no component-name or type-hint specified");
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
     * Creates a boxed reference to a component with a given name.
     *
     * You can use this in conjunction with `register()` to provide a component reference
     * without expanding that reference until first use - for example:
     *
     *     $factory->register(UserRepo::class, [$factory->ref('cache')]);
     *
     * This will reference the "cache" component and provide it as the first argument to the
     * constructor of `UserRepo` - compared with using `$container->get('cache')`, this has
     * the advantage of not actually activating the "cache" component until `UserRepo` is
     * used for the first time.
     *
     * Another reason (besides performance) to use references, is to defer the reference:
     *
     *     $factory->register(FileCache::class, ['root_path' => $factory->ref('cache.path')]);
     *
     * In this example, the component "cache.path" will be fetched from the container on
     * first use of `FileCache`, giving you a chance to configure "cache.path" later.
     *
     * @param string $name component name
     *
     * @return BoxedReference component reference
     */
    public function ref(string $name): BoxedReference
    {
        return new BoxedReference($name);
    }

    /**
     * Add a Provider (a packaged unit of bootstrapping) to this Container.
     *
     * @param ProviderInterface $provider
     *
     * @return void
     *
     * @throws ContainerException if the given Requirement has already been fulfilled
     *
     * @see ProviderInterface
     */
    public function add(ProviderInterface $provider): void
    {
        $provider->register($this);
    }

    /**
     * Defines a Requirement, which will be checked when you call {@see createContainer()}.
     *
     * Requirements must be fulfilled by either {@see register()} or {@see provides()}.
     *
     * @param string $requirement Requirement name.
     * @param string $description Optional description.
     *                            Displayed in an Exception message on failure.
     *
     * @see provides()
     */
    public function requires($requirement, $description = "")
    {
        $this->required[$requirement][] = $description;
    }

    /**
     * Fulfills an abstract Requirement defined by {@see requires()}.
     *
     * @param string $requirement Requirement name.
     * @param string $description Optional description.
     *                            Displayed in an Exception message on failure.
     *
     * @throws ContainerException if the given Requirement has already been fulfilled
     *
     * @see requires()
     */
    public function provides($requirement, $description = "")
    {
        if (array_key_exists($requirement, $this->provided)) {
            $message = "The following Requirement has already been fulfilled: {$requirement}";

            $description = $this->provided[$requirement];

            if ($description) {
                $message .= " ($description)";
            }

            throw new ContainerException($message);
        }

        $this->provided[$requirement] = $description;
    }

    /**
     * Add a fallback container to this container.
     * 
     * Fallback containers will be queried (in the order they were added) for any components
     * that haven't been registered in the container itself - effectively, this means that
     * calls to `has` and `get` will propagate to any registered fallbacks.
     * 
     * Note that the relationship with fallback containers is one-directional: the container
     * can resolve dependencies via fallbacks, but the fallbacks cannot resolve dependencies
     * from the container itself.
     * 
     * (You can use this feature to build layered architecture with different component
     * life-cycles - refer to the README for more details.)
     * 
     * @param ContainerInterface $container
     * 
     * @return void
     */
    public function registerFallback(ContainerInterface $container): void
    {
        $this->fallbacks[] = $container;
    }

    /**
     * Create and bootstrap a new `Container` instance
     *
     * @return Container
     *
     * @throws ContainerException if any Requirements have not been fulfilled
     */
    public function createContainer()
    {
        $messages = [];

        foreach ($this->required as $requirement => $descriptions) {
            if (! array_key_exists($requirement, $this->provided) && !$this->has($requirement)) {
                $messages[] = "The following Requirement has not been fulfilled: {$requirement}"
                    . (array_filter($descriptions) ? " (" . implode("; ", $descriptions) . ")" : "");
            }
        }

        if ($messages) {
            throw new ContainerException(implode("\n", $messages));
        }

        return new Container($this);
    }
}
