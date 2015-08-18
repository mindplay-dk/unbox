<?php

// rough benchmark comparing the time required to bootstrap and
// resolve the dependencies in a simple test-case.

use mindplay\benchpress\Benchmark;

require __DIR__ . '/header.php';

$bench = new Benchmark();

$unbox_configuration = function () {
    $container = require __DIR__ . '/unbox.php';
};

$phpdi_configuration = function () {
    $container = require __DIR__ . '/php-di.php';
};

$pimple_configuration = function () {
    $container = require __DIR__ . '/pimple.php';
};

$unbox_resolution = function () {
    $container = require __DIR__ . '/unbox.php';

    $cache = $container->get(UserRepository::class);
};

$phpdi_resolution = function () {
    $container = require __DIR__ . '/php-di.php';

    $cache = $container->get(UserRepository::class);
};

$pimple_resolution = function () {
    $container = require __DIR__ . '/pimple.php';

    $cache = $container[UserRepository::class];
};

$bench->add(
    'unbox: configuration',
    $unbox_configuration
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

$bench = new Benchmark();

foreach (array(3,5,10) as $num) {
    $bench->add(
        "unbox: {$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/unbox.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $unbox_resolution
    );

    $bench->add(
        "php-di: {$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/php-di.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $phpdi_resolution
    );

    $bench->add(
        "pimple: {$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/pimple.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container[UserRepository::class];
            }
        },
        $pimple_resolution
    );
}

$bench->run();
