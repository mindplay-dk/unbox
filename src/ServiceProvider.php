<?php

namespace container_registry_thingy;

/**
 * This interface should be implemented by service providers.
 */
interface ServiceProvider
{
    public function __invoke(Container $container);
}
