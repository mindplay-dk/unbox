<?php

namespace mindplay\unbox;

use Exception;
use Psr\Container\ContainerExceptionInterface as InteropContainerException;

/**
 * @inheritdoc
 */
class ContainerException extends Exception implements InteropContainerException
{
}
