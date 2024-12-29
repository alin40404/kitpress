<?php
namespace kitpress\core\Facades;

use kitpress\core\abstracts\Facade;
use kitpress\core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap Facade
 *
 * @method static self getInstance(Container $container = null)
 * @method static self boot(Container $container = null)
 * @method static bool start()
 * @method static void run()
 * @method static void init()
 * @method static void adminInit()
 * @method static void registerAdminMenus()
 * @method static void enqueueScripts()
 * @method static void enqueueAdminScripts(string $hook)
 * @method static void shutdown()
 *
 * @see \kitpress\core\Bootstrap
 */
class Bootstrap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bootstrap';
    }
}