<?php

use mindplay\unbox\ContainerException;
use mindplay\unbox\ContainerFactory;

use function mindplay\testies\{ test, ok, expect };

class UnionTypeDependencyA {}
class UnionTypeDependencyB {}

class ClassWithUnionTypeDependency
{
    public function __construct(public UnionTypeDependencyA|UnionTypeDependencyB $dep)
    {}
}

test(
    'PHP 8: ambiguous union-types CAN NOT automatically be resolved',
    function () {
        $factory = new ContainerFactory();

        $factory->register(UnionTypeDependencyA::class);
        $factory->register(UnionTypeDependencyB::class);

        $factory->register(ClassWithUnionTypeDependency::class);

        $container = $factory->createContainer();

        expect(
            ContainerException::class,
            "WHY",
            function () use ($container) {
                $container->get(ClassWithUnionTypeDependency::class);
            },
            "/unable to resolve parameter: \\\$dep/"
        );
    }
);


test(
    'PHP 8: can inject against ambiguous union-type by manually referencing the dependency',
    function () {
        $factory = new ContainerFactory();

        $factory->register(UnionTypeDependencyA::class);
        $factory->register(UnionTypeDependencyB::class);

        $factory->register(
            ClassWithUnionTypeDependency::class,
            [
                "dep" => $factory->ref(UnionTypeDependencyA::class)
            ]
        );

        $container = $factory->createContainer();

        ok($container->get(ClassWithUnionTypeDependency::class)->dep instanceof UnionTypeDependencyA);
    }
);
