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
     * @var bool[] map where component name => true (if the component has been initialized)
     */
    protected $initialized = array();

    /**
     * @var (callable[])[] map where component name => list of configuration functions
     */
    protected $config = array();

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

            if (isset($this->config[$name])) {
                foreach ($this->config[$name] as $config) {
                    $this->applyConfiguration($name, $config);
                }
            }

            $this->initialized[$name] = true; // prevent further changes to this component
        }

        return $this->values[$name];
    }

    /**
     * @param string $name component name
     * @param mixed  $value
     *
     * @return void
     *
     * @throws ContainerException
     */
    public function set($name, $value)
    {
        if (isset($this->initialized[$name])) {
            throw new ContainerException("attempted overwrite of initialized component: {$name}");
        }

        $this->values[$name] = $value;
    }

    /**
     * @param string                 $name component name
     * @param callable|string[]|null $func `function ($owner) : mixed`
     * @param string|string[]        $map  mixed list/map of parameter values (and/or boxed values)
     *
     * @return void
     *
     * @throws ContainerException
     */
    public function register($name, $func = null, $map = array())
    {
        if (@$this->initialized[$name]) {
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
            $func = function () use ($name, $func) {
                return $this->create($name, $func);
            };
        }

        $this->factory[$name] = $func;

        $this->factory_map[$name] = $map;

        unset($this->values[$name]);
    }

    /**
     * @param string   $name component name
     * @param callable $func `function ($component, $owner) : void`
     *
     * @return void
     *
     * @throws NotFoundException
     */
    public function configure($name, callable $func)
    {
        if ($this->isActive($name)) {
            // component is already active - run the configuration function right away:

            $this->applyConfiguration($name, $func);

            return;
        }

        if (!isset($this->factory[$name])) {
            throw new NotFoundException($name);
        }

        $this->config[$name][] = $func;
    }

    /**
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
     * @param string $name component name
     *
     * @return bool
     */
    public function isActive($name)
    {
        return array_key_exists($name, $this->values);
    }

    /**
     * @param callable|object $callback any arbitrary closure or callable, or object implementing __invoke()
     * @param string[]|string $map      mixed list/map of parameter values (and/or boxed values)
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
     * @param string          $class_name fully-qualified class-name
     * @param string[]|string $map        mixed list/map of parameter values (and/or boxed values)
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
     * @param string $name component name
     *
     * @return BoxedValueInterface boxed component reference
     */
    public function ref($name)
    {
        return new BoxedReference($this, $name);
    }

    /**
     * @param ProviderInterface $provider
     */
    public function add(ProviderInterface $provider)
    {
        $provider->register($this);
    }

    /**
     * @param ReflectionParameter[] $params parameter reflections
     * @param string[]|string       $map    mixed list/map of parameter values (and/or boxed values)
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
            $param_name = $param->getName();

            if (array_key_exists($param_name, $map)) {
                $value = $map[$param_name];
            } elseif (array_key_exists($index, $map)) {
                $value = $map[$index];
            } else {
                preg_match(self::ARG_PATTERN, $param->__toString(), $matches);

                $type = $matches[1];
                
                if ($type && $this->has($type)) {
                    $value = $this->get($type);
                } elseif ($this->has($param_name)) {
                    $value = $this->get($param_name);
                } elseif ($param->isOptional()) {
                    $value = $param->getDefaultValue();
                } else {
                    $reflection = $param->getDeclaringFunction();

                    throw new ContainerException(
                        "unable to resolve \"{$type}\" for parameter: \${$param_name}" .
                        ' in: ' . $reflection->getFileName() . '#' . $reflection->getStartLine()
                    );
                }
            }

            if ($value instanceof BoxedValueInterface) {
                $value = $value->unbox();
            }

            $args[] = $value;
        }

        return $args;
    }

    /**
     * @param string  $name   component name
     * @param Closure $config configuration function
     *
     * @return void
     */
    protected function applyConfiguration($name, $config)
    {
        $config_reflection = new ReflectionFunction($config);

        $config_params = $this->resolve($config_reflection->getParameters(), array());

        $config_refs = array();

        foreach (array_keys($config_params) as $key) {
            $config_refs[$key] = &$config_params[$key];
        }

        call_user_func_array($config, $config_refs);

        $this->values[$name] = $config_refs[0];
    }
}
