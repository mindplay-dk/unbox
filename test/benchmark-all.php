<?php

// rough benchmark comparing the time required to bootstrap and
// resolve the dependencies in a simple test-case.

use mindplay\benchpress\Benchmark;

require __DIR__ . '/header.php';

$bench = new Benchmark();

$unbox_configuration = function () {
    $setup = require __DIR__ . '/bootstrap-unbox.php';
    $container = $setup();
};

$phpdi_configuration = function () {
    $setup = require __DIR__ . '/bootstrap-php-di.php';
    $container = $setup();
};

$phpdi_compiled_configuration = function() {
    $setup = require __DIR__ . '/bootstrap-php-di.php';
    $container = $setup(compiled: true);
};

$pimple_configuration = function () {
    $setup = require __DIR__ . '/bootstrap-pimple.php';
    $container = $setup();
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
    'php-di: configuration [compiled]',
    $phpdi_compiled_configuration
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
            $setup = require __DIR__ . '/bootstrap-unbox.php';
            $container = $setup();

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $unbox_configuration
    );

    $bench->add(
        "php-di: {$num} repeated resolutions",
        function () use ($num) {
            $setup = require __DIR__ . '/bootstrap-php-di.php';
            $container = $setup();

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $phpdi_configuration
    );

    $bench->add(
        "php-di: {$num} repeated resolutions [compiled]",
        function () use ($num) {
            $setup = require __DIR__ . '/bootstrap-php-di.php';
            $container = $setup(compiled: true);

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $phpdi_configuration
    );

    $bench->add(
        "pimple: {$num} repeated resolutions",
        function () use ($num) {
            $setup = require __DIR__ . '/bootstrap-pimple.php';
            $container = $setup();

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $pimple_configuration
    );

    $bench->run();
}
