<?php

namespace mindplay\unbox;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use ReflectionParameter;
use TypeError;

/**
 * Pseudo-namespace for some common reflection helper-functions.
 */
abstract class Reflection
{
    /**
     * Create a Reflection of the function referenced by any type of callable (or object implementing `__invoke()`)
     *
     * @param callable|object $callback
     *
     * @return ReflectionFunctionAbstract
     *
     * @throws InvalidArgumentException
     */
    public static function createFromCallable($callback): ReflectionFunctionAbstract
    {
        try {
            return new ReflectionFunction(Closure::fromCallable($callback));
        } catch (TypeError $error) {
            throw new InvalidArgumentException("unexpected value: " . var_export($callback, true) . " - expected callable");
        }
    }

    /**
     * Obtain the type-hint of a `ReflectionParameter`, ignoring scalar types and PHP 8 union types.
     *
     * @param ReflectionParameter $param
     *
     * @return string|null fully-qualified type-name (or NULL, if no type-hint was available)
     */
    public static function getParameterType(ReflectionParameter $param): ?string
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return null; // ignore scalar type-hints
            }

            return $type->getName();
        }

        return null; // no acceptable type-hint available
    }
}
