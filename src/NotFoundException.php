<?php

namespace mindplay\unbox;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @inheritdoc
 */
class NotFoundException
    extends Exception
    implements NotFoundExceptionInterface
{
    /**
     * @param string $name component name
     */
    public function __construct($name)
    {
        parent::__construct("undefined component: {$name}");
    }
}
