<?php

namespace mindplay\unbox;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * @inheritdoc
 */
class ContainerException
    extends Exception
    implements ContainerExceptionInterface
{
}
