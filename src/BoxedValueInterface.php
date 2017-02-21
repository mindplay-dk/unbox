<?php

namespace mindplay\unbox;

use Psr\Container\ContainerInterface;

/**
 * This interface defines a simple means of boxing a value, which will be unboxed
 * as late as possible by the dependency injection container.
 */
interface BoxedValueInterface
{
    /**
     * @param ContainerInterface $container Container from which to Unbox a value
     *
     * @return mixed the unboxed value
     */
    public function unbox(ContainerInterface $container);
}
