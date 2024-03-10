<?php

use mindplay\unbox\ContainerFactory;

return function () {

    $factory = new ContainerFactory();

    $factory->add(new TestProvider());

    return $factory->createContainer();

};
