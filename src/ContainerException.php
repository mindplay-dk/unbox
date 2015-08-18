<?php

namespace mindplay\unbox;

use Exception;
use Interop\Container\Exception\ContainerException as InteropContainerException;

/**
 * @inheritdoc
 */
class ContainerException
    extends Exception
    implements InteropContainerException
{
}
