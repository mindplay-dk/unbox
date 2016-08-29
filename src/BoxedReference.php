<?php

namespace mindplay\unbox;

/**
 * This class implements a boxed reference to a component in a Container.
 */
class BoxedReference implements BoxedValueInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @param string $name component name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed the boxed value
     */
    public function unbox(Container $container)
    {
        return $container->get($this->name);
    }
}
