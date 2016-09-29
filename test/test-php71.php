<?php

use mindplay\unbox\ContainerFactory;
use mindplay\unbox\Reflection;

test(
    'PHP 7.1: can obtain reflections from nullable type-hinted callable',
    function () {
        $reflection = new ReflectionFunction(function (?Foo $foo) {});

        $params = $reflection->getParameters();

        eq(Reflection::getParameterType($params[0]), Foo::class);
    }
);

class PHP7_Constructor
{
    /**
     * @var PHP7_Dependency|null
     */
    public $dep;

    public function __construct(?PHP7_Dependency $dep)
    {
        $this->dep = $dep;
    }
}

class PHP7_Dependency
{}

test(
    'PHP 7.1: can inject dependency against nullable type-hint',
    function () {
        $factory = new ContainerFactory();

        $factory->register(PHP7_Constructor::class);
        $factory->register(PHP7_Dependency::class);

        $container = $factory->createContainer();

        ok($container->get(PHP7_Constructor::class)->dep instanceof PHP7_Dependency);
    }
);

test(
    'PHP 7.1: can inject null against nullable type-hint',
    function () {
        $factory = new ContainerFactory();

        $factory->register(PHP7_Constructor::class);

        $container = $factory->createContainer();

        eq($container->get(PHP7_Constructor::class)->dep, null);
    }
);
