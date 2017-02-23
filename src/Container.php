<?php

namespace mindplay\unbox;

use Psr\Container\ContainerInterface;
use ReflectionFunction;

/**
 * This class implements a minimal PSR-11 dependency injection container.
 */
class Container extends Configuration implements ContainerInterface
{
    /**
     * @var bool[] map where component name => TRUE, if the component has been initialized
     */
    protected $active = [];

    /**
     * @param Configuration $config    Configuration (e.g. ContainerFactory) to copy to this Container instance
     */
    public function __construct(Configuration $config)
    {
        $config->copyTo($this);

        $defaults = [
            get_class($this)          => $this,
            __CLASS__                 => $this,
            ContainerInterface::class => $this,
        ];

        $this->values = $this->values + $defaults;
    }

    /**
     * Resolve the registered component with the given name.
     *
     * @param string $id component name
     *
     * @return mixed
     *
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function get($id)
    {
        if (! isset($this->active[$id])) {
            if (isset($this->factory[$id])) {
                $factory = $this->factory[$id];

                $reflection = new ReflectionFunction($factory);

                $params = Invoker::resolveParameters($this, $reflection->getParameters(), $this->factory_map[$id]);

                $this->values[$id] = call_user_func_array($factory, $params);
            } elseif (! array_key_exists($id, $this->values)) {
                throw new NotFoundException($id);
            }

            $this->active[$id] = true;

            $this->initialize($id);
        }

        return $this->values[$id];
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

                $params = Invoker::resolveParameters($this, $reflection->getParameters(), $map);

                $value = call_user_func_array($config, $params);

                if ($value !== null) {
                    $this->values[$name] = $value;
                }
            }
        }
    }
}
