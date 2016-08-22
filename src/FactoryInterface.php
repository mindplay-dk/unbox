<?php

namespace mindplay\unbox;

/**
 * This interface defines the factory aspect of {@see Container} - you should
 * type-hint against this interface when all you need is the factory aspect.
 */
interface FactoryInterface
{
    /**
     * Create an instance of a given class.
     *
     * The factory will internally resolve and inject any constructor arguments
     * not explicitly provided in the (optional) second parameter.
     *
     * @param string        $name fully-qualified class/interface-name (or any prototype name)
     * @param mixed|mixed[] $map  mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed
     */
    public function create($name, $map = []);
}
