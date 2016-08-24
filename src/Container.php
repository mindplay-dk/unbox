<?php

namespace mindplay\unbox;

use Closure;
use Interop\Container\ContainerInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * This class implements a simple dependency injection container.
 */
class Container implements ContainerInterface, FactoryInterface
{
    /**
     * @type string pattern for parsing an argument type from a ReflectionParameter string
     * @see getArgumentType()
     */
    const ARG_PATTERN = '/(?:\<required\>|\<optional\>)\\s+([\\w\\\\]+)/';

    /**
     * @var mixed[] map where component name => value
     */
    protected $values = [];

    /**
     * @var bool[] map where component name => flag
     */
    protected $active = [];

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
     * Self-register this container for dependency injection
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * This method makes Containers cloneable, and ensures that a cloned Container has
     * no active components. This effectively enables you to use an existing Container
     * as a prototype for new Containers, without having to bootstrap it from scratch.
     *
     * @internal
     */
    public function __clone()
    {
        $this->init();
    }

    /**
     * Internally self-register after construction or cloning.
     */
    private function init()
    {
        $this->values = [];
        $this->active = [];

        $this->set(get_class($this), $this);
        $this->set(__CLASS__, $this);
        $this->set(ContainerInterface::class, $this);
        $this->set(FactoryInterface::class, $this);
    }

    /**
     * Resolve the registered component with the given name.
     *
     * @param string $name component name
     *
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function get($name)
    {
        if (!isset($this->active[$name])) {
            if (isset($this->factory[$name])) {
                $factory = $this->factory[$name];

                $reflection = new ReflectionFunction($factory);

                $params = $this->resolve($reflection->getParameters(), @$this->factory_map[$name]);

                $this->values[$name] = call_user_func_array($factory, $params);
            } elseif (!array_key_exists($name, $this->values)) {
                throw new NotFoundException($name);
            }

            $this->active[$name] = true;

            $this->initialize($name);
        }

        return $this->values[$name];
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
        if ($this->isActive($name)) {
            throw new ContainerException("attempted overwrite of initialized component: {$name}");
        }

        $this->values[$name] = $value;

        unset($this->factory[$name], $this->factory_map[$name]);
    }

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
        if ($this->isActive($name)) {
            throw new ContainerException("attempted re-registration of active component: {$name}");
        }

        if (is_callable($func_or_map_or_type)) {
            // second argument is a creation function
            $func = $func_or_map_or_type;
        } elseif (is_string($func_or_map_or_type)) {
            // second argument is a class-name
            $func = function () use ($func_or_map_or_type, $map) {
                return $this->create($func_or_map_or_type, $map);
            };
        } elseif (is_array($func_or_map_or_type)) {
            // second argument is a map of constructor arguments
            $func = function () use ($name, $func_or_map_or_type) {
                return $this->create($name, $func_or_map_or_type);
            };
        } elseif (is_null($func_or_map_or_type)) {
            // first argument is both the component and class-name
            $func = function () use ($name) {
                return $this->create($name);
            };
        } else {
            throw new InvalidArgumentException("unexpected argument type for \$func_or_map_or_type: " . gettype($func_or_map_or_type));
        }

        $this->factory[$name] = $func;

        $this->factory_map[$name] = $map;

        unset($this->values[$name]);
    }

    /**
     * Register a component as an alias of another registered component.
     *
     * @param string $name     new component name
     * @param string $ref_name existing component name
     */
    public function alias($name, $ref_name)
    {
        $this->register($name, function () use ($ref_name) {
            return $this->get($ref_name);
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
                list($param) = $this->reflect($func)->getParameters();
            }

            // obtain the type-hint, but avoid triggering autoload:

            $name = preg_match(self::ARG_PATTERN, $param->__toString(), $matches) === 1
                ? $matches[1] // infer component name from type-hint
                : $param->name; // infer component name from parameter name

            if (!$this->has($name) && $this->has($param->name)) {
                $name = $param->name;
            }
        } else {
            $name = $name_or_func;
            $func = $func_or_map;

            if (!array_key_exists(0, $map)) {
                $map[0] = $this->ref($name);
            }
        }

        if ($this->isActive($name)) {
            throw new ContainerException("attempted re-registration of active component: {$name}");
        }

        $this->config[$name][] = $func;
        $this->config_map[$name][] = $map;
    }

    /**
     * Check for the existence of a component with a given name.
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
     * Check if a component has been unboxed and is currently active.
     *
     * @param string $name component name
     *
     * @return bool
     */
    public function isActive($name)
    {
        return isset($this->active[$name]);
    }

