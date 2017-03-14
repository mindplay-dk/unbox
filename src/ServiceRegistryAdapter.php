<?php

namespace mindplay\unbox;

use Interop\Provider\ServiceRegistryInterface;

/**
 * Interoperability-adapter for ContainerFactory to act as a `provider-interop` registry
 */
class ServiceRegistryAdapter implements ServiceRegistryInterface
{
    /**
     * @var ContainerFactory
     */
    private $factory;

    public function __construct(ContainerFactory $factory)
    {
        $this->factory = $factory;
    }

    public function register($id, callable $resolver)
    {
        $this->factory->register($id, $resolver);
    }
}
