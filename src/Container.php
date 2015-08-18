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
     * @param string          $name component name
     * @param callable        $func `function ($owner) : mixed`
     * @param string|string[] $map  mixed list/map of parameter names
     *
     * @return void
     *
     * @throws ContainerException
     */
    public function register($name, callable $func, $map = array())
    {
        if (@$this->initialized[$name]) {
            throw new ContainerException("attempted re-registration of active component: {$name}");
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
     * @param callable        $callback any arbitrary closure or callable
     * @param string[]|string $map      mixed list/map of parameter names
     *
     * @return mixed return value from the given callable
     */
    public function call(callable $callback, $map = array())
    {
        if (is_array($callback)) {
            switch (count($callback)) {
                case 1:
                    $reflection = new ReflectionFunction($callback[0]);
                    return call_user_func_array($callback[0], $this->resolve($reflection->getParameters(), $map));

                case 2:
                    $reflection = new ReflectionMethod($callback[0], $callback[1]);
                    return $reflection->invokeArgs($callback[0], $this->resolve($reflection->getParameters(), $map));

                default:
                    throw new InvalidArgumentException("expected callable");
            }
        }

        $reflection = new ReflectionFunction($callback);

        return call_user_func_array($callback, $this->resolve($reflection->getParameters(), $map));
    }

    /**
     * @param string          $name component name
     * @param string[]|string $map  mixed list/map of parameter names
     *
     * @return mixed
     */
    public function create($name, $map = array())
    {
        if (isset($this->factory[$name])) {
            return $this->call($this->factory[$name], $map + $this->factory_map[$name]);
        } else {
            if (!class_exists($name)) {
                throw new InvalidArgumentException("unable to create component: {$name}");
            }

            $reflection = new ReflectionClass($name);

            if (!$reflection->isInstantiable()) {
                throw new InvalidArgumentException("unable to create instance of abstract class: {$name}");
            }

            $constructor = $reflection->getConstructor();

            $params = $constructor
                ? $this->resolve($constructor->getParameters(), $map)
                : array();

            return $reflection->newInstanceArgs($params);
        }
    }

    /**
     * @param string $name component name
     *
     * @return callable component reference for use in parameter maps
     */
    public function ref($name)
    {
        return function () use ($name) {
            return $this->get($name);
        };
    }

    /**
     * @param mixed $value
     *
     * @return callable fixed value for use in parameter maps
     */
    public function value($value)
    {
        return function () use ($value) {
            return $value;
        };
    }

    /**
     * @param ProviderInterface $provider
     */
    public function add(ProviderInterface $provider)
    {
        $provider->register($this);
    }

    /**
     * @param ReflectionParameter[] $params
     * @param string[]|string       $map mixed list/map of parameter names
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
                $component = $map[$param_name];
            } elseif (array_key_exists($index, $map)) {
                $component = $map[$index];
            } else {
                preg_match(self::ARG_PATTERN, $param->__toString(), $matches);

                $component = $matches[1];

                if (!$this->has($component)) {
                    $component = $param_name;
                }
            }

            if ($component instanceof Closure) {
                $args[] = $this->call($component);

                continue;
            }

            if ($this->has($component)) {
                $args[] = $this->get($component);

                continue;
            }

            if ($param->isOptional()) {
                $args[] = $param->getDefaultValue();

                continue;
            }

            throw new ContainerException("unable to resolve \"{$component}\" for parameter: \${$param_name}");
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
