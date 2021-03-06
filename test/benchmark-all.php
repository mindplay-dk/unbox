<?php

// rough benchmark comparing the time required to bootstrap and
// resolve the dependencies in a simple test-case.

use mindplay\benchpress\Benchmark;

require __DIR__ . '/header.php';

$bench = new Benchmark();

$unbox_configuration = function () {
    $container = require __DIR__ . '/bootstrap-unbox.php';
};

$funbox_configuration = function () {
    $container = require __DIR__ . '/bootstrap-funbox.php';
};

$phpdi_configuration = function () {
    $container = require __DIR__ . '/bootstrap-php-di.php';
};

$pimple_configuration = function () {
    $container = require __DIR__ . '/bootstrap-pimple.php';
};

$bench->add(
    'unbox: configuration',
    $unbox_configuration
);

$bench->add(
    'funbox: configuration',
    $funbox_configuration
);

$bench->add(
    'php-di: configuration',
    $phpdi_configuration
);

$bench->add(
    'pimple: configuration',
    $pimple_configuration
);

$bench->run();

foreach ([1,3,5,10] as $num) {
    $bench = new Benchmark();

    $bench->add(
        "unbox: {$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/bootstrap-unbox.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $unbox_configuration
    );

    $bench->add(
        "funbox: {$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/bootstrap-funbox.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $funbox_configuration
    );

    $bench->add(
        "php-di: {$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/bootstrap-php-di.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $phpdi_configuration
    );

    $bench->add(
        "pimple: {$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/bootstrap-pimple.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container[UserRepository::class];
            }
        },
        $pimple_configuration
    );

    $bench->run();
}
