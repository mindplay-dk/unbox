<?php

namespace mindplay\unbox;

use Psr\Container\ContainerInterface;

/**
 * The Composite Container delegates look-ups to a prioritized list of Container instances.
 */
class Composite implements ContainerInterface
{
    /**
     * @var ContainerInterface[]
     */
    protected $containers;

    /**
     * @param ContainerInterface[] $containers prioritized list of Container instances to query
     */
    public function __construct(array $containers)
    {
        $this->containers = $containers;
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return $container->get($id);
            }
        }

        throw new NotFoundException($id);
    }

    /**
     * @inheritdoc
     */
    public function has($id)
    {
        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }
}
