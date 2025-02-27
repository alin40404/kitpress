<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Router Facade
 *
 * @method static void load(string|array $names)
 * @method static mixed get(string|null $key = null, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void reset()
 * @method static void setRootPath(string $rootPath)
 *
 * @see \kitpress\library\Router
 */
class Router extends Facade {
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}