# mindplay/unbox

Wicked awesome simple dependency injection container.

[![Build Status](https://travis-ci.org/mindplay-dk/unbox.svg)](https://travis-ci.org/mindplay-dk/unbox)

[![Code Coverage](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)

Documentation coming reeeeal soon like...

## Benchmark

This is not intended as a competitive benchmark, but more to give you an idea of the performance
implications of choosing from three very different DI containers with very different goals and
different qualities - from the smallest and simplest to the largest and most ambitious:

  * [pimple](http://pimple.sensiolabs.org/) is as simple as a DI container can get, with absolutely
    no bell and whistles, and barely any learning curve.

  * **unbox** with just one class (less than 400 lines of code) and a few interfaces - more concepts
    than pimple (and therefore a bit more learning curve) and convenient closure injections, which
    are somewhat more costly in terms of performance.

  * [php-di](http://php-di.org/) is a pristine dependency injection framework with all the bells and
    whistles.

The included [simple benchmark](test/benchmark.php) generates the following benchmark results.

Time to configure the container:

    pimple ........ 0.076 msec ......  59.84% ......... 1.00x
    unbox ......... 0.081 msec ......  63.71% ......... 1.06x
    php-di ........ 0.127 msec ...... 100.00% ......... 1.67x

Time to resolve the dependencies in the container, on first access:

    pimple ........ 0.031 msec ....... 10.68% ......... 1.00x
    unbox ......... 0.080 msec ....... 27.42% ......... 2.57x
    php-di ........ 0.293 msec ...... 100.00% ......... 9.37x

Time for multiple subsequent lookups:

    pimple: 3 repeated resolutions ......... 0.003 msec ........ 9.64% ......... 1.00x
    unbox: 3 repeated resolutions .......... 0.005 msec ......  14.84% ......... 1.54x
    pimple: 5 repeated resolutions ......... 0.007 msec ......  23.81% ......... 2.47x
    php-di: 3 repeated resolutions ......... 0.010 msec ......  31.99% ......... 3.32x
    php-di: 5 repeated resolutions ......... 0.010 msec ......  33.60% ......... 3.48x
    unbox: 5 repeated resolutions .......... 0.011 msec ......  36.32% ......... 3.77x
    pimple: 10 repeated resolutions ........ 0.015 msec ......  47.34% ......... 4.91x
    unbox: 10 repeated resolutions ......... 0.018 msec ......  58.62% ......... 6.08x
    php-di: 10 repeated resolutions ........ 0.031 msec ...... 100.00% ........ 10.37x
