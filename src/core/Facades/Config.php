<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config Facade
 *
 * @method static void load(string|array $names, string $namespace)
 * @method static mixed get(string|null $key = null, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static void reset()
 *
 * @see \kitpress\library\Config
 */
class Config extends Facade {
    protected static function getFacadeAccessor(): string
    {
        return 'config';
    }
}