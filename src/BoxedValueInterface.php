<?php

namespace mindplay\unbox;

/**
 * This interface defines a simple means of boxing a value, which will be unboxed
 * as late as possible by the dependency injection container.
 */
interface BoxedValueInterface
{
    /**
     * @return mixed the boxed value
     */
    public function unbox();
}
