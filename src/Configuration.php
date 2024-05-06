<?php

namespace mindplay\unbox;

use Psr\Container\ContainerInterface;

/**
 * This abstract base-class defines the internal state of `Container` and `ContainerFactory`
 */
abstract class Configuration
{
    /**
     * @var array<string,mixed> map where component name => value
     */
    protected $values = [];

    /**
     * @var array<string,callable> map where component name => factory function
     */
    protected $factory = [];

    /**
     * @var array<string,array<int|string,string>> map where component name => mixed list/map of parameter names
     */
    protected $factory_map = [];

    /**
     * @var array<string,callable[]> map where component name => list of configuration functions
     */
    protected $config = [];

    /**
     * @var array<string,array<int|string,array<int|string,string>>> map where component name => mixed list/map of parameter names
     */
    protected $config_map = [];

    /**
     * @var ContainerInterface[] list of fallback containers for unregistered components
     */
    protected $fallbacks = [];

    /**
     * Check for the existence of a component with a given name.
     *
     * @param string $name component name
     *
     * @return bool true, if a component with the given name has been defined
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values) || isset($this->factory[$name]);
    }

    /**
     * Internally copy configuration state, e.g. from `ContainerFactory` to `Container`
     */
    protected function copyTo(Configuration $target): void
    {
        $target->values = $this->values;
        $target->factory = $this->factory;
        $target->factory_map = $this->factory_map;
        $target->config = $this->config;
        $target->config_map = $this->config_map;
        $target->fallbacks = $this->fallbacks;
    }
}
