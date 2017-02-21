<?php

namespace mindplay\unbox;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;

/**
 * This class implements a pseudo-namespace of functions that dynamically invoke and
 * resolve parameters for callables and constructors.
 */
abstract class Invoker
{
    /**
     * Call any given callable, using a given Container to satisfy it's arguments, and/or
     * manually specifying some of those arguments - then return the value from the call.
     *
     * This will work for any callable:
     *
     *     $container->call('foo');               // function foo()
     *     $container->call($foo, 'baz');         // instance method $foo->baz()
     *     $container->call([Foo::class, 'bar']); // static method Foo::bar()
     *     $container->call($foo);                // closure (or class implementing __invoke)
     *
     * In any of those examples, you can also supply custom arguments, either named or
     * positional (int) or a mix of those.
     *
     * See also {@see create()} which lets you invoke any constructor.
     *
     * @param ContainerInterface $container Container against which to resolve parameters
     * @param callable|object    $callable  any arbitrary closure or callable, or object implementing __invoke()
     * @param mixed|mixed[]      $map       mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed return value from the given callable
     */
    public static function invokeCallable(ContainerInterface $container, $callable, $map = [])
    {
        $params = Reflection::createFromCallable($callable)->getParameters();

        return call_user_func_array($callable, Invoker::resolveParameters($container, $params, $map));
    }

    /**
     * Create an instance of a given class.
     *
     * The container will be used to resolve any constructor arguments
     * not explicitly provided in the (optional) `$map` parameter.
     *
     * @param ContainerInterface $container  Container against which to resolve constructor arguments
     * @param string             $class_name fully-qualified class-name
     * @param mixed|mixed[]      $map        mixed list/map of parameter values (and/or boxed values)
     *
     * @return mixed
     */
    public static function invokeConstructor(ContainerInterface $container, $class_name, $map = [])
    {
        if (! class_exists($class_name)) {
            throw new InvalidArgumentException("unable to create component: {$class_name}");
        }

        $reflection = new ReflectionClass($class_name);

        if (! $reflection->isInstantiable()) {
            throw new InvalidArgumentException("unable to create instance of abstract class: {$class_name}");
        }

        $constructor = $reflection->getConstructor();

        $params = $constructor
            ? Invoker::resolveParameters($container, $constructor->getParameters(), $map, false)
            : [];

        return $reflection->newInstanceArgs($params);
    }

    /**
     * Resolves parameters against a specified `ContainerInterface` implementation
     *
     * @param ContainerInterface    $container Container instance against which to resolve parameters
     * @param ReflectionParameter[] $params    parameter reflections
     * @param array                 $map       mixed list/map of parameter values (and/or boxed values)
     * @param bool                  $safe      if TRUE, it's considered safe to resolve against parameter names
     *
     * @return array resolved parameters
     *
     * @throws ContainerException
     */
    public static function resolveParameters(ContainerInterface $container, array $params, $map, $safe = true)
    {
        $args = [];

        foreach ($params as $index => $param) {
            $param_name = $param->name;

            if (array_key_exists($param_name, $map)) {
                $value = $map[$param_name]; // // resolve as user-provided named argument
            } elseif (array_key_exists($index, $map)) {
                $value = $map[$index]; // resolve as user-provided positional argument
            } else {
                // as on optimization, obtain the argument type without triggering autoload:

                $type = Reflection::getParameterType($param);

                if ($type && isset($map[$type])) {
                    $value = $map[$type]; // resolve as user-provided type-hinted argument
                } elseif ($type && $container->has($type)) {
                    $value = $container->get($type); // resolve as component registered by class/interface name
                } elseif ($safe && $container->has($param_name)) {
                    $value = $container->get($param_name); // resolve as component with matching parameter name
                } elseif ($param->isOptional()) {
                    $value = $param->getDefaultValue(); // unresolved, optional: resolve using default value
                } elseif ($type && $param->allowsNull()) {
                    $value = null; // unresolved, type-hinted, nullable: resolve as NULL
                } else {
                    // unresolved - throw a container exception:

                    $reflection = $param->getDeclaringFunction();

                    throw new ContainerException(
                        "unable to resolve parameter: \${$param_name} " . ($type ? "({$type}) " : "") .
                        "in file: " . $reflection->getFileName() . ", line " . $reflection->getStartLine()
                    );
                }
            }

            if ($value instanceof BoxedValueInterface) {
                $value = $value->unbox($container); // unbox a boxed value
            }

            $args[] = $value; // argument resolved!
        }

        return $args;
    }
}
