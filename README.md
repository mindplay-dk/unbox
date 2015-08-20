# mindplay/unbox

Wicked awesome simple dependency injection container.

[![Build Status](https://travis-ci.org/mindplay-dk/unbox.svg)](https://travis-ci.org/mindplay-dk/unbox)

[![Code Coverage](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mindplay-dk/unbox/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mindplay-dk/unbox/?branch=master)

## Installation

With Composer: `require mindplay/unbox`

## Usage

<coming soon...>

## Opinionated

Features:

  * **Productivity-oriented** - favoring heavy use of **closures** for full IDE support:
    refactoring-friendly definitions with auto-complete support, inspections and so on.

  * **Performance-oriented** only to the extent that it doesn't encumber the API.

  * **PHP 5.5+** for `::class` support, and because you really shouldn't be using anything older.

Non-features:

  * **NO annotations** - because sprinkling bits of your container configuration across
    your domain model is a really terrible idea.

  * **NO auto-wiring** - because `$container->register(Foo::name)` isn't a burden, and explicitly
    designates something as being a service; unintentionally treating a non-singleton as a singleton
    can be a weird experience.

  * **NO caching** and no "builder" or "container factory" class - because configuring a container
    really shouldn't take

  * **NO property injections** because it blurs your dependencies - use constructor injection, and
    for optional dependencies, use optional constructor arguments; you don't, after all, need to
    count the number of arguments anymore, since everything will be injected.

  * No chainable API, because call chains (in PHP) don't play nice with source-control.

## Benchmark

This is not intended as a competitive benchmark, but more to give you an idea of the performance
implications of choosing from three very different DI containers with very different goals and
different qualities - from the smallest and simplest to the largest and most ambitious:

  * [pimple](http://pimple.sensiolabs.org/) is as simple as a DI container can get, with absolutely
    no bell and whistles, and barely any learning curve.

  * **unbox** with just two classes (less than 400 lines of code) and a few interfaces - more concepts
    than pimple (and therefore a bit more learning curve) and convenient closure injections, which
    are somewhat more costly in terms of performance.

  * [php-di](http://php-di.org/) is a pristine dependency injection framework with all the bells and
    whistles - rich with features, but also has more concepts and learning curve, and more overhead.

The included [simple benchmark](test/benchmark.php) generates the following benchmark results on
a Windows 8 system running PHP 5.6.6.

Time to configure the container:

    pimple ........ 0.076 msec ......  59.84% ......... 1.00x
    unbox ......... 0.081 msec ......  63.71% ......... 1.06x
    php-di ........ 0.127 msec ...... 100.00% ......... 1.67x

Time to resolve the dependencies in the container, on first access:

    pimple ........ 0.031 msec ....... 10.68% ......... 1.00x
    unbox ......... 0.080 msec ....... 27.42% ......... 2.57x
    php-di ........ 0.293 msec ...... 100.00% ......... 9.37x

Time for multiple subsequent lookups:

    pimple: 3 repeated resolutions ........ 0.034 msec ....... 11.42% ......... 1.00x
    unbox: 3 repeated resolutions ......... 0.085 msec ....... 28.62% ......... 2.51x
    php-di: 3 repeated resolutions ........ 0.298 msec ...... 100.00% ......... 8.76x

    pimple: 5 repeated resolutions ........ 0.038 msec ....... 12.40% ......... 1.00x
    unbox: 5 repeated resolutions ......... 0.089 msec ....... 29.24% ......... 2.36x
    php-di: 5 repeated resolutions ........ 0.305 msec ...... 100.00% ......... 8.06x

    pimple: 10 repeated resolutions ....... 0.046 msec ....... 14.34% ......... 1.00x
    unbox: 10 repeated resolutions ........ 0.102 msec ....... 31.78% ......... 2.22x
    php-di: 10 repeated resolutions ....... 0.322 msec ...... 100.00% ......... 6.97x
