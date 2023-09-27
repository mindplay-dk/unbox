<?php

namespace mindplay\unbox;

use Psr\Container\ContainerExceptionInterface;

/**
 * @inheritdoc
 */
class InvalidArgumentException
    extends \InvalidArgumentException
    implements ContainerExceptionInterface
{
}
