<?php

namespace mindplay\unbox;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Pseudo-namespace for some common reflection helper-functions.
 */
abstract class Reflection
{
    /**
     * @type string pattern for parsing an argument type from a ReflectionParameter string
     *
     * @see Reflection::getParameterType()
     */
    const ARG_PATTERN = '/(?:\<required\>|\<optional\>)\\s+([\\w\\\\]+)/';

    /**
     * Create a Reflection of the function references by any type of callable (or object implementing `__invoke()`)
     *
     * @param callable|object $callback
     *
     * @return ReflectionFunctionAbstract
     */
    public static function createFromCallable($callback)
    {
        if (is_object($callback)) {
            if ($callback instanceof Closure) {
                return new ReflectionFunction($callback);
            } elseif (method_exists($callback, '__invoke')) {
                return new ReflectionMethod($callback, '__invoke');
            }

            throw new InvalidArgumentException("class " . get_class($callback) . " does not implement __invoke()");
        } elseif (is_array($callback)) {
            if (is_callable($callback)) {
                return new ReflectionMethod($callback[0], $callback[1]);
            }

            throw new InvalidArgumentException("expected callable");
        } elseif (is_callable($callback)) {
            return new ReflectionFunction($callback);
        }

        throw new InvalidArgumentException("unexpected value: " . var_export($callback, true) . " - expected callable");
    }

    /**
     * Obtain the type-hint of a `ReflectionParameter`, but avoid triggering autoload (as a performance optimization)
     *
     * @param ReflectionParameter $param
     *
     * @return string|null fully-qualified type-name (or NULL, if no type-hint was available)
     */
    public static function getParameterType(ReflectionParameter $param)
    {
        if (preg_match(self::ARG_PATTERN, $param->__toString(), $matches) === 1) {
            return $matches[1];
        }

        return null; // no type-hint is available
    }
}
