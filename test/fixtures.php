<?php

interface CacheProvider {}

class FileCache implements CacheProvider
{
    public function __construct($path)
    {
        $this->path = $path;
    }

    public $path;
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
}
