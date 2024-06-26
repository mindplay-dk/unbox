<?php

namespace mindplay\unbox;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;

/**
 * This class implements a simple dependency injection container.
 */
class Container extends Configuration implements ContainerInterface, FactoryInterface
{
    /**
     * @var array<string,bool> map where component name => TRUE, if the component has been initialized
     */
    protected $active = [];

    /**
     * @var array<string,int> map where component name => activation depth
     *
     * @see get()
     */
    private $activations = [];

    public function __construct(Configuration $config)
    {
        $config->copyTo($this);

        $this->values = $this->values +
            [
                get_class($this)             => $this,
                __CLASS__                    => $this,
                ContainerInterface::class    => $this,
                FactoryInterface::class      => $this,
            ];
    }

    /**
     * Resolve the registered component with the given name.
     *
	 * @template T of object
     * 
     * @param string $name component name
     * 
	 * @return ($name is class-string<T> ? T : mixed) component instance/value
     *
     * @throws NotFoundException
     */
    public function get(string $name)
    {
        if (! isset($this->active[$name])) {
            try {
                if (isset($this->activations[$name])) {
                    $activations = array_flip($this->activations);

                    ksort($activations, SORT_NUMERIC); // order by activation depth

                    $activations = array_slice($activations, array_search($name, $activations, true) ?: 0);

                    $activations[] = $name;

                    $activation_path = implode(" -> ", $activations);

                    throw new ContainerException("Dependency cycle detected: " . $activation_path);
                }

                $this->activations[$name] = count($this->activations);

                if (isset($this->factory[$name])) {
                    $this->values[$name] = $this->call($this->factory[$name], $this->factory_map[$name]);
                } elseif (! array_key_exists($name, $this->values)) {
                    foreach ($this->fallbacks as $fallback) {
                        if ($fallback->has($name)) {
                            $this->values[$name] = $fallback->get($name);

                            break;
                        }
                    }

                    if (! array_key_exists($name, $this->values)) {
                        throw new NotFoundException($name);
                    }
                }

                if (isset($this->config[$name])) {
                    foreach ($this->config[$name] as $index => $config) {
                        $value = $this->call($config, [$this->values[$name]] + $this->config_map[$name][$index]);

                        if ($value !== null) {
                            $this->values[$name] = $value;
                        }
                    }
                }

                $this->active[$name] = true;
            } finally {
                unset($this->activations[$name]);
            }
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
    public function has(string $name): bool
    {
        if (array_key_exists($name, $this->values) || isset($this->factory[$name])) {
            return true;
        }

        foreach ($this->fallbacks as $fallback) {
            if ($fallback->has($name)) {
                return true;
            }
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
    public function isActive(string $name): bool
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
     * @param callable                $callback any arbitrary closure or callable
     * @param array<int|string,mixed> $map      mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed return value from the given callable
     */
    public function call(callable $callback, array $map = [])
    {
        $params = Reflection::createFromCallable($callback)->getParameters();

        return $callback(...$this->resolve($params, $map));
    }

    /**
     * Create an instance of a given class.
     *
     * The container will internally resolve and inject any constructor arguments
     * not explicitly provided in the (optional) second parameter.
     *
     * @param string                  $class_name fully-qualified class-name
     * @param array<int|string,mixed> $map        mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed new instance of the specified class
     *
     * @throws InvalidArgumentException
     */
    public function create(string $class_name, array $map = []): mixed
    {
        if (! class_exists($class_name)) {
            throw new InvalidArgumentException("unable to create component: {$class_name} (autoloading failed)");
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
     * Internally resolves parameters to functions or constructors.
     *
     * This is the heart of the beast.
     *
     * @param ReflectionParameter[]   $params parameter reflections
     * @param array<int|string,mixed> $map    mixed list/map of parameter values (and/or boxed values)
     * @param bool                    $safe   if TRUE, it's considered safe to resolve against parameter names
     *
     * @return array<mixed> parameters
     *
     * @throws ContainerException
     */
    protected function resolve($params, $map, $safe = true): array
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
     * 
     * @return void
     */
    protected function inject($name, $value): void
    {
        $this->values[$name] = $value;
        $this->active[$name] = true;
    }
}
