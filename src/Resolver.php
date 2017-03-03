<?php

namespace mindplay\unbox;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;

/**
 * This class implements parameter resolution, invokation of callables and constructors, and
 * run-time injections, by proxying any PSR-11 Dependency Injection Container.
 */
class Resolver implements ContainerInterface, FactoryInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var bool[] map where active component name => TRUE
     */
    protected $active = [];

    /**
     * @var array map where component name => run-time injected component
     *
     * @see inject()
     */
    protected $injections = [];

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        if (array_key_exists($id, $this->injections)) {
            return $this->injections[$id];
        }

        if ($this->container->has($id)) {
            $this->active[$id] = true;
        }

        return $this->container->get($id);
    }

    /**
     * @inheritdoc
     */
    public function has($id)
    {
        if ($this->container->has($id)) {
            return true;
        }

        if (array_key_exists($id, $this->injections)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a component has been resolved and is currently active.
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
        if (! class_exists($class_name)) {
            throw new InvalidArgumentException("unable to create component: {$class_name}");
        }

        $reflection = new ReflectionClass($class_name);

        if (! $reflection->isInstantiable()) {
            throw new InvalidArgumentException("unable to create instance of abstract class: {$class_name}");
        }

        $constructor = $reflection->getConstructor();

        $params = $constructor
            ? $this->resolve($constructor->getParameters(), $map, false)
            : [];

        return $reflection->newInstanceArgs($params);
    }

    /**
     * Resolves parameters against the proxied `ContainerInterface` instance
     *
     * @param ReflectionParameter[] $params    parameter reflections
     * @param array                 $map       mixed list/map of parameter values (and/or boxed values)
     * @param bool                  $safe      if TRUE, it's considered safe to resolve against parameter names
     *
     * @return array resolved parameters
     *
     * @throws ContainerException
     */
    public function resolve(array $params, $map, $safe = true)
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
                } elseif ($type && $this->container->has($type)) {
                    $value = $this->container->get($type); // resolve as component registered by class/interface name
                } elseif ($safe && $this->container->has($param_name)) {
                    $value = $this->container->get($param_name); // resolve as component with matching parameter name
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
     * Dynamically inject a component into this Resolver.
     *
     * Allows for implementation of "auto-wiring" patterns, without altering the state of any internal Containers.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws InvalidArgumentException on attempted override of any component provided by the internal Containers
     */
    public function inject($name, $value)
    {
        if ($this->has($name)) {
            throw new InvalidArgumentException("attempted override of component: {$name}");
        }

        $this->injections[$name] = $value;
        $this->active[$name] = true;
    }
}
