<?php

namespace mindplay\unbox;

use Psr\Container\ContainerInterface;
use ReflectionFunction;

/**
 * This class implements a minimal PSR-11 dependency injection container.
 */
class Container extends Configuration implements ContainerInterface
{
    /**
     * @var bool[] map where component name => TRUE, if the component has been initialized
     */
    protected $active = [];

    /**
     * @param Configuration $config Configuration (e.g. ContainerFactory) to copy to this Container instance
     */
    public function __construct(Configuration $config)
    {
        $config->copyTo($this);

        $this->values[Resolver::class] = new Resolver($this);
        $this->active[Resolver::class] = true;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        if (! isset($this->active[$id])) {
            if (isset($this->factory[$id])) {
                $this->values[$id] = $this->getResolver()->call($this->factory[$id], $this->factory_map[$id]);
            } elseif (! array_key_exists($id, $this->values)) {
                throw new NotFoundException($id);
            }

            $this->active[$id] = true;

            $this->initialize($id);
        }

        return $this->values[$id];
    }

    /**
     * @inheritdoc
     */
    public function has($name)
    {
        return array_key_exists($name, $this->values) || isset($this->factory[$name]);
    }

    /**
     * @return Resolver
     */
    protected function getResolver()
    {
        return $this->get(Resolver::class);
    }

    /**
     * Internally initialize an active component.
     *
     * @param string $name component name
     *
     * @return void
     */
    private function initialize($name)
    {
        if (isset($this->config[$name])) {
            foreach ($this->config[$name] as $index => $config) {
                $value = $this->getResolver()->call($config, $this->config_map[$name][$index]);

                if ($value !== null) {
                    $this->values[$name] = $value;
                }
            }
        }
    }
}
