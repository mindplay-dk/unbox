<?php

namespace mindplay\unbox;

use Closure;
use Interop\Container\ContainerInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;
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
    const ARG_PATTERN = '/.*\[\s*(?:\<required\>|\<optional\>)\s*([^\s]+)/';

    /**
     * @var mixed[] map where component name => value
     */
    protected $values = array();

    /**
     * @var callable[] map where component name => factory function
     */
    protected $factory = array();

    /**
     * @var array map where component name => mixed list/map of parameter names
     */
    protected $factory_map = array();

    /**
     * @var bool[] map where component name => true (if the component is immutable)
     */
    protected $immutable = array();

    /**
     * @var (callable[])[] map where component name => list of configuration functions
     */
    protected $config = array();

    /**
     * @var array map where component name => mixed list/map of parameter names
     */
    protected $config_map = array();

    /**
     * Self-register this container for dependency injection
     */
    public function __construct()
    {
        $this->values[get_class($this)] =
        $this->values[__CLASS__] =
        $this->values[ContainerInterface::class] =
        $this->values[FactoryInterface::class] =
            $this;
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
        if (!array_key_exists($name, $this->values)) {
            if (!isset($this->factory[$name])) {
                throw new NotFoundException($name);
            }

            $factory = $this->factory[$name];

            $reflection = new ReflectionFunction($factory);

            $params = $this->resolve($reflection->getParameters(), $this->factory_map[$name]);

            $this->values[$name] = call_user_func_array($factory, $params);

            $this->initialize($name);

            $this->immutable[$name] = true; // prevent further changes to this component
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
        if (isset($this->immutable[$name])) {
            throw new ContainerException("attempted overwrite of initialized component: {$name}");
        }

        $this->values[$name] = $value;

        $this->initialize($name);
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
     * @param string                        $name component name
     * @param callable|string|string[]|null $func `function ($owner) : mixed`
     * @param mixed|mixed[]                 $map  mixed list/map of parameter values (and/or boxed values)
     *
     * @return void
     *
     * @throws ContainerException
     */
    public function register($name, $func = null, $map = array())
    {
        if (@$this->immutable[$name]) {
            throw new ContainerException("attempted re-registration of active component: {$name}");
        }

        if (is_null($func)) {
            $func = function () use ($name) {
                return $this->create($name);
            };
        } elseif (is_string($func)) {
            $func = function () use ($func, $map) {
                return $this->create($func, $map);
            };
        } elseif (is_array($func)) {
            $map = $func;

            $func = function () use ($name, $map) {
                return $this->create($name, $map);
            };
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
     * @throws NotFoundException
     */
    public function configure($name_or_func, $func_or_map = null, $map = null)
    {
        if ($name_or_func instanceof Closure) {
            // no component name supplied, infer it from the closure:

            $func = $name_or_func;
            $map = $func_or_map;

            $param = new ReflectionParameter($func, 0);

            preg_match(self::ARG_PATTERN, $param->__toString(), $matches);

            $name = $matches[1];

            if (!$this->has($name) && $this->has($param->name)) {
                $name = $param->name;
            }
        } else {
            $name = $name_or_func;
            $func = $func_or_map;
        }

        $this->config[$name][] = $func;
        $this->config_map[$name][] = $map;

        if ($this->isActive($name)) {
            // component is already active - initialize the component immediately:

            $this->initialize($name);
        }
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
        return array_key_exists($name, $this->values)
        || isset($this->factory[$name]);
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
        return array_key_exists($name, $this->values);
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
    public function call($callback, $map = array())
    {
        if (is_array($callback)) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException("expected callable");
            }

            $reflection = new ReflectionMethod($callback[0], $callback[1]);

            return $reflection->invokeArgs(
                is_object($callback[0]) ? $callback[0] : null,
                $this->resolve($reflection->getParameters(), $map)
            );
        } elseif (is_object($callback)) {
            if ($callback instanceof Closure) {
                $reflection = new ReflectionFunction($callback);
            } elseif (method_exists($callback, '__invoke')) {
                $reflection = new ReflectionMethod($callback, '__invoke');
            } else {
                throw new InvalidArgumentException("class " . get_class($callback) . " does not implement __invoke()");
            }
        } else {
            $reflection = new ReflectionFunction($callback);
        }

        return call_user_func_array($callback, $this->resolve($reflection->getParameters(), $map));
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
    public function create($class_name, $map = array())
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
            : array();

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
     * Internally resolves parameters to functions or constructors.
     *
     * This is the heart of the beast.
     *
     * @param ReflectionParameter[] $params parameter reflections
     * @param mixed|mixed[]         $map    mixed list/map of parameter values (and/or boxed values)
     *
     * @return array parameters
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function resolve(array $params, $map)
    {
        $args = array();

        $map = (array)$map;

        foreach ($params as $index => $param) {
            $param_name = $param->name;

            if (array_key_exists($param_name, $map)) {
                $value = $map[$param_name]; // // resolve as user-provided named argument
            } elseif (array_key_exists($index, $map)) {
                $value = $map[$index]; // resolve as user-provided positional argument
            } else {
                // as on optimization, obtain the argument type without triggering autoload:

                preg_match(self::ARG_PATTERN, $param->__toString(), $matches);

                $type = $matches[1];

                if ($type && $this->has($type)) {
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
     *
     * @throws ContainerException on attempt to initialize an already-initialized component
     */
    protected function initialize($name)
    {
        if (isset($this->config[$name])) {
            foreach ($this->config[$name] as $index => $config) {
                $map = $this->config_map[$name][$index];

                $reflection = new ReflectionFunction($config);

                $params = $this->resolve($reflection->getParameters(), $map);

                $value = call_user_func_array($config, $params);

                if ($value !== null) {
                    $this->values[$name] = $value;
                }
            }
        }

        unset($this->config[$name]);
    }
}
