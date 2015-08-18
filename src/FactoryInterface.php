<?php

namespace mindplay\unbox;

/**
 * This interface defines the factory aspect of {@see Container} - you should
 * type-hint against this interface when all you need is the factory aspect.
 */
interface FactoryInterface
{
    /**
     * @param string          $name component name
     * @param string[]|string $map  mixed list/map of parameter names
     *
     * @return mixed
     */
    public function create($name, $map = array());
}
