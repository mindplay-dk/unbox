<?php

namespace mindplay\unbox;

/**
 * This interface defines a simple means of boxing a value, which will be unboxed
 * as late as possible by the dependency injection container.
 */
interface BoxedValueInterface
{
    /**
     * @param Container $container Container from which to Unbox a value
     *
     * @return mixed the unboxed value
     */
    public function unbox(Container $container): mixed;
}
