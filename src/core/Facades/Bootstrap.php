<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;
use kitpress\core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 *
 * @see \kitpress\library\Bootstrap 实际的后台功能实现类
 *
 * @method static bool start()
 * @method static void initializeAll()
 * @method static void registerInitializable(\kitpress\core\abstracts\Initializable $instance)
 *
 */
class Bootstrap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bootstrap';
    }
}