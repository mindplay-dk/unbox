<?php

namespace mindplay\unbox;

use Interop\Container\ContainerInterface;

/**
 * This class implements a boxed reference to a component in a Container.
 */
class BoxedReference implements BoxedValueInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface $container container reference
     * @param string             $name      component name
     */
    public function __construct(ContainerInterface $container, $name)
    {
        $this->container = $container;
        $this->name = $name;
    }

    /**
     * @return mixed the boxed value
     */
    public function unbox()
    {
        return $this->container->get($this->name);
    }
}
