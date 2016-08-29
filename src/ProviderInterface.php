<?php

namespace mindplay\unbox;

/**
 * This interface enables you to package service definitions for reuse.
 *
 * @see ContainerFactory::register()
 */
interface ProviderInterface
{
    /**
     * Registers services and components with a given `ContainerFactory`
     *
     * @param ContainerFactory $container
     *
     * @return void
     */
    public function register(ContainerFactory $container);
}