    /**
     * Call any given callable, using dependency injection to satisfy it's arguments, and/or
     * manually specifying some of those arguments - then return the value from the call.
     *
     * This will work for any callable:
     *
     *     $container->call('foo');               // function foo()
     *     $container->call($foo, 'baz');         // instance method $foo->baz()
     *     $container->call([Foo::class, 'bar']); // static method Foo::bar()
     *     $container->call($foo);                // closure (or class implementing __invoke)
     *
     * In any of those examples, you can also supply custom arguments, either named or
     * positional, or mixed, as per the `$map` argument in `register()`, `configure()`, etc.
     *
     * @param callable|object $callback any arbitrary closure or callable, or object implementing __invoke()
     * @param mixed|mixed[]   $map      mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed return value from the given callable
     */
    public function call($callback, $map = [])
    {
        $params = $this->reflect($callback)->getParameters();

        return call_user_func_array($callback, $this->resolve($params, $map));
    }

    /**
     * Create an instance of a given class.
     *
     * The container will internally resolve and inject any constructor arguments
     * not explicitly provided in the (optional) second parameter.
     *
     * @param string        $class_name fully-qualified class-name
     * @param mixed|mixed[] $map        mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed
     */
    public function create($class_name, $map = [])
    {
        if (!class_exists($class_name)) {
            throw new InvalidArgumentException("unable to create component: {$class_name}");
        }

        $reflection = new ReflectionClass($class_name);

        if (!$reflection->isInstantiable()) {
            throw new InvalidArgumentException("unable to create instance of abstract class: {$class_name}");
        }

        $constructor = $reflection->getConstructor();

        $params = $constructor
            ? $this->resolve($constructor->getParameters(), $map)
            : [];

        return $reflection->newInstanceArgs($params);
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
     * @return BoxedValueInterface boxed component reference
     */
    public function ref($name)
    {
        return new BoxedReference($this, $name);
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
     * Internally reflect on any type of callable
     *
     * @param callable $callback
     *
     * @return ReflectionFunctionAbstract
     */
    protected function reflect($callback)
    {
        if (is_object($callback)) {
            if ($callback instanceof Closure) {
                return new ReflectionFunction($callback);
            } elseif (method_exists($callback, '__invoke')) {
                return new ReflectionMethod($callback, '__invoke');
            }

            throw new InvalidArgumentException("class " . get_class($callback) . " does not implement __invoke()");
        } elseif (is_array($callback)) {
            if (is_callable($callback)) {
                return new ReflectionMethod($callback[0], $callback[1]);
            }

            throw new InvalidArgumentException("expected callable");
        } elseif (is_callable($callback)) {
            return new ReflectionFunction($callback);
        }

        throw new InvalidArgumentException("unexpected value: " . var_export($callback, true) . " - expected callable");
    }

    /**
     * Internally resolves parameters to functions or constructors.
     *
     * This is the heart of the beast.
     *
     * @param ReflectionParameter[] $params parameter reflections
     * @param array                 $map    mixed list/map of parameter values (and/or boxed values)
     *
     * @return array parameters
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function resolve(array $params, $map)
    {
        $args = [];

        foreach ($params as $index => $param) {
            $param_name = $param->name;

            if (array_key_exists($param_name, $map)) {
                $value = $map[$param_name]; // // resolve as user-provided named argument
            } elseif (array_key_exists($index, $map)) {
                $value = $map[$index]; // resolve as user-provided positional argument
            } else {
                // as on optimization, obtain the argument type without triggering autoload:

                $type = preg_match(self::ARG_PATTERN, $param->__toString(), $matches)
                    ? $matches[1]
                    : null; // no type-hint available

                if ($type && isset($map[$type])) {
                    $value = $map[$type]; // resolve as user-provided type-hinted argument
                } elseif ($type && $this->has($type)) {
                    $value = $this->get($type); // resolve as component registered by class/interface name
                } elseif ($this->has($param_name)) {
                    $value = $this->get($param_name); // resolve as component with matching parameter name
                } elseif ($param->isOptional()) {
                    $value = $param->getDefaultValue(); // unresolved: resolve using default value
                } else {
                    // unresolved - throw a container exception:

                    $reflection = $param->getDeclaringFunction();

                    throw new ContainerException(
                        "unable to resolve \"{$type}\" for parameter: \${$param_name}" .
                        ' in file: ' . $reflection->getFileName() . ', line ' . $reflection->getStartLine()
                    );
                }
            }

            if ($value instanceof BoxedValueInterface) {
                $value = $value->unbox(); // unbox a boxed value
            }

            $args[] = $value; // argument resolved!
        }

        return $args;
    }

    /**
     * Internally initialize an active component.
     *
     * @param string $name component name
     *
     * @return void
     */
    protected function initialize($name)
    {
        if (isset($this->config[$name])) {
            foreach ($this->config[$name] as $index => $config) {
                $map = $this->config_map[$name][$index];

                $reflection = $this->reflect($config);

                $params = $this->resolve($reflection->getParameters(), $map);

                $value = call_user_func_array($config, $params);

                if ($value !== null) {
                    $this->values[$name] = $value;
                }
            }
        }
    }
}
