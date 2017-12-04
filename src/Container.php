<?php

namespace mindplay\unbox;

use Psr\Container\ContainerInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;

/**
 * This class implements a simple dependency injection container.
 */
class Container extends Configuration implements ContainerInterface, FactoryInterface
{
    /**
     * @var bool[] map where component name => TRUE, if the component has been initialized
     */
    protected $active = [];

    /**
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
    {
        $config->copyTo($this);

        $this->values = $this->values +
            [
                get_class($this)          => $this,
                __CLASS__                 => $this,
                ContainerInterface::class => $this,
                FactoryInterface::class   => $this,
            ];
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
        if (! isset($this->active[$name])) {
            if (isset($this->factory[$name])) {
                $factory = $this->factory[$name];

                $reflection = new ReflectionFunction($factory);

                $params = $this->resolve($reflection->getParameters(), $this->factory_map[$name]);

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
     * See also {@see create()} which lets you invoke any constructor.
     *
     * @param callable|object $callback any arbitrary closure or callable, or object implementing __invoke()
     * @param mixed|mixed[]   $map      mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed return value from the given callable
     */
    public function call($callback, $map = [])
    {
        $params = Reflection::createFromCallable($callback)->getParameters();

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
            ? $this->resolve($constructor->getParameters(), $map, false)
            : [];

        return $reflection->newInstanceArgs($params);
    }

    /**
     * Internally resolves parameters to functions or constructors.
     *
     * This is the heart of the beast.
     *
     * @param ReflectionParameter[] $params parameter reflections
     * @param array                 $map    mixed list/map of parameter values (and/or boxed values)
     * @param bool                  $safe   if TRUE, it's considered safe to resolve against parameter names
     *
     * @return array parameters
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    protected function resolve(array $params, $map, $safe = true)
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

                $type = Reflection::getParameterType($param);

                if ($type && isset($map[$type])) {
                    $value = $map[$type]; // resolve as user-provided type-hinted argument
                } elseif ($type && $this->has($type)) {
                    $value = $this->get($type); // resolve as component registered by class/interface name
                } elseif ($safe && $this->has($param_name)) {
                    $value = $this->get($param_name); // resolve as component with matching parameter name
                } elseif ($param->isOptional()) {
                    $value = $param->getDefaultValue(); // unresolved, optional: resolve using default value
                } elseif ($type && $param->allowsNull()) {
                    $value = null; // unresolved, type-hinted, nullable: resolve as NULL
                } else {
                    // unresolved - throw a container exception:

                    $reflection = $param->getDeclaringFunction();

                    throw new ContainerException(
                        "unable to resolve parameter: \${$param_name} " . ($type ? "({$type}) " : "") .
                        "in file: " . $reflection->getFileName() . ", line " . $reflection->getStartLine()
                    );
                }
            }

            if ($value instanceof BoxedValueInterface) {
                $value = $value->unbox($this); // unbox a boxed value
            }

            $args[] = $value; // argument resolved!
        }

        return $args;
    }

    /**
     * Dynamically inject a component into this Container.
     *
     * Enables classes that extend `Container` to dynamically inject components (to implement "auto-wiring")
     *
     * @param string $name
     * @param mixed  $value
     */
    protected function inject($name, $value)
    {
        $this->values[$name] = $value;
        $this->active[$name] = true;
    }

    /**
     * Internally initialize an active component.
     *
     * @param string $name component name
     *
     * @return void
     */
    private function initialize($name)
    {
        if (isset($this->config[$name])) {
            foreach ($this->config[$name] as $index => $config) {
                $map = $this->config_map[$name][$index];

                $reflection = Reflection::createFromCallable($config);

                $params = $this->resolve($reflection->getParameters(), $map);

                $value = call_user_func_array($config, $params);

                if ($value !== null) {
                    $this->values[$name] = $value;
                }
            }
        }
    }
}
