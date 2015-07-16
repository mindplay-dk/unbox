<?php

namespace container_registry_thingy;

use RuntimeException;

/**
 * Abstract base-class for service registry components.
 */
abstract class Registry
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * Initialize the internal container in this service registry
     */
    public function __construct()
    {
        $this->container = new Container($this, $this->getTypes());
    }

    /**
     * @param ServiceProvider|callable $provider service provider, or `function (Container $container) : void`
     */
    public function register(callable $provider)
    {
        $provider($this->container);
    }

    /**
     * Check the container for completeness, and type-check active components
     *
     * @throws RuntimeException if the Container is incomplete
     */
    public function validate()
    {
        $this->container->validate();
    }

    /**
     * @return string[] map where component name => fully-qualified class name (or pseudo-type name)
     */
    abstract protected function getTypes();
}
