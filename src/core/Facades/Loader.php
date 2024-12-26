<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Loader Facade
 *
 * @see \kitpress\library\Loader
 *
 * @method static void register()
 * @method static bool autoload(string $class)
 * @method static string getFilePath(string $class)
 */
class Loader extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'loader';
    }
}