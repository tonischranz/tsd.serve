<?php

namespace tsd\serve;

/**
 * @Implementation tsd\serve\DefaultRouting
 * @Implementation tsd\serve\SimpleRouting
 */
abstract class RoutingStrategy
{
    abstract function createRoute (string $host, string $method, string $path, Factory $factory, array $plugins);
}