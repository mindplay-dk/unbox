<?php

use mindplay\unbox\Reflection;

test(
    'PHP 7.0: ignore scalar type-hints under PHP >= 7.0',
    function () {
        $reflection = new ReflectionFunction(function (string $foo) {});

        $params = $reflection->getParameters();

        eq(Reflection::getParameterType($params[0]), null);
    }
);
