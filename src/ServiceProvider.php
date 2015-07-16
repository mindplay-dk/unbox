<?php

namespace mindplay\unbox;

/**
 * This interface should be implemented by service providers.
 */
interface ServiceProvider
{
    public function __invoke(Container $container);
}
