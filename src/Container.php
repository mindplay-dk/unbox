<?php

namespace container_registry_thingy;

use RuntimeException;

/**
 * This class implements a type-checked dependency injection container.
 */
class Container
{
    /**
     * @var mixed[] map where component name => value
     */
    protected $values = array();

    /**
     * @var callable[] map where component name => factory function
     */
    protected $factory = array();

    /**
     * @var bool[] map where component name => true (if the component has been initialized)
     */
    protected $initialized = array();

    /**
     * @var (callable[])[] map where component name => list of configuration functions
     */
    protected $config = array();

    /**
     * @var string[] map where component name => class name
     */
    protected $types;

    /**
     * @var object owner reference
     */
    protected $owner;

    /**
     * @param object $owner owner reference (e.g. service registry object; provided to factory and config functions)
     * @param string[] $types map where component name => fully-qualified class name (or pseudo-type name)
     */
    public function __construct($owner = null, array $types = array())
    {
        $this->owner = $owner ?: $this;
        $this->types = $types;
    }

    /**
     * @var callable[] map where type-name => type-checking callback (for common pseudo-types)
     *
     * @see http://www.phpdoc.org/docs/latest/for-users/types.html
     */
    public static $checkers = array(
        'string'   => 'is_string',
        'integer'  => 'is_int',
        'int'      => 'is_int',
        'boolean'  => 'is_bool',
        'bool'     => 'is_bool',
        'float'    => 'is_float',
        'double'   => 'is_float',
        'object'   => 'is_object',
        'array'    => 'is_array',
        'resource' => 'is_resource',
        'null'     => 'is_null',
        'callable' => 'is_callable',
    );

    /**
     * @param string $name component name
     *
     * @return mixed
     */
    public function get($name)
    {
        if (!array_key_exists($name, $this->values)) {
            if (!isset($this->factory[$name])) {
                throw new RuntimeException("undefined component: {$name}");
            }

            $this->values[$name] = call_user_func($this->factory[$name], $this->owner);

            if (isset($this->config[$name])) {
                foreach ($this->config[$name] as $func) {
                    call_user_func_array($func, array(&$this->values[$name], $this->owner));
                }
            }

            $this->initialized[$name] = true; // prevent further changes to this component

            $this->check($name);
        }

        return $this->values[$name];
    }

    /**
     * @param string $name component name
     * @param mixed $value
     *
     * @return void
     */
    public function set($name, $value)
    {
        if (isset($this->initialized[$name])) {
            throw new RuntimeException("attempted overwrite of initialized component: {$name}");
        }

        $this->values[$name] = $value;

        $this->check($name);
    }

    /**
     * @param string   $name component name
     * @param callable $func `function ($owner) : mixed`
     *
     * @return void
     */
    public function register($name, callable $func)
    {
        if (@$this->initialized[$name]) {
            throw new RuntimeException("oh noes!");
        }

        $this->factory[$name] = $func;

        unset($this->values[$name]);
    }

    /**
     * @param string   $name component name
     * @param callable $func `function ($component, $owner) : void`
     *
     * @return void
     */
    public function configure($name, callable $func)
    {
        if ($this->isActive($name)) {
            // component is already active - run the configuration function right away:

            call_user_func_array($func, array(&$this->values[$name], $this->owner));

            return;
        }

        if (!isset($this->factory[$name])) {
            throw new RuntimeException("undefined component: {$name}");
        }

        $this->config[$name][] = $func;
    }

    /**
     * @param string $name component name
     *
     * @return bool true, if a component with the given name has been defined
     */
    public function has($name)
    {
        return array_key_exists($name, $this->values)
            || isset($this->factory[$name]);
    }

    /**
     * @param string $name component name
     *
     * @return bool
     */
    public function isActive($name)
    {
        return array_key_exists($name, $this->values);
    }

    /**
     * Validate the Container for completeness
     *
     * @throws RuntimeException if any component is undefined
     */
    public function validate()
    {
        // Note to self - why no type-checking is necessary at this point:
        // values directly injected via set() are type-checked immediately,
        // whereas values indirectly defined via register() cannot be type-
        // checked, since this would require them to be initialized; these
        // get type-checked as soon as possible, e.g. upon initialization.

        foreach ($this->types as $name => $type) {
            if (!$this->has($name)) {
                throw new RuntimeException("undefined component: {$name} (expected type: {$type})");
            }
        }
    }

    /**
     * Minimally checks that the given component exists; if the given component
     * has been initialized, additionally, a type-check is performed.
     *
     * @param string $name component name
     *
     * @throws RuntimeException if the component is undefined
     * @throws RuntimeException if type-checking the component fails
     */
    protected function check($name)
    {
        if (!isset($this->types[$name])) {
            return; // no type-check defined for this component
        }

        $type = $this->types[$name];

        $value = $this->get($name);

        if ($value === null) {
            return; // explicitly configured null-value is allowed
        }

        if ($type === 'mixed') {
            return; // any type is allowed
        }

        if (array_key_exists($type, self::$checkers) && call_user_func(self::$checkers[$type], $value)) {
            return; // pseudo type-check passed
        } elseif (is_object($value) && $value instanceof $type) {
            return; // class/interface type-check passed
        }

        $actual = is_object($value)
            ? get_class($value)
            : gettype($value);

        throw new RuntimeException("unexpected component: {$name}\nexpected type: {$type}\nprovided type: {$actual}");
    }
}
