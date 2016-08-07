<?php

use mindplay\benchpress\Benchmark;

require __DIR__ . '/header.php';

$bench = new Benchmark();

$unbox_configuration = function () {
    $container = require __DIR__ . '/bootstrap-unbox.php';
};

$bench->add(
    'configuration',
    $unbox_configuration
);

foreach ([1,10] as $num) {
    $bench->add(
        "{$num} repeated resolutions",
        function () use ($num) {
            $container = require __DIR__ . '/bootstrap-unbox.php';

            for ($i = 0; $i < $num; $i++) {
                $cache = $container->get(UserRepository::class);
            }
        },
        $unbox_configuration
    );
}

$bench->run();
