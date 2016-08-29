<?php

use mindplay\unbox\ContainerFactory;

return call_user_func(function () {

    $factory = new ContainerFactory();

    $factory->add(new TestProvider());

    return $factory->createContainer();

});
