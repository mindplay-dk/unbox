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

$bench->add(
    'unbox: configuration',
    $unbox_configuration
);

$bench->add(
    'php-di: configuration',
    $phpdi_configuration
);

$bench->run();

$bench = new Benchmark();

$bench->add(
    'unbox: resolution',
    function () {
        $container = require __DIR__ . '/unbox.php';

        $cache = $container->get(UserRepository::class);
    },
    $unbox_configuration
);

$bench->add(
    'php-di: resolution',
    function () {
        $container = require __DIR__ . '/php-di.php';

        $cache = $container->get(UserRepository::class);
    },
    $phpdi_configuration
);

$bench->run();
