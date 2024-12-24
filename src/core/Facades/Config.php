<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config Facade
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static bool has(string $key)
 * @method static array all()
 * @method static void load(string $name)
 * @method static void reload()
 * @method static void clear()
 * @method static array getLoadedFiles()
 *
 * @see \kitpress\library\Config
 */
class Config extends Facade {
    protected static function getFacadeAccessor() {
        return 'config';
    }
}