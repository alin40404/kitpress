<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache Facade
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string $key, mixed $value, int $expires = 3600)
 * @method static bool delete(string $key)
 * @method static bool flush()
 * @method static bool has(string $key)
 * @method static int increment(string $key, int $value = 1)
 * @method static int decrement(string $key, int $value = 1)
 * @method static mixed remember(string $key, callable $callback, int $expires = 3600)
 *
 * @see \kitpress\library\Cache
 */
class Cache extends Facade {
    protected static function getFacadeAccessor() {
        return 'cache';
    }
}