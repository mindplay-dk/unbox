<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// This is the example featured in the documentation.

use mindplay\unbox\Container;
use mindplay\unbox\ContainerFactory;
use mindplay\unbox\Resolver;

### src/*.php:

interface CacheProvider {
    // ...
}

class FileCache implements CacheProvider
{
    /**
     * @var string
     */
    public $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    // ...
}

class UserRepository
{
    /**
     * @var CacheProvider
     */
    public $cache;

    public function __construct(CacheProvider $cache)
    {
        $this->cache = $cache;
    }

    // ...
}

class UserController
{
    /**
     * @var UserRepository
     */
    private $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function show($user_id)
    {
        // $user = $this->users->getUserById($user_id);
        // ...
    }
}

class Dispatcher
{
    /**
     * @var Resolver
     */
    private $resolver;

    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function run($path, $params)
    {
        list($controller, $action) = explode("/", $path);

        $class = ucfirst($controller) . "Controller";
        $method = $action;

        $this->resolver->call(
            [$this->resolver->create($class), $method],
            $params
        );
    }
}

### bootstrap.php:

$factory = new ContainerFactory();

$factory->register("cache", function ($cache_path) {
    return new FileCache($cache_path);
});

$factory->alias(CacheProvider::class, "cache");

$factory->register(UserRepository::class);

$factory->register(Dispatcher::class);

### config.php:

$factory->set("cache_path", "/tmp/cache");

### index.php:

$resolver = $factory->createResolver();

$resolver->call(function (Dispatcher $dispatcher) {
    $path = "user/show"; // $path = $_SERVER["PATH_INFO"];
    $params = ["user_id" => 123]; // $params = $_GET;

    $dispatcher->run($path, $params);
});
