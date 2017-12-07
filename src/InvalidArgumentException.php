<?php

namespace mindplay\unbox;

use Interop\Container\Exception\ContainerException;

class InvalidArgumentException
    extends \InvalidArgumentException
    implements ContainerException # which extends Psr\Container\ContainerExceptionInterface
{
}
