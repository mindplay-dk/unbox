<?php

use mindplay\unbox\Container;

return call_user_func(function () {

    $container = new Container();

    $container->add(new TestProvider());

    return $container;

});
