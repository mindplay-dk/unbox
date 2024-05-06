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
     * @param string       $class_name fully-qualified class-name
     * @param array<mixed> $map        mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed new instance of the specified class
     */
    public function create(string $class_name, array $map = []);
}
