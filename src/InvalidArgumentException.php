<?php

namespace mindplay\unbox;

use Psr\Container\ContainerExceptionInterface;

class InvalidArgumentException
    extends \InvalidArgumentException
    implements ContainerExceptionInterface
{
}
