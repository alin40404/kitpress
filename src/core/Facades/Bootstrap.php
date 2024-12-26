<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @method static bool start()
 * @method static void initializeAll()
 * @method static void registerInitializable(\kitpress\core\abstracts\Initializable $instance)
 */
class Bootstrap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bootstrap';
    }
}