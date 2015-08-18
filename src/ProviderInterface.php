<?php

namespace mindplay\unbox;

/**
 * This interface enables you to package service definitions for reuse.
 *
 * @see Container::register()
 */
interface ProviderInterface
{
    /**
     * Registers services and components with a given Container
     *
     * @param Container $container
     *
     * @return void
     */
    public function register(Container $container);
}
