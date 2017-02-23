<?php

namespace mindplay\unbox;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * This class implements a Resolver for any PSR-11 Dependency Injection Container,
 * and doubles as a proxy to the inner Container, with the option to inject (at run-time)
 * additional components in "auto-wiring" scenarios.
 */
class Resolver implements ContainerInterface, FactoryInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var bool[] map where component name => TRUE, if the component has been initialized
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

        $this->injections = [
            get_class($this)          => $this,
            __CLASS__                 => $this,
            ContainerInterface::class => $this,
        ];

        $this->active = [
            get_class($this)          => true,
            __CLASS__                 => true,
            ContainerInterface::class => true,
        ];
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

            return $this->container->get($id);
        }

        throw new NotFoundException($id);
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
        return Invoker::invokeCallable($this, $callback, $map);
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
        return Invoker::invokeConstructor($this, $class_name, $map);
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
